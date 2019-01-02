<?php

namespace Drupal\social_discord\EventSubscriber;

use Drupal\user\UserInterface;
use Drupal\social_auth\Event\SocialAuthUserEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\AuthManager\OAuth2ManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use \RestCord\DiscordClient;

/**
 * Reacts on Social Auth events.
 */
class DiscordSubscriber implements EventSubscriberInterface {

  /**
   * The data handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  private $dataHandler;

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The provider auth manager.
   *
   * @var \Drupal\social_auth\AuthManager\OAuth2ManagerInterface
   */
  private $providerAuth;

  /**
   * DiscordSubscriber constructor.
   *
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   Used to manage session variables.
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of the social auth implementer network plugin.
   * @param \Drupal\social_auth\AuthManager\OAuth2ManagerInterface $providerAuth
   *   Used to get the provider auth manager.
   */
  public function __construct(SocialAuthDataHandler $data_handler,
                              NetworkManager $network_manager,
                              OAuth2ManagerInterface $providerAuth) {
    $this->dataHandler = $data_handler;
    $this->networkManager = $network_manager;
    $this->providerAuth = $providerAuth;

    // Sets the plugin id.
    $this->dataHandler->setSessionPrefix('social_discord');
  }

  /**
   * {@inheritdoc}
   *
   * Returns an array of event names this subscriber wants to listen to.
   */
  public static function getSubscribedEvents() {
    $events[SocialAuthEvents::USER_LOGIN] = ['onUserLogin'];

    return $events;
  }

  /**
   * Sets a drupal message when a user logs in.
   *
   * @param \Drupal\social_auth\Event\SocialAuthUserEvent $event
   *   The Social Auth user event object.
   */
  public function onUserLogin(SocialAuthUserEvent $event) {
    $token = $this->dataHandler->get('access_token');

    // If user logs in using social_discord and the access token exists.
    if ($event->getPluginId() == 'social_discord' && $token) {
      // Get SDK.
      $client = $this->networkManager->createInstance($event->getPluginId())->getSdk();

      // Create client.
      // Can also use $client directly and request data using the library/SDK.
      $this->providerAuth->setClient($client)->setAccessToken($this->dataHandler->get('access_token'));

      $config = \Drupal::configFactory()->get('social_discord.settings');

      $guildId = $config->get('guild_id');
      $botToken = $config->get('bot_token');

      $this->joinServer($guildId, $botToken, $token);

      $addRoles = $config->get('add_roles');

      $this->addRoles($event->getUser(), $addRoles);      
    }
  }

  private function joinServer($guildId, $botToken, $userToken) {
    if ($guildId === FALSE || $botToken === FALSE) {
      return FALSE;
    }

    $discord = new \RestCord\DiscordClient(['token' => $botToken]);

    $discord_user = $this->providerAuth->getUserInfo();

    // Make the user join the Discord guild that was set in the settings.
    $discord->guild->addGuildMember([
      'guild.id' => (int)$guildId,
      'user.id' => (int)$discord_user->getId(),
      // User's access token.
      'access_token' => (string)$userToken
    ]);
  }

  /*
   * @var Drupal\user\UserInterface $user
   *
   * For all available methods, see User class
   * @see https://api.drupal.org/api/drupal/core!modules!user!src!Entity!User.php/class/User
   */
  private function addRoles(UserInterface $user, $addRoles) {
    if ($addRoles === FALSE) {
      return FALSE;
    }

    foreach ($addRoles as $role) {
      if (!$user->hasRole($role)) {
        $user->addRole($role);
      }
    }
    $user->save();
  }

}
