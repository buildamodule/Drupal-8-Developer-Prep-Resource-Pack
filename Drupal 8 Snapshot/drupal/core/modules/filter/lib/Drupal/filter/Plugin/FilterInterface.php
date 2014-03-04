<?php

/**
 * @file
 * Contains \Drupal\filter\Plugin\Filter\FilterInterface.
 */

namespace Drupal\filter\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;

/**
 * Defines the interface for text processing filter plugins.
 *
 * User submitted content is passed through a group of filters before it is
 * output in HTML, in order to remove insecure or unwanted parts, correct or
 * enhance the formatting, transform special keywords, etc. A group of filters
 * is referred to as a "text format". Administrators can create as many text
 * formats as needed. Individual filters can be enabled and configured
 * differently for each text format.
 *
 * @see \Drupal\filter\Plugin\Core\Entity\FilterFormat
 *
 * Filtering is a two-step process. First, the content is 'prepared' by calling
 * the FilterInterface::prepare() method for every filter. The purpose is to
 * escape HTML-like structures. For example, imagine a filter which allows the
 * user to paste entire chunks of programming code without requiring manual
 * escaping of special HTML characters like < or &. If the programming code were
 * left untouched, then other filters could think it was HTML and change it. For
 * many filters, the prepare step is not necessary.
 *
 * The second step is the actual processing step. The result from passing the
 * text through all the filters' prepare steps gets passed to all the filters
 * again, this time to the FilterInterface::process() method. The process method
 * should then actually change the content: transform URLs into hyperlinks,
 * convert smileys into images, etc.
 *
 * For performance reasons, content is only filtered once; the result is stored
 * in the cache table and retrieved from the cache the next time the same piece
 * of content is displayed. If a filter's output is dynamic, it can override
 * the cache mechanism, but obviously this should be used with caution: having
 * one filter that does not support caching in a collection of filters disables
 * caching for the entire collection, not just for one filter.
 *
 * Beware of the filter cache when developing your module: it is advised to set
 * your filter to 'cache' to FALSE while developing, but be sure to remove that
 * setting if it's not needed, when you are no longer in development mode.
 *
 * @see check_markup()
 *
 * Filters are discovered through annotations, which may contain the following
 * definition properties:
 * - title: (required) An administrative summary of what the filter does.
 *   - type: (required) A classification of the filter's purpose. This is one
 *     of the following:
 *     - FILTER_TYPE_HTML_RESTRICTOR: HTML tag and attribute restricting
 *       filters.
 *     - FILTER_TYPE_MARKUP_LANGUAGE: Non-HTML markup language filters that
 *       generate HTML.
 *     - FILTER_TYPE_TRANSFORM_IRREVERSIBLE: Irreversible transformation
 *       filters.
 *     - FILTER_TYPE_TRANSFORM_REVERSIBLE: Reversible transformation filters.
 * - description: Additional administrative information about the filter's
 *   behavior, if needed for clarification.
 * - status: The default status for new instances of the filter. Defaults to
 *   FALSE.
 * - weight: A default weight for new instances of the filter. Defaults to 0.
 * - cache: Whether the filtered text can be cached. Defaults to TRUE.
 *   Note that setting this to FALSE disables caching for an entire text format,
 *   which can have a negative impact on the site's overall performance.
 * - settings: An associative array containing default settings for new
 *   instances of the filter.
 *
 * Most implementations want to extend the generic basic implementation for
 * filter plugins.
 *
 * @see \Drupal\filter\Plugin\Filter\FilterBase
 */
interface FilterInterface extends ConfigurablePluginInterface, PluginInspectionInterface {

  /**
   * Returns the processing type of this filter plugin.
   *
   * @return int
   *   One of:
   *   - FILTER_TYPE_MARKUP_LANGUAGE
   *   - FILTER_TYPE_HTML_RESTRICTOR
   *   - FILTER_TYPE_TRANSFORM_REVERSIBLE
   *   - FILTER_TYPE_TRANSFORM_IRREVERSIBLE
   */
  public function getType();

  /**
   * Returns the administrative label for this filter plugin.
   *
   * @return string
   */
  public function getLabel();

  /**
   * Returns the administrative description for this filter plugin.
   *
   * @return string
   */
  public function getDescription();

  /**
   * Generates a filter's settings form.
   *
   * @param array $form
   *   A minimally prepopulated form array.
   * @param array $form_state
   *   The state of the (entire) configuration form.
   *
   * @return array
   *   The $form array with additional form elements for the settings of this
   *   filter. The submitted form values should match $this->settings.
   */
  public function settingsForm(array $form, array &$form_state);

  /**
   * Prepares the text for processing.
   *
   * Filters should not use the prepare method for anything other than escaping,
   * because that would short-circuit the control the user has over the order in
   * which filters are applied.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $langcode
   *   The language code of the text to be filtered.
   * @param bool $cache
   *   A Boolean indicating whether the filtered text is going to be cached in
   *   {cache_filter}.
   * @param string $cache_id
   *   The ID of the filtered text in {cache_filter}, if $cache is TRUE.
   *
   * @return string
   *   The prepared, escaped text.
   */
  public function prepare($text, $langcode, $cache, $cache_id);

  /**
   * Performs the filter processing.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $langcode
   *   The language code of the text to be filtered.
   * @param bool $cache
   *   A Boolean indicating whether the filtered text is going to be cached in
   *   {cache_filter}.
   * @param string $cache_id
   *   The ID of the filtered text in {cache_filter}, if $cache is TRUE.
   *
   * @return string
   *   The filtered text.
   */
  public function process($text, $langcode, $cache, $cache_id);

  /**
   * Returns HTML allowed by this filter's configuration.
   *
   * May be implemented by filters of the type FILTER_TYPE_HTML_RESTRICTOR, this
   * won't be used for filters of other types; they should just return FALSE.
   *
   * This callback function is only necessary for filters that strip away HTML
   * tags (and possibly attributes) and allows other modules to gain insight in
   * a generic manner into which HTML tags and attributes are allowed by a
   * format.
   *
   * @return array|FALSE
   *   A nested array with *either* of the following keys:
   *     - 'allowed': (optional) the allowed tags as keys, and for each of those
   *       tags (keys) either of the following values:
   *       - TRUE to indicate any attribute is allowed
   *       - FALSE to indicate no attributes are allowed
   *       - an array to convey attribute restrictions: the keys must be
   *         attribute names (which may use a wildcard, e.g. "data-*"), the
   *         possible values are similar to the above:
   *           - TRUE to indicate any attribute value is allowed
   *           - FALSE to indicate the attribute is forbidden
   *           - an array to convey attribute value restrictions: the key must
   *             be attribute values (which may use a wildcard, e.g. "xsd:*"),
   *             the possible values are TRUE or FALSE: to mark the attribute
   *             value as allowed or forbidden, respectively
   *     -  'forbidden_tags': (optional) the forbidden tags
   *
   *   There is one special case: the "wildcard tag", "*": any attribute
   *   restrictions on that pseudotag apply to all tags.
   *
   *   If no restrictions apply, then FALSE must be returned.
   *
   *   Here is a concrete example, for a very granular filter:
   *     @code
   *     array(
   *       'allowed' => array(
   *         // Allows any attribute with any value on the <div> tag.
   *         'div' => TRUE,
   *         // Allows no attributes on the <p> tag.
   *         'p' => FALSE,
   *         // Allows the following attributes on the <a> tag:
   *         //  - 'href', with any value;
   *         //  - 'rel', with the value 'nofollow' value.
   *         'a' => array(
   *           'href' => TRUE,
   *           'rel' => array('nofollow' => TRUE),
   *         ),
   *         // Only allows the 'src' and 'alt' attributes on the <alt> tag,
   *         // with any value.
   *         'img' => array(
   *           'src' => TRUE,
   *           'alt' => TRUE,
   *         ),
   *         // Allow RDFa on <span> tags, using only the dc, foaf, xsd and sioc
   *         // vocabularies/namespaces.
   *         'span' => array(
   *           'property' => array('dc:*' => TRUE, 'foaf:*' => TRUE),
   *           'datatype' => array('xsd:*' => TRUE),
   *           'rel' => array('sioc:*' => TRUE),
   *         ),
   *         // Forbid the 'style' and 'on*' ('onClick' etc.) attributes on any
   *         // tag.
   *         '*' => array(
   *           'style' => FALSE,
   *           'on*' => FALSE,
   *         ),
   *       )
   *     )
   *     @endcode
   *
   *   A simpler example, for a very coarse filter:
   *     @code
   *     array(
   *       'forbidden_tags' => array('iframe', 'script')
   *     )
   *     @endcode
   *
   *   The simplest example possible: a filter that doesn't allow any HTML:
   *     @code
   *     array(
   *       'allowed' => array()
   *     )
   *     @endcode
   *
   *   And for a filter that applies no restrictions, i.e. allows any HTML:
   *     @code
   *     FALSE
   *     @endcode
   *
   * @see filter_get_html_restrictions_by_format()
   */
  public function getHTMLRestrictions();

  /**
   * Generates a filter's tip.
   *
   * A filter's tips should be informative and to the point. Short tips are
   * preferably one-liners.
   *
   * @param bool $long
   *   Whether this callback should return a short tip to display in a form
   *   (FALSE), or whether a more elaborate filter tips should be returned for
   *   theme_filter_tips() (TRUE).
   *
   * @return string|null
   *   Translated text to display as a tip, or NULL if this filter has no tip.
   *
   * @todo Split into getSummaryItem() and buildGuidelines().
   */
  public function tips($long = FALSE);

}
