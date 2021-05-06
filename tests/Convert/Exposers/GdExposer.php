<?php

namespace WebPConvert\Tests\Convert\Exposers;

use WebPConvert\Convert\Converters\Gd;

/**
 * Class for exposing otherwise unaccessible methods of AbstractConverter,
 * - so they can be tested
 *
 * TODO: expose and test more methods! (and make more methods private/protected in AbstractConverter)
 */
class GdExposer extends AbstractConverterExposer {

    public function __construct($gd)
    {
        parent::__construct($gd);
    }

    public function createImageResource()
    {
        return $this->callPrivateFunction('createImageResource', null);
    }


    public function makeTrueColorUsingWorkaround(&$image)
    {
        return $this->callPrivateFunctionByRef('makeTrueColorUsingWorkaround', $image);

//        return $this->callPrivateFunction('makeTrueColorUsingWorkaround', null, $image);
    /*
       The following would also work:

        $cb = function(&$image) {
            echo 'callback:...' . gettype($image);
            return $this->makeTrueColorUsingWorkaround($image);
        };
        //$class = get_class(Gd::class);
        $functionNowBinded = $cb->bindTo($this->objectToExposeFrom, Gd::class);

        return $functionNowBinded($image);*/
    }

    public function trySettingAlphaBlending(&$image)
    {
        return $this->callPrivateFunctionByRef('trySettingAlphaBlending', $image);
    }

    public function tryToMakeTrueColorIfNot(&$image)
    {
        return $this->callPrivateFunctionByRef('tryToMakeTrueColorIfNot', $image);
    }

    public function tryConverting(&$image)
    {
        return $this->callPrivateFunctionByRef('tryConverting', $image);
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
