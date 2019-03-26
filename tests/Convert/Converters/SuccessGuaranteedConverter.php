<?php

namespace WebPConvert\Tests\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverters\AbstractConverter;

class SuccessGuaranteedConverter extends AbstractConverter {
    public static $extraOptions = [];
    public function doConvert()
    {
        file_put_contents($this->destination, 'we-pretend-this-is-a-valid-webp!');
    }
}
