<?php

function my_autoloader($namespace)
{
  // Set up a recursive directory iterator.
  $directories = new RecursiveIteratorIterator(
    new ParentIterator(new RecursiveDirectoryIterator(__DIR__)), 
    RecursiveIteratorIterator::SELF_FIRST);
  
  // Loop through directories, looking for 'lib' folders.
  foreach ($directories as $directory) {
    if ($directory->getFilename() == 'lib') {
      $prefixes[] = $directory->getPath() . '/lib/';
    }
  }
  
  // Check each lib folder for the existence of the file.
  foreach ($prefixes as $prefix) {
    $filename = $prefix . str_replace("\\", "/", $namespace) . '.php';
    if (file_exists($filename)) {
      include $filename;
      return;
    }
  }
}

spl_autoload_register('my_autoloader');

use BAM\OOPExampleSite\Builder;
use BAM\OOPExampleSite\ContactUsController;


// Instantiate a Builder object to use below.
$builder = new Builder();

// Create an array for the contact form.
$contact_form = array(
  'name' => array(
    'title' => 'Name',
    'type' => 'text',
    'validations' => array('not_empty'),
  ),
  'email' => array(
    'title' => 'Email',
    'type' => 'email',
    'validations' => array('not_empty', 'is_valid_email'),
  ),
  'comment' => array(
    'title' => 'Comments',
    'type' => 'textarea',
    'validations' => array('not_empty'),
  ),
  'submit' => array(
    'title' => 'Submit me!',
    'type' => 'submit',
  ),
);

// Create an array for the footer content and render it.
$footer_content = array(
  'divider' => array(
    'type' => 'html',
    'value' => '<hr />',
  ),
  'content' => array(
    'type' => 'html',
    'value' => '<div style="text-align:center">&copy; ' . date('Y') . ' BuildAModule</div>',
  ),
);
$footer = $builder->render($footer_content);

// Create an array for the page.
$page_elements = array(
  'header' => array(
    'type' => 'html',
    'value' => '<p>Please submit this form. You will make my day if you do.</p>',
  ),
  'contact_form' => array(
    'type' => 'form',
    'value' => $contact_form,
  ),
  'footer' => array(
    'type' => 'html',
    'value' => $footer,
  ),
);

print ContactUsController::ContactUsPage($page_elements);