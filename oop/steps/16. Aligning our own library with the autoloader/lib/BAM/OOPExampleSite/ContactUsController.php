<?php

namespace BAM\OOPExampleSite;

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