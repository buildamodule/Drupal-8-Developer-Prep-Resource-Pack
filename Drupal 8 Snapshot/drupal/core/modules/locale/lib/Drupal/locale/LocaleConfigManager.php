<?php

/**
 * @file
 * Contains \Drupal\locale\LocaleConfigManager.
 */

namespace Drupal\locale;

use Drupal\Core\Language\Language;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\Core\Config\StorageInterface;

/**
 * Manages localized configuration type plugins.
 */
class LocaleConfigManager extends TypedConfigManager {

  /**
   * A storage controller instance for reading default configuration data.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $installStorage;

  /**
   * A string storage for reading and writing translations.
   */
  protected $localeStorage;

  /**
   * Array with preloaded string translations.
   *
   * @var array
   */
  protected $translations;

  /**
   * Creates a new typed configuration manager.
   *
   * @param \Drupal\Core\Config\StorageInterface $configStorage
   *   The storage controller object to use for reading configuration data.
   * @param \Drupal\Core\Config\StorageInterface $schemaStorage
   *   The storage controller object to use for reading schema data.
   * @param \Drupal\Core\Config\StorageInterface $installStorage
   *   The storage controller object to use for reading default configuration
   *   data.
   * @param \Drupal\locale\StringStorageInterface $localeStorage
   *   The locale storage to use for reading string translations.
   */
  public function __construct(StorageInterface $configStorage, StorageInterface $schemaStorage, StorageInterface $installStorage, StringStorageInterface $localeStorage) {
    // Note we use the install storage for the parent constructor.
    parent::__construct($configStorage, $schemaStorage);
    $this->installStorage = $installStorage;
    $this->localeStorage = $localeStorage;
  }

  /**
   * Gets locale wrapper with typed configuration data.
   *
   * @param string $name
   *   Configuration object name.
   *
   * @return \Drupal\locale\LocaleTypedConfig
   *   Locale-wrapped configuration element.
   */
  public function get($name) {
    // Read default and current configuration data.
    $default = $this->installStorage->read($name);
    $updated = $this->configStorage->read($name);
    // We get only the data that didn't change from default.
    $data = $this->compareConfigData($default, $updated);
    $definition = $this->getDefinition($name);
    // Unless the configuration has a explicit language code we assume English.
    $langcode = isset($default['langcode']) ? $default['langcode'] : 'en';
    $wrapper = new LocaleTypedConfig($definition, $name, $langcode, $this);
    $wrapper->setValue($data);
    return $wrapper;
  }

  /**
   * Compares default configuration with updated data.
   *
   * @param array $default
   *   Default configuration data.
   * @param array|false $updated
   *   Current configuration data, or FALSE if no configuration data existed.
   *
   * @return array
   *   The elements of default configuration that haven't changed.
   */
  protected function compareConfigData(array $default, $updated) {
    // Speed up comparison, specially for install operations.
    if ($default === $updated) {
      return $default;
    }
    $result = array();
    foreach ($default as $key => $value) {
      if (isset($updated[$key])) {
        if (is_array($value)) {
          $result[$key] = $this->compareConfigData($value, $updated[$key]);
        }
        elseif ($value === $updated[$key]) {
          $result[$key] = $value;
        }
      }
    }
    return $result;
  }

  /**
   * Saves translated configuration data.
   *
   * @param string $name
   *   Configuration object name.
   * @param string $langcode
   *   Language code.
   * @param array $data
   *   Configuration data to be saved, that will be only the translated values.
   */
  public function saveTranslationData($name, $langcode, array $data) {
    $locale_name = self::localeConfigName($langcode, $name);
    $this->configStorage->write($locale_name, $data);
  }

  /**
   * Deletes translated configuration data.
   *
   * @param string $name
   *   Configuration object name.
   * @param string $langcode
   *   Language code.
   */
  public function deleteTranslationData($name, $langcode) {
    $locale_name = self::localeConfigName($langcode, $name);
    $this->configStorage->delete($locale_name);
  }

  /**
   * Gets configuration names associated with components.
   *
   * @param array $components
   *   (optional) Array of component lists indexed by type. If not present or it
   *   is an empty array, it will update all components.
   *
   * @return array
   *   Array of configuration object names.
   */
  public function getComponentNames(array $components) {
    $components = array_filter($components);
    if ($components) {
      $names = array();
      foreach ($components as $type => $list) {
        // InstallStorage::getComponentNames returns a list of folders keyed by
        // config name.
        $names = array_merge($names, array_keys($this->installStorage->getComponentNames($type, $list)));
      }
      return $names;
    }
    else {
      return $this->installStorage->listAll();
    }
  }

  /**
   * Deletes configuration translations for uninstalled components.
   *
   * @param array $components
   *   Array with string identifiers.
   * @param array $langcodes
   *   Array of language codes.
   */
  public function deleteComponentTranslations(array $components, array $langcodes) {
    $names = $this->getComponentNames($components);
    if ($names && $langcodes) {
      foreach ($names as $name) {
        foreach ($langcodes as $langcode) {
          $this->deleteTranslationData($name, $langcode);
        }
      }
    }
  }

  /**
   * Gets configuration names associated with strings.
   *
   * @param array $lids
   *   Array with string identifiers.
   *
   * @return array
   *   Array of configuration object names.
   */
  public function getStringNames(array $lids) {
    $names = array();
    $locations = $this->localeStorage->getLocations(array('sid' => $lids, 'type' => 'configuration'));
    foreach ($locations as $location) {
      $names[$location->name] = $location->name;
    }
    return $names;
  }

  /**
   * Deletes configuration for language.
   *
   * @param string $langcode
   *   Language code to delete.
   */
  public function deleteLanguageTranslations($langcode) {
    $locale_name = self::localeConfigName($langcode);
    foreach ($this->configStorage->listAll($locale_name) as $name) {
      $this->configStorage->delete($name);
    }
  }

  /**
   * Translates string using the localization system.
   *
   * So far we only know how to translate strings from English so the source
   * string should be in English.
   * Unlike regular t() translations, strings will be added to the source
   * tables only if this is marked as default data.
   *
   * @param string $name
   *   Name of the configuration location.
   * @param string $langcode
   *   Language code to translate to.
   * @param string $source
   *   The source string, should be English.
   * @param string $context
   *   The string context.
   *
   * @return string|false
   *   Translated string if there is a translation, FALSE if not.
   */
  public function translateString($name, $langcode, $source, $context) {
    if ($source) {
      // If translations for a language have not been loaded yet.
      if (!isset($this->translations[$name][$langcode])) {
        // Preload all translations for this configuration name and language.
        $this->translations[$name][$langcode] = array();
        foreach ($this->localeStorage->getTranslations(array('language' => $langcode, 'type' => 'configuration', 'name' => $name)) as $string){
          $this->translations[$name][$langcode][$string->context][$string->source] = $string;
        }
      }
      if (!isset($this->translations[$name][$langcode][$context][$source])) {
        // There is no translation of the source string in this config location
        // to this language for this context.
        if ($translation = $this->localeStorage->findTranslation(array('source' => $source, 'context' => $context, 'language' => $langcode))) {
          // Look for a translation of the string. It might have one, but not
          // be saved in this configuration location yet.
          // If the string has a translation for this context to this language,
          // save it in the configuration location so it can be looked up faster
          // next time.
          $this->localeStorage->createString((array) $translation)
            ->addLocation('configuration', $name)
            ->save();
        }
        else {
          // No translation was found. Add the source to the configuration
          // location so it can be translated, and the string is faster to look
          // for next time.
          $translation = $this->localeStorage
            ->createString(array('source' => $source, 'context' => $context))
            ->addLocation('configuration', $name)
            ->save();
        }

        // Add an entry, either the translation found, or a blank string object
        // to track the source string, to this configuration location, language,
        // and context.
        $this->translations[$name][$langcode][$context][$source] = $translation;
      }

      // Return the string only when the string object had a translation.
      if ($this->translations[$name][$langcode][$context][$source]->isTranslation()) {
        return $this->translations[$name][$langcode][$context][$source]->getString();
      }
    }
    return FALSE;
  }

  /**
   * Provides configuration data location for given langcode and name.
   *
   * @param string $langcode
   *   The language code.
   * @param string|NULL $name
   *   Name of the original configuration. Set to NULL to get the name prefix
   *   for all $langcode overrides.
   *
   * @return string
   */
  public static function localeConfigName($langcode, $name = NULL) {
    return rtrim('locale.config.' . $langcode . '.' . $name, '.');
  }
}
