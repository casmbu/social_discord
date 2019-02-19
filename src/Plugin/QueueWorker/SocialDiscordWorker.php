<?php
/**
 * @file
 * Contains \Drupal\social_discord\Plugin\QueueWorker\SocialDiscordWorker.
 */

namespace Drupal\social_discord\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \RestCord\DiscordClient;

/**
 * Processes Tasks for SocialDiscordWorker.
 *
 * @QueueWorker(
 *   id = "social_discord_queue",
 *   title = @Translation("Social discord task worker"),
 *   cron = {"time" = 86400}
 * )
 */
class SocialDiscordWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface
{
    /**
     * The Social API Network Manager.
     *
     * @var \Drupal\social_api\Plugin\NetworkManager
     */
    protected $networkManager;

    /**
     * The Drupal entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * DiscordController constructor.
     *
     * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
     *   Used to get an instance of social_discord network plugin.
     */
    public function __construct(
        NetworkManager $network_manager
    ) {
        $this->networkManager = $network_manager;
        $this->entityTypeManager = \Drupal::entityTypeManager();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $container->get('plugin.network.manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processItem($data)
    {
        $social_auth_storage = $this->entityTypeManager->getStorage('social_auth');
        $authorized_users = $social_auth_storage->loadMultiple();

        $user_storage = $this->entityTypeManager->getStorage('user');

        // Get SDK.
        $wohali_client = $this->networkManager->createInstance('social_discord')->getSdk();

        // Get settings.
        $config = \Drupal::configFactory()->get('social_discord.settings');
        $guild_id = $config->get('guild_id');
        $bot_token = $config->get('bot_token');
        $add_roles = $config->get('add_roles');

        $restcord_client = new \RestCord\DiscordClient(['token' => $bot_token]);

        $members = (object) [];
        $largest_user_id = 0;
        $count = 1000;
        while ($count >= 1000) {
            $page_members = $restcord_client->guild->listGuildMembers([
                'guild.id' => (int) $guild_id,
                'limit' => 1000,
                'after' => $largest_user_id,
            ]);
            $count = 0;
            foreach ($page_members as $member) {
                $guild_user_id = $member->user->id;
                $members->{$guild_user_id} = true;
                if ($guild_user_id > $largest_user_id) {
                    $largest_user_id = $guild_user_id;
                }
                $count = $count + 1;
            }
        }

        foreach ($authorized_users as $authorized_user) {
            $additional_data = (object) json_decode($authorized_user->getAdditionalData());
            $new_access_token = false;
            if (isset($additional_data->refresh_token)) {
                $new_access_token = $wohali_client->getAccessToken('refresh_token', [
                    'refresh_token' => $additional_data->refresh_token,
                ]);

                $authorized_user->setToken($new_access_token->getToken());
                $additional_data->refresh_token = $new_access_token->getRefreshToken();
                $authorized_user->setAdditionalData(json_encode($additional_data));

                $authorized_user->save();
            }

            if ($guild_id && $add_roles) {
                $discord_user_id = $authorized_user->get('provider_user_id')->getValue()[0]['value'];
                $safe = isset($members->{$discord_user_id});

                if (!$safe) {
                    $user = $user_storage->load($authorized_user->getUserId());
                    foreach ($add_roles as $role) {
                        if ($user->hasRole($role)) {
                            $user->removeRole($role);
                        }
                    }
                    $user->save();
                }
            }
        }
    }
}
