<?php

/**
 * @file
 * Contains \Drupal\action\Plugin\Action\GotoAction.
 */

namespace Drupal\action\Plugin\Action;

use Drupal\Core\Annotation\Action;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\PathBasedGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects to a different URL.
 *
 * @Action(
 *   id = "action_goto_action",
 *   label = @Translation("Redirect to URL"),
 *   type = "system"
 * )
 */
class GotoAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\PathBasedGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a new DeleteNode object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The tempstore factory.
   * @param \Drupal\Core\Routing\PathBasedGeneratorInterface $url_generator
   *   The url generator service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EventDispatcherInterface $dispatcher, PathBasedGeneratorInterface $url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->dispatcher = $dispatcher;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('event_dispatcher'), $container->get('url_generator'));
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $url = $this->urlGenerator
      ->generateFromPath($this->configuration['url'], array('absolute' => TRUE));
    $response = new RedirectResponse($url);
    $listener = function($event) use ($response) {
      $event->setResponse($response);
    };
    // Add the listener to the event dispatcher.
    $this->dispatcher->addListener(KernelEvents::RESPONSE, $listener);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultConfiguration() {
    return array(
      'url' => '',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form['url'] = array(
      '#type' => 'textfield',
      '#title' => t('URL'),
      '#description' => t('The URL to which the user should be redirected. This can be an internal URL like node/1234 or an external URL like @url.', array('@url' => 'http://drupal.org')),
      '#default_value' => $this->configuration['url'],
      '#required' => TRUE,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    $this->configuration['url'] = $form_state['values']['url'];
  }

}
