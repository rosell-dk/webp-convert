<?php

namespace WebPConvert\Tests\Convert\Exposers;

use WebPConvert\Convert\Converters\Vips;

/**
 * Class for exposing otherwise unaccessible methods of AbstractConverter,
 * - so they can be tested
 *
 * TODO: expose and test more methods! (and make more methods private/protected in AbstractConverter)
 */
class VipsExposer extends AbstractConverterExposer {

    public function __construct($vips)
    {
        parent::__construct($vips);
    }

    public function createParamsForVipsWebPSave()
    {
        return $this->callPrivateFunction('createParamsForVipsWebPSave', null);
    }

    public function createImageResource()
    {
        return $this->callPrivateFunction('createImageResource', null);
    }

    public function doActualConvert()
    {
        return $this->callPrivateFunction('doActualConvert', null);
    }

    public function webpsave($im, $options)
    {
        return $this->callPrivateFunction('webpsave', null, $im, $options);
    }
}
