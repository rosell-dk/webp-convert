<?php

namespace WebPConvert\Tests\Convert\TestConverters;

use WebPConvert\Convert\Converters\AbstractConverter;

/**
 * Class for exposing otherwise unaccessible methods of AbstractConverter,
 * - so they can be tested
 *
 * TODO: expose and test more methods! (and make more methods private/protected in AbstractConverter)
 */
class ExposedConverter extends AbstractConverter {

    protected function getOptionDefinitionsExtra()
    {
        return [];
    }

    public function doActualConvert()
    {
        file_put_contents($this->destination, 'we-pretend-this-is-a-valid-webp!');
    }

/*
    public static function exposedGetMimeType($filePath)
    {
        $instance = self::createInstance(
            $filePath,
            $filePath . '.webp'
        );
        return $instance->getMimeTypeOfSource();
    }*/
}
