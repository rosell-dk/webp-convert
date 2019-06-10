<?php

namespace WebPConvert\Tests\Convert\TestConverters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

class FailureGuaranteedConverter extends AbstractConverter {

    public function doActualConvert()
    {
        throw new ConversionFailedException('Failure guaranteed!');
    }
}
