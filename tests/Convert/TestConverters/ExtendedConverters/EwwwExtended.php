<?php

namespace WebPConvert\Tests\Convert\TestConverters\ExtendedConverters;

use WebPConvert\Convert\Converters\Ewww;

class EwwwExtended extends Ewww
{
    public function callDoActualConvert()
    {
        $this->doActualConvert();
    }

}
