<?php

namespace WebPConvert\Tests\Serve;

use WebPConvert\Serve\DecideWhatToServe;
//use WebPConvert\Serve\ServeFile;
//use WebPConvert\Serve\MockedHeader;
//use WebPConvert\Serve\Exceptions\ServeFailedException;

use PHPUnit\Framework\TestCase;

class DecideWhatToServeTest extends TestCase
{

    public static $imageFolder = __DIR__ . '/../images';

    public function testExplicitlyToldToServeOriginal()
    {
        $source = self::$imageFolder . '/test.png';

        $this->assertTrue(file_exists($source));

        $options = [
            'serve-original' => true
        ];
        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $source . '.webp', $options);

        $this->assertEquals('source', $whatToServe);
        $this->assertEquals('explicitly-told-to', $whyToServe);
    }

    public function testSourceIsLighter()
    {
        $source = self::$imageFolder . '/plaintext-with-jpg-extension.jpg';

        // create fake webp at destination, which is larger than the fake jpg
        file_put_contents($source . '.webp', 'aotehu aotnehuatnoehutaoehu atonse uaoehu');

        $this->assertTrue(file_exists($source));
        $this->assertTrue(file_exists($source . '.webp'));

        $options = [
        ];
        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $source . '.webp', $options);

        $this->assertEquals('source', $whatToServe);
        $this->assertEquals('source-lighter', $whyToServe);
    }

    public function testExplicitlyToldToServeFreshl()
    {
        $source = self::$imageFolder . '/test.png';

        $this->assertTrue(file_exists($source));

        $options = [
            'reconvert' => true
        ];
        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $source . '.webp', $options);

        $this->assertEquals('fresh-conversion', $whatToServe);
        $this->assertEquals('explicitly-told-to', $whyToServe);
    }

    public function testExistingOutDated()
    {
        // create fake png at source (it will be newer than the other, as it is created after)
        $source = self::$imageFolder . '/temporary.png';
        file_put_contents($source, '12345');
        $this->assertTrue(file_exists($source));

        // pretend one of our exiting files is the destination. Dont worry, it will not be modified
        $destination = self::$imageFolder . '/text-with-jpg-extension.jpg';
        $this->assertTrue(file_exists($destination));

        $this->assertLessThan(filemtime($source), filemtime($destination));

        $options = [
        ];
        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $destination, $options);

        $this->assertEquals('fresh-conversion', $whatToServe);
        $this->assertEquals('source-modified', $whyToServe);
    }

    public function testDestinitionIsGood()
    {
        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));

        // create fake webp at destination
        $destination = $source . '.webp';
        file_put_contents($destination, '1234');
        $this->assertTrue(file_exists($destination));

        $options = [
        ];
        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $destination, $options);

        $this->assertEquals('destination', $whatToServe);
        $this->assertEquals('no-reason-not-to', $whyToServe);
    }

    public function testFreshConversionBecauseNoExisting()
    {
        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));

        // create fake webp at destination, which is larger than the fake jpg
        $destination = $source . '.webp';
        unlink($destination);
        $this->assertFalse(file_exists($destination));

        $options = [
        ];
        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $destination, $options);

        $this->assertEquals('fresh-conversion', $whatToServe);
        $this->assertEquals('no-existing', $whyToServe);
    }

    public function testExplicitlyToldToReport()
    {
        $source = self::$imageFolder . '/test.png';
        $this->assertTrue(file_exists($source));

        $destination = $source . '.webp';

        $options = [
            'show-report' => true
        ];
        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $destination, $options);

        $this->assertEquals('report', $whatToServe);
        $this->assertEquals('explicitly-told-to', $whyToServe);
    }

}
//require_once('mock-header.inc');
