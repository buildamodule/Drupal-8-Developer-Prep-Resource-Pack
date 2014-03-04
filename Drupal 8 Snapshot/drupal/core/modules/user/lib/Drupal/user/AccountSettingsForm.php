<?php

/**
 * @file
 * Contains \Drupal\user\AccountSettingsForm.
 */

namespace Drupal\user;

use Drupal\system\SystemConfigFormBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\Context\ContextInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure user settings for this site.
 */
class AccountSettingsForm extends SystemConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Constructs a \Drupal\user\AccountSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\Context\ContextInterface $context
   *   The configuration context.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactory $config_factory, ContextInterface $context, ModuleHandler $module_handler) {
    parent::__construct($config_factory, $context);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.context.free'),
      $container->get('module_handler')
    );
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'user_admin_settings';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->configFactory->get('user.settings');
    $mail_config = $this->configFactory->get('user.mail');
    $site_config = $this->configFactory->get('system.site');

    // Settings for anonymous users.
    $form['anonymous_settings'] = array(
      '#type' => 'details',
      '#title' => t('Anonymous users'),
    );
    $form['anonymous_settings']['anonymous'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#default_value' => $config->get('anonymous'),
      '#description' => t('The name used to indicate anonymous users.'),
      '#required' => TRUE,
    );

    // Administrative role option.
    $form['admin_role'] = array(
      '#type' => 'details',
      '#title' => t('Administrator role'),
    );
    // Do not allow users to set the anonymous or authenticated user roles as the
    // administrator role.
    $roles = user_role_names(TRUE);
    unset($roles[DRUPAL_AUTHENTICATED_RID]);
    $form['admin_role']['user_admin_role'] = array(
      '#type' => 'select',
      '#title' => t('Administrator role'),
      '#empty_value' => '',
      '#default_value' => $config->get('admin_role'),
      '#options' => $roles,
      '#description' => t('This role will be automatically assigned new permissions whenever a module is enabled. Changing this setting will not affect existing permissions.'),
    );

    // @todo Remove this check once language settings are generalized.
    if ($this->moduleHandler->moduleExists('content_translation')) {
      $form['language'] = array(
        '#type' => 'details',
        '#title' => t('Language settings'),
        '#tree' => TRUE,
      );
      $form_state['content_translation']['key'] = 'language';
      $form['language'] += content_translation_enable_widget('user', 'user', $form, $form_state);
    }

    // User registration settings.
    $form['registration_cancellation'] = array(
      '#type' => 'details',
      '#title' => t('Registration and cancellation'),
    );
    $form['registration_cancellation']['user_register'] = array(
      '#type' => 'radios',
      '#title' => t('Who can register accounts?'),
      '#default_value' => $config->get('register'),
      '#options' => array(
        USER_REGISTER_ADMINISTRATORS_ONLY => t('Administrators only'),
        USER_REGISTER_VISITORS => t('Visitors'),
        USER_REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL => t('Visitors, but administrator approval is required'),
      )
    );
    $form['registration_cancellation']['user_email_verification'] = array(
      '#type' => 'checkbox',
      '#title' => t('Require e-mail verification when a visitor creates an account.'),
      '#default_value' => $config->get('verify_mail'),
      '#description' => t('New users will be required to validate their e-mail address prior to logging into the site, and will be assigned a system-generated password. With this setting disabled, users will be logged in immediately upon registering, and may select their own passwords during registration.')
    );
    $form['registration_cancellation']['user_password_strength'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable password strength indicator'),
      '#default_value' => $config->get('password_strength'),
    );
    form_load_include($form_state, 'inc', 'user', 'user.pages');
    $form['registration_cancellation']['user_cancel_method'] = array(
      '#type' => 'radios',
      '#title' => t('When cancelling a user account'),
      '#default_value' => $config->get('cancel_method'),
      '#description' => t('Users with the %select-cancel-method or %administer-users <a href="@permissions-url">permissions</a> can override this default method.', array('%select-cancel-method' => t('Select method for cancelling account'), '%administer-users' => t('Administer users'), '@permissions-url' => url('admin/people/permissions'))),
    );
    $form['registration_cancellation']['user_cancel_method'] += user_cancel_methods();
    foreach (element_children($form['registration_cancellation']['user_cancel_method']) as $key) {
      // All account cancellation methods that specify #access cannot be
      // configured as default method.
      // @see hook_user_cancel_methods_alter()
      if (isset($form['registration_cancellation']['user_cancel_method'][$key]['#access'])) {
        $form['registration_cancellation']['user_cancel_method'][$key]['#access'] = FALSE;
      }
    }

    // Account settings.
    $form['personalization'] = array(
      '#type' => 'details',
      '#title' => t('Personalization'),
    );
    $form['personalization']['user_signatures'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable signatures.'),
      '#default_value' => $config->get('signatures'),
    );

    // Default notifications address.
    $form['mail_notification_address'] = array(
      '#type' => 'email',
      '#title' => t('Notification e-mail address'),
      '#default_value' => $site_config->get('mail_notification'),
      '#description' => t("The e-mail address to be used as the 'from' address for all account notifications listed below. If <em>'Visitors, but administrator approval is required'</em> is selected above, a notification email will also be sent to this address for any new registrations. Leave empty to use the default system e-mail address <em>(%site-email).</em>", array('%site-email' => $site_config->get('mail'))),
      '#maxlength' => 180,
    );

    $form['email'] = array(
      '#type' => 'vertical_tabs',
      '#title' => t('E-mails'),
    );
    // These email tokens are shared for all settings, so just define
    // the list once to help ensure they stay in sync.
    $email_token_help = t('Available variables are: [site:name], [site:url], [user:name], [user:mail], [site:login-url], [site:url-brief], [user:edit-url], [user:one-time-login-url], [user:cancel-url].');

    $form['email_admin_created'] = array(
      '#type' => 'details',
      '#title' => t('Welcome (new user created by administrator)'),
      '#collapsed' => ($config->get('register') != USER_REGISTER_ADMINISTRATORS_ONLY),
      '#description' => t('Edit the welcome e-mail messages sent to new member accounts created by an administrator.') . ' ' . $email_token_help,
      '#group' => 'email',
    );
    $form['email_admin_created']['user_mail_register_admin_created_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('register_admin_created.subject'),
      '#maxlength' => 180,
    );
    $form['email_admin_created']['user_mail_register_admin_created_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' =>  $mail_config->get('register_admin_created.body'),
      '#rows' => 15,
    );

    $form['email_pending_approval'] = array(
      '#type' => 'details',
      '#title' => t('Welcome (awaiting approval)'),
      '#collapsed' => ($config->get('register') != USER_REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL),
      '#description' => t('Edit the welcome e-mail messages sent to new members upon registering, when administrative approval is required.') . ' ' . $email_token_help,
      '#group' => 'email',
    );
    $form['email_pending_approval']['user_mail_register_pending_approval_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('register_pending_approval.subject'),
      '#maxlength' => 180,
    );
    $form['email_pending_approval']['user_mail_register_pending_approval_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' => $mail_config->get('register_pending_approval.body'),
      '#rows' => 8,
    );

    $form['email_pending_approval_admin'] = array(
      '#type' => 'details',
      '#title' => t('Admin (user awaiting approval)'),
      '#collapsed' => ($config->get('register') != USER_REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL),
      '#description' => t('Edit the e-mail notifying the site administrator that there are new members awaiting administrative approval.') . ' ' . $email_token_help,
      '#group' => 'email',
    );
    $form['email_pending_approval_admin']['register_pending_approval_admin_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('register_pending_approval_admin.subject'),
      '#maxlength' => 180,
    );
    $form['email_pending_approval_admin']['register_pending_approval_admin_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' => $mail_config->get('register_pending_approval_admin.body'),
      '#rows' => 8,
    );

    $form['email_no_approval_required'] = array(
      '#type' => 'details',
      '#title' => t('Welcome (no approval required)'),
      '#collapsed' => ($config->get('register') != USER_REGISTER_VISITORS),
      '#description' => t('Edit the welcome e-mail messages sent to new members upon registering, when no administrator approval is required.') . ' ' . $email_token_help,
      '#group' => 'email',
    );
    $form['email_no_approval_required']['user_mail_register_no_approval_required_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('register_no_approval_required.subject'),
      '#maxlength' => 180,
    );
    $form['email_no_approval_required']['user_mail_register_no_approval_required_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' => $mail_config->get('register_no_approval_required.body'),
      '#rows' => 15,
    );

    $form['email_password_reset'] = array(
      '#type' => 'details',
      '#title' => t('Password recovery'),
      '#collapsed' => TRUE,
      '#description' => t('Edit the e-mail messages sent to users who request a new password.') . ' ' . $email_token_help,
      '#group' => 'email',
      '#weight' => 10,
    );
    $form['email_password_reset']['user_mail_password_reset_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('password_reset.subject'),
      '#maxlength' => 180,
    );
    $form['email_password_reset']['user_mail_password_reset_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' => $mail_config->get('password_reset.body'),
      '#rows' => 12,
    );

    $form['email_activated'] = array(
      '#type' => 'details',
      '#title' => t('Account activation'),
      '#collapsed' => TRUE,
      '#description' => t('Enable and edit e-mail messages sent to users upon account activation (when an administrator activates an account of a user who has already registered, on a site where administrative approval is required).') . ' ' . $email_token_help,
      '#group' => 'email',
    );
    $form['email_activated']['user_mail_status_activated_notify'] = array(
      '#type' => 'checkbox',
      '#title' => t('Notify user when account is activated.'),
      '#default_value' => $config->get('notify.status_activated'),
    );
    $form['email_activated']['settings'] = array(
      '#type' => 'container',
      '#states' => array(
        // Hide the additional settings when this email is disabled.
        'invisible' => array(
          'input[name="user_mail_status_activated_notify"]' => array('checked' => FALSE),
        ),
      ),
    );
    $form['email_activated']['settings']['user_mail_status_activated_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('status_activated.subject'),
      '#maxlength' => 180,
    );
    $form['email_activated']['settings']['user_mail_status_activated_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' => $mail_config->get('status_activated.body'),
      '#rows' => 15,
    );

    $form['email_blocked'] = array(
      '#type' => 'details',
      '#title' => t('Account blocked'),
      '#collapsed' => TRUE,
      '#description' => t('Enable and edit e-mail messages sent to users when their accounts are blocked.') . ' ' . $email_token_help,
      '#group' => 'email',
    );
    $form['email_blocked']['user_mail_status_blocked_notify'] = array(
      '#type' => 'checkbox',
      '#title' => t('Notify user when account is blocked.'),
      '#default_value' => $config->get('notify.status_blocked'),
    );
    $form['email_blocked']['settings'] = array(
      '#type' => 'container',
      '#states' => array(
        // Hide the additional settings when the blocked email is disabled.
        'invisible' => array(
          'input[name="user_mail_status_blocked_notify"]' => array('checked' => FALSE),
        ),
      ),
    );
    $form['email_blocked']['settings']['user_mail_status_blocked_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('status_blocked.subject'),
      '#maxlength' => 180,
    );
    $form['email_blocked']['settings']['user_mail_status_blocked_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' => $mail_config->get('status_blocked.body'),
      '#rows' => 3,
    );

    $form['email_cancel_confirm'] = array(
      '#type' => 'details',
      '#title' => t('Account cancellation confirmation'),
      '#collapsed' => TRUE,
      '#description' => t('Edit the e-mail messages sent to users when they attempt to cancel their accounts.') . ' ' . $email_token_help,
      '#group' => 'email',
    );
    $form['email_cancel_confirm']['user_mail_cancel_confirm_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('cancel_confirm.subject'),
      '#maxlength' => 180,
    );
    $form['email_cancel_confirm']['user_mail_cancel_confirm_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' => $mail_config->get('cancel_confirm.body'),
      '#rows' => 3,
    );

    $form['email_canceled'] = array(
      '#type' => 'details',
      '#title' => t('Account canceled'),
      '#collapsed' => TRUE,
      '#description' => t('Enable and edit e-mail messages sent to users when their accounts are canceled.') . ' ' . $email_token_help,
      '#group' => 'email',
    );
    $form['email_canceled']['user_mail_status_canceled_notify'] = array(
      '#type' => 'checkbox',
      '#title' => t('Notify user when account is canceled.'),
      '#default_value' => $config->get('notify.status_canceled'),
    );
    $form['email_canceled']['settings'] = array(
      '#type' => 'container',
      '#states' => array(
        // Hide the settings when the cancel notify checkbox is disabled.
        'invisible' => array(
          'input[name="user_mail_status_canceled_notify"]' => array('checked' => FALSE),
        ),
      ),
    );
    $form['email_canceled']['settings']['user_mail_status_canceled_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#default_value' => $mail_config->get('status_canceled.subject'),
      '#maxlength' => 180,
    );
    $form['email_canceled']['settings']['user_mail_status_canceled_body'] = array(
      '#type' => 'textarea',
      '#title' => t('Body'),
      '#default_value' => $mail_config->get('status_canceled.body'),
      '#rows' => 3,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    parent::submitForm($form, $form_state);

    $this->configFactory->get('user.settings')
      ->set('anonymous', $form_state['values']['anonymous'])
      ->set('admin_role', $form_state['values']['user_admin_role'])
      ->set('register', $form_state['values']['user_register'])
      ->set('password_strength', $form_state['values']['user_password_strength'])
      ->set('verify_mail', $form_state['values']['user_email_verification'])
      ->set('signatures', $form_state['values']['user_signatures'])
      ->set('cancel_method', $form_state['values']['user_cancel_method'])
      ->set('notify.status_activated', $form_state['values']['user_mail_status_activated_notify'])
      ->set('notify.status_blocked', $form_state['values']['user_mail_status_blocked_notify'])
      ->set('notify.status_canceled', $form_state['values']['user_mail_status_canceled_notify'])
      ->save();
    $this->configFactory->get('user.mail')
      ->set('cancel_confirm.body', $form_state['values']['user_mail_cancel_confirm_body'])
      ->set('cancel_confirm.subject', $form_state['values']['user_mail_cancel_confirm_subject'])
      ->set('password_reset.body', $form_state['values']['user_mail_password_reset_body'])
      ->set('password_reset.subject', $form_state['values']['user_mail_password_reset_subject'])
      ->set('register_admin_created.body', $form_state['values']['user_mail_register_admin_created_body'])
      ->set('register_admin_created.subject', $form_state['values']['user_mail_register_admin_created_subject'])
      ->set('register_no_approval_required.body', $form_state['values']['user_mail_register_no_approval_required_body'])
      ->set('register_no_approval_required.subject', $form_state['values']['user_mail_register_no_approval_required_subject'])
      ->set('register_pending_approval.body', $form_state['values']['user_mail_register_pending_approval_body'])
      ->set('register_pending_approval.subject', $form_state['values']['user_mail_register_pending_approval_subject'])
      ->set('status_activated.body', $form_state['values']['user_mail_status_activated_body'])
      ->set('status_activated.subject', $form_state['values']['user_mail_status_activated_subject'])
      ->set('status_blocked.body', $form_state['values']['user_mail_status_blocked_body'])
      ->set('status_blocked.subject', $form_state['values']['user_mail_status_blocked_subject'])
      ->set('status_canceled.body', $form_state['values']['user_mail_status_canceled_body'])
      ->set('status_canceled.subject', $form_state['values']['user_mail_status_canceled_subject'])
      ->save();
    $this->configFactory->get('system.site')
      ->set('mail_notification', $form_state['values']['mail_notification_address'])
      ->save();
  }

}
