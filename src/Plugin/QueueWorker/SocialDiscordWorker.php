<?php
/**
 * @file
 * Contains \Drupal\social_discord\Plugin\QueueWorker\SocialDiscordQueue.
 */

namespace Drupal\social_discord\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \RestCord\DiscordClient;

/**
 * Processes Tasks for SocialDiscordQueue.
 *
 * @QueueWorker(
 *   id = "social_discord_queue",
 *   title = @Translation("Social discord task worker"),
 *   cron = {"time" = 86400}
 * )
 */
class EmailQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface
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
    public static function create(ContainerInterface $container)
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
        $client = $this->networkManager->createInstance('social_discord')->getSdk();

        // Get settings.
        $config = \Drupal::configFactory()->get('social_discord.settings');
        $guildId = $config->get('guild_id');
        $botToken = $config->get('bot_token');
        $addRoles = $config->get('add_roles');

        foreach ($authorized_users as $authorized_user) {
            $additional_data = decode_json($authorized_user->getAdditionalData());
            $newAccessToken = $client->getAccessToken('refresh_token', [
                'refresh_token' => $additional_data['refresh_token'],
            ]);

            $authorized_user->setAccessToken($newAccessToken->getToken());
            $additional_data['refresh_token'] = $newAccessToken->getRefreshToken();
            $authorized_user->setAdditionalData(encode_json($additional_data));

            $authorized_user->save();

            if ($guildId && $addRoles) {
                $discord = new \RestCord\DiscordClient(['token' => $botToken]);
                $discord_user = $client->getResourceOwner($newAccessToken);

                $safe = $discord->guild->getGuildMember([
                    'guild.id' => $guildId,
                    'user.id' => $discord_user->getId(),
                ]) || false;

                if (!$safe) {
                    $user = $user_storage->load($authorized_user->getUserId());
                    foreach ($addRoles as $role) {
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
