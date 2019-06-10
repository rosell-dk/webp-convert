<?php

namespace WebPConvert\Tests\Convert\TestConverters;

use WebPConvert\Convert\Converters\AbstractConverter;

class SuccessGuaranteedConverter extends AbstractConverter {

    public function doActualConvert()
    {
        file_put_contents($this->destination, 'we-pretend-this-is-a-valid-webp!');
    }
}
