<?php

namespace WebPConvert\Tests\Helpers;

use WebPConvert\Helpers\BinaryDiscovery;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass WebPConvert\Helpers\BinaryDiscovery
 * @covers WebPConvert\Helpers\BinaryDiscovery
 */
class BinaryDiscoveryTest extends TestCase
{

    /**
     * @covers ::discoverInCommonSystemPaths
     */
    public function testDiscoverInCommonSystemPaths()
    {
        BinaryDiscovery::discoverInCommonSystemPaths('cwebp');
    }

    /**
     * @covers ::discoverInstalledBinaries
     */
    public function testDiscoverInstalledBinaries()
    {
        BinaryDiscovery::discoverInstalledBinaries('cwebp');
    }


}
