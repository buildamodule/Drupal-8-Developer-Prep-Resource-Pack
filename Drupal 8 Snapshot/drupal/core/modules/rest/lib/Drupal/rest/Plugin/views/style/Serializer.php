<?php

/**
 * @file
 * Contains \Drupal\rest\Plugin\views\style\Serializer.
 */

namespace Drupal\rest\Plugin\views\style;

use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @Plugin(
 *   id = "serializer",
 *   module = "rest",
 *   title = @Translation("Serializer"),
 *   help = @Translation("Serializes views row data using the Serializer component."),
 *   display_types = {"data"}
 * )
 */
class Serializer extends StylePluginBase {

  /**
   * Overrides \Drupal\views\Plugin\views\style\StylePluginBase::$usesRowPlugin.
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Overrides Drupal\views\Plugin\views\style\StylePluginBase::$usesFields.
   */
  protected $usesGrouping = FALSE;

  /**
   * The serializer which serializes the views result.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The available serialization formats.
   *
   * @var array
   */
  protected $formats = array();

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->getParameter('serializer.formats')
    );
  }

  /**
   * Constructs a Plugin object.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, SerializerInterface $serializer, array $serializer_formats) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->definition = $plugin_definition + $configuration;
    $this->serializer = $serializer;
    $this->formats = $serializer_formats;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['formats'] = array('default' => array());

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['formats'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Accepted request formats'),
      '#description' => t('Request formats that will be allowed in responses. If none are selected all formats will be allowed.'),
      '#options' => drupal_map_assoc($this->formats),
      '#default_value' => $this->options['formats'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, &$form_state) {
    parent::submitOptionsForm($form, $form_state);

    $form_state['values']['style_options']['formats'] = array_filter($form_state['values']['style_options']['formats']);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = array();
    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row) {
      $rows[] = $this->view->rowPlugin->render($row);
    }

    return $this->serializer->serialize($rows, $this->displayHandler->getContentType());
  }

  /**
   * Gets a list of all available formats that can be requested.
   *
   * This will return the configured formats, or all formats if none have been
   * selected.
   *
   * @return array
   *   An array of formats.
   */
  public function getFormats() {
    if (!empty($this->options['formats'])) {
      return $this->options['formats'];
    }

    return $this->formats;
  }

}
