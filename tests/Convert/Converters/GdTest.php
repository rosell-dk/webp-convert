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

      private function createGd($src)
      {
          $source = self::getImagePath($src);
          $this->assertTrue(file_exists($source), 'source does not exist:' . $source);

          return new Gd($source, $source . '.webp');
      }

      private function createGdExposer($src)
      {
          $gd = $this->createGd($src);
          return new GdExposer($gd);
      }

      private static function resetPretending()
      {
          reset_pretending();
      }

      // pretend imagewebp is missing
      public function testNotOperational1()
      {
          global $pretend;

          $gd = $this->createGd('test.png');
          self::resetPretending();

          $pretend['functionsNotExisting'] = ['imagewebp'];
          $this->expectException(SystemRequirementsNotMetException::class);
          $gd->checkOperationality();
      }

}

require_once('pretend.inc');
