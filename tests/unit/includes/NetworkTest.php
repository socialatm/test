<?php
/**
 * tests function from include/network.php
 *
 * @package test.util
 */

use PHPUnit\Framework\TestCase;

require_once('include/network.php');

class NetworkTest extends TestCase {

  public function setup() : void {
    \App::set_baseurl("https://mytest.org");
  }

  /**
   * @dataProvider localUrlTestProvider
   */
  public function testIsLocalURL($url, $expected) {
    $this->assertEquals($expected, is_local_url($url));
  }

  public function localUrlTestProvider() : array {
    return [
      [ '/some/path', true ],
      [ 'https://mytest.org/some/path', true ],
      [ 'https://other.site/some/path', false ],
    ];
  }
}

