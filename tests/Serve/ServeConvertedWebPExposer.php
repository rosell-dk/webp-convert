<?php

namespace WebPConvert\Tests\Convert\Exposers;

use WebPConvert\Serve\ServeConvertedWebP;
use WebPConvert\Tests\BaseExposer;

/**
 * Class for exposing otherwise unaccessible methods of AbstractConverter,
 * - so they can be tested
 *
 * TODO: expose and test more methods! (and make more methods private/protected in AbstractConverter)
 */
class ServeConvertedWebPExposer extends BaseExposer {

    public function __construct($instance)
    {
        parent::__construct($instance);
    }
/*
    public function serveDestination($destination, $options)
    {
        return $this->callPrivateFunction('serveDestination', null, $destination, $options);
    }*/
}
