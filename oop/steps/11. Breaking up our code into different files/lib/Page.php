<?php

abstract class Page {
    
  public $settings;
  public $title;
  public $output;
  
  public function __construct($settings, $title) {
    $this->settings = $settings;
    $this->title = $title;
  }
  
  public function build() {
    $builder = new Builder();
    $this->output = $builder->render($this->settings);
  }
  
  abstract function theme();

}