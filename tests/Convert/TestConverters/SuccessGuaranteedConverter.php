<?php

namespace WebPConvert\Tests\Convert\TestConverters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;

class SuccessGuaranteedConverter extends AbstractConverter {

    protected function getOptionDefinitionsExtra()
    {
        return [];
    }

    public function doActualConvert()
    {
        file_put_contents($this->destination, 'we-pretend-this-is-a-valid-webp!');
    }
}
