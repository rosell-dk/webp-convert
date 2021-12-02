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
        $paths = BinaryDiscovery::discoverInCommonSystemPaths('cwebp');
        $this->assertSame('array', gettype($paths));
    }

    /**
     * @covers ::discoverInstalledBinaries
     */
    public function testDiscoverInstalledBinaries()
    {
        $paths = BinaryDiscovery::discoverInstalledBinaries('cwebp');
        $this->assertSame('array', gettype($paths));
    }


}
