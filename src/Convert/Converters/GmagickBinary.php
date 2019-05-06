<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\BaseConverters\AbstractExecConverter;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp by calling gmagick binary (gm).
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class GmagickBinary extends AbstractExecConverter
{
    protected $supportsLossless = false;

    protected function getOptionDefinitionsExtra()
    {
        return [
            ['use-nice', 'boolean', false],
        ];
    }

    private static function getGmagickPath()
    {
        if (empty(getenv('GMAGICK_PATH'))) {
            return 'gm';
        } else {
            return getenv('GMAGICK_PATH') . '/gm';
        }
    }

    public static function gmagickInstalled()
    {
        exec(self::getGmagickPath() . ' -version', $output, $returnCode);
        return ($returnCode == 0);
    }

    // Check if webp delegate is installed
    public static function webPDelegateInstalled()
    {
        exec(self::getGmagickPath() . ' -version', $output, $returnCode);
        foreach ($output as $line) {
            if (preg_match('#WebP.*yes#i', $line)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        if (!self::gmagickInstalled()) {
            throw new SystemRequirementsNotMetException('gmagick is not installed');
        }
        if (!self::webPDelegateInstalled()) {
            throw new SystemRequirementsNotMetException('webp delegate missing');
        }
    }

    protected function doActualConvert()
    {
        //$this->logLn('Using quality:' . $this->getCalculatedQuality());

        $commandArguments = [];
        if ($this->isQualityDetectionRequiredButFailing()) {
            // quality:auto was specified, but could not be determined.
            // we cannot apply the max-quality logic, but we can provide auto quality
            // simply by not specifying the quality option.
        } else {
            $commandArguments[] = '-quality ' . escapeshellarg($this->getCalculatedQuality());
        }
        $commandArguments[] = escapeshellarg($this->source);
        $commandArguments[] = escapeshellarg('webp:' . $this->destination);

        $command = self::getGmagickPath() . ' convert ' . implode(' ', $commandArguments);

        $useNice = (($this->options['use-nice']) && self::hasNiceSupport()) ? true : false;
        if ($useNice) {
            $this->logLn('using nice');
            $command = 'nice ' . $command;
        }
        $this->logLn('command: ' . $command);
        exec($command, $output, $returnCode);
        if ($returnCode == 127) {
            throw new SystemRequirementsNotMetException('gmagick is not installed');
        }
        if ($returnCode != 0) {
            $this->logLn('command:' . $command);
            $this->logLn('return code:' . $returnCode);
            $this->logLn('output:' . print_r(implode("\n", $output), true));
            throw new SystemRequirementsNotMetException('The exec call failed');
        }
    }
}
