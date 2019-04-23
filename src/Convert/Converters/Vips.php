<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

//require '/home/rosell/.composer/vendor/autoload.php';

class Vips extends AbstractConverter
{
    protected $supportsLossless = true;

    protected function getOptionDefinitionsExtra()
    {
        return [
            ['smart-subsample', 'boolean', false],
            ['alpha-quality', 'int', 100],        // alpha quality in lossless mode
            ['near-lossless', 'boolean', false],  // apply near-lossless preprocessing (controled by setting quality to 20,40,60 or 80)
            ['preset', 'int', 0],                 // preset. 0:default, 1:picture, 2:photo, 3:drawing, 4:icon, 5:text, 6:last
        ];
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

        if (function_exists('vips_version')) {
            $this->logLn('vipslib version: ' . vips_version());
        }
        $this->logLn('vips extension version: ' . phpversion('vips'));
    }

    protected function doActualConvert()
    {
/*
        $im = \Jcupitt\Vips\Image::newFromFile($this->source);
        //$im->writeToFile(__DIR__ . '/images/small-vips.webp', ["Q" => 10]);

        $im->webpsave($this->destination, [
            "Q" => 80,
            //'near_lossless' => true
        ]);
        return;*/



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

        // webpsave options are described here:
        // https://jcupitt.github.io/libvips/API/current/VipsForeignSave.html#vips-webpsave

        $options = [
            "Q" => $this->getCalculatedQuality(),
            'lossless' => $this->options['lossless'],
            'strip' => $this->options['metadata'] == 'none',
        ];

        // Only set the following options if they differ from the default of vipslib
        // This ensures we do not get warning if that property isn't supported
        if ($this->options['near-lossless'] !== false) {
            $options['near_lossless'] = $this->options['near-lossless'];
        }
        if ($this->options['smart-subsample'] !== false) {
            $options['smart_subsample'] = $this->options['smart-subsample'];
        }
        if ($this->options['alpha-quality'] !== 100) {
            $options['alpha_q'] = $this->options['alpha-quality'];
        }
        if ($this->options['preset'] !== 0) {
            $options['preset'] = $this->options['preset'];
        }

        $done = false;

        // A bit unusual loop.
        // Iterations happens when vips errors out because of unsupported properties
        // in that case, we remove that property and try again.
        while (!$done) {
            $result = vips_call('webpsave', $im, $this->destination, $options);

            if ($result === -1) {
                $message = vips_error_buffer();

                // If the error
                if (preg_match("#no property named .(.*).#", $message, $matches)) {
                    $nameOfPropertyNotFound = $matches[1];
                    $this->logLn(
                        'Your version of vipslib does not support the "' . $nameOfPropertyNotFound . '" property. ' .
                        'The option is ignored.'
                    );
                    unset($options[$nameOfPropertyNotFound]);
                } else {
                    throw new ConversionFailedException($message);
                }
            } else {
                $done = true;
            }

        }
    }
}