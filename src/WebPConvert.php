<?php

namespace WebPConvert;

//use WebPConvert\Convert\Converters\ConverterHelper;
use WebPConvert\Convert\Converters\Stack;
//use WebPConvert\Serve\ServeExistingOrHandOver;
use WebPConvert\Convert\ConverterFactory;
use WebPConvert\Options\OptionFactory;
use WebPConvert\Serve\ServeConvertedWebP;
use WebPConvert\Serve\ServeConvertedWebPWithErrorHandling;

/**
 * Convert images to webp and/or serve them.
 *
 * This class is just a couple of convenience methods for doing conversion and/or
 * serving.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class WebPConvert
{

    /**
     * Convert jpeg or png into webp
     *
     * Convenience method for calling Stack::convert.
     *
     * @param  string  $source       The image to convert (absolute,no backslashes)
     *                               Image must be jpeg or png.
     * @param  string  $destination  Where to store the converted file (absolute path, no backslashes).
     * @param  array   $options      (optional) Array of named options
     *                               The options are documented here:
     *                            https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     *
     * @throws  \WebPConvert\Convert\Exceptions\ConversionFailedException   in case conversion fails
     * @return  void
     */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        Stack::convert($source, $destination, $options, $logger);
    }

    /**
     * Serve webp image, converting first if neccessary.
     *
     * If an image already exists, it will be served, unless it is older or larger than the source. (If it is larger,
     * the original is served, if it is older, the existing webp will be deleted and a fresh conversion will be made
     * and served). In case of error, the action indicated in the 'fail' option will be triggered (default is to serve
     * the original). Look up the ServeConvertedWebP:serve() and the ServeConvertedWebPWithErrorHandling::serve()
     * methods to learn more.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting. The options are documented in the
     *                                       ServeConvertedWebPWithErrorHandling::serve() method
     * @param  \WebPConvert\Loggers\BaseLogger $serveLogger (optional)
     * @param  \WebPConvert\Loggers\BaseLogger $convertLogger (optional)
     * @return void
     */
    public static function serveConverted(
        $source,
        $destination,
        $options = [],
        $serveLogger = null,
        $convertLogger = null
    ) {
        //return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
        //if (isset($options['handle-errors']) && $options['handle-errors'] === true) {
        if (isset($options['fail']) && ($options['fail'] != 'throw')) {
            ServeConvertedWebPWithErrorHandling::serve($source, $destination, $options, $serveLogger, $convertLogger);
        } else {
            ServeConvertedWebP::serve($source, $destination, $options, $serveLogger, $convertLogger);
        }
    }

    /**
     *  Get ids of all converters available in webp-convert.
     *
     *  @return  array  Array of ids.
     */
    public static function getConverterIds()
    {
        $all = Stack::getAvailableConverters();
        $all[] = 'stack';
        return $all;
    }

    /**
     *  Get option definitions for all converters
     *
     *  Added in order to give GUI's a way to automatically adjust their setting screens.
     *
     *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
     *  @param   bool     $returnGeneral              Whether the general setting definitions should be returned
     *  @param   bool     $returnGeneralSupport       Whether the ids of supported/unsupported general options
     *                                                should be returned
     *
     *  @return  array  Array of options definitions - ready to be json encoded, or whatever
     */
     /*
     Commented out, as it is not ready for release yet


    public static function getConverterOptionDefinitions(
        $imageType = 'png',
        $returnGeneral = true,
        $returnGeneralSupport = true
    ) {

        // TODO: Return converter too - even though it is no a "real" option

        $converterIds = self::getConverterIds();
        $result = [];

        $ewww = ConverterFactory::makeConverter('ewww', '', '');

        //$result['general'] = $ewww->getGeneralOptionDefinitions();
        $result['general'] = $ewww->getGeneralOptionDefinitions();
        $generalOptionHash = [];
        $generalOptionIds = [];
        foreach ($result['general'] as &$option) {
            $generalOptionIds[] = $option['id'];
            //$option['supportedBy'] = $converterIds;   // we will remove the unsupported below
            $option['unsupportedBy'] = [];
            $generalOptionHash[$option['id']] = &$option;
        }
        //$result['general'] = $generalOptionIds;
        array_unshift($result['general'], OptionFactory::createOption('converter', 'string', [
                'title' => 'Converter',
                'description' => 'Conversion method',
                'description' => "Cwebp and vips are best. " .
                    'the *magick are nearly as good, but only recent versions supports near-lossless. ' .
                    'gd is poor, as it does not support any webp options. ' .
                    'For full discussion, check the guide',
                'default' => 'cwebp', // TODO: set to best working
                'enum' => $converterIds,
                'ui' => [
                    'component' => 'select',
                    'links' => [
                        [
                          'Guide',
                          'https://github.com/rosell-dk/webp-convert/blob/master/docs/v1.3/converting/converters.md'
                        ]
                    ],
                ]
            ])->getDefinition());


        //getUIForGeneralOptions
        //$generalOption->addOptions(... $this->getGeneralOptions($imageType));

        $supportedBy = [];

        foreach ($converterIds as $converterId) {
            $c = ConverterFactory::makeConverter($converterId, '', '');

            //$supports = $generalOptionIds;
            foreach ($c->getUnsupportedGeneralOptions() as $optionId) {
                //$result['general']
                //if ()$support[]
                //unsupportedBy
                $generalOptionHash[$optionId]['unsupportedBy'][] = $converterId;
            }


            //$optionDefinitions = $c->getOptionDefinitions($imageType, $returnGeneral, $returnGeneralSupport);
            $optionDefinitions = $c->getUniqueOptionDefinitions($imageType);
            $result[$converterId] = $optionDefinitions;
        }
        return $result;
    }*/
}
