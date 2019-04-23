<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

class Vips extends AbstractConverter
{
    protected $supportsLossless = true;

    protected function getOptionDefinitionsExtra()
    {
        return [];
    }

    /**
     * Check operationality of Vips converter.
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    protected function checkOperationality()
    {
        if (!extension_loaded('vips')) {
            throw new SystemRequirementsNotMetException('Required Vips extension is not available.');
        }

        if (!function_exists('vips_image_new_from_file')) {
            throw new SystemRequirementsNotMetException(
                'Vips extension seems to be installed, however something is not right: ' .
                'the function "vips_image_new_from_file" is not available.'
            );
        }

        // TODO: Should we also test if webp is available? (It seems not to be neccessary - it seems
        // that webp be well intergrated part of vips)
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     * @throws SystemRequirementsNotMetException  if Vips does not support image type
     */
    protected function checkConvertability()
    {
        // It seems that png and jpeg are always supported by Vips
        // - so nothing needs to be done here
    }

    protected function doActualConvert()
    {
        /*
        $im = \Jcupitt\Vips\Image::newFromFile(__DIR__ . '/images/small.jpg');
        //$im->writeToFile(__DIR__ . '/images/small-vips.webp', ["Q" => 10]);
        $im->webpsave(__DIR__ . '/images/small-vips.webp', [
            "Q" => 80,
            'near_lossless' => true
        ]);
        return;
        */

        if (function_exists('vips_version')) {
            $this->logLn('vips version: ' . vips_version());
        }

        // We are currently using vips_image_new_from_file(), but we could consider
        // calling vips_jpegload / vips_pngload instead
        $result = vips_image_new_from_file($this->source, []);
        if ($result === -1) {
            /*throw new ConversionFailedException(
                'Failed creating new vips image from file: ' . $this->source
            );*/
            $message = vips_error_buffer();
            throw new ConversionFailedException($message);
        }

        if (!is_array($result)) {
            throw new ConversionFailedException(
                'vips_image_new_from_file did not return an array, which we expected'
            );
        }

        if (count($result) != 1) {
            throw new ConversionFailedException(
                'vips_image_new_from_file did not return an array of length 1 as we expected ' .
                '- length was: ' . count($result)
            );
        }

        $im = array_shift($result);

        $this->logLn('lossless:' . ($this->options['lossless'] ? 'yes' : 'no'));

        // for some reason, vips_webpsave function is unavailable on at least one system, so we
        // use vips_call instead.

        // webpsave options are described here:
        // https://jcupitt.github.io/libvips/API/current/VipsForeignSave.html#vips-webpsave
        $result = vips_call('webpsave', $im, $this->destination, [
            "Q" => $this->getCalculatedQuality(),
            'lossless' => $this->options['lossless'],
            //'lossless' => $this->options['lossless'],       // boolean
            //'preset'
            //'smart_subsample'     // boolean

            // hm, when I use near_lossless, I get error: "no property named `near_lossless'"
            // btw: beware that if this is used, q must be 20, 40, 60 or 80 (according to link above)
            //'near_lossless' => true,   // boolean

            //'alpha_q'     // int
            'strip' => $this->options['metadata'] == 'none'
        ]);
        if ($result === -1) {
            $message = vips_error_buffer();
            throw new ConversionFailedException($message);
        }
    }
}
