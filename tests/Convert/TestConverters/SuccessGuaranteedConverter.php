<?php

namespace WebPConvert\Tests\Convert\TestConverters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;

class SuccessGuaranteedConverter extends AbstractConverter {
    public static $extraOptions = [];
    public function doConvert()
    {
        file_put_contents($this->destination, 'we-pretend-this-is-a-valid-webp!');
    }
}
