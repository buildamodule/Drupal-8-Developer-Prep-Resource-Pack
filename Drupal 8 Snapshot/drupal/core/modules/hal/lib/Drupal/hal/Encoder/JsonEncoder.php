<?php

/**
 * @file
 * Contains \Drupal\hal\JsonEncoder.
 */

namespace Drupal\hal\Encoder;

use Symfony\Component\Serializer\Encoder\JsonEncoder as SymfonyJsonEncoder;

/**
 * Encodes HAL data in JSON.
 *
 * Simply respond to application/hal+json requests using the JSON encoder.
 */
class JsonEncoder extends SymfonyJsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected $format = 'hal_json';

  /**
   * Overrides \Symfony\Component\Serializer\Encoder\JsonEncoder::supportsEncoding()
   */
  public function supportsEncoding($format) {
    return $format == $this->format;
  }

  /**
   * Overrides \Symfony\Component\Serializer\Encoder\JsonEncoder::supportsDecoding()
   */
  public function supportsDecoding($format) {
    return $format == $this->format;
  }

}
