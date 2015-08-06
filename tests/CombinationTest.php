<?php
namespace WS\Tests;
use WS\Wasabi\Combination;
/**
 * @covers WS\Wasabi\Combination
 */
class CombinationTest extends \PHPUnit_Framework_TestCase {
    public function testGetSubProtocolsReturnsArray() {
        $null = new Combination;
        $this->assertInternalType('array', $null->getSubProtocols());
    }
}