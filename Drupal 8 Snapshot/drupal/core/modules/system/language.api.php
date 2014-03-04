<?php

/**
 * @file
 * Hooks provided by the base system for language support.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Perform alterations on language switcher links.
 *
 * A language switcher link may need to point to a different path or use a
 * translated link text before going through l(), which will just handle the
 * path aliases.
 *
 * @param $links
 *   Nested array of links keyed by language code.
 * @param $type
 *   The language type the links will switch.
 * @param $path
 *   The current path.
 */
function hook_language_switch_links_alter(array &$links, $type, $path) {
  $language_interface = language(\Drupal\Core\Language\Language::TYPE_INTERFACE);

  if ($type == \Drupal\Core\Language\Language::TYPE_CONTENT && isset($links[$language_interface->id])) {
    foreach ($links[$language_interface->id] as $link) {
      $link['attributes']['class'][] = 'active-language';
    }
  }
}

/**
 * Define language types.
 *
 * @return
 *   An associative array of language type definitions. The keys are the
 *   identifiers, which are also used as names for global variables representing
 *   the types in the bootstrap phase. The values are associative arrays that
 *   may contain the following elements:
 *   - name: The human-readable language type identifier.
 *   - description: A description of the language type.
 *   - locked: A boolean indicating if the user can choose wether to configure
 *     the language type or not using the UI.
 *   - fixed: A fixed array of language negotiation method identifiers to use to
 *     initialize this language. If locked is set to TRUE and fixed is set, it
 *     will always use the specified methods in the given priority order. If not
 *     present and locked is TRUE then LANGUAGE_NEGOTIATION_INTERFACE will be
 *     used.
 *
 *  @todo Rename the 'fixed' key to something more meaningful, for instance
 *     'negotiation settings'.
 *
 * @see hook_language_types_info_alter()
 * @ingroup language_negotiation
 */
function hook_language_types_info() {
  return array(
    'custom_language_type' => array(
      'name' => t('Custom language'),
      'description' => t('A custom language type.'),
      'locked' => FALSE,
    ),
    'fixed_custom_language_type' => array(
      'locked' => TRUE,
      'fixed' => array('custom_language_negotiation_method'),
    ),
  );
}

/**
 * Perform alterations on language types.
 *
 * @param $language_types
 *   Array of language type definitions.
 *
 * @see hook_language_types_info()
 * @ingroup language_negotiation
 */
function hook_language_types_info_alter(array &$language_types) {
  if (isset($language_types['custom_language_type'])) {
    $language_types['custom_language_type_custom']['description'] = t('A far better description.');
  }
}

/**
 * Define language negotiation methods.
 *
 * @return
 *   An associative array of language negotiation method definitions. The keys
 *   are method identifiers, and the values are associative arrays definining
 *   each method, with the following elements:
 *   - types: An array of allowed language types. If a language negotiation
 *     method does not specify which language types it should be used with, it
 *     will be available for all the configurable language types.
 *   - callbacks: An associative array of functions that will be called to
 *     perform various tasks. Possible elements are:
 *     - negotiation: (required) Name of the callback function that determines
 *       the language value.
 *     - language_switch: (optional) Name of the callback function that
 *       determines links for a language switcher block associated with this
 *       method. See language_switcher_url() for an example.
 *     - url_rewrite: (optional) Name of the callback function that provides URL
 *       rewriting, if needed by this method.
 *   - file: The file where callback functions are defined (this file will be
 *     included before the callbacks are invoked).
 *   - weight: The default weight of the method.
 *   - name: The translated human-readable name for the method.
 *   - description: A translated longer description of the method.
 *   - config: An internal path pointing to the method's configuration page.
 *   - cache: The value Drupal's page cache should be set to for the current
 *     method to be invoked.
 *
 * @see hook_language_negotiation_info_alter()
 * @ingroup language_negotiation
 */
function hook_language_negotiation_info() {
  return array(
    'custom_language_negotiation_method' => array(
      'callbacks' => array(
        'negotiation' => 'custom_negotiation_callback',
        'language_switch' => 'custom_language_switch_callback',
        'url_rewrite' => 'custom_url_rewrite_callback',
      ),
      'file' => drupal_get_path('module', 'custom') . '/custom.module',
      'weight' => -4,
      'types' => array('custom_language_type'),
      'name' => t('Custom language negotiation method'),
      'description' => t('This is a custom language negotiation method.'),
      'cache' => 0,
    ),
  );
}

/**
 * Perform alterations on language negotiation methods.
 *
 * @param $negotiation_info
 *   Array of language negotiation method definitions.
 *
 * @see hook_language_negotiation_info()
 * @ingroup language_negotiation
 */
function hook_language_negotiation_info_alter(array &$negotiation_info) {
  if (isset($negotiation_info['custom_language_method'])) {
    $negotiation_info['custom_language_method']['config'] = 'admin/config/regional/language/detection/custom-language-method';
  }
}

/**
 * Perform alterations on the language fallback candidates.
 *
 * @param $fallback_candidates
 *   An array of language codes whose order will determine the language fallback
 *   order.
 */
function hook_language_fallback_candidates_alter(array &$fallback_candidates) {
  $fallback_candidates = array_reverse($fallback_candidates);
}

/**
 * @} End of "addtogroup hooks".
 */

/**
 * @defgroup transliteration Transliteration
 * @{
 * Transliterate from Unicode to US-ASCII
 *
 * Transliteration is the process of translating individual non-US-ASCII
 * characters into ASCII characters, which specifically does not transform
 * non-printable and punctuation characters in any way. This process will always
 * be both inexact and language-dependent. For instance, the character Ö (O with
 * an umlaut) is commonly transliterated as O, but in German text, the
 * convention would be to transliterate it as Oe or OE, depending on the context
 * (beginning of a capitalized word, or in an all-capital letter context).
 *
 * The Drupal default transliteration process transliterates text character by
 * character using a database of generic character transliterations and
 * language-specific overrides. Character context (such as all-capitals
 * vs. initial capital letter only) is not taken into account, and in
 * transliterations of capital letters that result in two or more letters, by
 * convention only the first is capitalized in the Drupal transliteration
 * result. Also, only Unicode characters of 4 bytes or less can be
 * transliterated in the base system; language-specific overrides can be made
 * for longer Unicode characters. So, the process has limitations; however,
 * since the reason for transliteration is typically to create machine names or
 * file names, this should not really be a problem. After transliteration,
 * other transformation or validation may be necessary, such as converting
 * spaces to another character, removing non-printable characters,
 * lower-casing, etc.
 *
 * Here is a code snippet to transliterate some text:
 * @code
 * // Use the current default interface language.
 * $langcode = language(\Drupal\Core\Language\Language::TYPE_INTERFACE)->id;
 * // Instantiate the transliteration class.
 * $trans = drupal_container()->get('transliteration');
 * // Use this to transliterate some text.
 * $transformed = $trans->transliterate($string, $langcode);
 * @endcode
 *
 * Drupal Core provides the generic transliteration character tables and
 * overrides for a few common languages; modules can implement
 * hook_transliteration_overrides_alter() to provide further language-specific
 * overrides (including providing transliteration for Unicode characters that
 * are longer than 4 bytes). Modules can also completely override the
 * transliteration classes in \Drupal\Core\CoreServiceProvider.
 */

/**
 * Provide language-specific overrides for transliteration.
 *
 * If the overrides you want to provide are standard for your language, consider
 * providing a patch for the Drupal Core transliteration system instead of using
 * this hook. This hook can be used temporarily until Drupal Core's
 * transliteration tables are fixed, or for sites that want to use a
 * non-standard transliteration system.
 *
 * @param array $overrides
 *   Associative array of language-specific overrides whose keys are integer
 *   Unicode character codes, and whose values are the transliterations of those
 *   characters in the given language, to override default transliterations.
 * @param string $langcode
 *   The code for the language that is being transliterated.
 *
 * @ingroup hooks
 */
function hook_transliteration_overrides_alter(&$overrides, $langcode) {
  // Provide special overrides for German for a custom site.
  if ($langcode == 'de') {
    // The core-provided transliteration of Ä is Ae, but we want just A.
    $overrides[0xC4] = 'A';
  }
}

/**
 * @} End of "defgroup transliteration".
 */
