<?php

namespace WebPConvert;

//use WebPConvert\Convert\Converters\ConverterHelper;
use WebPConvert\Convert\Converters\Stack;
//use WebPConvert\Serve\ServeExistingOrHandOver;
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
     *       The following options are generally supported (individual converters provides more options):
     *       'quality'     => (integer|"auto")   Quality. If set to auto and source image is jpeg, the quality will
     *                                           be set to same as source - if detectable. The detection requires
     *                                           imagick or gmagick. Default: "auto".
     *       'max-quality' => (integer)          Limit quality (relevant only if "quality" is set to "auto").
     *                                           Default: 85.
     *       'default-quality' => (integer)      Default quality (used when auto detection fails). Default: 75
     *       'metadata'    => (string)           Valid values: 'all', 'none', 'exif', 'icc', 'xmp'.
     *                                           Note: Only *cwebp* supports all values. *gd* will always remove all
     *                                           metadata. The rest can either strip all or keep all (they will keep
     *                                           all, unless metadata is set to *none*). Default: 'none'.
     *        'lossless'   => (boolean|"auto")   Whether to convert into the lossless webp format or the lossy.
     *                                           If "auto" is selected, the format that results in the smallest file
     *                                           is selected (two actual conversions are made and the smallest file
     *                                           wins). Note that only *cwebp* and *vips* converters supports
     *                                           the lossless encoding. Converters that does not support lossless
     *                                           simply always converts to lossy encoding (and "auto" will not trigger
     *                                           two conversions for these). Default is "auto" when converting PNGs and
     *                                           false when converting JPEGs. The reason for this default is that it is
     *                                           probably rare that a JPEG is compressed better with lossless encoding
     *                                           (as the jpeg format typically have been choosen only for photos and
     *                                           photos almost always is best encoding with the lossy encoding. On the
     *                                           other hand, graphics and icons are sometimes compressed best with
     *                                           lossy encoding and sometimes best with lossless encoding). Note that
     *                                           you can use the 'png' and 'jpeg' options to set this option different
     *                                           for png and jpegs. Ie: ['png' => ['lossless' => 'auto'], 'jpeg' =>
     *                                           'lossless' => false]].
     *        'skip'       => (boolean)          If set to true, conversion is skipped entirely. Can for example be used
     *                                           to skip pngs for certain converters. You might for example want to use
     *                                           Gd for jpegs and ewww for pngs.
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
     * @param  \WebPConvert\Loggers\BaseLogger $logger (optional)
     */
    public static function serveConverted($source, $destination, $options = [], $logger = null)
    {
        //return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
        //if (isset($options['handle-errors']) && $options['handle-errors'] === true) {
        if (isset($options['fail']) && ($options['fail'] != 'throw')) {
            ServeConvertedWebPWithErrorHandling::serve($source, $destination, $options, $logger);
        } else {
            ServeConvertedWebP::serve($source, $destination, $options, $logger);
        }
    }
}
