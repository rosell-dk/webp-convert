<?php

namespace WebPConvert\Tests\Serve;

use WebPConvert\Serve\ServeConverted;
use PHPUnit\Framework\TestCase;

function aboutToPerformFailAction() {
    echo 'aoeutnshaoesnuth!!!!';
    return false;
}
function aboutToPerformFailActionCallback() {
    echo 'aoeutnshaoesnuth';
    return false;
}

class ServeConverted2 extends ServeConverted
{
    protected function header($header, $replace = true)
    {
        return;
    }
}

class ServeConvertedTest extends TestCase
{
    public static $imageDir = __DIR__ . '/../images/';

    public function mustNotHappen()
    {
        $thisHappened = true;
        $this->assertFalse($thisHappened);
        return false;
    }

    function testSourceIsLighter()
    {
        ServeConverted2::serveConverted(
            self::$imageDir . 'test.jpg',
            self::$imageDir . 'pre-converted/test-bigger.webp',
            [
                'aboutToServeImageCallBack' => function($servingWhat, $whyServingThis, $obj) {
                    $this->assertEquals($servingWhat, 'source');
                    $this->assertEquals($whyServingThis, 'source-lighter');
                    return false;
                },
                'aboutToPerformFailActionCallback' => array($this, 'mustNotHappen')
            ]
        );
    }

    function testServeOriginal()
    {
        ServeConverted2::serveConverted(
            self::$imageDir . 'test.jpg',
            self::$imageDir . 'test.webp',
            [
                'serve-original' => true,
                'aboutToServeImageCallBack' => function($servingWhat, $whyServingThis, $obj) {
                    $this->assertEquals($servingWhat, 'source');
                    $this->assertEquals($whyServingThis, 'explicitly-told-to');
                    return false;
                },
                'aboutToPerformFailActionCallback' => array($this, 'mustNotHappen')
            ]
        );
    }

    function testServeDestination()
    {
        ServeConverted2::serveConverted(
            self::$imageDir . 'test.jpg',
            self::$imageDir . 'pre-converted/test.webp',
            [
                'aboutToServeImageCallBack' => function($servingWhat, $whyServingThis, $obj) {
                    $this->assertEquals($servingWhat, 'destination');
                    $this->assertEquals($whyServingThis, 'no-reason-not-to');
                    return false;
                },
                'aboutToPerformFailActionCallback' => array($this, 'mustNotHappen')
            ]
        );
    }

}
