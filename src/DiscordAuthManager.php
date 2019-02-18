<?php

namespace Drupal\social_discord;

use Drupal\social_auth\AuthManager\OAuth2Manager;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Contains all the logic for Discord OAuth2 authentication.
 */
class DiscordAuthManager extends OAuth2Manager {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $settings
   *   The implementer settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ConfigFactory $settings, LoggerChannelFactoryInterface $logger_factory) {
	parent::__construct($settings->get('social_discord.settings'), $logger_factory);
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
