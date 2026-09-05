<?php
/**
 * WebPConvert - Convert JPEG & PNG to WebP with PHP
 *
 * @link https://github.com/rosell-dk/webp-convert
 * @license MIT
 */

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\Wpc;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\InvalidApiKeyException;
use WebPConvert\Loggers\BufferLogger;

use WebPConvert\Tests\CompatibleTestCase;
use WebPConvert\Tests\Convert\Exposers\WpcExposer;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Convert\Converters\Wpc
 * @covers WebPConvert\Convert\Converters\Wpc
 */
class WPCTest extends CompatibleTestCase
{

    public function getImageFolder()
    {
        return realpath(__DIR__ . '/../../images');
    }

    public function getImagePath($image)
    {
        return $this->getImageFolder() . '/' . $image;
    }

/*    public function testApi0()
    {
        if (!empty(getenv('WEBPCONVERT_WPC_API_URL_API0'))) {
            $source = $this->imageDir . '/test.png';
            Wpc::convert($source, $source . '.webp', [
                'api-version' => 0,
                'api-url' => getenv('WEBPCONVERT_WPC_API_URL_API0')
            ]);
        }
    }
*/

    private static function tryThis($test, $source, $options)
    {
        $bufferLogger = new BufferLogger();

        try {
            Wpc::convert($source, $source . '.webp', $options, $bufferLogger);
        } catch (ConversionFailedException $e) {

            // we accept this failure that seems to happen when WPC gets stressed:
            if (strpos($e->getMessage(), 'unable to open image') !== false) {
                return true;
            }

            // we also accept this failure that also seems to happen when WPC gets stressed:
            if (strpos($e->getMessage(), 'We got nothing back') !== false) {
                return true;
            }

            if ($e->getMessage() == 'Error saving file. Check file permissions') {
                throw new ConversionFailedException(
                    'Failed saving file. Here is the log:' . $bufferLogger->getText()
                );
            }

            throw $e;
        }
        return true;
    }

    public function testWarnIfNotOperational()
    {
        ConverterTestHelper::warnIfNotOperational('Wpc');
        $this->addToAssertionCount(1);
    }

    public function testApi0()
    {
        if (empty(getenv('WEBPCONVERT_WPC_API_URL'))) {
            echo "\n" . 'NOTICE: WPC is not operational. It needs api-key and api-url. ';
            echo 'You can set this up by setting environment varibles WEBPCONVERT_WPC_API_URL_API and WEBPCONVERT_WPC_API_KEY. ';
            echo 'To also test old api=0, use WEBPCONVERT_WPC_API_URL_API0.';
            echo "\n";
        } else {
            if (empty(getenv('WEBPCONVERT_WPC_API_URL_API0'))) {
                echo "\n" . 'NOTICE: WPC is not tested with api-version=0. To test this, you must set environment varibles WEBPCONVERT_WPC_API_URL_API0 and WEBPCONVERT_WPC_API_KEY' . "\n";
            }
        }
        if (empty(getenv('WEBPCONVERT_WPC_API_URL_API0'))) {
            $this->addToAssertionCount(1);
            return;
        }

        $source = $this->getImagePath('test.png');
        $options = [
            'api-version' => 0,
            'api-url' => getenv('WEBPCONVERT_WPC_API_URL_API0'),
            'lossless' => true,
        ];

        $this->assertTrue(self::tryThis($this, $source, $options));


    }

    public function testApi1()
    {
        if (empty(getenv('WEBPCONVERT_WPC_API_URL'))) {
            $this->addToAssertionCount(1);
            return;
        }

        $source = $this->getImagePath('test.png');
        $options = [
            'api-version' => 1,
            'crypt-api-key-in-transfer' => true,
            'lossless' => true,
        ];

        $this->assertTrue(self::tryThis($this, $source, $options));
    }

    public function testApi2()
    {
        if (empty(getenv('WEBPCONVERT_WPC_API_URL'))) {
            $this->addToAssertionCount(1);
            return;
        }

        $source = $this->getImagePath('test.png');
        $options = [
            'api-version' => 2,
            'crypt-api-key-in-transfer' => true,
            'lossless' => true,
        ];

        $this->assertTrue(self::tryThis($this, $source, $options));
    }

    public function testWrongSecretButRightUrl()
    {
        if (empty(getenv('WEBPCONVERT_WPC_API_URL'))) {
            return;
        }

        $source = $this->getImagePath('test.png');
        $options = [
            'api-version' => 1,
            'crypt-api-key-in-transfer' => true,
            'api-key' => 'wrong!',
        ];

        $this->expectException(InvalidApiKeyException::class);
        $this->assertTrue(self::tryThis($this, $source, $options));
    }

    public function testBadURL()
    {
        $this->expectException(ConverterNotOperationalException::class);

        Wpc::convert(
            $this->getImagePath('test.png'),
            $this->getImagePath('test.webp'),
            [
                'api-url' => 'badurl!',
                'secret' => 'bad dog!',
            ]
        );
    }

    public function test404()
    {
        //$this->expectException(ConversionFailedException::class);

        try {
            Wpc::convert(
                $this->getImagePath('test.png'),
                $this->getImagePath('test.webp'),
                [
                    'api-url' => 'https://google.com/hello',
                    'secret' => 'bad dog!',
                ]
            );
            $this->fail('Expected an exception');

        } catch (ConversionFailedException $e) {
            // this is expected!
            $this->addToAssertionCount(1);

            $this->assertMatchesRegularExpression2('#we got a 404 response#', $e->getMessage());
        }

    }

    public function testUnexpectedResponse()
    {
        //$this->expectException(ConversionFailedException::class);

        try {
            Wpc::convert(
                $this->getImagePath('test.png'),
                $this->getImagePath('test.webp'),
                [
                    'api-url' => 'https://www.google.com/',
                    'secret' => 'bad dog!',
                ]
            );
            $this->fail('Expected an exception');

        } catch (ConversionFailedException $e) {
            // this is expected!
            $this->addToAssertionCount(1);

            $this->assertMatchesRegularExpression2('#We did not receive an image#', $e->getMessage());
        }
    }

    public function testPublicIpCheck()
    {
        $wpcExposer = new WpcExposer(Wpc::createInstance(
            $this->getImagePath('test.png'),
            $this->getImagePath('test.webp')
        ));

        // Urls that hold an IP require no lookup, so they can be checked without touching dns
        $this->assertEquals(['203.0.113.10'], $wpcExposer->getPublicIpsOfUrl('https://203.0.113.10/wpc.php'));
        $this->assertEquals([], $wpcExposer->getPublicIpsOfUrl('https://127.0.0.1/wpc.php'));
        $this->assertEquals([], $wpcExposer->getPublicIpsOfUrl('https://192.168.0.1/wpc.php'));
        $this->assertEquals([], $wpcExposer->getPublicIpsOfUrl('https://localhost/wpc.php'));
    }

    public function testDnsPinning()
    {
        $wpcExposer = new WpcExposer(Wpc::createInstance(
            $this->getImagePath('test.png'),
            $this->getImagePath('test.webp')
        ));

        // The host is pinned to the checked IPs, on the port that the request will use
        $this->assertEquals(
            ['example.com:443:203.0.113.10'],
            $wpcExposer->createResolveOption('https://example.com/wpc.php', ['203.0.113.10'])
        );
        $this->assertEquals(
            ['example.com:80:203.0.113.10'],
            $wpcExposer->createResolveOption('http://example.com/wpc.php', ['203.0.113.10'])
        );
        $this->assertEquals(
            ['example.com:8080:203.0.113.10'],
            $wpcExposer->createResolveOption('https://example.com:8080/wpc.php', ['203.0.113.10'])
        );

        // All the checked IPs are pinned, so curl can move on to the next, if one is unreachable
        $this->assertEquals(
            ['example.com:443:203.0.113.10,2001:db8::1'],
            $wpcExposer->createResolveOption('https://example.com/wpc.php', ['203.0.113.10', '2001:db8::1'])
        );

        // There is nothing to pin, when there are no IPs, or when the url requires no lookup
        $this->assertEquals([], $wpcExposer->createResolveOption('https://example.com/wpc.php', []));
        $this->assertEquals(
            [],
            $wpcExposer->createResolveOption('https://203.0.113.10/wpc.php', ['203.0.113.10'])
        );
    }

    public function testPinningRequiresResolveOption()
    {
        $wpc = Wpc::createInstance(
            $this->getImagePath('test.png'),
            $this->getImagePath('test.webp'),
            [
                // An url that holds an IP requires no lookup, so this needs no dns
                'api-url' => 'https://203.0.113.10/wpc.php',
                'require-public-ip' => true,
            ]
        );

        // Without CURLOPT_RESOLVE there is no way to pin, so the converter must report itself as
        // not operational, rather than sending an unpinned request
        if (!defined('CURLOPT_RESOLVE')) {
            $this->expectException(ConverterNotOperationalException::class);
            $wpc->checkOperationality();
            return;
        }

        // Here it is available, so the option must not stand in the way
        $wpc->checkOperationality();
        $this->addToAssertionCount(1);
    }


/*
    HMM.. Apparently wpc can't handle much stress.
    The runAllConvertTests often results in an error like this:

    'WPC failed converting image: "unable to open image '../conversions/80c80b20834edd62456fe9e6da4d24d64be51dc1.jpg': No such file or directory @ error/blob.c/OpenBlob/3489"'

    public function testApi0()
    {
        if (!empty(getenv('WEBPCONVERT_WPC_API_URL_API0'))) {
            ConverterTestHelper::runAllConvertTests($this, 'Wpc', [
                'api-version' => 0,
                'api-url' => getenv('WEBPCONVERT_WPC_API_URL_API0')
            ]);
        }
    }

    public function testApi1()
    {
        if (empty(getenv('WEBPCONVERT_WPC_API_URL')) || empty(getenv('WEBPCONVERT_WPC_API_KEY'))) {
            return;
        }

        ConverterTestHelper::runAllConvertTests($this, 'Wpc', [
            'api-version' => 1,
            'crypt-api-key-in-transfer' => true
        ]);

        // TODO: Also test without crypt
    }
*/

/*
    public function testMissingURL()
    {
        $this->expectException(ConverterNotOperationalException::class);

        Wpc::convert($this->imageDir . '/test.png', $this->imageDir . '/test.webp', [
            'api-url' => '',
            'secret' => 'bad dog!',
        ]);
    }*/


/*
    public function testWrongSecretButRightUrl()
    {
        if (empty(getenv('WEBPCONVERT_WPC_API_URL'))) {
            return;
        }

        $this->expectException(InvalidApiKeyException::class);

        Wpc::convert($this->imageDir . '/test.png', $this->imageDir . '/test.webp', [
            'api-version' => 0,
            'api-url' => getenv('WEBPCONVERT_WPC_API_URL'),
            'secret' => 'purposely-wrong-secret!'
        ]);
    }

    public function testBadURL()
    {
        $this->expectException(ConverterNotOperationalException::class);

        Wpc::convert($this->imageDir . '/test.png', $this->imageDir . '/test.webp', [
            'api-url' => 'badurl!',
            'secret' => 'bad dog!',
        ]);
    }*/

}
