<?php

/**
 * Contains \Drupal\acquia_connector\Form\SettingsForm.
 */

namespace Drupal\acquia_connector\Form;

use Drupal\acquia_connector\Client;
use Drupal\acquia_connector\Migration;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\PrivateKey;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\acquia_connector\ConnectorException;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The private key.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected $privateKey;

  /**
   * The Acquia connector client.
   *
   * @var \Drupal\acquia_connector\Client
   */
  protected $client;

  /**
   * Constructs a \Drupal\aggregator\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\PrivateKey $private_key
   *   The private key.
   * @param \Drupal\acquia_connector\Client $client
   *   The Acquia client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, PrivateKey $private_key, Client $client) {
    parent::__construct($config_factory);

    $this->moduleHandler = $module_handler;
    $this->privateKey = $private_key;
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('private_key'),
      $container->get('acquia_connector.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acquia_connector.settings', 'acquia_search.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_connector_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('acquia_connector.settings');

    $identifier = $config->get('identifier');
    $key = $config->get('key');
    $subscription = $config->get('subscription_name');

    if (empty($identifier) && empty($key)) {
      return new RedirectResponse($this->url('acquia_connector.start'));
    }

    // Check our connection to the Acquia Network and validity of the credentials.
    try {
      $this->client->getSubscription($identifier, $key);
    }
    catch (ConnectorException $e) {
      $error_message = acquia_connector_connection_error_message($e->getCustomMessage('code', FALSE));
      $ssl_available = in_array('ssl', stream_get_transports(), TRUE) && !defined('ACQUIA_DEVELOPMENT_NOSSL') && $config->get('spi.ssl_verify');
      if (empty($error_message) && $ssl_available) {
        $error_message = $this->t('There was an error in validating your subscription credentials. You may want to try disabling SSL peer verification by setting the variable acquia_connector.settings:spi.ssl_verify to false.');
      }
      drupal_set_message($error_message, 'error', FALSE);
    }

    $form['connected'] = array(
      '#markup' => $this->t('<h3>Connected to the Acquia Network</h3>'),
    );
    if (!empty($subscription)) {
      $form['subscription'] = array(
        '#markup' => $this->t('Subscription: @sub <a href="@url">change</a>', array('@sub' => $subscription, '@url' => $this->url('acquia_connector.setup'))),
      );
    }
    $form['connection'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Acquia Subscription Settings'),
      '#collapsible' => FALSE,
    );

    $form['migrate'] = array(
      '#type' => 'details',
      '#title' => $this->t('Acquia Cloud Migrate'),
      '#description' => $this->t('Transfer a fully-functional copy of your site to Acquia Cloud. <a href="@url">Learn more</a>.', array('@url' => Url::fromUri('https://docs.acquia.com/cloud/site/import/connector')->getUri())),
      // Collapse migrate if Acquia hosting.
      '#open' => !\Drupal::request()->server->has('AH_SITE_GROUP'),
    );
    $form['migrate']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Migrate'),
      '#submit' => ['::submitMigrateGoForm'],
    );

    $last_migration = \Drupal::state()->get('migrate.cloud', []);

    if (!empty($last_migration['db_file']) || !empty($last_migration['tar_file']) || !empty($last_migration['dir'])) {
      // Replace Upload button with Cleanup.
      unset($form['migrate']['#description']);
      $form['migrate']['#prefix'] = '<div class="messages error">' . $this->t('Temporary files were leftover from last migration attempt.') . '</div>';
      $form['migrate']['submit']['#value'] = $this->t('Cleanup files');
      $form['migrate']['submit']['#submit'] = ['::submitMigrateCleanupForm'];
    }

    // Help documentation is local unless the Help module is disabled.
    if ($this->moduleHandler->moduleExists('help')) {
      $help_url = \Drupal::url('help.page', array('name' => 'acquia_connector'));
    }
    else {
      $help_url = Url::fromUri('https://docs.acquia.com/network/install')->getUri();
    }

    if (!empty($identifier) && !empty($key)) {
      $ssl_available = (in_array('ssl', stream_get_transports(), TRUE) && !defined('ACQUIA_DEVELOPMENT_NOSSL'));

      $form['connection']['spi'] = array(
        '#prefix' => '<div class="acquia-spi">',
        '#suffix' => '</div>',
        '#weight' => 0,
      );

      $form['connection']['description']['#markup'] = $this->t('Allow collection and examination of the following items. <a href="@url">Learn more</a>.', array('@url' => $help_url));
      $form['connection']['description']['#weight'] = '-1';

      $form['connection']['spi']['admin_priv'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Admin privileges'),
        '#default_value' => $config->get('spi.admin_priv'),
      );
      $form['connection']['spi']['send_node_user'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Nodes and users'),
        '#default_value' => $config->get('spi.send_node_user'),
      );
      $form['connection']['spi']['send_watchdog'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Watchdog logs'),
        '#default_value' => $config->get('spi.send_watchdog'),
      );
      $form['connection']['spi']['module_diff_data'] = array(
        '#type' => 'checkbox',
        '#title' => t('Source code'),
        '#default_value' => (int) $config->get('spi.module_diff_data') && $ssl_available,
        '#description' => $this->t('Source code analysis requires a SSL connection and for your site to be publicly accessible. <a href="@url">Learn more</a>.', array('@url' => $help_url)),
        '#disabled' => !$ssl_available,
      );
      $form['connection']['acquia_dynamic_banner'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Receive updates from Acquia Subscription'),
        '#default_value' => $config->get('spi.dynamic_banner'),
      );
      $form['connection']['alter_variables'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Allow Insight to update list of approved variables.'),
        '#default_value' => (int) $config->get('spi.set_variables_override'),
        '#description' => $this->t('Insight can set variables on your site to recommended values at your approval, but only from a specific list of variables. Check this box to allow Insight to update the list of approved variables. <a href="@url">Learn more</a>.', array('@url' => $help_url)),
      );

      $use_cron = $config->get('spi.use_cron');

      $form['connection']['use_cron'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Send via Drupal cron'),
        '#default_value' => $use_cron,
      );

      $form['#attached']['library'][] = 'acquia_connector/acquia_connector.form';
      $key = sha1($this->privateKey->get());
      $url = Url::fromRoute('acquia_connector.send', [], ['query' => ['key' => $key], 'absolute' => TRUE])->toString();

      $form['connection']['use_cron_url'] = array(
        '#type' => 'container',
        '#children' => $this->t('Enter the following URL in your server\'s crontab to send SPI data:<br /><em>@url</em>', array('@url' => $url)),
        '#states' => array(
          'visible' => array(
            ':input[name="use_cron"]' => array('checked' => FALSE),
          )
        ),
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('acquia_connector.settings');
    $values = $form_state->getValues();
    $config->set('spi.module_diff_data', $values['module_diff_data'])
      ->set('spi.dynamic_banner', $values['acquia_dynamic_banner'])
      ->set('spi.admin_priv', $values['admin_priv'])
      ->set('spi.send_node_user', $values['send_node_user'])
      ->set('spi.send_watchdog', $values['send_watchdog'])
      ->set('spi.use_cron', $values['use_cron'])
      ->set('spi.set_variables_override', $values['alter_variables'])
      ->save();

    parent::submitForm($form, $form_state);
    // Send information as soon as the key/identifier pair is submitted.
    \Drupal::service('acquia_connector.spi')->sendFullSpi(ACQUIA_SPI_METHOD_CREDS);
  }

  /**
   * Submit handler for Migrate button on settings form.
   */
  public function submitMigrateGoForm($form, FormStateInterface &$form_state) {
    $form_state->setRedirect('acquia_connector.migrate');
  }

  /**
   * @param $form
   * @param $form_state
   */
  public function submitMigrateCleanupForm($form, FormStateInterface &$form_state) {
    $migration = $this->config('acquia_connector.settings')->get('cloud_migration');
    $migration_class = new Migration();
    $migration_class->cleanup($migration);
    drupal_set_message($this->t('Temporary files removed'));
    $form_state->setRedirect('acquia_connector.settings');
  }

}
