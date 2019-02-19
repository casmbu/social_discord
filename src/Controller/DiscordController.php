<?php

namespace Drupal\social_discord\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\Controller\OAuth2ControllerBase;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\User\UserAuthenticator;
use Drupal\social_discord\DiscordAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Social Discord module routes.
 */
class DiscordController extends OAuth2ControllerBase
{
    /**
     * DiscordController constructor.
     *
     * @param \Drupal\Core\Messenger\MessengerInterface $messenger
     *   The messenger service.
     * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
     *   Used to get an instance of social_discord network plugin.
     * @param \Drupal\social_auth\User\UserAuthenticator $user_authenticator
     *   Used to manage user authentication/registration.
     * @param \Drupal\social_discord\DiscordAuthManager $discord_manager
     *   Used to manage authentication methods.
     * @param \Symfony\Component\HttpFoundation\RequestStack $request
     *   Used to access GET parameters.
     * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
     *   The Social Auth data handler.
     */
    public function __construct(
        MessengerInterface $messenger,
        NetworkManager $network_manager,
        UserAuthenticator $user_authenticator,
        DiscordAuthManager $discord_manager,
        RequestStack $request,
        SocialAuthDataHandler $data_handler
    ) {
        parent::__construct(
            'Social Discord',
            'social_discord',
            $messenger,
            $network_manager,
            $user_authenticator,
            $discord_manager,
            $request,
            $data_handler
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('messenger'),
            $container->get('plugin.network.manager'),
            $container->get('social_auth.user_authenticator'),
            $container->get('social_discord.manager'),
            $container->get('request_stack'),
            $container->get('social_auth.data_handler')
        );
    }

    /**
     * Checks access for redirectToDiscord.
     *
     * @param \Drupal\Core\Session\AccountInterface $account
     *   Run access checks for this account.
     */
    public function accessRedirect(AccountInterface $account)
    {
        // Check permissions and combine that with any custom access checking
        // needed. Pass forward parameters from the route and/or request as needed.
        $token = $this->dataHandler->get('access_token');
        return AccessResult::allowedIf(!$token);
    }

    /**
     * Response for path 'user/login/discord/callback'.
     *
     * Discord returns the user here after user has authenticated in Discord.
     */
    public function callback()
    {
        // Checks if user cancel login via Discord.
        $error = $this->request->getCurrentRequest()->get('error');
        if ($error == 'access_denied') {
            drupal_set_message($this->t('You could not be authenticated.'), 'error');
            return $this->redirect('user.login');
        }

        /* @var \Wohali\OAuth2\Client\Provider\Discord|false $discord */
        $discord_profile = $this->processCallback();

        // If authentication was successful.
        if ($discord_profile !== null) {
            // Gets (or not) extra initial data.
            $data = (
                $this->userAuthenticator->checkProviderIsAssociated($discord_profile->getId())
                ? null
                : $this->providerManager->getExtraDetails()
            );

            $avatarHash = $discord_profile->getAvatarHash();
            $avatar = null;
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
            return $this->userAuthenticator->authenticateUser(
                $discord_profile->getUsername(),
                $discord_profile->getEmail(),
                $discord_profile->getId(),
                $this->providerManager->getAccessToken(),
                $avatar,
                $data
            );
        }

        return $this->redirect('user.login');
    }
}
