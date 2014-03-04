<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\field\FieldPluginBase.
 */

namespace Drupal\views\Plugin\views\field;

use Drupal\views\Plugin\views\HandlerBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * @defgroup views_field_handlers Views field handlers
 * @{
 * Handlers to tell Views how to build and display fields.
 *
 */

/**
 * Indicator of the renderText() method for rendering a single item.
 * (If no render_item() is present).
 */
define('VIEWS_HANDLER_RENDER_TEXT_PHASE_SINGLE_ITEM', 0);

/**
 * Indicator of the renderText() method for rendering the whole element.
 * (if no render_item() method is available).
 */
define('VIEWS_HANDLER_RENDER_TEXT_PHASE_COMPLETELY', 1);

/**
 * Indicator of the renderText() method for rendering the empty text.
 */
define('VIEWS_HANDLER_RENDER_TEXT_PHASE_EMPTY', 2);

/**
 * Base field handler that has no options and renders an unformatted field.
 *
 * Definition terms:
 * - additional fields: An array of fields that should be added to the query
 *                      for some purpose. The array is in the form of:
 *                      array('identifier' => array('table' => tablename,
 *                      'field' => fieldname); as many fields as are necessary
 *                      may be in this array.
 * - click sortable: If TRUE, this field may be click sorted.
 *
 * @ingroup views_field_handlers
 */
abstract class FieldPluginBase extends HandlerBase {

  var $field_alias = 'unknown';
  var $aliases = array();

  /**
   * The field value prior to any rewriting.
   *
   * @var mixed
   */
  public $original_value = NULL;

  /**
   * @var array
   * Stores additional fields which get's added to the query.
   * The generated aliases are stored in $aliases.
   */
  var $additional_fields = array();

  /**
   * Overrides Drupal\views\Plugin\views\HandlerBase::init().
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields = array();
    if (!empty($this->definition['additional fields'])) {
      $this->additional_fields = $this->definition['additional fields'];
    }

    if (!isset($this->options['exclude'])) {
      $this->options['exclude'] = '';
    }
  }

  /**
   * Determine if this field can allow advanced rendering.
   *
   * Fields can set this to FALSE if they do not wish to allow
   * token based rewriting or link-making.
   */
  protected function allowAdvancedRender() {
    return TRUE;
  }

  /**
   * Called to add the field to a query.
   */
  public function query() {
    $this->ensureMyTable();
    // Add the field.
    $params = $this->options['group_type'] != 'group' ? array('function' => $this->options['group_type']) : array();
    $this->field_alias = $this->query->addField($this->tableAlias, $this->realField, NULL, $params);

    $this->addAdditionalFields();
  }

  /**
   * Add 'additional' fields to the query.
   *
   * @param $fields
   * An array of fields. The key is an identifier used to later find the
   * field alias used. The value is either a string in which case it's
   * assumed to be a field on this handler's table; or it's an array in the
   * form of
   * @code array('table' => $tablename, 'field' => $fieldname) @endcode
   */
  protected function addAdditionalFields($fields = NULL) {
    if (!isset($fields)) {
      // notice check
      if (empty($this->additional_fields)) {
        return;
      }
      $fields = $this->additional_fields;
    }

    $group_params = array();
    if ($this->options['group_type'] != 'group') {
      $group_params = array(
        'function' => $this->options['group_type'],
      );
    }

    if (!empty($fields) && is_array($fields)) {
      foreach ($fields as $identifier => $info) {
        if (is_array($info)) {
          if (isset($info['table'])) {
            $table_alias = $this->query->ensureTable($info['table'], $this->relationship);
          }
          else {
            $table_alias = $this->tableAlias;
          }

          if (empty($table_alias)) {
            debug(t('Handler @handler tried to add additional_field @identifier but @table could not be added!', array('@handler' => $this->definition['handler'], '@identifier' => $identifier, '@table' => $info['table'])));
            $this->aliases[$identifier] = 'broken';
            continue;
          }

          $params = array();
          if (!empty($info['params'])) {
            $params = $info['params'];
          }

          $params += $group_params;
          $this->aliases[$identifier] = $this->query->addField($table_alias, $info['field'], NULL, $params);
        }
        else {
          $this->aliases[$info] = $this->query->addField($this->tableAlias, $info, NULL, $group_params);
        }
      }
    }
  }

  /**
   * Called to determine what to tell the clicksorter.
   */
  public function clickSort($order) {
    if (isset($this->field_alias)) {
      // Since fields should always have themselves already added, just
      // add a sort on the field.
      $params = $this->options['group_type'] != 'group' ? array('function' => $this->options['group_type']) : array();
      $this->query->addOrderBy(NULL, NULL, $order, $this->field_alias, $params);
    }
  }

  /**
   * Determine if this field is click sortable.
   *
   * @return bool
   *   The value of 'click sortable' from the plugin definition, this defaults
   *   to TRUE if not set.
   */
  public function clickSortable() {
    return isset($this->definition['click sortable']) ? $this->definition['click sortable'] : TRUE;
  }

  /**
   * Get this field's label.
   */
  public function label() {
    if (!isset($this->options['label'])) {
      return '';
    }
    return $this->options['label'];
  }

  /**
   * Return an HTML element based upon the field's element type.
   */
  public function elementType($none_supported = FALSE, $default_empty = FALSE, $inline = FALSE) {
    if ($none_supported) {
      if ($this->options['element_type'] === '0') {
        return '';
      }
    }
    if ($this->options['element_type']) {
      return check_plain($this->options['element_type']);
    }

    if ($default_empty) {
      return '';
    }

    if ($inline) {
      return 'span';
    }

    if (isset($this->definition['element type'])) {
      return $this->definition['element type'];
    }

    return 'span';
  }

  /**
   * Return an HTML element for the label based upon the field's element type.
   */
  public function elementLabelType($none_supported = FALSE, $default_empty = FALSE) {
    if ($none_supported) {
      if ($this->options['element_label_type'] === '0') {
        return '';
      }
    }
    if ($this->options['element_label_type']) {
      return check_plain($this->options['element_label_type']);
    }

    if ($default_empty) {
      return '';
    }

    return 'span';
  }

  /**
   * Return an HTML element for the wrapper based upon the field's element type.
   */
  public function elementWrapperType($none_supported = FALSE, $default_empty = FALSE) {
    if ($none_supported) {
      if ($this->options['element_wrapper_type'] === '0') {
        return 0;
      }
    }
    if ($this->options['element_wrapper_type']) {
      return check_plain($this->options['element_wrapper_type']);
    }

    if ($default_empty) {
      return '';
    }

    return 'div';
  }

  /**
   * Provide a list of elements valid for field HTML.
   *
   * This function can be overridden by fields that want more or fewer
   * elements available, though this seems like it would be an incredibly
   * rare occurence.
   */
  public function getElements() {
    static $elements = NULL;
    if (!isset($elements)) {
      // @todo Add possible html5 elements.
      $elements = array(
        '' => t(' - Use default -'),
        '0' => t('- None -')
      );
      $elements += \Drupal::config('views.settings')->get('field_rewrite_elements');
    }

    return $elements;
  }

  /**
   * Return the class of the field.
   */
  public function elementClasses($row_index = NULL) {
    $classes = explode(' ', $this->options['element_class']);
    foreach ($classes as &$class) {
      $class = $this->tokenizeValue($class, $row_index);
      $class = drupal_clean_css_identifier($class);
    }
    return implode(' ', $classes);
  }

  /**
   * Replace a value with tokens from the last field.
   *
   * This function actually figures out which field was last and uses its
   * tokens so they will all be available.
   */
  public function tokenizeValue($value, $row_index = NULL) {
    if (strpos($value, '[') !== FALSE || strpos($value, '!') !== FALSE || strpos($value, '%') !== FALSE) {
      $fake_item = array(
        'alter_text' => TRUE,
        'text' => $value,
      );

      // Use isset() because empty() will trigger on 0 and 0 is
      // the first row.
      if (isset($row_index) && isset($this->view->style_plugin->render_tokens[$row_index])) {
        $tokens = $this->view->style_plugin->render_tokens[$row_index];
      }
      else {
        // Get tokens from the last field.
        $last_field = end($this->view->field);
        if (isset($last_field->last_tokens)) {
          $tokens = $last_field->last_tokens;
        }
        else {
          $tokens = $last_field->getRenderTokens($fake_item);
        }
      }

      $value = strip_tags($this->renderAltered($fake_item, $tokens));
      if (!empty($this->options['alter']['trim_whitespace'])) {
        $value = trim($value);
      }
    }

    return $value;
  }

  /**
   * Return the class of the field's label.
   */
  public function elementLabelClasses($row_index = NULL) {
    $classes = explode(' ', $this->options['element_label_class']);
    foreach ($classes as &$class) {
      $class = $this->tokenizeValue($class, $row_index);
      $class = drupal_clean_css_identifier($class);
    }
    return implode(' ', $classes);
  }

  /**
   * Return the class of the field's wrapper.
   */
  public function elementWrapperClasses($row_index = NULL) {
    $classes = explode(' ', $this->options['element_wrapper_class']);
    foreach ($classes as &$class) {
      $class = $this->tokenizeValue($class, $row_index);
      $class = drupal_clean_css_identifier($class);
    }
    return implode(' ', $classes);
  }

  /**
   * Gets the entity matching the current row and relationship.
   *
   * @param \Drupal\views\ResultRow $values
   *   An object containing all retrieved values.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Returns the entity matching the values.
   */
  public function getEntity(ResultRow $values) {
    $relationship_id = $this->options['relationship'];
    if ($relationship_id == 'none') {
      return $values->_entity;
    }
    else {
      return $values->_relationship_entities[$relationship_id];
    }
  }

  /**
   * Get the value that's supposed to be rendered.
   *
   * This api exists so that other modules can easy set the values of the field
   * without having the need to change the render method as well.
   *
   * @param $values
   *   An object containing all retrieved values.
   * @param $field
   *   Optional name of the field where the value is stored.
   */
  public function getValue($values, $field = NULL) {
    $alias = isset($field) ? $this->aliases[$field] : $this->field_alias;
    if (isset($values->{$alias})) {
      return $values->{$alias};
    }
  }

  /**
   * Determines if this field will be available as an option to group the result
   * by in the style settings.
   *
   * @return bool
   *  TRUE if this field handler is groupable, otherwise FALSE.
   */
  public function useStringGroupBy() {
    return TRUE;
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['label'] = array('default' => $this->definition['title'], 'translatable' => TRUE);
    $options['exclude'] = array('default' => FALSE, 'bool' => TRUE);
    $options['alter'] = array(
      'contains' => array(
        'alter_text' => array('default' => FALSE, 'bool' => TRUE),
        'text' => array('default' => '', 'translatable' => TRUE),
        'make_link' => array('default' => FALSE, 'bool' => TRUE),
        'path' => array('default' => ''),
        'absolute' => array('default' => FALSE, 'bool' => TRUE),
        'external' => array('default' => FALSE, 'bool' => TRUE),
        'replace_spaces' => array('default' => FALSE, 'bool' => TRUE),
        'path_case' => array('default' => 'none', 'translatable' => FALSE),
        'trim_whitespace' => array('default' => FALSE, 'bool' => TRUE),
        'alt' => array('default' => '', 'translatable' => TRUE),
        'rel' => array('default' => ''),
        'link_class' => array('default' => ''),
        'prefix' => array('default' => '', 'translatable' => TRUE),
        'suffix' => array('default' => '', 'translatable' => TRUE),
        'target' => array('default' => ''),
        'nl2br' => array('default' => FALSE, 'bool' => TRUE),
        'max_length' => array('default' => ''),
        'word_boundary' => array('default' => TRUE, 'bool' => TRUE),
        'ellipsis' => array('default' => TRUE, 'bool' => TRUE),
        'more_link' => array('default' => FALSE, 'bool' => TRUE),
        'more_link_text' => array('default' => '', 'translatable' => TRUE),
        'more_link_path' => array('default' => ''),
        'strip_tags' => array('default' => FALSE, 'bool' => TRUE),
        'trim' => array('default' => FALSE, 'bool' => TRUE),
        'preserve_tags' => array('default' => ''),
        'html' => array('default' => FALSE, 'bool' => TRUE),
      ),
    );
    $options['element_type'] = array('default' => '');
    $options['element_class'] = array('default' => '');

    $options['element_label_type'] = array('default' => '');
    $options['element_label_class'] = array('default' => '');
    $options['element_label_colon'] = array('default' => TRUE, 'bool' => TRUE);

    $options['element_wrapper_type'] = array('default' => '');
    $options['element_wrapper_class'] = array('default' => '');

    $options['element_default_classes'] = array('default' => TRUE, 'bool' => TRUE);

    $options['empty'] = array('default' => '', 'translatable' => TRUE);
    $options['hide_empty'] = array('default' => FALSE, 'bool' => TRUE);
    $options['empty_zero'] = array('default' => FALSE, 'bool' => TRUE);
    $options['hide_alter_empty'] = array('default' => TRUE, 'bool' => TRUE);

    return $options;
  }

  /**
   * Performs some cleanup tasks on the options array before saving it.
   */
  public function submitOptionsForm(&$form, &$form_state) {
    $options = &$form_state['values']['options'];
    $types = array('element_type', 'element_label_type', 'element_wrapper_type');
    $classes = array_combine(array('element_class', 'element_label_class', 'element_wrapper_class'), $types);

    foreach ($types as $type) {
      if (!$options[$type . '_enable']) {
        $options[$type] = '';
      }
    }

    foreach ($classes as $class => $type) {
      if (!$options[$class . '_enable'] || !$options[$type . '_enable']) {
        $options[$class] = '';
      }
    }

    if (empty($options['custom_label'])) {
      $options['label'] = '';
      $options['element_label_colon'] = FALSE;
    }
  }

  /**
   * Default options form that provides the label widget that all fields
   * should have.
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $label = $this->label();
    $form['custom_label'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create a label'),
      '#default_value' => $label !== '',
      '#weight' => -103,
    );
    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#default_value' => $label,
      '#states' => array(
        'visible' => array(
          ':input[name="options[custom_label]"]' => array('checked' => TRUE),
        ),
      ),
      '#weight' => -102,
    );
    $form['element_label_colon'] = array(
      '#type' => 'checkbox',
      '#title' => t('Place a colon after the label'),
      '#default_value' => $this->options['element_label_colon'],
      '#states' => array(
        'visible' => array(
          ':input[name="options[custom_label]"]' => array('checked' => TRUE),
        ),
      ),
      '#weight' => -101,
    );

    $form['exclude'] = array(
      '#type' => 'checkbox',
      '#title' => t('Exclude from display'),
      '#default_value' => $this->options['exclude'],
      '#description' => t('Enable to load this field as hidden. Often used to group fields, or to use as token in another field.'),
      '#weight' => -100,
    );

    $form['style_settings'] = array(
      '#type' => 'details',
      '#title' => t('Style settings'),
      '#collapsed' => TRUE,
      '#weight' => 99,
    );

    $form['element_type_enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Customize field HTML'),
      '#default_value' => !empty($this->options['element_type']) || (string) $this->options['element_type'] == '0' || !empty($this->options['element_class']) || (string) $this->options['element_class'] == '0',
      '#fieldset' => 'style_settings',
    );
    $form['element_type'] = array(
      '#title' => t('HTML element'),
      '#options' => $this->getElements(),
      '#type' => 'select',
      '#default_value' => $this->options['element_type'],
      '#description' => t('Choose the HTML element to wrap around this field, e.g. H1, H2, etc.'),
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_type_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#fieldset' => 'style_settings',
    );

    $form['element_class_enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create a CSS class'),
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_type_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#default_value' => !empty($this->options['element_class']) || (string) $this->options['element_class'] == '0',
      '#fieldset' => 'style_settings',
    );
    $form['element_class'] = array(
      '#title' => t('CSS class'),
      '#description' => t('You may use token substitutions from the rewriting section in this class.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['element_class'],
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_type_enable]"]' => array('checked' => TRUE),
          ':input[name="options[element_class_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#fieldset' => 'style_settings',
    );

    $form['element_label_type_enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Customize label HTML'),
      '#default_value' => !empty($this->options['element_label_type']) || (string) $this->options['element_label_type'] == '0' || !empty($this->options['element_label_class']) || (string) $this->options['element_label_class'] == '0',
      '#fieldset' => 'style_settings',
    );
    $form['element_label_type'] = array(
      '#title' => t('Label HTML element'),
      '#options' => $this->getElements(FALSE),
      '#type' => 'select',
      '#default_value' => $this->options['element_label_type'],
      '#description' => t('Choose the HTML element to wrap around this label, e.g. H1, H2, etc.'),
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_label_type_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#fieldset' => 'style_settings',
    );
    $form['element_label_class_enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create a CSS class'),
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_label_type_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#default_value' => !empty($this->options['element_label_class']) || (string) $this->options['element_label_class'] == '0',
      '#fieldset' => 'style_settings',
    );
    $form['element_label_class'] = array(
      '#title' => t('CSS class'),
      '#description' => t('You may use token substitutions from the rewriting section in this class.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['element_label_class'],
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_label_type_enable]"]' => array('checked' => TRUE),
          ':input[name="options[element_label_class_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#fieldset' => 'style_settings',
    );

    $form['element_wrapper_type_enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Customize field and label wrapper HTML'),
      '#default_value' => !empty($this->options['element_wrapper_type']) || (string) $this->options['element_wrapper_type'] == '0' || !empty($this->options['element_wrapper_class']) || (string) $this->options['element_wrapper_class'] == '0',
      '#fieldset' => 'style_settings',
    );
    $form['element_wrapper_type'] = array(
      '#title' => t('Wrapper HTML element'),
      '#options' => $this->getElements(FALSE),
      '#type' => 'select',
      '#default_value' => $this->options['element_wrapper_type'],
      '#description' => t('Choose the HTML element to wrap around this field and label, e.g. H1, H2, etc. This may not be used if the field and label are not rendered together, such as with a table.'),
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_wrapper_type_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#fieldset' => 'style_settings',
    );

    $form['element_wrapper_class_enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create a CSS class'),
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_wrapper_type_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#default_value' => !empty($this->options['element_wrapper_class']) || (string) $this->options['element_wrapper_class'] == '0',
      '#fieldset' => 'style_settings',
    );
    $form['element_wrapper_class'] = array(
      '#title' => t('CSS class'),
      '#description' => t('You may use token substitutions from the rewriting section in this class.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['element_wrapper_class'],
      '#states' => array(
        'visible' => array(
          ':input[name="options[element_wrapper_class_enable]"]' => array('checked' => TRUE),
          ':input[name="options[element_wrapper_type_enable]"]' => array('checked' => TRUE),
        ),
      ),
      '#fieldset' => 'style_settings',
    );

    $form['element_default_classes'] = array(
      '#type' => 'checkbox',
      '#title' => t('Add default classes'),
      '#default_value' => $this->options['element_default_classes'],
      '#description' => t('Use default Views classes to identify the field, field label and field content.'),
      '#fieldset' => 'style_settings',
    );

    $form['alter'] = array(
      '#title' => t('Rewrite results'),
      '#type' => 'details',
      '#collapsed' => TRUE,
      '#weight' => 100,
    );

    if ($this->allowAdvancedRender()) {
      $form['alter']['#tree'] = TRUE;
      $form['alter']['alter_text'] = array(
        '#type' => 'checkbox',
        '#title' => t('Override the output of this field with custom text'),
        '#default_value' => $this->options['alter']['alter_text'],
      );

      $form['alter']['text'] = array(
        '#title' => t('Text'),
        '#type' => 'textarea',
        '#default_value' => $this->options['alter']['text'],
        '#description' => t('The text to display for this field. You may include HTML. You may enter data from this view as per the "Replacement patterns" below.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][alter_text]"]' => array('checked' => TRUE),
          ),
        ),
      );

      $form['alter']['make_link'] = array(
        '#type' => 'checkbox',
        '#title' => t('Output this field as a custom link'),
        '#default_value' => $this->options['alter']['make_link'],
      );
      $form['alter']['path'] = array(
        '#title' => t('Link path'),
        '#type' => 'textfield',
        '#default_value' => $this->options['alter']['path'],
        '#description' => t('The Drupal path or absolute URL for this link. You may enter data from this view as per the "Replacement patterns" below.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
        '#maxlength' => 255,
      );
      $form['alter']['absolute'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use absolute path'),
        '#default_value' => $this->options['alter']['absolute'],
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['replace_spaces'] = array(
        '#type' => 'checkbox',
        '#title' => t('Replace spaces with dashes'),
        '#default_value' => $this->options['alter']['replace_spaces'],
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['external'] = array(
        '#type' => 'checkbox',
        '#title' => t('External server URL'),
        '#default_value' => $this->options['alter']['external'],
        '#description' => t("Links to an external server using a full URL: e.g. 'http://www.example.com' or 'www.example.com'."),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['path_case'] = array(
        '#type' => 'select',
        '#title' => t('Transform the case'),
        '#description' => t('When printing url paths, how to transform the case of the filter value.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
       '#options' => array(
          'none' => t('No transform'),
          'upper' => t('Upper case'),
          'lower' => t('Lower case'),
          'ucfirst' => t('Capitalize first letter'),
          'ucwords' => t('Capitalize each word'),
        ),
        '#default_value' => $this->options['alter']['path_case'],
      );
      $form['alter']['link_class'] = array(
        '#title' => t('Link class'),
        '#type' => 'textfield',
        '#default_value' => $this->options['alter']['link_class'],
        '#description' => t('The CSS class to apply to the link.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['alt'] = array(
        '#title' => t('Title text'),
        '#type' => 'textfield',
        '#default_value' => $this->options['alter']['alt'],
        '#description' => t('Text to place as "title" text which most browsers display as a tooltip when hovering over the link.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['rel'] = array(
        '#title' => t('Rel Text'),
        '#type' => 'textfield',
        '#default_value' => $this->options['alter']['rel'],
        '#description' => t('Include Rel attribute for use in lightbox2 or other javascript utility.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['prefix'] = array(
        '#title' => t('Prefix text'),
        '#type' => 'textfield',
        '#default_value' => $this->options['alter']['prefix'],
        '#description' => t('Any text to display before this link. You may include HTML.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['suffix'] = array(
        '#title' => t('Suffix text'),
        '#type' => 'textfield',
        '#default_value' => $this->options['alter']['suffix'],
        '#description' => t('Any text to display after this link. You may include HTML.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['target'] = array(
        '#title' => t('Target'),
        '#type' => 'textfield',
        '#default_value' => $this->options['alter']['target'],
        '#description' => t("Target of the link, such as _blank, _parent or an iframe's name. This field is rarely used."),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
          ),
        ),
      );


      // Get a list of the available fields and arguments for token replacement.

      // Setup the tokens for fields.
      $previous = $this->getPreviousFieldLabels();
      foreach ($previous as $id => $label) {
        $options[t('Fields')]["[$id]"] = $label;
      }

      $count = 0; // This lets us prepare the key as we want it printed.
      foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
        $options[t('Arguments')]['%' . ++$count] = t('@argument title', array('@argument' => $handler->adminLabel()));
        $options[t('Arguments')]['!' . $count] = t('@argument input', array('@argument' => $handler->adminLabel()));
      }

      $this->documentSelfTokens($options[t('Fields')]);

      // Default text.
      $output = '<p>' . t('You must add some additional fields to this display before using this field. These fields may be marked as <em>Exclude from display</em> if you prefer. Note that due to rendering order, you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.') . '</p>';
      // We have some options, so make a list.
      if (!empty($options)) {
        $output = '<p>' . t("The following tokens are available for this field. Note that due to rendering order, you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields. If you would like to have the characters '[' and ']' use the html entity codes '%5B' or '%5D' or they will get replaced with empty space.") . '</p>';
        foreach (array_keys($options) as $type) {
          if (!empty($options[$type])) {
            $items = array();
            foreach ($options[$type] as $key => $value) {
              $items[] = $key . ' == ' . $value;
            }
            $item_list = array(
              '#theme' => 'item_list',
              '#items' => $items,
              '#list_type' => $type,
            );
            $output .= drupal_render($item_list);
          }
        }
      }
      // This construct uses 'hidden' and not markup because process doesn't
      // run. It also has an extra div because the dependency wants to hide
      // the parent in situations like this, so we need a second div to
      // make this work.
      $form['alter']['help'] = array(
        '#type' => 'details',
        '#title' => t('Replacement patterns'),
        '#collapsed' => TRUE,
        '#value' => $output,
        '#states' => array(
          'visible' => array(
            array(
              ':input[name="options[alter][make_link]"]' => array('checked' => TRUE),
            ),
            array(
              ':input[name="options[alter][alter_text]"]' => array('checked' => TRUE),
            ),
            array(
              ':input[name="options[alter][more_link]"]' => array('checked' => TRUE),
            ),
          ),
        ),
      );

      $form['alter']['trim'] = array(
        '#type' => 'checkbox',
        '#title' => t('Trim this field to a maximum number of characters'),
        '#default_value' => $this->options['alter']['trim'],
      );

      $form['alter']['max_length'] = array(
        '#title' => t('Maximum number of characters'),
        '#type' => 'textfield',
        '#default_value' => $this->options['alter']['max_length'],
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][trim]"]' => array('checked' => TRUE),
          ),
        ),
      );

      $form['alter']['word_boundary'] = array(
        '#type' => 'checkbox',
        '#title' => t('Trim only on a word boundary'),
        '#description' => t('If checked, this field be trimmed only on a word boundary. This is guaranteed to be the maximum characters stated or less. If there are no word boundaries this could trim a field to nothing.'),
        '#default_value' => $this->options['alter']['word_boundary'],
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][trim]"]' => array('checked' => TRUE),
          ),
        ),
      );

      $form['alter']['ellipsis'] = array(
        '#type' => 'checkbox',
        '#title' => t('Add "..." at the end of trimmed text'),
        '#default_value' => $this->options['alter']['ellipsis'],
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][trim]"]' => array('checked' => TRUE),
          ),
        ),
      );

      $form['alter']['more_link'] = array(
        '#type' => 'checkbox',
        '#title' => t('Add a read-more link if output is trimmed.'),
        '#default_value' => $this->options['alter']['more_link'],
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][trim]"]' => array('checked' => TRUE),
          ),
        ),
      );

      $form['alter']['more_link_text'] = array(
        '#type' => 'textfield',
        '#title' => t('More link label'),
        '#default_value' => $this->options['alter']['more_link_text'],
        '#description' => t('You may use the "Replacement patterns" above.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][trim]"]' => array('checked' => TRUE),
            ':input[name="options[alter][more_link]"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['alter']['more_link_path'] = array(
        '#type' => 'textfield',
        '#title' => t('More link path'),
        '#default_value' => $this->options['alter']['more_link_path'],
        '#description' => t('This can be an internal Drupal path such as node/add or an external URL such as "http://drupal.org". You may use the "Replacement patterns" above.'),
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][trim]"]' => array('checked' => TRUE),
            ':input[name="options[alter][more_link]"]' => array('checked' => TRUE),
          ),
        ),
      );

      $form['alter']['html'] = array(
        '#type' => 'checkbox',
        '#title' => t('Field can contain HTML'),
        '#description' => t('An HTML corrector will be run to ensure HTML tags are properly closed after trimming.'),
        '#default_value' => $this->options['alter']['html'],
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][trim]"]' => array('checked' => TRUE),
          ),
        ),
      );

      $form['alter']['strip_tags'] = array(
        '#type' => 'checkbox',
        '#title' => t('Strip HTML tags'),
        '#default_value' => $this->options['alter']['strip_tags'],
      );

      $form['alter']['preserve_tags'] = array(
        '#type' => 'textfield',
        '#title' => t('Preserve certain tags'),
        '#description' => t('List the tags that need to be preserved during the stripping process. example &quot;&lt;p&gt; &lt;br&gt;&quot; which will preserve all p and br elements'),
        '#default_value' => $this->options['alter']['preserve_tags'],
        '#states' => array(
          'visible' => array(
            ':input[name="options[alter][strip_tags]"]' => array('checked' => TRUE),
          ),
        ),
      );

      $form['alter']['trim_whitespace'] = array(
        '#type' => 'checkbox',
        '#title' => t('Remove whitespace'),
        '#default_value' => $this->options['alter']['trim_whitespace'],
      );

      $form['alter']['nl2br'] = array(
        '#type' => 'checkbox',
        '#title' => t('Convert newlines to HTML &lt;br&gt; tags'),
        '#default_value' => $this->options['alter']['nl2br'],
      );
    }

    $form['empty_field_behavior'] = array(
      '#type' => 'details',
      '#title' => t('No results behavior'),
      '#collapsed' => TRUE,
      '#weight' => 100,
    );

    $form['empty'] = array(
      '#type' => 'textarea',
      '#title' => t('No results text'),
      '#default_value' => $this->options['empty'],
      '#description' => t('Provide text to display if this field contains an empty result. You may include HTML. You may enter data from this view as per the "Replacement patterns" in the "Rewrite Results" section below.'),
      '#fieldset' => 'empty_field_behavior',
    );

    $form['empty_zero'] = array(
      '#type' => 'checkbox',
      '#title' => t('Count the number 0 as empty'),
      '#default_value' => $this->options['empty_zero'],
      '#description' => t('Enable to display the "no results text" if the field contains the number 0.'),
      '#fieldset' => 'empty_field_behavior',
    );

    $form['hide_empty'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide if empty'),
      '#default_value' => $this->options['hide_empty'],
      '#description' => t('Enable to hide this field if it is empty. Note that the field label or rewritten output may still be displayed. To hide labels, check the style or row style settings for empty fields. To hide rewritten content, check the "Hide rewriting if empty" checkbox.'),
      '#fieldset' => 'empty_field_behavior',
    );

    $form['hide_alter_empty'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide rewriting if empty'),
      '#default_value' => $this->options['hide_alter_empty'],
      '#description' => t('Do not display rewritten content if this field is empty.'),
      '#fieldset' => 'empty_field_behavior',
    );
  }

  /**
   * Returns all field labels of fields before this field.
   *
   * @return array
   *   An array of field labels keyed by their field IDs.
   */
  protected function getPreviousFieldLabels() {
    $all_fields = $this->view->display_handler->getFieldLabels();
    $field_options = array_slice($all_fields, 0, array_search($this->options['id'], array_keys($all_fields)));
    return $field_options;
  }

  /**
   * Provide extra data to the administration form
   */
  public function adminSummary() {
    return $this->label();
  }

  /**
   * Run before any fields are rendered.
   *
   * This gives the handlers some time to set up before any handler has
   * been rendered.
   *
   * @param \Drupal\views\ResultRow[] $values
   *   An array of all ResultRow objects returned from the query.
   */
  public function preRender(&$values) { }

  /**
   * Renders the field.
   *
   * @param \Drupal\views\ResultRow $values
   *   The values retrieved from the database.
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    return $this->sanitizeValue($value);
  }

  /**
   * Render a field using advanced settings.
   *
   * This renders a field normally, then decides if render-as-link and
   * text-replacement rendering is necessary.
   *
   * @param \Drupal\views\ResultRow $values
   *   The values retrieved from the database.
   */
  public function advancedRender(ResultRow $values) {
    if ($this->allowAdvancedRender() && method_exists($this, 'render_item')) {
      $raw_items = $this->getItems($values);
      // If there are no items, set the original value to NULL.
      if (empty($raw_items)) {
        $this->original_value = NULL;
      }
    }
    else {
      $value = $this->render($values);
      if (is_array($value)) {
        $value = drupal_render($value);
      }
      $this->last_render = $value;
      $this->original_value = $value;
    }

    if ($this->allowAdvancedRender()) {
      $tokens = NULL;
      if (method_exists($this, 'render_item')) {
        $items = array();
        foreach ($raw_items as $count => $item) {
          $value = $this->render_item($count, $item);
          if (is_array($value)) {
            $value = drupal_render($value);
          }
          $this->last_render = $value;
          $this->original_value = $this->last_render;

          $alter = $item + $this->options['alter'];
          $alter['phase'] = VIEWS_HANDLER_RENDER_TEXT_PHASE_SINGLE_ITEM;
          $items[] = $this->renderText($alter);
        }

        $value = $this->renderItems($items);
      }
      else {
        $alter = array('phase' => VIEWS_HANDLER_RENDER_TEXT_PHASE_COMPLETELY) + $this->options['alter'];
        $value = $this->renderText($alter);
      }

      if (is_array($value)) {
        $value = drupal_render($value);
      }
      // This happens here so that renderAsLink can get the unaltered value of
      // this field as a token rather than the altered value.
      $this->last_render = $value;
    }

    if (empty($this->last_render)) {
      if ($this->isValueEmpty($this->last_render, $this->options['empty_zero'], FALSE)) {
        $alter = $this->options['alter'];
        $alter['alter_text'] = 1;
        $alter['text'] = $this->options['empty'];
        $alter['phase'] = VIEWS_HANDLER_RENDER_TEXT_PHASE_EMPTY;
        $this->last_render = $this->renderText($alter);
      }
    }

    return $this->last_render;
  }

  /**
   * Checks if a field value is empty.
   *
   * @param $value
   *   The field value.
   * @param bool $empty_zero
   *   Whether or not this field is configured to consider 0 as empty.
   * @param bool $no_skip_empty
   *   Whether or not to use empty() to check the value.
   *
   * @return bool
   * TRUE if the value is considered empty, FALSE otherwise.
   */
  public function isValueEmpty($value, $empty_zero, $no_skip_empty = TRUE) {
    if (!isset($value)) {
      $empty = TRUE;
    }
    else {
      $empty = ($empty_zero || ($value !== 0 && $value !== '0'));
    }

    if ($no_skip_empty) {
      $empty = empty($value) && $empty;
    }
    return $empty;
  }

  /**
   * Perform an advanced text render for the item.
   *
   * This is separated out as some fields may render lists, and this allows
   * each item to be handled individually.
   */
  public function renderText($alter) {
    $value = $this->last_render;

    if (!empty($alter['alter_text']) && $alter['text'] !== '') {
      $tokens = $this->getRenderTokens($alter);
      $value = $this->renderAltered($alter, $tokens);
    }

    if (!empty($this->options['alter']['trim_whitespace'])) {
      $value = trim($value);
    }

    // Check if there should be no further rewrite for empty values.
    $no_rewrite_for_empty = $this->options['hide_alter_empty'] && $this->isValueEmpty($this->original_value, $this->options['empty_zero']);

    // Check whether the value is empty and return nothing, so the field isn't rendered.
    // First check whether the field should be hidden if the value(hide_alter_empty = TRUE) /the rewrite is empty (hide_alter_empty = FALSE).
    // For numeric values you can specify whether "0"/0 should be empty.
    if ((($this->options['hide_empty'] && empty($value))
        || ($alter['phase'] != VIEWS_HANDLER_RENDER_TEXT_PHASE_EMPTY && $no_rewrite_for_empty))
      && $this->isValueEmpty($value, $this->options['empty_zero'], FALSE)) {
      return '';
    }
    // Only in empty phase.
    if ($alter['phase'] == VIEWS_HANDLER_RENDER_TEXT_PHASE_EMPTY && $no_rewrite_for_empty) {
      // If we got here then $alter contains the value of "No results text"
      // and so there is nothing left to do.
      return $value;
    }

    if (!empty($alter['strip_tags'])) {
      $value = strip_tags($value, $alter['preserve_tags']);
    }

    $suffix = '';
    if (!empty($alter['trim']) && !empty($alter['max_length'])) {
      $length = strlen($value);
      $value = $this->renderTrimText($alter, $value);
      if ($this->options['alter']['more_link'] && strlen($value) < $length) {
        $tokens = $this->getRenderTokens($alter);
        $more_link_text = $this->options['alter']['more_link_text'] ? $this->options['alter']['more_link_text'] : t('more');
        $more_link_text = strtr(filter_xss_admin($more_link_text), $tokens);
        $more_link_path = $this->options['alter']['more_link_path'];
        $more_link_path = strip_tags(decode_entities(strtr($more_link_path, $tokens)));

        // Take sure that paths which was runned through url() does work as well.
        $base_path = base_path();
        // Checks whether the path starts with the base_path.
        if (strpos($more_link_path, $base_path) === 0) {
          $more_link_path = drupal_substr($more_link_path, drupal_strlen($base_path));
        }

        $more_link = l($more_link_text, $more_link_path, array('attributes' => array('class' => array('views-more-link'))));

        $suffix .= " " . $more_link;
      }
    }

    if (!empty($alter['nl2br'])) {
      $value = nl2br($value);
    }
    $this->last_render_text = $value;

    if (!empty($alter['make_link']) && !empty($alter['path'])) {
      if (!isset($tokens)) {
        $tokens = $this->getRenderTokens($alter);
      }
      $value = $this->renderAsLink($alter, $value, $tokens);
    }

    return $value . $suffix;
  }

  /**
   * Render this field as altered text, from a fieldset set by the user.
   */
  protected function renderAltered($alter, $tokens) {
    // Filter this right away as our substitutions are already sanitized.
    $value = filter_xss_admin($alter['text']);
    $value = strtr($value, $tokens);

    return $value;
  }

  /**
   * Trim the field down to the specified length.
   */
  public function renderTrimText($alter, $value) {
    if (!empty($alter['strip_tags'])) {
      // NOTE: It's possible that some external fields might override the
      // element type.
      $this->definition['element type'] = 'span';
    }
    return static::trimText($alter, $value);
  }

  /**
   * Render this field as a link, with the info from a fieldset set by
   * the user.
   */
  protected function renderAsLink($alter, $text, $tokens) {
    $value = '';

    if (!empty($alter['prefix'])) {
      $value .= filter_xss_admin(strtr($alter['prefix'], $tokens));
    }

    $options = array(
      'html' => TRUE,
      'absolute' => !empty($alter['absolute']) ? TRUE : FALSE,
    );

    // $path will be run through check_url() by l() so we do not need to
    // sanitize it ourselves.
    $path = $alter['path'];

    // strip_tags() removes <front>, so check whether its different to front.
    if ($path != '<front>') {
      // Use strip tags as there should never be HTML in the path.
      // However, we need to preserve special characters like " that
      // were removed by check_plain().
      $path = strip_tags(decode_entities(strtr($path, $tokens)));

      if (!empty($alter['path_case']) && $alter['path_case'] != 'none') {
        $path = $this->caseTransform($path, $this->options['alter']['path_case']);
      }

      if (!empty($alter['replace_spaces'])) {
        $path = str_replace(' ', '-', $path);
      }
    }

    // Parse the URL and move any query and fragment parameters out of the path.
    $url = parse_url($path);

    // Seriously malformed URLs may return FALSE or empty arrays.
    if (empty($url)) {
      return $text;
    }

    // If the path is empty do not build a link around the given text and return
    // it as is.
    // http://www.example.com URLs will not have a $url['path'], so check host as well.
    if (empty($url['path']) && empty($url['host']) && empty($url['fragment'])) {
      return $text;
    }

    // If no scheme is provided in the $path, assign the default 'http://'.
    // This allows a url of 'www.example.com' to be converted to 'http://www.example.com'.
    // Only do this on for external URLs.
    if ($alter['external']) {
      if (!isset($url['scheme'])) {
        // There is no scheme, add the default 'http://' to the $path.
        $path = "http://$path";
        // Reset the $url array to include the new scheme.
        $url = parse_url($path);
      }
    }

    if (isset($url['query'])) {
      $path = strtr($path, array('?' . $url['query'] => ''));
      $query = array();
      parse_str($url['query'], $query);
      // Remove query parameters that were assigned a query string replacement
      // token for which there is no value available.
      foreach ($query as $param => $val) {
        if ($val == '%' . $param) {
          unset($query[$param]);
        }
      }
      $options['query'] = $query;
    }
    if (isset($url['fragment'])) {
      $path = strtr($path, array('#' . $url['fragment'] => ''));
      // If the path is empty we want to have a fragment for the current site.
      if ($path == '') {
        $options['external'] = TRUE;
      }
      $options['fragment'] = $url['fragment'];
    }

    $alt = strtr($alter['alt'], $tokens);
    // Set the title attribute of the link only if it improves accessibility
    if ($alt && $alt != $text) {
      $options['attributes']['title'] = decode_entities($alt);
    }

    $class = strtr($alter['link_class'], $tokens);
    if ($class) {
      $options['attributes']['class'] = array($class);
    }

    if (!empty($alter['rel']) && $rel = strtr($alter['rel'], $tokens)) {
      $options['attributes']['rel'] = $rel;
    }

    $target = check_plain(trim(strtr($alter['target'], $tokens)));
    if (!empty($target)) {
      $options['attributes']['target'] = $target;
    }

    // Allow the addition of arbitrary attributes to links. Additional attributes
    // currently can only be altered in preprocessors and not within the UI.
    if (isset($alter['link_attributes']) && is_array($alter['link_attributes'])) {
      foreach ($alter['link_attributes'] as $key => $attribute) {
        if (!isset($options['attributes'][$key])) {
          $options['attributes'][$key] = strtr($attribute, $tokens);
        }
      }
    }

    // If the query and fragment were programatically assigned overwrite any
    // parsed values.
    if (isset($alter['query'])) {
      // Convert the query to a string, perform token replacement, and then
      // convert back to an array form for l().
      $options['query'] = drupal_http_build_query($alter['query']);
      $options['query'] = strtr($options['query'], $tokens);
      $query = array();
      parse_str($options['query'], $query);
      $options['query'] = $query;
    }
    if (isset($alter['alias'])) {
      // Alias is a boolean field, so no token.
      $options['alias'] = $alter['alias'];
    }
    if (isset($alter['fragment'])) {
      $options['fragment'] = strtr($alter['fragment'], $tokens);
    }
    if (isset($alter['language'])) {
      $options['language'] = $alter['language'];
    }

    // If the url came from entity_uri(), pass along the required options.
    if (isset($alter['entity'])) {
      $options['entity'] = $alter['entity'];
    }
    if (isset($alter['entity_type'])) {
      $options['entity_type'] = $alter['entity_type'];
    }

    $value .= l($text, $path, $options);

    if (!empty($alter['suffix'])) {
      $value .= filter_xss_admin(strtr($alter['suffix'], $tokens));
    }

    return $value;
  }

  /**
   * Get the 'render' tokens to use for advanced rendering.
   *
   * This runs through all of the fields and arguments that
   * are available and gets their values. This will then be
   * used in one giant str_replace().
   */
  public function getRenderTokens($item) {
    $tokens = array();
    if (!empty($this->view->build_info['substitutions'])) {
      $tokens = $this->view->build_info['substitutions'];
    }
    $count = 0;
    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $token = '%' . ++$count;
      if (!isset($tokens[$token])) {
        $tokens[$token] = '';
      }

      // Use strip tags as there should never be HTML in the path.
      // However, we need to preserve special characters like " that
      // were removed by check_plain().
      $tokens['!' . $count] = isset($this->view->args[$count - 1]) ? strip_tags(decode_entities($this->view->args[$count - 1])) : '';
    }

    // Get flattened set of tokens for any array depth in $_GET parameters.
    $tokens += $this->getTokenValuesRecursive(drupal_container()->get('request')->query->all());

    // Now add replacements for our fields.
    foreach ($this->view->display_handler->getHandlers('field') as $field => $handler) {
      if (isset($handler->last_render)) {
        $tokens["[$field]"] = $handler->last_render;
      }
      else {
        $tokens["[$field]"] = '';
      }

      // We only use fields up to (and including) this one.
      if ($field == $this->options['id']) {
        break;
      }
    }

    // Store the tokens for the row so we can reference them later if necessary.
    $this->view->style_plugin->render_tokens[$this->view->row_index] = $tokens;
    $this->last_tokens = $tokens;
    if (!empty($item)) {
      $this->addSelfTokens($tokens, $item);
    }

    return $tokens;
  }

  /**
   * Recursive function to add replacements for nested query string parameters.
   *
   * E.g. if you pass in the following array:
   *   array(
   *     'foo' => array(
   *       'a' => 'value',
   *       'b' => 'value',
   *     ),
   *     'bar' => array(
   *       'a' => 'value',
   *       'b' => array(
   *         'c' => value,
   *       ),
   *     ),
   *   );
   *
   * Would yield the following array of tokens:
   *   array(
   *     '%foo_a' => 'value'
   *     '%foo_b' => 'value'
   *     '%bar_a' => 'value'
   *     '%bar_b_c' => 'value'
   *   );
   *
   * @param $array
   *   An array of values.
   *
   * @param $parent_keys
   *   An array of parent keys. This will represent the array depth.
   *
   * @return
   *   An array of available tokens, with nested keys representative of the array structure.
   */
  protected function getTokenValuesRecursive(array $array, array $parent_keys = array()) {
    $tokens = array();

    foreach ($array as $param => $val) {
      if (is_array($val)) {
        // Copy parent_keys array, so we don't affect other elements of this
        // iteration.
        $child_parent_keys = $parent_keys;
        $child_parent_keys[] = $param;
        // Get the child tokens.
        $child_tokens = $this->getTokenValuesRecursive($val, $child_parent_keys);
        // Add them to the current tokens array.
        $tokens += $child_tokens;
      }
      else {
        // Create a token key based on array element structure.
        $token_string = !empty($parent_keys) ? implode('_', $parent_keys) . '_' . $param : $param;
        $tokens['%' . $token_string] = strip_tags(decode_entities($val));
      }
    }

    return $tokens;
  }

  /**
   * Add any special tokens this field might use for itself.
   *
   * This method is intended to be overridden by items that generate
   * fields as a list. For example, the field that displays all terms
   * on a node might have tokens for the tid and the term.
   *
   * By convention, tokens should follow the format of [token-subtoken]
   * where token is the field ID and subtoken is the field. If the
   * field ID is terms, then the tokens might be [terms-tid] and [terms-name].
   */
  protected function addSelfTokens(&$tokens, $item) { }

  /**
   * Document any special tokens this field might use for itself.
   *
   * @see addSelfTokens()
   */
  protected function documentSelfTokens(&$tokens) { }

  /**
   * Call out to the theme() function, which probably just calls render() but
   * allows sites to override output fairly easily.
   */
  function theme($values) {
    return theme($this->themeFunctions(),
      array(
        'view' => $this->view,
        'field' => $this,
        'row' => $values
      ));
  }

  public function themeFunctions() {
    $themes = array();
    $hook = 'views_view_field';

    $display = $this->view->display_handler->display;

    if (!empty($display)) {
      $themes[] = $hook . '__' . $this->view->storage->id()  . '__' . $display['id'] . '__' . $this->options['id'];
      $themes[] = $hook . '__' . $this->view->storage->id()  . '__' . $display['id'];
      $themes[] = $hook . '__' . $display['id'] . '__' . $this->options['id'];
      $themes[] = $hook . '__' . $display['id'];
      if ($display['id'] != $display['display_plugin']) {
        $themes[] = $hook . '__' . $this->view->storage->id()  . '__' . $display['display_plugin'] . '__' . $this->options['id'];
        $themes[] = $hook . '__' . $this->view->storage->id()  . '__' . $display['display_plugin'];
        $themes[] = $hook . '__' . $display['display_plugin'] . '__' . $this->options['id'];
        $themes[] = $hook . '__' . $display['display_plugin'];
      }
    }
    $themes[] = $hook . '__' . $this->view->storage->id() . '__' . $this->options['id'];
    $themes[] = $hook . '__' . $this->view->storage->id();
    $themes[] = $hook . '__' . $this->options['id'];
    $themes[] = $hook;

    return $themes;
  }

  public function adminLabel($short = FALSE) {
    return $this->getField(parent::adminLabel($short));
  }

  /**
   * Trims the field down to the specified length.
   *
   * @param array $alter
   *   The alter array of options to use.
   *     - max_length: Maximum lenght of the string, the rest gets truncated.
   *     - word_boundary: Trim only on a word boundary.
   *     - ellipsis: Show an ellipsis (...) at the end of the trimmed string.
   *     - html: Take sure that the html is correct.
   *
   * @param string $value
   *   The string which should be trimmed.
   *
   * @return string
   *   The trimmed string.
   */
  public static function trimText($alter, $value) {
    if (drupal_strlen($value) > $alter['max_length']) {
      $value = drupal_substr($value, 0, $alter['max_length']);
      if (!empty($alter['word_boundary'])) {
        $regex = "(.*)\b.+";
        if (function_exists('mb_ereg')) {
          mb_regex_encoding('UTF-8');
          $found = mb_ereg($regex, $value, $matches);
        }
        else {
          $found = preg_match("/$regex/us", $value, $matches);
        }
        if ($found) {
          $value = $matches[1];
        }
      }
      // Remove scraps of HTML entities from the end of a strings
      $value = rtrim(preg_replace('/(?:<(?!.+>)|&(?!.+;)).*$/us', '', $value));

      if (!empty($alter['ellipsis'])) {
        // @todo: What about changing this to a real ellipsis?
        $value .= t('...');
      }
    }
    if (!empty($alter['html'])) {
      $value = _filter_htmlcorrector($value);
    }

    return $value;
  }

}

/**
 * @}
 */

