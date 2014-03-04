<?php
/**
 * @file
 * Contains \Drupal\datetime\Plugin\field\widget\DateTimeDatelistWidget.
 */

namespace Drupal\datetime\Plugin\field\widget;

use Drupal\field\Annotation\FieldWidget;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\Field\FieldInterface;
use Drupal\field\Plugin\PluginSettingsBase;
use Drupal\field\FieldInstanceInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\DateHelper;

/**
 * Plugin implementation of the 'datetime_datelist' widget.
 *
 * @FieldWidget(
 *   id = "datetime_datelist",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "datetime"
 *   },
 *   settings = {
 *     "increment" = 15,
 *     "date_order" = "YMD",
 *     "time_type" = "24",
 *   }
 * )
 */
class DateTimeDatelistWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, array $plugin_definition, FieldDefinitionInterface $field_definition, array $settings) {
    // Identify the function used to set the default value.
    // @todo Make this work for both configurable and nonconfigurable fields:
    //   https://drupal.org/node/1989468.
    if ($field_definition instanceof FieldInstanceInterface) {
      $field_definition->default_value_function = $this->defaultValueFunction();
    }
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings);
  }

  /**
   * Returns the callback used to set a date default value.
   *
   * @return string
   *   The name of the callback to use when setting a default date value.
   */
  public function defaultValueFunction() {
    return 'datetime_default_value';
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldInterface $items, $delta, array $element, $langcode, array &$form, array &$form_state) {
    $date_order = $this->getSetting('date_order');
    $time_type = $this->getSetting('time_type');
    $increment = $this->getSetting('increment');

    // We're nesting some sub-elements inside the parent, so we
    // need a wrapper. We also need to add another #title attribute
    // at the top level for ease in identifying this item in error
    // messages. We don't want to display this title because the
    // actual title display is handled at a higher level by the Field
    // module.

    $element['#theme_wrappers'][] = 'datetime_wrapper';
    $element['#attributes']['class'][] = 'container-inline';
    $element['#element_validate'][] = 'datetime_datelist_widget_validate';

    // Identify the type of date and time elements to use.
    switch ($this->getFieldSetting('datetime_type')) {
      case 'date':
        $storage_format = DATETIME_DATE_STORAGE_FORMAT;
        $type_type = 'none';
        break;

      default:
        $storage_format = DATETIME_DATETIME_STORAGE_FORMAT;
        break;
    }

    // Set up the date part order array.
    switch ($date_order) {
      case 'YMD':
        $date_part_order = array('year', 'month', 'day');
        break;

      case 'MDY':
        $date_part_order = array('month', 'day', 'year');
        break;

      case 'DMY':
        $date_part_order = array('day', 'month', 'year');
        break;
    }
    switch ($time_type) {
       case '24':
         $date_part_order = array_merge($date_part_order, array('hour', 'minute'));
         break;

       case '12':
         $date_part_order = array_merge($date_part_order, array('hour', 'minute', 'ampm'));
         break;

       case 'none':
         break;
    }

    $element['value'] = array(
      '#type' => 'datelist',
      '#default_value' => NULL,
      '#date_increment' => $increment,
      '#date_part_order'=> $date_part_order,
      '#date_timezone' => drupal_get_user_timezone(),
      '#required' => $element['#required'],
    );

    // Set the storage and widget options so the validation can use them. The
    // validator will not have access to the field definition.
    $element['value']['#date_storage_format'] = $storage_format;

    if (!empty($items[$delta]->date)) {
      $date = $items[$delta]->date;
      // The date was created and verified during field_load(), so it is safe to
      // use without further inspection.
      $date->setTimezone( new \DateTimeZone($element['value']['#date_timezone']));
      if ($this->getFieldSetting('datetime_type') == 'date') {
        // A date without time will pick up the current time, use the default
        // time.
        datetime_date_default_time($date);
      }
      $element['value']['#default_value'] = $date;
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  function settingsForm(array $form, array &$form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['date_order'] = array(
      '#type' => 'select',
      '#title' => t('Date part order'),
      '#default_value' => $this->getSetting('date_order'),
      '#options' => array('MDY' => t('Month/Day/Year'), 'DMY' => t('Day/Month/Year'), 'YMD' => t('Year/Month/Day')),
    );

    if ($this->getFieldSetting('datetime_type') == 'datetime') {
      $element['time_type'] = array(
        '#type' => 'select',
        '#title' => t('Time type'),
        '#default_value' => $this->getSetting('time_type'),
        '#options' => array('24' => t('24 hour time'), '12' => t('12 hour time')),
      );
    }
    else {
      $element['time_type'] = array(
        '#type' => 'hidden',
        '#value' => 'none',
      );
    }

    $element['increment'] = array(
      '#type' => 'select',
      '#title' => t('Time increments'),
      '#default_value' => $this->getSetting('increment'),
      '#options' => array(
        1 => t('1 minute'),
        5 => t('5 minute'),
        10 => t('10 minute'),
        15 => t('15 minute'),
        30 => t('30 minute')),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $summary[] = t('Date part order: !order', array('!order' => $this->getSetting('date_order')));
    if ($this->getFieldSetting('datetime_type') == 'datetime') {
      $summary[] = t('Time type: !time_type', array('!time_type' => $this->getSetting('time_type')));
    }
    $summary[] = t('Time increments: !increment', array('!increment' => $this->getSetting('increment')));

    return $summary;
  }

}
