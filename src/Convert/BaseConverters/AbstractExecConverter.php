<?php

namespace WebPConvert\Convert\BaseConverters;

use WebPConvert\Convert\BaseConverters\AbstractConverter;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;

/**
 * Base for converters that uses the "exec" function.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
abstract class AbstractExecConverter extends AbstractConverter
{

    /**
     * Helper function for examining if "nice" command is available
     *
     * @return  boolean  true if nice is available
     */
    protected static function hasNiceSupport()
    {
        exec("nice 2>&1", $niceOutput);

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
    }

    /**
     * Check basic operationality of exec converters (that the "exec" function is available)
     *
     * @throws  SystemRequirementsNotMetException
     * @return  void
     */
    public function checkOperationality()
    {
        if (!function_exists('exec')) {
            throw new SystemRequirementsNotMetException('exec() is not enabled.');
        }
    }
}
