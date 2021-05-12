<?php

namespace WebPConvert\Tests\Helpers;

use WebPConvert\Helpers\Sanitize;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Helpers\Sanitize
 * @covers WebPConvert\Helpers\Sanitize
 */
class SanitizeTest extends TestCase
{

    /**
     * @covers ::removeNUL
     */
    public function testRemoveNUL()
    {
        $this->assertEquals(
            'a',
            Sanitize::removeNUL("a\0")
        );
    }

    /**
     * @covers ::removeStreamWrappers
     */
    public function testRemoveStreamWrappers()
    {
        $this->assertEquals(
            'dytdyt',
            Sanitize::removeStreamWrappers("phar://dytdyt")
        );
    }

}
