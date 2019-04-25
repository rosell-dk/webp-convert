<?php

namespace WebPConvert\Tests\Helpers;

use WebPConvert\Helpers\WarningsIntoExceptions;
use WebPConvert\Exceptions\WarningException;

use PHPUnit\Framework\TestCase;

class WarningsIntoExceptionsTest extends TestCase
{
    public function testNothing()
    {
        $this->assertTrue(true);
    }
/*
    private static $imgDir = __DIR__ . '/../../images';

    public function testUserWarning()
    {
        WarningsIntoExceptions::activate();

        $this->expectException(WarningException::class);

        // trigger user warning
        trigger_error('warning test', E_USER_WARNING);

        WarningsIntoExceptions::deactivate();
    }


    public function testWarning()
    {
        WarningsIntoExceptions::activate();

        $this->expectException(WarningException::class);

        // trigger build-in warning (chmod expects exactly two parameters)
        chmod('hth');
        WarningsIntoExceptions::deactivate();
    }*/


/*
    To suppress and capture output from exec calls, you need to redirect the stderr to stdout.
    Otherwise it is "echoed to screen"


    https://stackoverflow.com/questions/1606943/suppressing-output-from-exec-calls-in-php
    public function testWarning2()
    {
        WarningsIntoExceptions::activate();

        //$this->expectException(WarningException);

        //ob_start();
        exec('hahotehua 2>&1', $output, $returnCode);
        //ob_end_clean();

        WarningsIntoExceptions::deactivate();


    }*/
}
