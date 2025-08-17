<?php

declare(strict_types = 1);

namespace Drupal\Tests\openid_connect\Unit;

/**
 * Helper traint for openid_connect tests.
 */
trait OpenIDConnectTestHelperTrait {

  /**
   * Handle deprecation of InvocationMocker::withConsecutive.
   */
  private static function consecutive($args): callable {
    $index = 0;
    return function ($arg) use (&$index, $args) {
      $ok = count($args) > $index && $arg === $args[$index];
      $index += 1;
      return $ok;
    };
  }

}
