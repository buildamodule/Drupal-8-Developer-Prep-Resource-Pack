<?php

/**
 * @file
 * Contains \Drupal\system\Tests\Theme\TwigTransTest.
 */

namespace Drupal\system\Tests\Theme;

use Drupal\simpletest\WebTestBase;

/**
 * Tests Twig "trans" tags.
 */
class TwigTransTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'theme_test',
    'twig_theme_test',
    'locale',
    'language'
  );

  /**
   * An administrative user for testing.
   *
   * @var \Drupal\user\Plugin\Core\Entity\User
   */
  protected $admin_user;

  /**
   * Custom languages.
   *
   * @var array
   */
  protected $languages = array(
    'xx' => 'Lolspeak',
    'zz' => 'Lolspeak2',
  );

  /**
   * Defines information about this test.
   *
   * @return array
   *   An associative array of information.
   */
  public static function getInfo() {
    return array(
      'name' => 'Twig Translation',
      'description' => 'Test Twig "trans" tags.',
      'group' => 'Theme',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Setup test_theme.
    theme_enable(array('test_theme'));
    \Drupal::config('system.theme')->set('default', 'test_theme')->save();

    // Create and log in as admin.
    $this->admin_user = $this->drupalCreateUser(array(
      'administer languages',
      'access administration pages',
      'administer site configuration',
      'translate interface'
    ));
    $this->drupalLogin($this->admin_user);

    // Install languages.
    $this->installLanguages();

    // Assign Lolspeak (xx) to be the default language.
    $edit = array('site_default_language' => 'xx');
    $this->drupalPost('admin/config/regional/settings', $edit, t('Save configuration'));

    // Reset the static cache of the language list.
    drupal_static_reset('language_list');

    // Check that lolspeak is the default language for the site.
    $this->assertEqual(language_default()->id, 'xx', 'Lolspeak is the default language');
  }

  /**
   * Test Twig "trans" tags.
   */
  public function testTwigTransTags() {
    $this->drupalGet('twig-theme-test/trans', array('language' => language_load('xx')));

    $this->assertText(
      'OH HAI SUNZ',
      '{% trans "Hello sun." %} was successfully translated.'
    );

    $this->assertText(
      'O HAI SUNZZZZZZZ',
      '{% trans "Hello sun." with {"context": "Lolspeak"} %} was successfully translated.'
    );

    $this->assertText(
      'O HERRO ERRRF.',
      '{{ "Hello Earth."|trans }} was successfully translated.'
    );

    $this->assertText(
      'OH HAI TEH MUUN',
      '{% trans %}Hello moon.{% endtrans %} was successfully translated.'
    );

    $this->assertText(
      'O HAI STARRRRR',
      '{% trans %} with {% plural count = 1 %} was successfully translated.'
    );

    $this->assertText(
      'O HAI 2 STARZZZZ',
      '{% trans %} with {% plural count = 2 %} was successfully translated.'
    );

    $this->assertRaw(
      'ESCAPEE: &amp;&quot;&lt;&gt;',
      '{{ token }} was successfully translated and prefixed with "@".'
    );

    $this->assertRaw(
      'PAS-THRU: &"<>',
      '{{ token|passthrough }} was successfully translated and prefixed with "!".'
    );

    $this->assertRaw(
      'PLAYSHOLDR: <em class="placeholder">&amp;&quot;&lt;&gt;</em>',
      '{{ token|placeholder }} was successfully translated and prefixed with "%".'
    );

    $this->assertRaw(
      'DIS complex token HAZ LENGTH OV: 3. IT CONTAYNZ: <em class="placeholder">12345</em> AN &amp;&quot;&lt;&gt;. LETS PAS TEH BAD TEXT THRU: &"<>.',
      '{{ complex.tokens }} were successfully translated with appropriate prefixes.'
    );

    $this->assertText(
      'I have context.',
      '{% trans %} with a context only msgid was excluded from translation.'
    );

    $this->assertText(
      'I HAZ KONTEX.',
      '{% trans with {"context": "Lolspeak"} %} was successfully translated with context.'
    );

    $this->assertText(
      'O HAI NU TXT.',
      '{% trans with {"langcode": "zz"} %} was successfully translated in specified language.'
    );

    $this->assertText(
      'O HAI NU TXTZZZZ.',
      '{% trans with {"context": "Lolspeak", "langcode": "zz"} %} was successfully translated with context in specified language.'
    );

    // Ensure debug output does not print.
    $this->checkForDebugMarkup(FALSE);
  }

  /**
   * Test Twig "trans" debug markup.
   */
  public function testTwigTransDebug() {
    // Enable twig debug and write to the test settings.php file.
    $this->settingsSet('twig_debug', TRUE);
    $settings['settings']['twig_debug'] = (object) array(
      'value' => TRUE,
      'required' => TRUE,
    );
    $this->writeSettings($settings);

    // Get page for assertion testing.
    $this->drupalGet('twig-theme-test/trans', array('language' => language_load('xx')));

    // Ensure debug output is printed.
    $this->checkForDebugMarkup(TRUE);
  }

  /**
   * Helper function: test twig debug translation markup.
   *
   * @param bool $visible
   *   Toggle determining which assertion to use for test.
   */
  protected function checkForDebugMarkup($visible) {
    $tests = array(
      '{% trans "Hello sun." %}' => '<!-- TRANSLATION: "Hello sun." -->',
      '{% trans "Hello sun." with {"context": "Lolspeak"} %}' => '<!-- TRANSLATION: "Hello sun.", CONTEXT: "Lolspeak" -->',
      '{{ "Hello moon."|trans }}' => '<!-- TRANSLATION: "Hello moon." -->',
      '{% trans %} with {% plural %}' => '<!-- TRANSLATION: "Hello star.", PLURAL: "Hello @count stars." -->',
      '{{ token }}' => '<!-- TRANSLATION: "Escaped: @string" -->',
      '{{ token|passthrough }}' => '<!-- TRANSLATION: "Pass-through: !string" -->',
      '{{ token|placeholder }}' => '<!-- TRANSLATION: "Placeholder: %string" -->',
      '{{ complex.tokens }}' => '<!-- TRANSLATION: "This @name has a length of: @count. It contains: %numbers and @bad_text. Lets pass the bad text through: !bad_text." -->',
      '{% trans with {"context": "Lolspeak"} %}I have context.{% endtrans %}' => '<!-- TRANSLATION: "I have context.", CONTEXT: "Lolspeak" -->',
      '{% trans with {"langcode": "zz"} %}Hello new text.{% endtrans %}' => '<!-- TRANSLATION: "Hello new text.", LANGCODE: "zz" -->',
      '{% trans with {"context": "Lolspeak", "langcode": "zz"} %}Hello new text.{% endtrans %}' => '<!-- TRANSLATION: "Hello new text.", CONTEXT: "Lolspeak", LANGCODE: "zz" -->',
    );
    foreach ($tests as $test => $markup) {
      if ($visible) {
        $this->assertRaw($markup, "Twig debug translation markup exists in source for: $test");
      }
      else {
        $this->assertNoRaw($markup, "Twig debug translation markup does not exist in source for: $test");
      }
    }
  }

  /**
   * Helper function: install languages.
   */
  protected function installLanguages() {
    foreach ($this->languages as $langcode => $name) {
      // Generate custom .po contents for the language.
      $contents = $this->poFileContents($langcode);
      if ($contents) {
        // Add test language for translation testing.
        $edit = array(
          'predefined_langcode' => 'custom',
          'langcode' => $langcode,
          'name' => $name,
          'direction' => '0',
        );

        // Install the language in Drupal.
        $this->drupalPost('admin/config/regional/language/add', $edit, t('Add custom language'));
        $this->assertRaw('"edit-languages-' . $langcode . '-weight"', 'Language code found.');

        // Import the custom .po contents for the language.
        $filename = tempnam('temporary://', "po_") . '.po';
        file_put_contents($filename, $contents);
        $options = array(
          'files[file]' => $filename,
          'langcode' => $langcode,
          'customized' => TRUE,
        );
        $this->drupalPost('admin/config/regional/translate/import', $options, t('Import'));
        drupal_unlink($filename);
      }
    }
  }

  /**
   * Generate a custom .po file for a specific test language.
   *
   * @param string $langcode
   *   The langcode of the specified language.
   *
   * @return string|FALSE
   *   The .po contents for the specified language or FALSE if none exists.
   */
  protected function poFileContents($langcode) {
    if ($langcode === 'xx') {
      return <<< EOF
msgid ""
msgstr ""
"Project-Id-Version: Drupal 8\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\\n"

msgid "Hello sun."
msgstr "OH HAI SUNZ"

msgctxt "Lolspeak"
msgid "Hello sun."
msgstr "O HAI SUNZZZZZZZ"

msgid "Hello Earth."
msgstr "O HERRO ERRRF."

msgid "Hello moon."
msgstr "OH HAI TEH MUUN"

msgid "Hello star."
msgid_plural "Hello @count stars."
msgstr[0] "O HAI STARRRRR"
msgstr[1] "O HAI @count STARZZZZ"

msgid "Escaped: @string"
msgstr "ESCAPEE: @string"

msgid "Pass-through: !string"
msgstr "PAS-THRU: !string"

msgid "Placeholder: %string"
msgstr "PLAYSHOLDR: %string"

msgid "This @name has a length of: @count. It contains: %numbers and @bad_text. Lets pass the bad text through: !bad_text."
msgstr "DIS @name HAZ LENGTH OV: @count. IT CONTAYNZ: %numbers AN @bad_text. LETS PAS TEH BAD TEXT THRU: !bad_text."

msgctxt "Lolspeak"
msgid "I have context."
msgstr "I HAZ KONTEX."
EOF;
    }
    else if ($langcode === 'zz') {
      return <<< EOF
msgid ""
msgstr ""
"Project-Id-Version: Drupal 8\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\\n"

msgid "Hello new text."
msgstr "O HAI NU TXT."

msgctxt "Lolspeak"
msgid "Hello new text."
msgstr "O HAI NU TXTZZZZ."
EOF;
    }
    return FALSE;
  }

}
