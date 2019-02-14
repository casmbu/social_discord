<?php

namespace Drupal\social_discord;

use Drupal\social_auth\AuthManager\OAuth2Manager;
use Drupal\Core\Config\ConfigFactory;

/**
 * Contains all the logic for Discord OAuth2 authentication.
 */
class DiscordAuthManager extends OAuth2Manager {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Used for accessing configuration object factory.
   */
  public function __construct(ConfigFactory $configFactory) {
    parent::__construct($configFactory->get('social_discord.settings'));
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate() {
    $this->setAccessToken(
      $this->client->getAccessToken('authorization_code', ['code' => $_GET['code']])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInfo() {
    $this->user = $this->client->getResourceOwner($this->getAccessToken());
    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationUrl() {
    $scopes = [
      'identify',
      'email',
      'connections',
      'guilds',
      'guilds.join',
    ];

    $discord_scopes = $this->getScopes();
    if ($discord_scopes) {
      if (strpos($discord_scopes, ',')) {
        $scopes = array_merge($scopes, explode(',', $discord_scopes));
      }
      else {
        $scopes[] = $discord_scopes;
      }
    }

    // Returns the URL where user will be redirected.
    return $this->client->getAuthorizationUrl([
      'scope' => $scopes,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function requestEndPoint($method, $path, $domain = NULL, array $options = []) {
    $url = $this->client->apiDomain . $path;

    $request = $this->client->getAuthenticatedRequest($method, $url, $this->getAccessToken(), $options);

    $response = $this->client->getResponse($request);

    return $response->getBody()->getContents();
  }

  /**
   * {@inheritdoc}
   */
  public function getState() {
    return $this->client->getState();
  }

  /**
   * Get Discord auth settings.
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * Get Discord auth client.
   */
  public function getClient() {
    return $this->client;
  }

}
