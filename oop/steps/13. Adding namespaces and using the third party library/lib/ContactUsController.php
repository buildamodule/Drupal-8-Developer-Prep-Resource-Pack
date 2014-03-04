<?php

namespace BAM\OOPExampleSite;

require_once __DIR__ . '/DefaultPage.php';
require_once __DIR__ . '/PrintedPage.php';

use BAM\OOPExampleSite\Page\PrintedPage;
use BAM\OOPExampleSite\Page\DefaultPage;

class ContactUsController {
  static public function ContactUsPage($page_elements) {
    if (isset($_GET['print'])) {
      $page = new PrintedPage($page_elements, 'Contact Us');
    } else {
      $page = new DefaultPage($page_elements, 'Contact Us');
    }
    $page->build();
    return $page->theme();
  }
}