<?php

/**
 * Base TestCase which ensures that PHPUnit does not print warnings because of deprecated methods
 *
 * @license MIT
 */

namespace WebPConvert\Tests;

use PHPUnit\Framework\TestCase;
// use PHPUnit\Runner\Version as PHPUnitVersion; // no - breaks our PHP 5.6 travis test

class CompatibleTestCase extends TestCase
{

    // By the way:
    // Just discovered that its possible to easily skip tests with an annotation
    // based on criterias such as PHPUnit version or extention.
    // See: https://phpunit.de/manual/3.7/en/incomplete-and-skipped-tests.html#incomplete-and-skipped-tests.skipping-tests-using-requires
    //      (and https://stackoverflow.com/questions/4837748/how-to-detect-version-of-phpunit)

    
    public function assertDoesNotMatchRegularExpression2($arg1, $arg2)
    {
        // https://stackoverflow.com/questions/4837748/how-to-detect-version-of-phpunit
        /*
        $phpUnitVersion = PHPUnitVersion::id();
        $phpUnitMajorVersion = explode('.', $phpUnitVersion)[0];
        $phpUnitMinorVersion = explode('.', $phpUnitVersion)[1];

        $phpUnit91OrNewer = (($phpUnitMajorVersion >=9) && ($phpUnitMinorVersion >=1));
        */

        if (method_exists(TestCase::class, 'assertDoesNotMatchRegularExpression')) {
            $this->assertDoesNotMatchRegularExpression($arg1, $arg2);
        } else {
            $this->assertNotRegExp($arg1, $arg2);
        }
    }

    public function assertMatchesRegularExpression2($arg1, $arg2)
    {
        // https://stackoverflow.com/questions/4837748/how-to-detect-version-of-phpunit

        if (method_exists(TestCase::class, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression($arg1, $arg2);
        } else {
            $this->assertRegExp($arg1, $arg2);
        }
    }

}
