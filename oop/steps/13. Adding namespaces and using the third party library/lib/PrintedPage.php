<?php

namespace BAM\OOPExampleSite\Page;

use BAM\OOPExampleSite\Page;

require_once __DIR__ . '/Page.php';

class PrintedPage extends Page {

  public function theme() {
    return '
      <html>
        <head>
          <title>FOR PRINT: ' . $this->title . '</title>
        </head>
        <body>
          <div style="width:800px;border:5px solid black;margin-left:auto;margin-right:auto;padding:20px;">' . $this->output . '</div>
        </body>
      </html>';
  }
}