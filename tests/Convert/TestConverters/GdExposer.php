<?php

namespace WebPConvert\Tests\Convert\TestConverters;

use WebPConvert\Convert\Converters\Gd;

/**
 * Class for exposing otherwise unaccessible methods of AbstractConverter,
 * - so they can be tested
 *
 * TODO: expose and test more methods! (and make more methods private/protected in AbstractConverter)
 */
class GdExposer {

    //public static $extraOptions = [];

    public $gd;

    public function __construct($source, $destination, $options = [], $logger = null)
    {
        $this->gd = new Gd($source, $destination, $options, $logger);
    }

    private function bindAndCall($functionToBind) {
        $functionToBind = $functionToBind->bindTo($this->gd, Gd::class);
        return $functionToBind();
    }

    public function getSource()
    {
        $sourceThief = function() {
            return $this->source;
        };
        return $this->bindAndCall($sourceThief);
    }

    public function isOperating()
    {
        $inject = function() {
            try {
                $this->checkOperationality();
                $this->checkConvertability();
            } catch (\Exception $e) {
                return false;
            }
            return true;
        };
        return $this->bindAndCall($inject);
    }

    public function getImage()
    {
        $thief = function() {
            return $this->image;
        };
        return $this->bindAndCall($thief);
    }

    public function createImageResource()
    {
        $cb = function() {
            call_user_func_array(array($this, 'createImageResource'), func_get_args());
        };
        return $this->bindAndCall($cb);
    }


/*
    public function checkOperationality()
    {
        $this->checkOperationality();
    }

    public function exposedCheckConvertability()
    {
        $this->checkConvertability();
    }

    public function exposedGetImage()
    {
        return $this->image;
    }

    public function exposedCreateImageResource()
    {
        $this->createImageResource();
    }

*/
/*
Other method for calling pnivate:

        https://stackoverflow.com/questions/2738663/call-private-methods-and-private-properties-from-outside-a-class-in-php/2738847#2738847
        $reflector = new \ReflectionClass(Gd::class);
        $reflector->getMethod('createImageResource')->setAccessible(true);
        $unlockedGate = $reflector->newInstance($source, $source . '.webp');
        $unlockedGate->createImageResource();
*/

/*
        $gd = new Gd($source, $source . '.webp');
        $reflectedGd = new \ReflectionObject($gd);
        $createImageResourceMethod = $reflectedGd->getMethod('createImageResource');
        $createImageResourceMethod->setAccessible(true);
        $createImageResourceMethod->invoke();

        */
        // https://ocramius.github.io/blog/accessing-private-php-class-members-without-reflection/
        /*
        $sourceThief = function($gd) {
            return $gd->source;
        };

        $gd = new Gd($source, $source . '.webp');
        $sourceThief = \Closure::bind($sourceThief, null, $gd);
        $this->assertEquals($source, $sourceThief($gd));
        */
}
