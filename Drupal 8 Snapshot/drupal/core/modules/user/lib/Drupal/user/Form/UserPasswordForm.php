<?php

/**
 * @file
 * Contains \Drupal\user\Form\UserPasswordForm.
 */

namespace Drupal\user\Form;

use Drupal\Core\Controller\ControllerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManager;
use Drupal\user\UserStorageControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a user password reset form.
 */
class UserPasswordForm implements FormInterface, ControllerInterface {

  /**
   * The user storage controller.
   *
   * @var \Drupal\user\UserStorageControllerInterface
   */
  protected $userStorageController;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Constructs a UserPasswordForm object.
   *
   * @param \Drupal\user\UserStorageControllerInterface $user_storage_controller
   *   The user storage controller.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   */
  public function __construct(UserStorageControllerInterface $user_storage_controller, LanguageManager $language_manager) {
    $this->userStorageController = $user_storage_controller;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity')->getStorageController('user'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'user_pass';
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  public function buildForm(array $form, array &$form_state, Request $request = NULL) {
    global $user;

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Username or e-mail address'),
      '#size' => 60,
      '#maxlength' => max(USERNAME_MAX_LENGTH, EMAIL_MAX_LENGTH),
      '#required' => TRUE,
      '#attributes' => array(
        'autocorrect' => 'off',
        'autocapitalize' => 'off',
        'spellcheck' => 'false',
        'autofocus' => 'autofocus',
      ),
    );
    // Allow logged in users to request this also.
    if ($user->isAuthenticated()) {
      $form['name']['#type'] = 'value';
      $form['name']['#value'] = $user->getEmail();
      $form['mail'] = array(
        '#prefix' => '<p>',
        '#markup' =>  t('Password reset instructions will be mailed to %email. You must log out to use the password reset link in the e-mail.', array('%email' => $user->getEmail())),
        '#suffix' => '</p>',
      );
    }
    else {
      $form['name']['#default_value'] = $request->query->get('name');
    }
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('E-mail new password'));

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    $name = trim($form_state['values']['name']);
    // Try to load by email.
    $users = $this->userStorageController->loadByProperties(array('mail' => $name, 'status' => '1'));
    if (empty($users)) {
      // No success, try to load by name.
      $users = $this->userStorageController->loadByProperties(array('name' => $name, 'status' => '1'));
    }
    $account = reset($users);
    if ($account && $account->id()) {
      form_set_value(array('#parents' => array('account')), $account, $form_state);
    }
    else {
      form_set_error('name', t('Sorry, %name is not recognized as a username or an e-mail address.', array('%name' => $name)));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $langcode = $this->languageManager->getLanguage(Language::TYPE_INTERFACE)->id;

    $account = $form_state['values']['account'];
    // Mail one time login URL and instructions using current language.
    $mail = _user_mail_notify('password_reset', $account, $langcode);
    if (!empty($mail)) {
      watchdog('user', 'Password reset instructions mailed to %name at %email.', array('%name' => $account->getUsername(), '%email' => $account->getEmail()));
      drupal_set_message(t('Further instructions have been sent to your e-mail address.'));
    }

    $form_state['redirect'] = 'user';
  }

}
