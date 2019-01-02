<?php

namespace Drupal\social_discord\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_discord\DiscordAuthManager;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth\SocialAuthDataHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Social Discord module routes.
 */
class DiscordController extends ControllerBase {

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;

  /**
   * The discord authentication manager.
   *
   * @var \Drupal\social_discord\DiscordAuthManager
   */
  private $authManager;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $request;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  private $dataHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_discord.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.data_handler')
    );
  }

  /**
   * DiscordController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_discord network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_discord\DiscordAuthManager $discord_auth_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   SocialAuthDataHandler object.
   */
  public function __construct(NetworkManager $network_manager,
                              SocialAuthUserManager $user_manager,
                              DiscordAuthManager $discord_auth_manager,
                              RequestStack $request,
                              SocialAuthDataHandler $data_handler) {

    $this->networkManager = $network_manager;
    $this->userManager = $user_manager;
    $this->authManager = $discord_auth_manager;
    $this->request = $request;
    $this->dataHandler = $data_handler;

    // Sets the plugin id.
    $this->dataHandler->setSessionPrefix('social_discord');
    $this->userManager->setPluginId('social_discord');

    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
  }

  /**
   * Checks access for redirectToDiscord.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function accessRedirect(AccountInterface $account) {
    // Check permissions and combine that with any custom access checking
    // needed. Pass forward parameters from the route and/or request as needed.
    $token = $this->dataHandler->get('access_token');
    return AccessResult::allowedIf(!$token);
  }

  /**
   * Response for path 'user/login/discord'.
   *
   * Redirects the user to Discord for authentication.
   */
  public function redirectToDiscord() {
    /* @var \Wohali\OAuth2\Client\Provider\Discord false $discord */
    $discord = $this->networkManager->createInstance('social_discord')->getSdk();

    // If discord client could not be obtained.
    if (!$discord) {
      drupal_set_message($this->t('Social Discord not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Destination parameter specified in url.
    $destination = $this->request->getCurrentRequest()->get('destination');
    // If destination parameter is set, save it.
    if ($destination) {
      $this->userManager->setDestination($destination);
    }

    // Discord service was returned, inject it to $authManager.
    $this->authManager->setClient($discord);

    // Generates the URL where the user will be redirected for Discord login.
    // If the user did not have email permission granted on previous attempt,
    // we use the re-request URL requesting only the email address.
    $discord_login_url = $this->authManager->getAuthorizationUrl();

    $state = $this->authManager->getState();

    $this->dataHandler->set('oauth2state', $state);

    return new TrustedRedirectResponse($discord_login_url);
  }

  /**
   * Response for path 'user/login/discord/callback'.
   *
   * Discord returns the user here after user has authenticated in Discord.
   */
  public function callback() {
    // Checks if user cancel login via Discord.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'access_denied') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('user.login');
    }

    /* @var \Wohali\OAuth2\Client\Provider\Discord|false $discord */
    $discord = $this->networkManager->createInstance('social_discord')->getSdk();

    // If Discord client could not be obtained.
    if (!$discord) {
      drupal_set_message($this->t('Social Discord not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');

    // Retrieves $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->userManager->nullifySessionKeys();
      drupal_set_message($this->t('Discord login failed. Invalid OAuth2 state.'), 'error');
      return $this->redirect('user.login');
    }

    // Saves access token to session.
    $this->authManager->setClient($discord)->authenticate();

    $this->dataHandler->set('access_token', $this->authManager->getAccessToken());

    // Gets user's info from Discord API.
    if (!$discord_profile = $this->authManager->getUserInfo()) {
      drupal_set_message($this->t('Discord login failed, could not load Discord profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Gets (or not) extra initial data.
    $data = $this->userManager->checkIfUserExists($discord_profile->getId()) ? NULL : $this->authManager->getExtraDetails();

    $avatarHash = $discord_profile->getAvatarHash();
    $avatar = NULL;
    if ($avatarHash) {
        $avatar_extension = substr($avatarHash, 0, 2) === 'a_' ? '.gif' : '.png';
        $avatar = sprintf(
            'https://cdn.discordapp.com/avatars/%s/%s%s',
            $discord_profile->getId(),
            $avatarHash,
            $avatar_extension
        );
    }

    // If user information could be retrieved.
    return $this->userManager->authenticateUser($discord_profile->getUsername(), $discord_profile->getEmail(), $discord_profile->getId(), $this->authManager->getAccessToken(), $avatar, $data);
  }

}
