<?php

namespace Drupal\social_discord\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\social_auth\Form\SocialAuthSettingsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \RestCord\DiscordClient;

/**
 * Settings form for Social Discord.
 */
class DiscordSettingsForm extends SocialAuthSettingsForm {

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this class.
    return new static(
    // Load the services required to construct this class.
      $container->get('config.factory'),
      $container->get('router.route_provider'),
      $container->get('path.validator'),
      $container->get('router.request_context')
    );
  }

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   Used to check if route exists.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   Used to check if path is valid and exists.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   Holds information about the current request.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              RouteProviderInterface $route_provider,
                              PathValidatorInterface $path_validator,
                              RequestContext $request_context) {
    parent::__construct($config_factory, $route_provider, $path_validator);
    $this->requestContext = $request_context;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_discord_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array_merge(
      parent::getEditableConfigNames(),
      ['social_discord.settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('social_discord.settings');

    $form['discord_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Discord Client settings'),
      '#open' => TRUE,
      '#description' => $this->t('You need to first create a Discord App at <a href="@discord-dev">@discord-dev</a>', ['@discord-dev' => 'https://discordapp.com/developers/applications']),
    ];

    $form['discord_settings']['client_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
      '#description' => $this->t('Copy the Client ID here.'),
    ];

    $form['discord_settings']['client_secret'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret'),
      '#description' => $this->t('Copy the Client Secret here.'),
    ];

    $form['discord_settings']['authorized_redirect_url'] = [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#title' => $this->t('Authorized redirect URIs'),
      '#description' => $this->t('Copy this value to <em>Authorized redirect URIs</em> field of your Discord App settings.'),
      '#default_value' => $GLOBALS['base_url'] . '/user/login/discord/callback',
    ];

    $form['discord_settings']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $roles = user_roles();
    $options = [];
    foreach ($roles as $key => $role_object) {
      if ($key != 'anonymous' && $key != 'authenticated' && $key != 'administrator') {
        $options[$key] = Html::escape($role_object->get('label'));
      }
    }

    $form['discord_settings']['advanced']['add_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Add roles to users authenticated by Discord.'),
      '#options' => $options,
      '#default_value' => $config->get('add_roles'),
    ];
    if (empty($roles)) {
      $form['discord_settings']['add_roles']['#description'] = $this->t('No roles found.');
    }

    $form['discord_settings']['advanced']['bot_token'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Discord Bot Token'),
      '#default_value' => $config->get('bot_token'),
      '#description' => $this->t('A Discord bot token for advanced functions. This should be the same bot attached to the Discord<br>
                                  application entered above.'),
    ];

    $form['discord_settings']['advanced']['guild_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Discord Server ID'),
      '#default_value' => $config->get('guild_id'),
      '#description' => $this->t('A Discord server ID to join users to when they login. Requires a bot token to work.'),
    ];

    $form['discord_settings']['advanced']['scopes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Scopes for API call'),
      '#default_value' => $config->get('scopes'),
      '#description' => $this->t('Define any additional scopes to be requested, separated by a comma (e.g.: rpc,messages.read).<br>
                                  The scopes \'identify\', \'email\', \'connections\', \'guilds\', and \'guilds.join\' are added<br>
                                  by default and always requested. You can see the full list of valid scopes and their description<br>
                                  <a href="@scopes">here</a>.', ['@scopes' => 'https://discordapp.com/developers/docs/topics/oauth2#shared-resources-oauth2-scopes']),
    ];

    $form['discord_settings']['advanced']['endpoints'] = [
      '#type' => 'textarea',
      '#title' => $this->t('API calls to be made to collect data'),
      '#default_value' => $config->get('endpoints'),
      '#description' => $this->t('Define the Endpoints to be requested when user authenticates with Discord for the first time<br>
                                  Enter each endpoint in different lines in the format <em>endpoint</em>|<em>name_of_endpoint</em>.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('social_discord.settings')
      ->set('client_id', trim($values['client_id']))
      ->set('client_secret', trim($values['client_secret']))
      ->set('add_roles', $values['add_roles'])
      ->set('bot_token', $values['bot_token'])
      ->set('guild_id', $values['guild_id'])
      ->set('scopes', $values['scopes'])
      ->set('endpoints', $values['endpoints'])
      ->save();

    if ($values['bot_token']) {
      $discord = new \RestCord\DiscordClient(['token' => $values['bot_token']]);
      $discord->gateway->getGatewayBot();
    }

    parent::submitForm($form, $form_state);
  }

}
