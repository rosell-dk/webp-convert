<?php

namespace WebPConvert\Tests\Convert\BaseConverters;

use WebPConvert\Convert\Helpers\PhpIniSizes;

use PHPUnit\Framework\TestCase;

class PhpIniSizesTest extends TestCase
{
    
    public function testParseShortHandSize()
    {
        // Test without units
        $this->assertEquals(0, PhpIniSizes::parseShortHandSize('0'));
        $this->assertEquals(10, PhpIniSizes::parseShortHandSize('10'));

        // Test "k" unit
        $this->assertEquals(1024, PhpIniSizes::parseShortHandSize('1k'));

        // Test capitial "K"
        $this->assertEquals(1024, PhpIniSizes::parseShortHandSize('1K'));

        // Test "M" unit
        $this->assertEquals(1024 * 1024, PhpIniSizes::parseShortHandSize('1M'));

        // Test "G" unit
        $this->assertEquals(1024 * 1024 * 1024, PhpIniSizes::parseShortHandSize('1G'));


        // Moving to terrabytes, we have to be careful.
        // Terrabytes cannot be represented as integers on 32 bit systems.
        // (on 32 bit systems, max integer value is 107.374.182.400, which can represent up to ~107G)

        // Testing floating point numbers for equality is prone to errors.
        //$this->assertInternalType('int', PhpIniSizes::parseShortHandSize('10'));
        //$this->assertEquals(10.0, 10);


        // So, ie "200G" can not be represented by an int.

        // The computation goes:
        // floor($size) * pow(1024, stripos('bkmgtpezy', $unit[0]));

        // floor() always returns float, according to docs (but may also
        // pow() returns int unless the number is too high, in that case it returns float.
        // And the result? What do you get if you multiply an int and a float (which is in fact representating an integer),
        // and the result is more than PHP_INT_MAX?
        // In the docs, it states the following:
        // "an operation which results in a number beyond the bounds of the integer type will return a float instead."
        // [https://www.php.net/manual/en/language.types.integer.php]
        // Se it seems we are good.
        // But let's check!

        $greatComputation = floor(100) * PHP_INT_MAX;
        $this->assertGreaterThan(PHP_INT_MAX, $greatComputation);

        $greaterComputation = floatval(200) * floatval(PHP_INT_MAX);
        $this->assertGreaterThan($greatComputation, $greaterComputation);

        // Test "T" unit
        $this->assertGreaterThan(PhpIniSizes::parseShortHandSize('1G'), PhpIniSizes::parseShortHandSize('100T'));
        $this->assertGreaterThan(1024 * 1024 * 1024 * 1024, PhpIniSizes::parseShortHandSize('1T') + 1);


        // Test that decimals are trunked, as described here:
        // https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
        $this->assertEquals(1024, PhpIniSizes::parseShortHandSize('1.5k'));
        $this->assertEquals(0, PhpIniSizes::parseShortHandSize('0.5M'));


        // Test syntax violations, which must result in parse error.
        $this->assertFalse(PhpIniSizes::parseShortHandSize('0.5MM'));
        $this->assertFalse(PhpIniSizes::parseShortHandSize('//5'));
    }

    /* TODO...
    public function testTestFilesizeRequirements()
    {
        $iniValue = ini_get('upload_max_filesize');

        // could we call ini_set? instead of mocking ini_get ?
    }
    */
}
