<?php

/**
 * @file
 * Contains \Drupal\action\ActionAddFormController.
 */

namespace Drupal\action;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form controller for action add forms.
 */
class ActionAddFormController extends ActionFormControllerBase implements EntityControllerInterface {

  /**
   * The action manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * Constructs a new ActionAddFormController.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $storage_controller
   *   The action storage controller.
   * @param \Drupal\Core\Action\ActionManager $action_manager
   *   The action plugin manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityStorageControllerInterface $storage_controller, ActionManager $action_manager) {
    parent::__construct($module_handler, $storage_controller);

    $this->actionManager = $action_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.entity')->getStorageController($entity_type),
      $container->get('plugin.manager.action')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param string $action_id
   *   The hashed version of the action ID.
   */
  public function buildForm(array $form, array &$form_state, $action_id = NULL) {
    // In \Drupal\action\Form\ActionAdminManageForm::buildForm() the action
    // are hashed. Here we have to decrypt it to find the desired action ID.
    foreach ($this->actionManager->getDefinitions() as $id => $definition) {
      $key = Crypt::hashBase64($id);
      if ($key === $action_id) {
        $this->entity->setPlugin($id);
        // Derive the label and type from the action definition.
        $this->entity->set('label', $definition['label']);
        $this->entity->set('type', $definition['type']);
        break;
      }
    }

    return parent::buildForm($form, $form_state);
  }

}
