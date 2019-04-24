<?php

namespace WebPConvert\Convert\BaseConverters\BaseTraits;

trait WarningLoggerTrait
{
    abstract protected function logLn($msg, $style = '');

    /**
     *  Handle errors during conversion.
     *  The function is a callback used with "set_error_handler". It logs
     */
    protected function warningHandler($errno, $errstr, $errfile, $errline)
    {
        /*
        We do NOT do the following (even though it is generally recommended):

        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }

        - Because we want to log all warnings and errors (also the ones that was suppressed with @)
        https://secure.php.net/manual/en/language.operators.errorcontrol.php
        */

        $errorTypes = [
            E_WARNING =>             "Warning",
            E_NOTICE =>              "Notice",
            E_USER_ERROR =>          "User Error",
            E_USER_WARNING =>        "User Warning",
            E_USER_NOTICE =>         "User Notice",
            E_STRICT =>              "Strict Notice",
            E_DEPRECATED =>          "Deprecated",
            E_USER_DEPRECATED =>     "User Deprecated",

            /*
            The following can never be catched by a custom error handler:
            E_PARSE, E_ERROR, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING

            We do do not currently trigger the following:
            E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE

            But we may want to do that at some point, like this:
            trigger_error('Your version of Gd is very old', E_USER_WARNING);
            */
        ];

        if (isset($errorTypes[$errno])) {
            $errType = $errorTypes[$errno];
        } else {
            $errType = "Unknown error ($errno)";
        }

        $msg = $errType . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline . ', PHP ' . PHP_VERSION .
            ' (' . PHP_OS . ')';
        $this->logLn($msg);

        /*
        if ($errno == E_USER_ERROR) {
            // trigger error.
            // unfortunately, we can only catch user errors
            throw new ConversionFailedException('Uncaught error in converter', $msg);
        }*/

        return false;   // let PHP handle the error from here
    }

    protected function activateWarningLogger()
    {
        set_error_handler(
            array($this, "warningHandler"),
            E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_USER_ERROR
        );
    }

    protected function deactivateWarningLogger()
    {
        restore_error_handler();
    }
}
