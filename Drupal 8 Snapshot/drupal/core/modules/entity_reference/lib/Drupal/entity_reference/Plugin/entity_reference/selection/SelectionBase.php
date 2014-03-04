<?php

/**
 * @file
 * Contains \Drupal\entity_reference\Plugin\entity_reference\selection\SelectionBase.
 */

namespace Drupal\entity_reference\Plugin\entity_reference\selection;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Field\FieldDefinitionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\entity_reference\Annotation\EntityReferenceSelection;
use Drupal\entity_reference\Plugin\Type\Selection\SelectionInterface;

/**
 * Plugin implementation of the 'selection' entity_reference.
 *
 * @EntityReferenceSelection(
 *   id = "default",
 *   label = @Translation("Default"),
 *   group = "default",
 *   weight = 0,
 *   derivative = "Drupal\entity_reference\Plugin\Derivative\SelectionBase"
 * )
 */
class SelectionBase implements SelectionInterface {

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Entity\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * The entity object, or NULL
   *
   * @var NULL|EntityInterface
   */
  protected $entity;

  /**
   * Constructs a SelectionBase object.
   */
  public function __construct(FieldDefinitionInterface $field_definition, EntityInterface $entity = NULL) {
    $this->fieldDefinition = $field_definition;
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function settingsForm(&$field, &$instance) {
    $entity_info = entity_get_info($field['settings']['target_type']);
    $bundles = entity_get_bundles($field['settings']['target_type']);

    // Merge-in default values.
    if (!isset($instance['settings']['handler_settings'])) {
      $instance['settings']['handler_settings'] = array();
    }
    $instance['settings']['handler_settings'] += array(
      'target_bundles' => array(),
      'sort' => array(
        'field' => '_none',
      ),
      'auto_create' => FALSE,
    );

    if (!empty($entity_info['entity_keys']['bundle'])) {
      $bundle_options = array();
      foreach ($bundles as $bundle_name => $bundle_info) {
        $bundle_options[$bundle_name] = $bundle_info['label'];
      }

      $target_bundles_title = t('Bundles');
      // Default core entity types with sensible labels.
      if ($field['settings']['target_type'] == 'node') {
        $target_bundles_title = t('Content types');
      }
      elseif ($field['settings']['target_type'] == 'taxonomy_term') {
        $target_bundles_title = t('Vocabularies');
      }

      $form['target_bundles'] = array(
        '#type' => 'checkboxes',
        '#title' => $target_bundles_title,
        '#options' => $bundle_options,
        '#default_value' => (!empty($instance['settings']['handler_settings']['target_bundles'])) ? $instance['settings']['handler_settings']['target_bundles'] : array(),
        '#required' => TRUE,
        '#size' => 6,
        '#multiple' => TRUE,
        '#element_validate' => array('_entity_reference_element_validate_filter'),
      );
    }
    else {
      $form['target_bundles'] = array(
        '#type' => 'value',
        '#value' => array(),
      );
    }

    // @todo Use Entity::getPropertyDefinitions() when all entity types are
    // converted to the new Field API.
    $fields = drupal_map_assoc(drupal_schema_fields_sql($entity_info['base_table']));
    foreach (field_info_instances($field['settings']['target_type']) as $bundle_instances) {
      foreach ($bundle_instances as $instance_name => $instance_info) {
        $field_info = field_info_field($instance_name);
        foreach ($field_info['columns'] as $column_name => $column_info) {
          $fields[$instance_name . '.' . $column_name] = t('@label (@column)', array('@label' => $instance_info['label'], '@column' => $column_name));
        }
      }
    }

    $form['sort']['field'] = array(
      '#type' => 'select',
      '#title' => t('Sort by'),
      '#options' => array(
        '_none' => t('- None -'),
      ) + $fields,
      '#ajax' => TRUE,
      '#limit_validation_errors' => array(),
      '#default_value' => $instance['settings']['handler_settings']['sort']['field'],
    );

    $form['sort']['settings'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('entity_reference-settings')),
      '#process' => array('_entity_reference_form_process_merge_parent'),
    );

    if ($instance['settings']['handler_settings']['sort']['field'] != '_none') {
      // Merge-in default values.
      $instance['settings']['handler_settings']['sort'] += array(
        'direction' => 'ASC',
      );

      $form['sort']['settings']['direction'] = array(
        '#type' => 'select',
        '#title' => t('Sort direction'),
        '#required' => TRUE,
        '#options' => array(
          'ASC' => t('Ascending'),
          'DESC' => t('Descending'),
        ),
        '#default_value' => $instance['settings']['handler_settings']['sort']['direction'],
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->fieldDefinition->getFieldSetting('target_type');

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return array();
    }

    $options = array();
    $entities = entity_load_multiple($target_type, $result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = check_plain($entity->label());
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS') {
    $query = $this->buildEntityQuery($match, $match_operator);
    return $query
      ->count()
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids) {
    $result = array();
    if ($ids) {
      $target_type = $this->fieldDefinition->getFieldSetting('target_type');
      $entity_info = entity_get_info($target_type);
      $query = $this->buildEntityQuery();
      $result = $query
        ->condition($entity_info['entity_keys']['id'], $ids, 'IN')
        ->execute();
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function validateAutocompleteInput($input, &$element, &$form_state, $form, $strict = TRUE) {
    $entities = $this->getReferenceableEntities($input, '=', 6);
    $params = array(
      '%value' => $input,
      '@value' => $input,
    );
    if (empty($entities)) {
      if ($strict) {
        // Error if there are no entities available for a required field.
        form_error($element, t('There are no entities matching "%value".', $params));
      }
    }
    elseif (count($entities) > 5) {
      $params['@id'] = key($entities);
      // Error if there are more than 5 matching entities.
      form_error($element, t('Many entities are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
    }
    elseif (count($entities) > 1) {
      // More helpful error if there are only a few matching entities.
      $multiples = array();
      foreach ($entities as $id => $name) {
        $multiples[] = $name . ' (' . $id . ')';
      }
      $params['@id'] = $id;
      form_error($element, t('Multiple entities match this reference; "%multiple". Specify the one you want by appending the id in parentheses, like "@value (@id)".', array('%multiple' => implode('", "', $multiples))));
    }
    else {
      // Take the one and only matching entity.
      return key($entities);
    }
  }

  /**
   * Builds an EntityQuery to get referenceable entities.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The EntityQuery object with the basic conditions and sorting applied to
   *   it.
   */
  public function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $target_type = $this->fieldDefinition->getFieldSetting('target_type');
    $handler_settings = $this->fieldDefinition->getFieldSetting('handler_settings');
    $entity_info = entity_get_info($target_type);

    $query = \Drupal::entityQuery($target_type);
    if (!empty($handler_settings['target_bundles'])) {
      $query->condition($entity_info['entity_keys']['bundle'], $handler_settings['target_bundles'], 'IN');
    }

    if (isset($match) && isset($entity_info['entity_keys']['label'])) {
      $query->condition($entity_info['entity_keys']['label'], $match, $match_operator);
    }

    // Add entity-access tag.
    $query->addTag($this->fieldDefinition->getFieldSetting('target_type') . '_access');

    // Add the Selection handler for
    // entity_reference_query_entity_reference_alter().
    $query->addTag('entity_reference');
    $query->addMetaData('field_definition', $this->fieldDefinition);
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    $handler_settings = $this->fieldDefinition->getFieldSetting('handler_settings');
    if (!empty($handler_settings['sort'])) {
      $sort_settings = $handler_settings['sort'];
      if ($sort_settings['field'] != '_none') {
        $query->sort($sort_settings['field'], $sort_settings['direction']);
      }
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(SelectInterface $query) { }

  /**
   * Helper method: Passes a query to the alteration system again.
   *
   * This allows Entity Reference to add a tag to an existing query so it can
   * ask access control mechanisms to alter it again.
   */
  protected function reAlterQuery(AlterableInterface $query, $tag, $base_table) {
    // Save the old tags and metadata.
    // For some reason, those are public.
    $old_tags = $query->alterTags;
    $old_metadata = $query->alterMetaData;

    $query->alterTags = array($tag => TRUE);
    $query->alterMetaData['base_table'] = $base_table;
    drupal_alter(array('query', 'query_' . $tag), $query);

    // Restore the tags and metadata.
    $query->alterTags = $old_tags;
    $query->alterMetaData = $old_metadata;
  }
}
