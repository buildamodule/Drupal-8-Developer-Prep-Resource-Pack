<?php

/**
 * @file
 * Contains \Drupal\image\Form\ImageStyleEditForm.
 */

namespace Drupal\image\Form;

use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\image\ConfigurableImageEffectInterface;
use Drupal\image\Form\ImageStyleFormBase;
use Drupal\image\ImageEffectManager;
use Drupal\Component\Utility\String;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for image style edit form.
 */
class ImageStyleEditForm extends ImageStyleFormBase {

  /**
   * The image effect manager service.
   *
   * @var \Drupal\image\ImageEffectManager
   */
  protected $imageEffectManager;

  /**
   * Constructs an ImageStyleEditForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $image_style_storage
   *   The storage controller.
   * @param \Drupal\image\ImageEffectManager $image_effect_manager
   *   The image effect manager service.
   * @param \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translator
   *   The translator service.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityStorageControllerInterface $image_style_storage, TranslatorInterface $translator, ImageEffectManager $image_effect_manager) {
    parent::__construct($module_handler, $image_style_storage, $translator);
    $this->imageEffectManager = $image_effect_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.entity')->getStorageController($entity_type),
      $container->get('string_translation'),
      $container->get('plugin.manager.image.effect')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {

    // @todo Remove drupal_set_title() in http://drupal.org/node/1981644
    $title = $this->translator->translate('Edit style %name', array('%name' => $this->entity->label()));
    drupal_set_title($title, PASS_THROUGH);

    $form['#tree'] = TRUE;
    $form['#attached']['css'][drupal_get_path('module', 'image') . '/css/image.admin.css'] = array();

    // Show the thumbnail preview.
    $preview_arguments = array('#theme' => 'image_style_preview', '#style' => $this->entity);
    $form['preview'] = array(
      '#type' => 'item',
      '#title' => $this->translator->translate('Preview'),
      '#markup' => drupal_render($preview_arguments),
      // Render preview above parent elements.
      '#weight' => -5,
    );

    // Build the list of existing image effects for this image style.
    $form['effects'] = array(
      '#theme' => 'image_style_effects',
      // Render effects below parent elements.
      '#weight' => 5,
    );
    foreach ($this->entity->getEffects()->sort() as $effect) {
      $key = $effect->getUuid();
      $form['effects'][$key]['#weight'] = isset($form_state['input']['effects']) ? $form_state['input']['effects'][$key]['weight'] : NULL;
      $form['effects'][$key]['label'] = array(
        '#markup' => String::checkPlain($effect->label()),
      );
      $form['effects'][$key]['summary'] = $effect->getSummary();
      $form['effects'][$key]['weight'] = array(
        '#type' => 'weight',
        '#title' => $this->translator->translate('Weight for @title', array('@title' => $effect->label())),
        '#title_display' => 'invisible',
        '#default_value' => $effect->getWeight(),
      );

      $links = array();
      $is_configurable = $effect instanceof ConfigurableImageEffectInterface;
      if ($is_configurable) {
        $links['edit'] = array(
          'title' => $this->translator->translate('edit'),
          'href' => 'admin/config/media/image-styles/manage/' . $this->entity->id() . '/effects/' . $key,
        );
      }
      $links['delete'] = array(
        'title' => $this->translator->translate('delete'),
        'href' => 'admin/config/media/image-styles/manage/' . $this->entity->id() . '/effects/' . $key . '/delete',
      );
      $form['effects'][$key]['operations'] = array(
        '#type' => 'operations',
        '#links' => $links,
      );
      $form['effects'][$key]['configure'] = array(
        '#type' => 'link',
        '#title' => $this->translator->translate('edit'),
        '#href' => 'admin/config/media/image-styles/manage/' . $this->entity->id() . '/effects/' . $key,
        '#access' => $is_configurable,
      );
      $form['effects'][$key]['remove'] = array(
        '#type' => 'link',
        '#title' => $this->translator->translate('delete'),
        '#href' => 'admin/config/media/image-styles/manage/' . $this->entity->id() . '/effects/' . $key . '/delete',
      );
    }

    // Build the new image effect addition form and add it to the effect list.
    $new_effect_options = array();
    $effects = $this->imageEffectManager->getDefinitions();
    uasort($effects, function ($a, $b) {
      return strcasecmp($a['id'], $b['id']);
    });
    foreach ($effects as $effect => $definition) {
      $new_effect_options[$effect] = $definition['label'];
    }
    $form['effects']['new'] = array(
      '#tree' => FALSE,
      '#weight' => isset($form_state['input']['weight']) ? $form_state['input']['weight'] : NULL,
    );
    $form['effects']['new']['new'] = array(
      '#type' => 'select',
      '#title' => $this->translator->translate('Effect'),
      '#title_display' => 'invisible',
      '#options' => $new_effect_options,
      '#empty_option' => $this->translator->translate('Select a new effect'),
    );
    $form['effects']['new']['weight'] = array(
      '#type' => 'weight',
      '#title' => $this->translator->translate('Weight for new effect'),
      '#title_display' => 'invisible',
      '#default_value' => count($form['effects']) - 1,
    );
    $form['effects']['new']['add'] = array(
      '#type' => 'submit',
      '#value' => $this->translator->translate('Add'),
      '#validate' => array(array($this, 'effectValidate')),
      '#submit' => array(array($this, 'effectSave')),
    );

    return parent::form($form, $form_state);
  }

  /**
   * Validate handler for image effect.
   */
  public function effectValidate($form, &$form_state) {
    if (!$form_state['values']['new']) {
      form_error($form['effects']['new']['new'], $this->translator->translate('Select an effect to add.'));
    }
  }

  /**
   * Submit handler for image effect.
   */
  public function effectSave($form, &$form_state) {

    // Update image effect weights.
    if (!empty($form_state['values']['effects'])) {
      $this->updateEffectWeights($form_state['values']['effects']);
    }

    $this->entity->set('name', $form_state['values']['name']);
    $this->entity->set('label', $form_state['values']['label']);

    $status = parent::save($form, $form_state);

    if ($status == SAVED_UPDATED) {
      drupal_set_message($this->translator->translate('Changes to the style have been saved.'));
    }

    // Check if this field has any configuration options.
    $effect = $this->imageEffectManager->getDefinition($form_state['values']['new']);

    // Load the configuration form for this option.
    if (is_subclass_of($effect['class'], '\Drupal\image\ConfigurableImageEffectInterface')) {
      $path = 'admin/config/media/image-styles/manage/' . $this->entity->id() . '/add/' . $form_state['values']['new'];
      $form_state['redirect'] = array($path, array('query' => array('weight' => $form_state['values']['weight'])));
    }
    // If there's no form, immediately add the image effect.
    else {
      $effect = array(
        'id' => $effect['id'],
        'data' => array(),
        'weight' => $form_state['values']['weight'],
      );
      $effect_id = $this->entity->saveImageEffect($effect);
      if (!empty($effect_id)) {
        drupal_set_message($this->translator->translate('The image effect was successfully applied.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, array &$form_state) {

    // Update image effect weights.
    if (!empty($form_state['values']['effects'])) {
      $this->updateEffectWeights($form_state['values']['effects']);
    }

    parent::save($form, $form_state);
    drupal_set_message($this->translator->translate('Changes to the style have been saved.'));
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->translator->translate('Update style');

    return $actions;
  }

  /**
   * Updates image effect weights.
   *
   * @param array $effects
   *   Associative array with effects having effect uuid as keys and and array
   *   with effect data as values.
   */
  protected function updateEffectWeights(array $effects) {
    foreach ($effects as $uuid => $effect_data) {
      if ($this->entity->getEffects()->has($uuid)) {
        $this->entity->getEffect($uuid)->setWeight($effect_data['weight']);
      }
    }
  }

}
