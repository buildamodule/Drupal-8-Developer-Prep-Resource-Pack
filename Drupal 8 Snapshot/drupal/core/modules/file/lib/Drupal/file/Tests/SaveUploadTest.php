<?php

/**
 * @file
 * Definition of Drupal\file\Tests\SaveUploadTest.
 */

namespace Drupal\file\Tests;

/**
 * Test the file_save_upload() function.
 */
class SaveUploadTest extends FileManagedTestBase {
  /**
   * An image file path for uploading.
   */
  protected $image;

  /**
   * A PHP file path for upload security testing.
   */
  protected $phpfile;

  /**
   * The largest file id when the test starts.
   */
  protected $maxFidBefore;

  public static function getInfo() {
    return array(
      'name' => 'File uploading',
      'description' => 'Tests the file uploading functions.',
      'group' => 'File API',
    );
  }

  function setUp() {
    parent::setUp();
    $account = $this->drupalCreateUser(array('access content'));
    $this->drupalLogin($account);

    $image_files = $this->drupalGetTestFiles('image');
    $this->image = entity_create('file', (array) current($image_files));

    list(, $this->image_extension) = explode('.', $this->image->getFilename());
    $this->assertTrue(is_file($this->image->getFileUri()), "The image file we're going to upload exists.");

    $this->phpfile = current($this->drupalGetTestFiles('php'));
    $this->assertTrue(is_file($this->phpfile->uri), 'The PHP file we are going to upload exists.');

    $this->maxFidBefore = db_query('SELECT MAX(fid) AS fid FROM {file_managed}')->fetchField();

    // Upload with replace to guarantee there's something there.
    $edit = array(
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri()),
    );
    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called then clean out the hook
    // counters.
    $this->assertFileHooksCalled(array('validate', 'insert'));
    file_test_reset();
  }

  /**
   * Test the file_save_upload() function.
   */
  function testNormal() {
    $max_fid_after = db_query('SELECT MAX(fid) AS fid FROM {file_managed}')->fetchField();
    $this->assertTrue($max_fid_after > $this->maxFidBefore, 'A new file was created.');
    $file1 = file_load($max_fid_after);
    $this->assertTrue($file1, 'Loaded the file.');
    // MIME type of the uploaded image may be either image/jpeg or image/png.
    $this->assertEqual(substr($file1->getMimeType(), 0, 5), 'image', 'A MIME type was set.');

    // Reset the hook counters to get rid of the 'load' we just called.
    file_test_reset();

    // Upload a second file.
    $max_fid_before = db_query('SELECT MAX(fid) AS fid FROM {file_managed}')->fetchField();
    $image2 = current($this->drupalGetTestFiles('image'));
    $edit = array('files[file_test_upload]' => drupal_realpath($image2->uri));
    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'));
    $max_fid_after = db_query('SELECT MAX(fid) AS fid FROM {file_managed}')->fetchField();

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'insert'));

    $file2 = file_load($max_fid_after);
    $this->assertTrue($file2, 'Loaded the file');
    // MIME type of the uploaded image may be either image/jpeg or image/png.
    $this->assertEqual(substr($file2->getMimeType(), 0, 5), 'image', 'A MIME type was set.');

    // Load both files using file_load_multiple().
    $files = file_load_multiple(array($file1->id(), $file2->id()));
    $this->assertTrue(isset($files[$file1->id()]), 'File was loaded successfully');
    $this->assertTrue(isset($files[$file2->id()]), 'File was loaded successfully');

    // Upload a third file to a subdirectory.
    $image3 = current($this->drupalGetTestFiles('image'));
    $image3_realpath = drupal_realpath($image3->uri);
    $dir = $this->randomName();
    $edit = array(
      'files[file_test_upload]' => $image3_realpath,
      'file_subdir' => $dir,
    );
    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'));
    $this->assertTrue(is_file('temporary://' . $dir . '/' . trim(drupal_basename($image3_realpath))));
  }

  /**
   * Test extension handling.
   */
  function testHandleExtension() {
    // The file being tested is a .gif which is in the default safe list
    // of extensions to allow when the extension validator isn't used. This is
    // implicitly tested at the testNormal() test. Here we tell
    // file_save_upload() to only allow ".foo".
    $extensions = 'foo';
    $edit = array(
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri()),
      'extensions' => $extensions,
    );

    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $message = t('Only files with the following extensions are allowed:') . ' <em class="placeholder">' . $extensions . '</em>';
    $this->assertRaw($message, 'Cannot upload a disallowed extension');
    $this->assertRaw(t('Epic upload FAIL!'), 'Found the failure message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate'));

    // Reset the hook counters.
    file_test_reset();

    $extensions = 'foo ' . $this->image_extension;
    // Now tell file_save_upload() to allow the extension of our test image.
    $edit = array(
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri()),
      'extensions' => $extensions,
    );

    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertNoRaw(t('Only files with the following extensions are allowed:'), 'Can upload an allowed extension.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'load', 'update'));

    // Reset the hook counters.
    file_test_reset();

    // Now tell file_save_upload() to allow any extension.
    $edit = array(
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri()),
      'allow_all_extensions' => TRUE,
    );
    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertNoRaw(t('Only files with the following extensions are allowed:'), 'Can upload any extension.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'load', 'update'));
  }

  /**
   * Test dangerous file handling.
   */
  function testHandleDangerousFile() {
    $config = \Drupal::config('system.file');
    // Allow the .php extension and make sure it gets renamed to .txt for
    // safety. Also check to make sure its MIME type was changed.
    $edit = array(
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload]' => drupal_realpath($this->phpfile->uri),
      'is_image_file' => FALSE,
      'extensions' => 'php',
    );

    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $message = t('For security reasons, your upload has been renamed to') . ' <em class="placeholder">' . $this->phpfile->filename . '.txt' . '</em>';
    $this->assertRaw($message, 'Dangerous file was renamed.');
    $this->assertRaw(t('File MIME type is text/plain.'), "Dangerous file's MIME type was changed.");
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'insert'));

    // Ensure dangerous files are not renamed when insecure uploads is TRUE.
    // Turn on insecure uploads.
    $config->set('allow_insecure_uploads', 1)->save();
    // Reset the hook counters.
    file_test_reset();

    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertNoRaw(t('For security reasons, your upload has been renamed'), 'Found no security message.');
    $this->assertRaw(t('File name is !filename', array('!filename' => $this->phpfile->filename)), 'Dangerous file was not renamed when insecure uploads is TRUE.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'insert'));

    // Turn off insecure uploads.
    $config->set('allow_insecure_uploads', 0)->save();
  }

  /**
   * Test file munge handling.
   */
  function testHandleFileMunge() {
    // Ensure insecure uploads are disabled for this test.
    \Drupal::config('system.file')->set('allow_insecure_uploads', 0)->save();
    $this->image = file_move($this->image, $this->image->getFileUri() . '.foo.' . $this->image_extension);

    // Reset the hook counters to get rid of the 'move' we just called.
    file_test_reset();

    $extensions = $this->image_extension;
    $edit = array(
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri()),
      'extensions' => $extensions,
    );

    $munged_filename = $this->image->getFilename();
    $munged_filename = substr($munged_filename, 0, strrpos($munged_filename, '.'));
    $munged_filename .= '_.' . $this->image_extension;

    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('For security reasons, your upload has been renamed'), 'Found security message.');
    $this->assertRaw(t('File name is !filename', array('!filename' => $munged_filename)), 'File was successfully munged.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'insert'));

    // Ensure we don't munge files if we're allowing any extension.
    // Reset the hook counters.
    file_test_reset();

    $edit = array(
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri()),
      'allow_all_extensions' => TRUE,
    );

    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertNoRaw(t('For security reasons, your upload has been renamed'), 'Found no security message.');
    $this->assertRaw(t('File name is !filename', array('!filename' => $this->image->getFilename())), 'File was not munged when allowing any extension.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'insert'));
  }

  /**
   * Test renaming when uploading over a file that already exists.
   */
  function testExistingRename() {
    $edit = array(
      'file_test_replace' => FILE_EXISTS_RENAME,
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri())
    );
    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'insert'));
  }

  /**
   * Test replacement when uploading over a file that already exists.
   */
  function testExistingReplace() {
    $edit = array(
      'file_test_replace' => FILE_EXISTS_REPLACE,
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri())
    );
    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('You WIN!'), 'Found the success message.');

    // Check that the correct hooks were called.
    $this->assertFileHooksCalled(array('validate', 'load', 'update'));
  }

  /**
   * Test for failure when uploading over a file that already exists.
   */
  function testExistingError() {
    $edit = array(
      'file_test_replace' => FILE_EXISTS_ERROR,
      'files[file_test_upload]' => drupal_realpath($this->image->getFileUri())
    );
    $this->drupalPost('file-test/upload', $edit, t('Submit'));
    $this->assertResponse(200, 'Received a 200 response for posted test file.');
    $this->assertRaw(t('Epic upload FAIL!'), 'Found the failure message.');

    // Check that the no hooks were called while failing.
    $this->assertFileHooksCalled(array());
  }

  /**
   * Test for no failures when not uploading a file.
   */
  function testNoUpload() {
    $this->drupalPost('file-test/upload', array(), t('Submit'));
    $this->assertNoRaw(t('Epic upload FAIL!'), 'Failure message not found.');
  }
}
