<?php
// TODO: Quality option
namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
//use WebPConvert\Exceptions\TargetNotFoundException;
use WebPConvert\Convert\ExecConverter;

// To futher improve this converter, I could check out:
// https://github.com/Orbitale/ImageMagickPHP
class ImagickBinary extends ExecConverter
{
    public static $extraOptions = [
        [
            'name' => 'use-nice',
            'type' => 'boolean',
            'sensitive' => false,
            'default' => true,
            'required' => false
        ],
    ];
    //public $id = 'imagickbinary';
    public static function imagickInstalled()
    {
        exec('convert -version', $output, $returnCode);
        return ($returnCode == 0);
    }
    // Check if webp delegate is installed
    public static function webPDelegateInstalled()
    {
        /* HM. We should not rely on grep being available
        $command = 'convert -list configure | grep -i "delegates" | grep -i webp';
        exec($command, $output, $returnCode);
        return (count($output) > 0);
        */
        $command = 'convert -version';
        exec($command, $output, $returnCode);
        $hasDelegate = false;
        foreach ($output as $line) {
            if (preg_match('/Delegate.*webp.*/i', $line)) {
                return true;
            }
        }
        return false;
    }
    // Although this method is public, do not call directly.
    // You should rather call the static convert() function, defined in BaseConverter, which
    // takes care of preparing stuff before calling doConvert, and validating after.
    public function doConvert()
    {
        if (!self::imagickInstalled()) {
            throw new ConverterNotOperationalException('imagick is not installed');
        }
        if (!self::webPDelegateInstalled()) {
            throw new ConverterNotOperationalException('webp delegate missing');
        }
        //$this->logLn('Using quality:' . $this->getCalculatedQuality());
        // Should we use "magick" or "convert" command?
        // It seems they do the same. But which is best supported? Which is mostly available (whitelisted)?
        // Should we perhaps try both?
        // For now, we just go with "convert"
        $command = 'convert ' .
            self::escapeFilename($this->source) . ' webp:' . self::escapeFilename($this->destination);

        // TODO:
        // quality. Like this: 'convert -quality 100 small.jpg small.webp'
        $useNice = (($this->options['use-nice']) && self::hasNiceSupport()) ? true : false;
        if ($useNice) {
            $this->logLn('using nice');
            $command = 'nice ' . $command;
        }
        exec($command, $output, $returnCode);
        if ($returnCode == 127) {
            throw new ConverterNotOperationalException('imagick is not installed');
        }
        if ($returnCode != 0) {
            $this->logLn('command:' . $command);
            $this->logLn('return code:' . $returnCode);
            $this->logLn('output:' . print_r(implode("\n", $output), true));
            throw new ConverterNotOperationalException('The exec call failed');
        }
    }
}
