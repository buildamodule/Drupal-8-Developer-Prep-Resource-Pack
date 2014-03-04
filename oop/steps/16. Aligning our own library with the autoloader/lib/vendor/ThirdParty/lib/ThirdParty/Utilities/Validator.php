<?php

namespace ThirdParty\Utilities;

class Validator {
  
  static public function notEmpty($value) {
    if (trim($value) == '') {
      return false;
    }
    return true;
  }
  
  static public function isValidEmail($value) {
    if (!strstr($value, '@') || !strstr($value, '.')) {
      return false;
    }
    return true;
  }
}