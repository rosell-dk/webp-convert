<?php

namespace WebPConvert\Tests\Convert\Exposers;

/**
 * Class for exposing otherwise unaccessible methods of Wpc,
 * - so they can be tested
 */
class WpcExposer extends AbstractConverterExposer {

    public function __construct($wpc)
    {
        parent::__construct($wpc);
    }

    public function getPublicIpsOfUrl($url)
    {
        return $this->callPrivateFunction('getPublicIpsOfUrl', null, $url);
    }

    public function createResolveOption($url, $ips)
    {
        return $this->callPrivateFunction('createResolveOption', null, $url, $ips);
    }

}
