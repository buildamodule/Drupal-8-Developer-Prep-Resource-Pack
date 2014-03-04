<?php

namespace BAM\OOPExampleSite;

require_once __DIR__ . '/Form.php';

class Builder {
  
  public $settings = array();
  public $output = '';

  public function render($settings) {
    $this->settings = $settings;
    foreach ($this->settings as $id => $values) {
      switch ($values['type']) {
        case 'html':
          $this->output .= '<div id="' . $id . '">' . $values['value'] . '</div>';
          break;
        case 'form':
          $form = new Form($values['value']);
          $this->output .= $form->build();
          break;
      }
    }
    return $this->output;
  }
}