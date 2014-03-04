<?php

require_once __DIR__ . '/lib/Builder.php';
require_once __DIR__ . '/lib/ContactUsController.php';

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