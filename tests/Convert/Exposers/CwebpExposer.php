<?php

namespace WebPConvert\Tests\Convert\Exposers;

/**
 * Class for exposing otherwise unaccessible methods of AbstractConverter,
 * - so they can be tested
 *
 * TODO: expose and test more methods! (and make more methods private/protected in AbstractConverter)
 */
class CwebpExposer extends AbstractConverterExposer {

    public function __construct($gd)
    {
        parent::__construct($gd);
    }

    public function createCommandLineOptions()
    {
        return $this->callPrivateFunction('createCommandLineOptions');
    }

}
