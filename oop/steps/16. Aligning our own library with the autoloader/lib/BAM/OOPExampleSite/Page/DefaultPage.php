<?php

namespace BAM\OOPExampleSite\Page;

use BAM\OOPExampleSite\Page;

class DefaultPage extends Page {

  public function theme() {
    return '
      <html>
        <head>
          <title>' . $this->title . '</title>
        </head>
        <body>
          ' . $this->output . '
        </body>
      </html>';
  }
}