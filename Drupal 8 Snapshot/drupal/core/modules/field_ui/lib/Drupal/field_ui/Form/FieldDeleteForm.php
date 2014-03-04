<?php

/**
 * @file
 * Contains \Drupal\field_ui\Form\FieldDeleteForm.
 */

namespace Drupal\field_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for removing a field instance from a bundle.
 */
class FieldDeleteForm extends EntityConfirmFormBase implements EntityControllerInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Constructs a new FieldDeleteForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityManager $entity_manager) {
    parent::__construct($module_handler);
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.entity')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the field %field?', array('%field' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelPath() {
    return $this->entityManager->getAdminPath($this->entity->entity_type, $this->entity->bundle) . '/fields';
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $field = $this->entity->getField();
    $bundles = entity_get_bundles();
    $bundle_label = $bundles[$this->entity->entity_type][$this->entity->bundle]['label'];

    if ($field && !$field['locked']) {
      $this->entity->delete();
      drupal_set_message(t('The field %field has been deleted from the %type content type.', array('%field' => $this->entity->label(), '%type' => $bundle_label)));
    }
    else {
      drupal_set_message(t('There was a problem removing the %field from the %type content type.', array('%field' => $this->entity->label(), '%type' => $bundle_label)), 'error');
    }

    $admin_path = $this->entityManager->getAdminPath($this->entity->entity_type, $this->entity->bundle);
    $form_state['redirect'] = "$admin_path/fields";

    // Fields are purged on cron. However field module prevents disabling modules
    // when field types they provided are used in a field until it is fully
    // purged. In the case that a field has minimal or no content, a single call
    // to field_purge_batch() will remove it from the system. Call this with a
    // low batch limit to avoid administrators having to wait for cron runs when
    // removing instances that meet this criteria.
    field_purge_batch(10);
  }

}
