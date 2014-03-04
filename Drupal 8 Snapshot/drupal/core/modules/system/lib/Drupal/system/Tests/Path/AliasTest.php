<?php

/**
 * @file
 * Contains Drupal\system\Tests\Path\AliasTest.
 */

namespace Drupal\system\Tests\Path;

use Drupal\Core\Path\Path;
use Drupal\Core\Database\Database;
use Drupal\Core\Path\AliasManager;
use Drupal\Core\Path\AliasWhitelist;

/**
 * Tests path alias CRUD and lookup functionality.
 */
class AliasTest extends PathUnitTestBase {

  public static function getInfo() {
    return array(
      'name' => t('Path Alias Unit Tests'),
      'description' => t('Tests path alias CRUD and lookup functionality.'),
      'group' => t('Path API'),
    );
  }

  function testCRUD() {
    //Prepare database table.
    $connection = Database::getConnection();
    $this->fixtures->createTables($connection);

    //Create AliasManager and Path object.
    $whitelist = new AliasWhitelist('path_alias_whitelist', $this->container->get('cache.cache'), $this->container->get('lock'), $this->container->get('state'), $connection);
    $aliasManager = new AliasManager($connection, $whitelist, $this->container->get('language_manager'));
    $path = new Path($connection, $aliasManager);

    $aliases = $this->fixtures->sampleUrlAliases();

    //Create a few aliases
    foreach ($aliases as $idx => $alias) {
      $path->save($alias['source'], $alias['alias'], $alias['langcode']);

      $result = $connection->query('SELECT * FROM {url_alias} WHERE source = :source AND alias= :alias AND langcode = :langcode', array(':source' => $alias['source'], ':alias' => $alias['alias'], ':langcode' => $alias['langcode']));
      $rows = $result->fetchAll();

      $this->assertEqual(count($rows), 1, format_string('Created an entry for %alias.', array('%alias' => $alias['alias'])));

      //Cache the pid for further tests.
      $aliases[$idx]['pid'] = $rows[0]->pid;
    }

    //Load a few aliases
    foreach ($aliases as $alias) {
      $pid = $alias['pid'];
      $loadedAlias = $path->load(array('pid' => $pid));
      $this->assertEqual($loadedAlias, $alias, format_string('Loaded the expected path with pid %pid.', array('%pid' => $pid)));
    }

    //Update a few aliases
    foreach ($aliases as $alias) {
      $path->save($alias['source'], $alias['alias'] . '_updated', $alias['langcode'], $alias['pid']);

      $result = $connection->query('SELECT pid FROM {url_alias} WHERE source = :source AND alias= :alias AND langcode = :langcode', array(':source' => $alias['source'], ':alias' => $alias['alias'] . '_updated', ':langcode' => $alias['langcode']));
      $pid = $result->fetchField();

      $this->assertEqual($pid, $alias['pid'], format_string('Updated entry for pid %pid.', array('%pid' => $pid)));
    }

    //Delete a few aliases
    foreach ($aliases as $alias) {
      $pid = $alias['pid'];
      $path->delete(array('pid' => $pid));

      $result = $connection->query('SELECT * FROM {url_alias} WHERE pid = :pid', array(':pid' => $pid));
      $rows = $result->fetchAll();

      $this->assertEqual(count($rows), 0, format_string('Deleted entry with pid %pid.', array('%pid' => $pid)));
    }
  }

  function testLookupPath() {
    //Prepare database table.
    $connection = Database::getConnection();
    $this->fixtures->createTables($connection);

    //Create AliasManager and Path object.
    $whitelist = new AliasWhitelist('path_alias_whitelist', $this->container->get('cache.cache'), $this->container->get('lock'), $this->container->get('state'), $connection);
    $aliasManager = new AliasManager($connection, $whitelist, $this->container->get('language_manager'));
    $pathObject = new Path($connection, $aliasManager);

    // Test the situation where the source is the same for multiple aliases.
    // Start with a language-neutral alias, which we will override.
    $path = array(
      'source' => "user/1",
      'alias' => 'foo',
    );

    $pathObject->save($path['source'], $path['alias']);
    $this->assertEqual($aliasManager->getPathAlias($path['source']), $path['alias'], 'Basic alias lookup works.');
    $this->assertEqual($aliasManager->getSystemPath($path['alias']), $path['source'], 'Basic source lookup works.');

    // Create a language specific alias for the default language (English).
    $path = array(
      'source' => "user/1",
      'alias' => "users/Dries",
      'langcode' => 'en',
    );
    $pathObject->save($path['source'], $path['alias'], $path['langcode']);
    $this->assertEqual($aliasManager->getPathAlias($path['source']), $path['alias'], 'English alias overrides language-neutral alias.');
    $this->assertEqual($aliasManager->getSystemPath($path['alias']), $path['source'], 'English source overrides language-neutral source.');

    // Create a language-neutral alias for the same path, again.
    $path = array(
      'source' => "user/1",
      'alias' => 'bar',
    );
    $pathObject->save($path['source'], $path['alias']);
    $this->assertEqual($aliasManager->getPathAlias($path['source']), "users/Dries", 'English alias still returned after entering a language-neutral alias.');

    // Create a language-specific (xx-lolspeak) alias for the same path.
    $path = array(
      'source' => "user/1",
      'alias' => 'LOL',
      'langcode' => 'xx-lolspeak',
    );
    $pathObject->save($path['source'], $path['alias'], $path['langcode']);
    $this->assertEqual($aliasManager->getPathAlias($path['source']), "users/Dries", 'English alias still returned after entering a LOLspeak alias.');
    // The LOLspeak alias should be returned if we really want LOLspeak.
    $this->assertEqual($aliasManager->getPathAlias($path['source'], 'xx-lolspeak'), 'LOL', 'LOLspeak alias returned if we specify xx-lolspeak to the alias manager.');

    // Create a new alias for this path in English, which should override the
    // previous alias for "user/1".
    $path = array(
      'source' => "user/1",
      'alias' => 'users/my-new-path',
      'langcode' => 'en',
    );
    $pathObject->save($path['source'], $path['alias'], $path['langcode']);
    $this->assertEqual($aliasManager->getPathAlias($path['source']), $path['alias'], 'Recently created English alias returned.');
    $this->assertEqual($aliasManager->getSystemPath($path['alias']), $path['source'], 'Recently created English source returned.');

    // Remove the English aliases, which should cause a fallback to the most
    // recently created language-neutral alias, 'bar'.
    $pathObject->delete(array('langcode' => 'en'));
    $this->assertEqual($aliasManager->getPathAlias($path['source']), 'bar', 'Path lookup falls back to recently created language-neutral alias.');

    // Test the situation where the alias and language are the same, but
    // the source differs. The newer alias record should be returned.
    $pathObject->save('user/2', 'bar');
    $this->assertEqual($aliasManager->getSystemPath('bar'), 'user/2', 'Newer alias record is returned when comparing two Language::LANGCODE_NOT_SPECIFIED paths with the same alias.');
  }

  /**
   * Tests the alias whitelist.
   */
  function testWhitelist() {
    // Prepare database table.
    $connection = Database::getConnection();
    $this->fixtures->createTables($connection);
    // Create AliasManager and Path object.
    $whitelist = new AliasWhitelist('path_alias_whitelist', $this->container->get('cache.cache'), $this->container->get('lock'), $this->container->get('state'), $connection);
    $aliasManager = new AliasManager($connection, $whitelist, $this->container->get('language_manager'));
    $path = new Path($connection, $aliasManager);

    // No alias for user and admin yet, so should be NULL.
    $this->assertNull($whitelist->get('user'));
    $this->assertNull($whitelist->get('admin'));

    // Non-existing path roots should be NULL too. Use a length of 7 to avoid
    // possible conflict with random aliases below.
    $this->assertNull($whitelist->get($this->randomName()));

    // Add an alias for user/1, user should get whitelisted now.
    $path->save('user/1', $this->randomName());
    $this->assertTrue($whitelist->get('user'));
    $this->assertNull($whitelist->get('admin'));
    $this->assertNull($whitelist->get($this->randomName()));

    // Add an alias for admin, both should get whitelisted now.
    $path->save('admin/something', $this->randomName());
    $this->assertTrue($whitelist->get('user'));
    $this->assertTrue($whitelist->get('admin'));
    $this->assertNull($whitelist->get($this->randomName()));

    // Remove the user alias again, whitelist entry should be removed.
    $path->delete(array('source' => 'user/1'));
    $this->assertNull($whitelist->get('user'));
    $this->assertTrue($whitelist->get('admin'));
    $this->assertNull($whitelist->get($this->randomName()));

  }
}
