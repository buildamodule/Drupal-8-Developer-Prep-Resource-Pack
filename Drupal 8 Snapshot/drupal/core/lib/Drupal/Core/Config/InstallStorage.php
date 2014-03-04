<?php

/**
 * @file
 * Contains Drupal\Core\Config\InstallStorage.
 */

namespace Drupal\Core\Config;

/**
 * Storage controller used by the Drupal installer.
 *
 * @see install_begin_request()
 */
class InstallStorage extends FileStorage {

  /**
   * Folder map indexed by configuration name.
   *
   * @var array
   */
  protected $folders;

  /**
   * Overrides Drupal\Core\Config\FileStorage::__construct().
   */
  public function __construct() {
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::getFilePath().
   *
   * Returns the path to the configuration file.
   *
   * Determines the owner and path to the default configuration file of a
   * requested config object name located in the installation profile, a module,
   * or a theme (in this order).
   *
   * @return string
   *   The path to the configuration file.
   *
   * @todo Improve this when figuring out how we want to handle configuration in
   *   installation profiles. E.g., a config object actually has to be searched
   *   in the profile first (whereas the profile is never the owner), only
   *   afterwards check for a corresponding module or theme.
   */
  public function getFilePath($name) {
    $folders = $this->getAllFolders();
    if (isset($folders[$name])) {
      return $folders[$name] . '/' . $name . '.' . $this->getFileExtension();
    }
    // If any code in the early installer requests a configuration object that
    // does not exist anywhere as default config, then that must be mistake.
    throw new StorageException(format_string('Missing configuration file: @name', array(
      '@name' => $name,
    )));
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::write().
   *
   * @throws Drupal\Core\Config\StorageException
   */
  public function write($name, array $data) {
    throw new StorageException('Write operation is not allowed during install.');
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::delete().
   *
   * @throws Drupal\Core\Config\StorageException
   */
  public function delete($name) {
    throw new StorageException('Delete operation is not allowed during install.');
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::rename().
   *
   * @throws Drupal\Core\Config\StorageException
   */
  public function rename($name, $new_name) {
    throw new StorageException('Rename operation is not allowed during install.');
  }

  /**
   * Implements Drupal\Core\Config\StorageInterface::listAll().
   */
  public function listAll($prefix = '') {
    $names = array_keys($this->getAllFolders());
    if (!$prefix) {
      return $names;
    }
    else {
      $return = array();
      foreach ($names as $index => $name) {
        if (strpos($name, $prefix) === 0 ) {
          $return[$index] = $names[$index];
        }
      }
      return $return;
    }
  }

  /**
   * Returns a map of all config object names and their folders.
   *
   * @return array
   *   An array mapping config object names with directories.
   */
  protected function getAllFolders() {
    if (!isset($this->folders)) {
      $this->folders = $this->getComponentNames('profile', array(drupal_get_profile()));
      $this->folders += $this->getComponentNames('module', array_keys(drupal_system_listing('/^' . DRUPAL_PHP_FUNCTION_PATTERN . '\.module$/', 'modules', 'name', 0)));
      $this->folders += $this->getComponentNames('theme', array_keys(drupal_system_listing('/^' . DRUPAL_PHP_FUNCTION_PATTERN . '\.info.yml$/', 'themes')));
    }
    return $this->folders;
  }

  /**
   * Get all configuration names and folders for a list of modules or themes.
   *
   * @param string $type
   *   Type of components: 'module' | 'theme' | 'profile'
   * @param array $list
   *   Array of theme or module names.
   *
   * @return array
   *   Folders indexed by configuration name.
   */
  public function getComponentNames($type, array $list) {
    $extension = '.' . $this->getFileExtension();
    $folders = array();
    foreach ($list as $name) {
      $directory = $this->getComponentFolder($type, $name);
      if (file_exists($directory)) {
        $files = glob($directory . '/*' . $extension);
        foreach ($files as $filename) {
          $name = basename($filename, $extension);
          $folders[$name] = $directory;
        }
      }
    }
    return $folders;
  }

  /**
   * Get folder inside each component that contains the files.
   *
   * @param string $type
   *   Component type: 'module' | 'theme' | 'profile'
   * @param string $name
   *   Component name.
   *
   * @return string
   *   The configuration folder name for this component.
   */
  protected function getComponentFolder($type, $name) {
    return drupal_get_path($type, $name) . '/config';
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::deleteAll().
   *
   * @throws Drupal\Core\Config\StorageException
   */
  public function deleteAll($prefix = '') {
    throw new StorageException('Delete operation is not allowed during install.');
  }

}
