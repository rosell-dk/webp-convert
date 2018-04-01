<?php

/**
 * WebPConvert - dfasf
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests;

use WebPConvert\WebPConvert;
use PHPUnit\Framework\TestCase;

class WebPConvertTest extends TestCase
{
    /**
     * @expectedException \Exception
     */
    public function testIsValidTargetInvalid()
    {
        WebPConvert::isValidTarget('/Invalid/Path/To/File.php');
    }

    public function testIsValidTargetValid()
    {
        $this->assertTrue(WebPConvert::isValidTarget(__DIR__ . '/WebPConvertTest.php'));
    }
}

