<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\style\Table.
 */

namespace Drupal\views\Plugin\views\style;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\views\Plugin\views\wizard\WizardInterface;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Style plugin to render each item as a row in a table.
 *
 * @ingroup views_style_plugins
 *
 * @Plugin(
 *   id = "table",
 *   title = @Translation("Table"),
 *   help = @Translation("Displays rows in a table."),
 *   theme = "views_view_table",
 *   display_types = {"normal"}
 * )
 */
class Table extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * Does the style plugin allows to use style plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = FALSE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * Contains the current active sort column.
   * @var string
   */
  public $active;

  /**
   * Contains the current active sort order, either desc or asc.
   * @var string
   */
  public $order;

  /**
   * Contains the current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('request'));
  }

  /**
   * Constructs a Table object.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->request = $request;
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['columns'] = array('default' => array());
    $options['default'] = array('default' => '');
    $options['info'] = array('default' => array());
    $options['override'] = array('default' => TRUE, 'bool' => TRUE);
    $options['sticky'] = array('default' => FALSE, 'bool' => TRUE);
    $options['order'] = array('default' => 'asc');
    $options['caption'] = array('default' => '', 'translatable' => TRUE);
    $options['summary'] = array('default' => '', 'translatable' => TRUE);
    $options['description'] = array('default' => '', 'translatable' => TRUE);
    $options['empty_table'] = array('default' => FALSE, 'bool' => TRUE);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSort() {
    $order = $this->request->query->get('order');
    if (!isset($order) && ($this->options['default'] == -1 || empty($this->view->field[$this->options['default']]))) {
      return TRUE;
    }

    // If a sort we don't know anything about gets through, exit gracefully.
    if (isset($order) && empty($this->view->field[$order])) {
      return TRUE;
    }

    // Let the builder know whether or not we're overriding the default sorts.
    return empty($this->options['override']);
  }

  /**
   * Add our actual sort criteria
   */
  public function buildSortPost() {
    $query = $this->request->query;
    $order = $query->get('order');
    if (!isset($order)) {
      // check for a 'default' clicksort. If there isn't one, exit gracefully.
      if (empty($this->options['default'])) {
        return;
      }
      $sort = $this->options['default'];
      if (!empty($this->options['info'][$sort]['default_sort_order'])) {
        $this->order = $this->options['info'][$sort]['default_sort_order'];
      }
      else {
        $this->order = !empty($this->options['order']) ? $this->options['order'] : 'asc';
      }
    }
    else {
      $sort = $order;
      // Store the $order for later use.
      $request_sort = $query->get('sort');
      $this->order = !empty($request_sort) ? strtolower($request_sort) : 'asc';
    }

    // If a sort we don't know anything about gets through, exit gracefully.
    if (empty($this->view->field[$sort])) {
      return;
    }

    // Ensure $this->order is valid.
    if ($this->order != 'asc' && $this->order != 'desc') {
      $this->order = 'asc';
    }

    // Store the $sort for later use.
    $this->active = $sort;

    // Tell the field to click sort.
    $this->view->field[$sort]->clickSort($this->order);
  }

  /**
   * Normalize a list of columns based upon the fields that are
   * available. This compares the fields stored in the style handler
   * to the list of fields actually in the view, removing fields that
   * have been removed and adding new fields in their own column.
   *
   * - Each field must be in a column.
   * - Each column must be based upon a field, and that field
   *   is somewhere in the column.
   * - Any fields not currently represented must be added.
   * - Columns must be re-ordered to match the fields.
   *
   * @param $columns
   *   An array of all fields; the key is the id of the field and the
   *   value is the id of the column the field should be in.
   * @param $fields
   *   The fields to use for the columns. If not provided, they will
   *   be requested from the current display. The running render should
   *   send the fields through, as they may be different than what the
   *   display has listed due to access control or other changes.
   *
   * @return array
   *    An array of all the sanitized columns.
   */
  public function sanitizeColumns($columns, $fields = NULL) {
    $sanitized = array();
    if ($fields === NULL) {
      $fields = $this->displayHandler->getOption('fields');
    }
    // Preconfigure the sanitized array so that the order is retained.
    foreach ($fields as $field => $info) {
      // Set to itself so that if it isn't touched, it gets column
      // status automatically.
      $sanitized[$field] = $field;
    }

    foreach ($columns as $field => $column) {
      // first, make sure the field still exists.
      if (!isset($sanitized[$field])) {
        continue;
      }

      // If the field is the column, mark it so, or the column
      // it's set to is a column, that's ok
      if ($field == $column || $columns[$column] == $column && !empty($sanitized[$column])) {
        $sanitized[$field] = $column;
      }
      // Since we set the field to itself initially, ignoring
      // the condition is ok; the field will get its column
      // status back.
    }

    return $sanitized;
  }

  /**
   * Render the given style.
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);
    $handlers = $this->displayHandler->getHandlers('field');
    if (empty($handlers)) {
      $form['error_markup'] = array(
        '#markup' => '<div class="messages messages--error">' . t('You need at least one field before you can configure your table settings') . '</div>',
      );
      return;
    }

    $form['override'] = array(
      '#type' => 'checkbox',
      '#title' => t('Override normal sorting if click sorting is used'),
      '#default_value' => !empty($this->options['override']),
    );

    $form['sticky'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable Drupal style "sticky" table headers (Javascript)'),
      '#default_value' => !empty($this->options['sticky']),
      '#description' => t('(Sticky header effects will not be active for preview below, only on live output.)'),
    );

    $form['caption'] = array(
      '#type' => 'textfield',
      '#title' => t('Caption for the table'),
      '#description' => t('A title which is semantically associated to your table for increased accessibility.'),
      '#default_value' => $this->options['caption'],
      '#maxlength' => 255,
    );

    $form['accessibility_details'] = array(
      '#type' => 'details',
      '#title' => t('Table details'),
      '#collapsed' => TRUE,
    );

    $form['summary'] = array(
      '#title' => t('Summary title'),
      '#type' => 'textfield',
      '#default_value' => $this->options['summary'],
      '#fieldset' => 'accessibility_details',
    );

    $form['description'] = array(
      '#title' => t('Table description'),
      '#type' => 'textarea',
      '#description' => t('Provide additional details about the table to increase accessibility.'),
      '#default_value' => $this->options['description'],
      '#states' => array(
        'visible' => array(
          'input[name="style_options[summary]"]' => array('filled' => TRUE),
        ),
      ),
      '#fieldset' => 'accessibility_details',
    );

    // Note: views UI registers this theme handler on our behalf. Your module
    // will have to register your theme handlers if you do stuff like this.
    $form['#theme'] = 'views_ui_style_plugin_table';

    $columns = $this->sanitizeColumns($this->options['columns']);

    // Create an array of allowed columns from the data we know:
    $field_names = $this->displayHandler->getFieldLabels();

    if (isset($this->options['default'])) {
      $default = $this->options['default'];
      if (!isset($columns[$default])) {
        $default = -1;
      }
    }
    else {
      $default = -1;
    }

    foreach ($columns as $field => $column) {
      $column_selector = ':input[name="style_options[columns][' . $field . ']"]';

      $form['columns'][$field] = array(
        '#type' => 'select',
        '#options' => $field_names,
        '#default_value' => $column,
      );
      if ($handlers[$field]->clickSortable()) {
        $form['info'][$field]['sortable'] = array(
          '#type' => 'checkbox',
          '#default_value' => !empty($this->options['info'][$field]['sortable']),
          '#states' => array(
            'visible' => array(
              $column_selector => array('value' => $field),
            ),
          ),
        );
        $form['info'][$field]['default_sort_order'] = array(
          '#type' => 'select',
          '#options' => array('asc' => t('Ascending'), 'desc' => t('Descending')),
          '#default_value' => !empty($this->options['info'][$field]['default_sort_order']) ? $this->options['info'][$field]['default_sort_order'] : 'asc',
          '#states' => array(
            'visible' => array(
              $column_selector => array('value' => $field),
              ':input[name="style_options[info][' . $field . '][sortable]"]' => array('checked' => TRUE),
            ),
          ),
        );
        // Provide an ID so we can have such things.
        $radio_id = drupal_html_id('edit-default-' . $field);
        $form['default'][$field] = array(
          '#type' => 'radio',
          '#return_value' => $field,
          '#parents' => array('style_options', 'default'),
          '#id' => $radio_id,
          // because 'radio' doesn't fully support '#id' =(
          '#attributes' => array('id' => $radio_id),
          '#default_value' => $default,
          '#states' => array(
            'visible' => array(
              $column_selector => array('value' => $field),
            ),
          ),
        );
      }
      $form['info'][$field]['align'] = array(
        '#type' => 'select',
        '#default_value' => !empty($this->options['info'][$field]['align']) ? $this->options['info'][$field]['align'] : '',
        '#options' => array(
          '' => t('None'),
          'views-align-left' => t('Left'),
          'views-align-center' => t('Center'),
          'views-align-right' => t('Right'),
          ),
        '#states' => array(
          'visible' => array(
            $column_selector => array('value' => $field),
          ),
        ),
      );
      $form['info'][$field]['separator'] = array(
        '#type' => 'textfield',
        '#size' => 10,
        '#default_value' => isset($this->options['info'][$field]['separator']) ? $this->options['info'][$field]['separator'] : '',
        '#states' => array(
          'visible' => array(
            $column_selector => array('value' => $field),
          ),
        ),
      );
      $form['info'][$field]['empty_column'] = array(
        '#type' => 'checkbox',
        '#default_value' => isset($this->options['info'][$field]['empty_column']) ? $this->options['info'][$field]['empty_column'] : FALSE,
        '#states' => array(
          'visible' => array(
            $column_selector => array('value' => $field),
          ),
        ),
      );
      $form['info'][$field]['responsive'] = array(
        '#type' => 'select',
        '#default_value' => isset($this->options['info'][$field]['responsive']) ? $this->options['info'][$field]['responsive'] : '',
        '#options' => array('' => t('High'), RESPONSIVE_PRIORITY_MEDIUM => t('Medium'), RESPONSIVE_PRIORITY_LOW => t('Low')),
        '#states' => array(
          'visible' => array(
            $column_selector => array('value' => $field),
          ),
        ),
      );

      // markup for the field name
      $form['info'][$field]['name'] = array(
        '#markup' => $field_names[$field],
      );
    }

    // Provide a radio for no default sort
    $form['default'][-1] = array(
      '#type' => 'radio',
      '#return_value' => -1,
      '#parents' => array('style_options', 'default'),
      '#id' => 'edit-default-0',
      '#default_value' => $default,
    );

    $form['empty_table'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show the empty text in the table'),
      '#default_value' => $this->options['empty_table'],
      '#description' => t('Per default the table is hidden for an empty view. With this option it is posible to show an empty table with the text in it.'),
    );

    $form['description_markup'] = array(
      '#markup' => '<div class="description form-item">' . t('Place fields into columns; you may combine multiple fields into the same column. If you do, the separator in the column specified will be used to separate the fields. Check the sortable box to make that column click sortable, and check the default sort radio to determine which column will be sorted by default, if any. You may control column order and field labels in the fields section.') . '</div>',
    );
  }

  public function evenEmpty() {
    return parent::evenEmpty() || !empty($this->options['empty_table']);
  }

  public function wizardSubmit(&$form, &$form_state, WizardInterface $wizard, &$display_options, $display_type) {
    // If any of the displays use the table style, take sure that the fields
    // always have a labels by unsetting the override.
    foreach ($display_options['default']['fields'] as &$field) {
      unset($field['label']);
    }
  }


}
