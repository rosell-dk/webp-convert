<?php

namespace WebPConvert\Tests;

use WebPConvert\WebPConvert;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;

use PHPUnit\Framework\TestCase;

/**
 *  Test the complete build (webp-convert.inc)
 */
class WebPConvertBuildTest extends TestCase
{

    public function testWebPConvertBuildNotCompletelyBroken()
    {
        require __DIR__ . '/../src-build/webp-convert.inc';

        $source = __DIR__ . '/images/png-without-extension';

        WebPConvert::convertAndServe(
            $source,
            $source . '.webp',
            [
                'reconvert' => true,
                //'converters' => ['imagick'],
                'aboutToServeImageCallBack' => function() {
                    return false;
                },
                'aboutToPerformFailActionCallback' => function() {
                    return false;
                }
            ]
        );
        $this->addToAssertionCount(1);

    }

}
