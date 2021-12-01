<?php

namespace WebPConvert\Convert\Converters\ConverterTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use ExecWithFallback\ExecWithFallback;

/**
 * Trait for converters that uses exec() or similar
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait ExecTrait
{

    abstract protected function logLn($msg, $style = '');


    /**
     * Helper function for examining if "nice" command is available
     *
     * @return  boolean  true if nice is available
     */
    protected static function hasNiceSupport()
    {
        ExecWithFallback::exec("nice 2>&1", $niceOutput);

        if (is_array($niceOutput) && isset($niceOutput[0])) {
            if (preg_match('/usage/', $niceOutput[0]) || (preg_match('/^\d+$/', $niceOutput[0]))) {
                /*
                 * Nice is available - default niceness (+10)
                 * https://www.lifewire.com/uses-of-commands-nice-renice-2201087
                 * https://www.computerhope.com/unix/unice.htm
                 */

                return true;
            }
            return false;
        }
        return false; // to satisfy phpstan
    }

    protected function checkNiceSupport()
    {
        $ok = self::hasNiceSupport();
        if ($ok) {
            $this->logLn('Tested "nice" command - it works :)');
        } else {
            $this->logLn(
                '**No "nice" support. To save a few ms, you can disable the "use-nice" option.**'
            );
        }
        return $ok;
    }

    protected static function niceOption()
    {
        return ['use-nice', 'boolean', [
            'title' => 'Use nice',
            'description' =>
                'If *use-nice* is set, it will be examined if the *nice* command is available. ' .
                'If it is, the binary is executed using *nice*. This assigns low priority to the process and ' .
                'will save system resources - but result in slower conversion.',
            'default' => true,
            'ui' => [
                'component' => 'checkbox',
                'advanced' => true
            ]
        ]];
    }

    /**
     * Logs output from the exec call.
     *
     * @param  array  $output
     *
     * @return  void
     */
    protected function logExecOutput($output)
    {
        if (is_array($output) && count($output) > 0) {
            $this->logLn('');
            $this->logLn('Output:', 'italic');
            foreach ($output as $line) {
                $this->logLn(print_r($line, true));
            }
            $this->logLn('');
        }
    }

    /**
     * Check basic operationality of exec converters (that the "exec" or similar function is available)
     *
     * @throws  WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException
     * @return  void
     */
    public function checkOperationalityExecTrait()
    {
        if (!ExecWithFallback::anyAvailable()) {
            throw new SystemRequirementsNotMetException(
                'exec() is not enabled (nor is alternative methods, such as proc_open())'
            );
        }
    }
}
