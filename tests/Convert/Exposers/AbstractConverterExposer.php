<?php

namespace WebPConvert\Tests\Convert\Exposers;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Tests\BaseExposer;

/**
 * Class for exposing otherwise unaccessible methods of AbstractConverter,
 * - so they can be tested
 *
 * TODO: expose and test more methods! (and make more methods private/protected in AbstractConverter)
 */
class AbstractConverterExposer extends BaseExposer {

    //public static $extraOptions = [];

    public $converter;
    public static $currentlyCalling;

    public function __construct($converter)
    {
        parent::__construct($converter);
    }

    public function getSource()
    {
        return $this->getPrivateProperty('source');
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
        return $this->bindDynamicFunctionToObjectAndCallIt($inject);
    }

    /*
    public function prepareOptions()
    {
        $this->callPrivateFunction('prepareOptions', AbstractConverter::class);
    }*/

    public function getOptions()
    {
        return $this->getPrivateProperty('options', AbstractConverter::class);
    }

/*
    public function getDefaultOptions()
    {
        //return $this->getPrivateStaticProperty('defaultOptions', AbstractConverter::class);
        return $this->callPrivateFunction('getDefaultOptions', AbstractConverter::class);
    }*/

}
