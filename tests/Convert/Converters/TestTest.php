<?php
namespace WebPConvert\Tests\Convert\Converters;
use WebPConvert\Tests\Convert\Exposers\GdExposer;
use WebPConvert\Convert\Converters\Gd;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

use PHPUnit\Framework\TestCase;

class TestTest extends TestCase
{

    public function testTesting()
    {
        $this->assertEquals(
            '1',
            '1'
        );
    }

}

require_once('pretend.inc');
