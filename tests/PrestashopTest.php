<?php

namespace ABM\tests;

use ABM\Wasabi\Prestashop;

/**
 * @covers WS\Wasabi\Combination
 */
class PrestashopTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSubProtocolsReturnsArray()
    {
        $null = new Prestashop();
        $this->assertInternalType('array', $null->getSubProtocols());
    }
}
