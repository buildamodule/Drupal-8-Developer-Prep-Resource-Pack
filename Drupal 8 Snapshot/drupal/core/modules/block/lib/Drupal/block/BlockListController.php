<?php

/**
 * @file
 * Contains \Drupal\block\BlockListController.
 */

namespace Drupal\block;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Json;
use Drupal\Core\Config\Entity\ConfigEntityListController;
use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the block list controller.
 */
class BlockListController extends ConfigEntityListController implements FormInterface, EntityControllerInterface {

  /**
   * The regions containing the blocks.
   *
   * @var array
   */
  protected $regions;

  /**
   * The theme containing the blocks.
   *
   * @var string
   */
  protected $theme;

  /**
   * The block manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $blockManager;

  /**
   * Constructs a new BlockListController object.
   *
   * @param string $entity_type
   *   The type of entity to be listed.
   * @param array $entity_info
   *   An array of entity info for the entity type.
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $storage
   *   The entity storage controller class.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke hooks on.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $block_manager
   *   The block manager.
   */
  public function __construct($entity_type, array $entity_info, EntityStorageControllerInterface $storage, ModuleHandlerInterface $module_handler, PluginManagerInterface $block_manager) {
    parent::__construct($entity_type, $entity_info, $storage, $module_handler);

    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $entity_type,
      $entity_info,
      $container->get('plugin.manager.entity')->getStorageController($entity_type),
      $container->get('module_handler'),
      $container->get('plugin.manager.block')
    );
  }

  /**
   * Overrides \Drupal\Core\Config\Entity\ConfigEntityListController::load().
   */
  public function load() {
    // If no theme was specified, use the current theme.
    if (!$this->theme) {
      $this->theme = $GLOBALS['theme'];
    }

    // Store the region list.
    $this->regions = system_region_list($this->theme, REGIONS_VISIBLE);

    // Load only blocks for this theme, and sort them.
    // @todo Move the functionality of _block_rehash() out of the listing page.
    $entities = _block_rehash($this->theme);

    uasort($entities, array($this->entityInfo['class'], 'sort'));
    return $entities;
  }

  /**
   * Overrides \Drupal\Core\Entity\EntityListController::render().
   */
  public function render($theme = NULL) {
    // If no theme was specified, use the current theme.
    $this->theme = $theme ?: $GLOBALS['theme_key'];

    return drupal_get_form($this);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'block_admin_display_form';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   *
   * Form constructor for the main block administration form.
   */
  public function buildForm(array $form, array &$form_state) {
    $entities = $this->load();
    $form['#attached']['library'][] = array('system', 'drupal.tableheader');
    $form['#attached']['library'][] = array('block', 'drupal.block');
    $form['#attached']['library'][] = array('block', 'drupal.block.admin');
    $form['#attributes']['class'][] = 'clearfix';

    // Add a last region for disabled blocks.
    $block_regions_with_disabled = $this->regions + array(BLOCK_REGION_NONE => BLOCK_REGION_NONE);
    $form['left']['#type'] = 'container';
    $form['left']['#attributes']['class'] = array(
      'block-list-region',
      'block-list-left',
    );

    $form['left']['block_regions'] = array(
      '#type' => 'value',
      '#value' => $block_regions_with_disabled,
    );

    // Weights range from -delta to +delta, so delta should be at least half
    // of the amount of blocks present. This makes sure all blocks in the same
    // region get an unique weight.
    $weight_delta = round(count($entities) / 2);

    // Build the form tree.
    $form['left']['edited_theme'] = array(
      '#type' => 'value',
      '#value' => $this->theme,
    );
    $form['left']['blocks'] = array(
      '#type' => 'table',
      '#header' => array(
        t('Block'),
        t('Region'),
        t('Weight'),
        t('Operations'),
      ),
      '#attributes' => array(
        'id' => 'blocks',
      ),
    );

    // Build blocks first for each region.
    foreach ($entities as $entity_id => $entity) {
      $definition = $entity->getPlugin()->getPluginDefinition();
      $blocks[$entity->get('region')][$entity_id] = array(
        'admin_label' => $definition['admin_label'],
        'entity_id' => $entity_id,
        'weight' => $entity->get('weight'),
        'entity' => $entity,
      );
    }

    // Loop over each region and build blocks.
    foreach ($block_regions_with_disabled as $region => $title) {
      $form['left']['blocks']['#tabledrag'][] = array(
        'match',
        'sibling',
        'block-region-select',
        'block-region-' . $region,
        NULL,
        FALSE,
      );
      $form['left']['blocks']['#tabledrag'][] = array(
        'order',
        'sibling',
        'block-weight',
        'block-weight-' . $region,
      );

      $form['left']['blocks'][$region] = array(
        '#attributes' => array(
          'class' => array('region-title', 'region-title-' . $region, 'odd'),
          'no_striping' => TRUE,
        ),
      );
      $form['left']['blocks'][$region]['title'] = array(
        '#markup' => $region != BLOCK_REGION_NONE ? $title : t('Disabled'),
        '#wrapper_attributes' => array(
          'colspan' => 5,
        ),
      );

      $form['left']['blocks'][$region . '-message'] = array(
        '#attributes' => array(
          'class' => array(
            'region-message',
            'region-' . $region . '-message',
            empty($blocks[$region]) ? 'region-empty' : 'region-populated',
          ),
        ),
      );
      $form['left']['blocks'][$region . '-message']['message'] = array(
        '#markup' => '<em>' . t('No blocks in this region') . '</em>',
        '#wrapper_attributes' => array(
          'colspan' => 5,
        ),
      );

      if (isset($blocks[$region])) {
        foreach ($blocks[$region] as $info) {
          $entity_id = $info['entity_id'];

          $form['left']['blocks'][$entity_id] = array(
            '#attributes' => array(
              'class' => array('draggable'),
            ),
          );

          $form['left']['blocks'][$entity_id]['info'] = array(
            '#markup' => check_plain($info['admin_label']),
            '#wrapper_attributes' => array(
              'class' => array('block'),
            ),
          );
          $form['left']['blocks'][$entity_id]['region-theme']['region'] = array(
            '#type' => 'select',
            '#default_value' => $region,
            '#empty_value' => BLOCK_REGION_NONE,
            '#title_display' => 'invisible',
            '#title' => t('Region for @block block', array('@block' => $info['admin_label'])),
            '#options' => $this->regions,
            '#attributes' => array(
              'class' => array('block-region-select', 'block-region-' . $region),
            ),
            '#parents' => array('blocks', $entity_id, 'region'),
          );
          $form['left']['blocks'][$entity_id]['region-theme']['theme'] = array(
            '#type' => 'hidden',
            '#value' => $this->theme,
            '#parents' => array('blocks', $entity_id, 'theme'),
          );
          $form['left']['blocks'][$entity_id]['weight'] = array(
            '#type' => 'weight',
            '#default_value' => $info['weight'],
            '#delta' => $weight_delta,
            '#title_display' => 'invisible',
            '#title' => t('Weight for @block block', array('@block' => $info['admin_label'])),
            '#attributes' => array(
              'class' => array('block-weight', 'block-weight-' . $region),
            ),
          );
          $form['left']['blocks'][$entity_id]['operations'] = $this->buildOperations($info['entity']);
        }
      }
    }

    // Do not allow disabling the main system content block when it is present.
    if (isset($form['left']['blocks']['system_main']['region'])) {
      $form['left']['blocks']['system_main']['region']['#required'] = TRUE;
    }

    $form['left']['actions'] = array(
      '#tree' => FALSE,
      '#type' => 'actions',
    );
    $form['left']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save blocks'),
      '#button_type' => 'primary',
    );

    $form['right']['#type'] = 'container';
    $form['right']['#attributes']['class'] = array(
      'block-list-region',
      'block-list-right',
    );

    $form['right']['list']['#type'] = 'container';
    $form['right']['list']['#attributes']['class'][] = 'entity-meta';

    $form['right']['list']['title'] = array(
      '#type' => 'container',
      '#children' => '<h3>' . t('Place blocks') . '</h3>',
      '#attributes' => array(
        'class' => array(
          'entity-meta-header',
        ),
      ),
    );
    $form['right']['list']['search'] = array(
      '#type' => 'search',
      '#title' => t('Filter'),
      '#title_display' => 'invisible',
      '#size' => 30,
      '#placeholder' => t('Filter by block name'),
      '#attributes' => array(
        'class' => array('block-filter-text'),
        'data-element' => '.entity-meta',
        'title' => t('Enter a part of the block name to filter by.'),
      ),
    );

    // Sort the plugins first by category, then by label.
    $plugins = $this->blockManager->getDefinitions();
    uasort($plugins, function ($a, $b) {
      if ($a['category'] != $b['category']) {
        return strnatcasecmp($a['category'], $b['category']);
      }
      return strnatcasecmp($a['admin_label'], $b['admin_label']);
    });
    foreach ($plugins as $plugin_id => $plugin_definition) {
      $category = $plugin_definition['category'];
      if (!isset($form['right']['list'][$category])) {
        $form['right']['list'][$category] = array(
          '#type' => 'details',
          '#title' => $category,
          'content' => array(
            '#theme' => 'links',
            '#links' => array(),
            '#attributes' => array(
              'class' => array(
                'block-list',
              ),
            ),
          ),
        );
      }
      $form['right']['list'][$category]['content']['#links'][$plugin_id] = array(
        'title' => $plugin_definition['admin_label'],
        'href' => 'admin/structure/block/add/' . $plugin_id . '/' . $this->theme,
        'attributes' => array(
          'class' => array('use-ajax', 'block-filter-text-source'),
          'data-accepts' => 'application/vnd.drupal-modal',
          'data-dialog-options' => Json::encode(array(
            'width' => 700,
          )),
        ),
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);

    if (isset($operations['edit'])) {
      $operations['edit']['title'] = t('Configure');
    }

    return $operations;
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::validateForm().
   */
  public function validateForm(array &$form, array &$form_state) {
    // No validation.
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   *
   * Form submission handler for the main block administration form.
   */
  public function submitForm(array &$form, array &$form_state) {
    $entities = entity_load_multiple('block', array_keys($form_state['values']['blocks']));
    foreach ($entities as $entity_id => $entity) {
      $entity->set('weight', $form_state['values']['blocks'][$entity_id]['weight']);
      $entity->set('region', $form_state['values']['blocks'][$entity_id]['region']);
      if ($entity->get('region') == BLOCK_REGION_NONE) {
        $entity->disable();
      }
      else {
        $entity->enable();
      }
      $entity->save();
    }
    drupal_set_message(t('The block settings have been updated.'));
    cache_invalidate_tags(array('content' => TRUE));
  }

}
