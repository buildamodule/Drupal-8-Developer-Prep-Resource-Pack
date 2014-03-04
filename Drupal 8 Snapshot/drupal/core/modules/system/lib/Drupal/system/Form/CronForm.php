<?php

/**
 * @file
 * Contains \Drupal\system\Form\CronForm.
 */

namespace Drupal\system\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\Context\ContextInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\system\SystemConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Configure cron settings for this site.
 */
class CronForm extends SystemConfigFormBase {

  /**
   * Stores the state storage service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $state;

  /**
   * Constructs a CronForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\Context\ContextInterface $context
   *   The configuration context used for this configuration object.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $state
   *   The state key value store.
   */
  public function __construct(ConfigFactory $config_factory, ContextInterface $context, KeyValueStoreInterface $state) {
    parent::__construct($config_factory, $context);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.context.free'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'system_cron_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->configFactory->get('system.cron');

    $form['description'] = array(
      '#markup' => '<p>' . t('Cron takes care of running periodic tasks like checking for updates and indexing content for search.') . '</p>',
    );
    $form['run'] = array(
      '#type' => 'submit',
      '#value' => t('Run cron'),
      '#submit' => array(array($this, 'submitCron')),
    );

    $status = '<p>' . t('Last run: %cron-last ago.', array('%cron-last' => format_interval(REQUEST_TIME - $this->state->get('system.cron_last')))) . '</p>';
    $form['status'] = array(
      '#markup' => $status,
    );

    $form['cron_url'] = array(
      '#markup' => '<p>' . t('To run cron from outside the site, go to <a href="!cron">!cron</a>', array('!cron' => url('cron/' . $this->state->get('system.cron_key'), array('absolute' => TRUE)))) . '</p>',
    );

    $form['cron'] = array(
      '#type' => 'details',
    );
    $form['cron']['cron_safe_threshold'] = array(
      '#type' => 'select',
      '#title' => t('Run cron every'),
      '#description' => t('More information about setting up scheduled tasks can be found by <a href="@url">reading the cron tutorial on drupal.org</a>.', array('@url' => url('http://drupal.org/cron'))),
      '#default_value' => $config->get('threshold.autorun'),
      '#options' => array(0 => t('Never')) + drupal_map_assoc(array(3600, 10800, 21600, 43200, 86400, 604800), 'format_interval'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->configFactory->get('system.cron')
      ->set('threshold.autorun', $form_state['values']['cron_safe_threshold'])
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Runs cron and reloads the page.
   */
  public function submitCron(array &$form, array &$form_state) {
    // Run cron manually from Cron form.
    if (drupal_cron_run()) {
      drupal_set_message(t('Cron run successfully.'));
    }
    else {
      drupal_set_message(t('Cron run failed.'), 'error');
    }

    return new RedirectResponse(url('admin/config/system/cron', array('absolute' => TRUE)));
  }

}
