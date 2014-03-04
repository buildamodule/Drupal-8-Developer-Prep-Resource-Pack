<?php

/**
 * @file
 * Contains \Drupal\system\Form\ThemeSettingsForm.
 */

namespace Drupal\system\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\Context\ContextInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\system\SystemConfigFormBase;

/**
 * Displays theme configuration for entire site and individual themes.
 */
class ThemeSettingsForm extends SystemConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a ThemeSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\Context\ContextInterface $context
   *   The configuration context to use.
   * @param Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler instance to use.
   */
  public function __construct(ConfigFactory $config_factory, ContextInterface $context, ModuleHandlerInterface $module_handler) {
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
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'system_theme_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @param string $theme_name
   *   The theme name.
   */
  public function buildForm(array $form, array &$form_state, $theme_name = '') {
    $form = parent::buildForm($form, $form_state);

    $themes = list_themes();

    // Deny access if the theme is disabled or not found.
    if (!empty($theme_name) && (empty($themes[$theme_name]) || !$themes[$theme_name]->status)) {
      throw new NotFoundHttpException();
    }

    // Default settings are defined in theme_get_setting() in includes/theme.inc
    if ($theme_name) {
      $var = 'theme_' . $theme_name . '_settings';
      $config_key = $theme_name . '.settings';
      $themes = list_themes();
      $features = $themes[$theme_name]->info['features'];
    }
    else {
      $var = 'theme_settings';
      $config_key = 'system.theme.global';
    }

    $form['var'] = array(
      '#type' => 'hidden',
      '#value' => $var
    );
    $form['config_key'] = array(
      '#type' => 'hidden',
      '#value' => $config_key
    );

    // Toggle settings
    $toggles = array(
      'logo' => t('Logo'),
      'name' => t('Site name'),
      'slogan' => t('Site slogan'),
      'node_user_picture' => t('User pictures in posts'),
      'comment_user_picture' => t('User pictures in comments'),
      'comment_user_verification' => t('User verification status in comments'),
      'favicon' => t('Shortcut icon'),
      'main_menu' => t('Main menu'),
      'secondary_menu' => t('Secondary menu'),
    );

    // Some features are not always available
    $disabled = array();
    if (!user_picture_enabled()) {
      $disabled['toggle_node_user_picture'] = TRUE;
      $disabled['toggle_comment_user_picture'] = TRUE;
    }
    if (!$this->moduleHandler->moduleExists('comment')) {
      $disabled['toggle_comment_user_picture'] = TRUE;
      $disabled['toggle_comment_user_verification'] = TRUE;
    }

    $form['theme_settings'] = array(
      '#type' => 'details',
      '#title' => t('Toggle display'),
      '#description' => t('Enable or disable the display of certain page elements.'),
    );
    foreach ($toggles as $name => $title) {
      if ((!$theme_name) || in_array($name, $features)) {
        $form['theme_settings']['toggle_' . $name] = array('#type' => 'checkbox', '#title' => $title, '#default_value' => theme_get_setting('features.' . $name, $theme_name));
        // Disable checkboxes for features not supported in the current configuration.
        if (isset($disabled['toggle_' . $name])) {
          $form['theme_settings']['toggle_' . $name]['#disabled'] = TRUE;
        }
      }
    }

    if (!element_children($form['theme_settings'])) {
      // If there is no element in the theme settings details then do not show
      // it -- but keep it in the form if another module wants to alter.
      $form['theme_settings']['#access'] = FALSE;
    }

    // Logo settings, only available when file.module is enabled.
    if ((!$theme_name) || in_array('logo', $features) && $this->moduleHandler->moduleExists('file')) {
      $form['logo'] = array(
        '#type' => 'details',
        '#title' => t('Logo image settings'),
        '#attributes' => array('class' => array('theme-settings-bottom')),
        '#states' => array(
          // Hide the logo image settings fieldset when logo display is disabled.
          'invisible' => array(
            'input[name="toggle_logo"]' => array('checked' => FALSE),
          ),
        ),
      );
      $form['logo']['default_logo'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use the default logo supplied by the theme'),
        '#default_value' => theme_get_setting('logo.use_default', $theme_name),
        '#tree' => FALSE,
      );
      $form['logo']['settings'] = array(
        '#type' => 'container',
        '#states' => array(
          // Hide the logo settings when using the default logo.
          'invisible' => array(
            'input[name="default_logo"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['logo']['settings']['logo_path'] = array(
        '#type' => 'textfield',
        '#title' => t('Path to custom logo'),
        '#default_value' => theme_get_setting('logo.path', $theme_name),
      );
      $form['logo']['settings']['logo_upload'] = array(
        '#type' => 'file',
        '#title' => t('Upload logo image'),
        '#maxlength' => 40,
        '#description' => t("If you don't have direct file access to the server, use this field to upload your logo.")
      );
    }

    if ((!$theme_name) || in_array('favicon', $features) && $this->moduleHandler->moduleExists('file')) {
      $form['favicon'] = array(
        '#type' => 'details',
        '#title' => t('Shortcut icon settings'),
        '#description' => t("Your shortcut icon, or 'favicon', is displayed in the address bar and bookmarks of most browsers."),
        '#states' => array(
          // Hide the shortcut icon settings fieldset when shortcut icon display
          // is disabled.
          'invisible' => array(
            'input[name="toggle_favicon"]' => array('checked' => FALSE),
          ),
        ),
      );
      $form['favicon']['default_favicon'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use the default shortcut icon supplied by the theme'),
        '#default_value' => theme_get_setting('favicon.use_default', $theme_name),
      );
      $form['favicon']['settings'] = array(
        '#type' => 'container',
        '#states' => array(
          // Hide the favicon settings when using the default favicon.
          'invisible' => array(
            'input[name="default_favicon"]' => array('checked' => TRUE),
          ),
        ),
      );
      $form['favicon']['settings']['favicon_path'] = array(
        '#type' => 'textfield',
        '#title' => t('Path to custom icon'),
        '#default_value' => theme_get_setting('favicon.path', $theme_name),
      );
      $form['favicon']['settings']['favicon_upload'] = array(
        '#type' => 'file',
        '#title' => t('Upload icon image'),
        '#description' => t("If you don't have direct file access to the server, use this field to upload your shortcut icon.")
      );
    }

    // Inject human-friendly values and form element descriptions for logo and
    // favicon.
    foreach (array('logo' => 'logo.png', 'favicon' => 'favicon.ico') as $type => $default) {
      if (isset($form[$type]['settings'][$type . '_path'])) {
        $element = &$form[$type]['settings'][$type . '_path'];

        // If path is a public:// URI, display the path relative to the files
        // directory; stream wrappers are not end-user friendly.
        $original_path = $element['#default_value'];
        $friendly_path = NULL;
        if (file_uri_scheme($original_path) == 'public') {
          $friendly_path = file_uri_target($original_path);
          $element['#default_value'] = $friendly_path;
        }

        // Prepare local file path for description.
        if ($original_path && isset($friendly_path)) {
          $local_file = strtr($original_path, array('public:/' => variable_get('file_public_path', conf_path() . '/files')));
        }
        elseif ($theme_name) {
          $local_file = drupal_get_path('theme', $theme_name) . '/' . $default;
        }
        else {
          $local_file = path_to_theme() . '/' . $default;
        }

        $element['#description'] = t('Examples: <code>@implicit-public-file</code> (for a file in the public filesystem), <code>@explicit-file</code>, or <code>@local-file</code>.', array(
          '@implicit-public-file' => isset($friendly_path) ? $friendly_path : $default,
          '@explicit-file' => file_uri_scheme($original_path) !== FALSE ? $original_path : 'public://' . $default,
          '@local-file' => $local_file,
        ));
      }
    }

    if ($theme_name) {
      // Call engine-specific settings.
      $function = $themes[$theme_name]->prefix . '_engine_settings';
      if (function_exists($function)) {
        $form['engine_specific'] = array(
          '#type' => 'details',
          '#title' => t('Theme-engine-specific settings'),
          '#description' => t('These settings only exist for the themes based on the %engine theme engine.', array('%engine' => $themes[$theme_name]->prefix)),
        );
        $function($form, $form_state);
      }

      // Create a list which includes the current theme and all its base themes.
      if (isset($themes[$theme_name]->base_themes)) {
        $theme_keys = array_keys($themes[$theme_name]->base_themes);
        $theme_keys[] = $theme_name;
      }
      else {
        $theme_keys = array($theme_name);
      }

      // Save the name of the current theme (if any), so that we can temporarily
      // override the current theme and allow theme_get_setting() to work
      // without having to pass the theme name to it.
      $default_theme = !empty($GLOBALS['theme_key']) ? $GLOBALS['theme_key'] : NULL;
      $GLOBALS['theme_key'] = $theme_name;

      // Process the theme and all its base themes.
      foreach ($theme_keys as $theme) {
        // Include the theme-settings.php file.
        $filename = DRUPAL_ROOT . '/' . str_replace("/$theme.info.yml", '', $themes[$theme]->filename) . '/theme-settings.php';
        if (file_exists($filename)) {
          require_once $filename;
        }

        // Call theme-specific settings.
        $function = $theme . '_form_system_theme_settings_alter';
        if (function_exists($function)) {
          $function($form, $form_state);
        }
      }

      // Restore the original current theme.
      if (isset($default_theme)) {
        $GLOBALS['theme_key'] = $default_theme;
      }
      else {
        unset($GLOBALS['theme_key']);
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    parent::validateForm($form, $form_state);

    if ($this->moduleHandler->moduleExists('file')) {
      // Handle file uploads.
      $validators = array('file_validate_is_image' => array());

      // Check for a new uploaded logo.
      $file = file_save_upload('logo_upload', $validators, FALSE, 0);
      if (isset($file)) {
        // File upload was attempted.
        if ($file) {
          // Put the temporary file in form_values so we can save it on submit.
          $form_state['values']['logo_upload'] = $file;
        }
        else {
          // File upload failed.
          form_set_error('logo_upload', t('The logo could not be uploaded.'));
        }
      }

      $validators = array('file_validate_extensions' => array('ico png gif jpg jpeg apng svg'));

      // Check for a new uploaded favicon.
      $file = file_save_upload('favicon_upload', $validators, FALSE, 0);
      if (isset($file)) {
        // File upload was attempted.
        if ($file) {
          // Put the temporary file in form_values so we can save it on submit.
          $form_state['values']['favicon_upload'] = $file;
        }
        else {
          // File upload failed.
          form_set_error('favicon_upload', t('The favicon could not be uploaded.'));
        }
      }

      // If the user provided a path for a logo or favicon file, make sure a file
      // exists at that path.
      if ($form_state['values']['logo_path']) {
        $path = $this->validatePath($form_state['values']['logo_path']);
        if (!$path) {
          form_set_error('logo_path', t('The custom logo path is invalid.'));
        }
      }
      if ($form_state['values']['favicon_path']) {
        $path = $this->validatePath($form_state['values']['favicon_path']);
        if (!$path) {
          form_set_error('favicon_path', t('The custom favicon path is invalid.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->configFactory->get($form_state['values']['config_key']);

    // Exclude unnecessary elements before saving.
    form_state_values_clean($form_state);
    unset($form_state['values']['var']);
    unset($form_state['values']['config_key']);

    $values = $form_state['values'];

    // If the user uploaded a new logo or favicon, save it to a permanent location
    // and use it in place of the default theme-provided file.
    if ($this->moduleHandler->moduleExists('file')) {
      if ($file = $values['logo_upload']) {
        unset($values['logo_upload']);
        $filename = file_unmanaged_copy($file->getFileUri());
        $values['default_logo'] = 0;
        $values['logo_path'] = $filename;
        $values['toggle_logo'] = 1;
      }
      if ($file = $values['favicon_upload']) {
        unset($values['favicon_upload']);
        $filename = file_unmanaged_copy($file->getFileUri());
        $values['default_favicon'] = 0;
        $values['favicon_path'] = $filename;
        $values['toggle_favicon'] = 1;
      }

      // If the user entered a path relative to the system files directory for
      // a logo or favicon, store a public:// URI so the theme system can handle it.
      if (!empty($values['logo_path'])) {
        $values['logo_path'] = $this->validatePath($values['logo_path']);
      }
      if (!empty($values['favicon_path'])) {
        $values['favicon_path'] = $this->validatePath($values['favicon_path']);
      }

      if (empty($values['default_favicon']) && !empty($values['favicon_path'])) {
        $values['favicon_mimetype'] = file_get_mimetype($values['favicon_path']);
      }
    }

    theme_settings_convert_to_config($values, $config)->save();

    Cache::invalidateTags(array('content' => TRUE));
  }

  /**
   * Helper function for the system_theme_settings form.
   *
   * Attempts to validate normal system paths, paths relative to the public files
   * directory, or stream wrapper URIs. If the given path is any of the above,
   * returns a valid path or URI that the theme system can display.
   *
   * @param string $path
   *   A path relative to the Drupal root or to the public files directory, or
   *   a stream wrapper URI.
   * @return mixed
   *   A valid path that can be displayed through the theme system, or FALSE if
   *   the path could not be validated.
   */
  protected function validatePath($path) {
    // Absolute local file paths are invalid.
    if (drupal_realpath($path) == $path) {
      return FALSE;
    }
    // A path relative to the Drupal root or a fully qualified URI is valid.
    if (is_file($path)) {
      return $path;
    }
    // Prepend 'public://' for relative file paths within public filesystem.
    if (file_uri_scheme($path) === FALSE) {
      $path = 'public://' . $path;
    }
    if (is_file($path)) {
      return $path;
    }
    return FALSE;
  }

}
