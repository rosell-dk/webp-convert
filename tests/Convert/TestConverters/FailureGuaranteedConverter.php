<?php

namespace WebPConvert\Tests\Convert\TestConverters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

class FailureGuaranteedConverter extends AbstractConverter {

    protected function getOptionDefinitionsExtra()
    {
        return [];
    }

    public function doActualConvert()
    {
        throw new ConversionFailedException('Failure guaranteed!');
    }
}
