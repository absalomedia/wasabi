<?php
namespace AB\Tests;
use AB\Wasabi\Cart;
/**
 * @covers WS\Wasabi\Cart
 */
class CartTest extends \PHPUnit_Framework_TestCase {
    public function testGetSubProtocolsReturnsArray() {
        $null = new Cart;
        $this->assertInternalType('array', $null->getSubProtocols());
    }
}