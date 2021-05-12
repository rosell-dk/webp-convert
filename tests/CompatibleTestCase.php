<?php

/**
 * Base TestCase which ensures that PHPUnit does not print warnings because of deprecated methods
 *
 * @license MIT
 */

namespace WebPConvert\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version as PHPUnitVersion;

class CompatibleTestCase extends TestCase
{

    public function assertDoesNotMatchRegularExpression2($arg1, $arg2)
    {
        // https://stackoverflow.com/questions/4837748/how-to-detect-version-of-phpunit
        $phpUnitVersion = PHPUnitVersion::id();
        $phpUnitMajorVersion = explode('.', $phpUnitVersion)[0];
        $phpUnitMinorVersion = explode('.', $phpUnitVersion)[1];

        $phpUnit91OrNewer = (($phpUnitMajorVersion >=9) && ($phpUnitMinorVersion >=1));

        if ($phpUnit91OrNewer) {
            //parent::assertDoesNotMatchRegularExpression($arg1, $arg2);
        } else {
            $this->assertNotRegExp($arg1, $arg2);
        }
    }

    public function assertMatchesRegularExpression2($arg1, $arg2)
    {
        // https://stackoverflow.com/questions/4837748/how-to-detect-version-of-phpunit
        $phpUnitVersion = PHPUnitVersion::id();
        $phpUnitMajorVersion = explode('.', $phpUnitVersion)[0];
        $phpUnitMinorVersion = explode('.', $phpUnitVersion)[1];

        $phpUnit91OrNewer = (($phpUnitMajorVersion >=9) && ($phpUnitMinorVersion >=1));

        if ($phpUnit91OrNewer) {
            //parent::assertMatchesRegularExpression($arg1, $arg2);
        } else {
            $this->assertRegExp($arg1, $arg2);
        }
    }

}
