<?php

namespace WebPConvert\Tests\Helpers;

use WebPConvert\Helpers\FileExists;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Helpers\FileExists
 * @covers WebPConvert\Helpers\FileExists
 */
class FileExistsTest extends TestCase
{

    /**
     * @covers ::honestFileExists
     */
    public function testHonestFileExists()
    {
        // Test existing
        $this->assertTrue(FileExists::honestFileExists(__DIR__));
        $this->assertTrue(FileExists::honestFileExists(__FILE__));

        // Test non-existing
        $this->assertFalse(FileExists::honestFileExists(__DIR__ . '/i-do-not-exist'));

        // Test failure (supplying an array instead of a string makes file_exists throw a warning (code=2, it seems))
        try {
            $exceptionHappened = false;
            FileExists::honestFileExists([]);
        } catch (\Exception $e) {
            $this->assertEquals($e->getCode(), 2);
            $exceptionHappened = true;
        }

        if (!$exceptionHappened) {
            $this->fail('An exception was expected!');
        }
    }

    /**
     * @covers ::fileExistsUsingExec
     */
    public function testFileExistsUsingExec()
    {
        if (function_exists('exec')) {
            // Test existing
            $this->assertTrue(FileExists::fileExistsUsingExec(__DIR__));
            $this->assertTrue(FileExists::fileExistsUsingExec(__FILE__));

            // Test non-existing
            $this->assertFalse(FileExists::fileExistsUsingExec(__DIR__ . '/i-do-not-exist'));
        }
    }

}
