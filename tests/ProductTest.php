<?php
namespace AB\Tests;
use AB\Wasabi\Product;
/**
 * @covers WS\Wasabi\Product
 */
class ProductTest extends \PHPUnit_Framework_TestCase {
    public function testGetSubProtocolsReturnsArray() {
        $null = new Product;
        $this->assertInternalType('array', $null->getSubProtocols());
 }
}
  