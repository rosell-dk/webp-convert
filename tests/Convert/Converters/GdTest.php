<?php
namespace WebPConvert\Tests\Convert\Converters;


use WebPConvert\Tests\Convert\Exposers\GdExposer;
use WebPConvert\Convert\Converters\Gd;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

use PHPUnit\Framework\TestCase;

class GdTest extends TestCase
{


      public static function getImageFolder()
      {
          return realpath(__DIR__ . '/../../images');
      }

      public static function getImagePath($image)
      {
          return self::getImageFolder() . '/' . $image;
      }

      public function testConvert()
      {
          ConverterTestHelper::runAllConvertTests($this, 'Gd');
      }

}

require_once('pretend.inc');
