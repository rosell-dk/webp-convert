<?php

namespace WebPConvert\Convert\BaseConverters\BaseTraits;

trait WarningLoggerTrait
{
    abstract protected function logLn($msg, $style = '');

    /** @var string|array|null  Previous error handler (stored in order to be able pass warnings on) */
    private $previousErrorHandler;

    /**
     *  Handle warnings and notices during conversion by logging them and passing them on.
     *
     *  The function is a callback used with "set_error_handler".
     *  It is declared public because it needs to be accessible from the point where the warning happened.
     *
     *  @param  integer  $errno
     *  @param  string   $errstr
     *  @param  string   $errfile
     *  @param  integer  $errline
     *
     *  @return false|null
     */
    public function warningHandler($errno, $errstr, $errfile, $errline)
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
            in that case, remember to add them to this array
            */
        ];

        if (isset($errorTypes[$errno])) {
            $errType = $errorTypes[$errno];
        } else {
            $errType = "Unknown error/warning/notice ($errno)";
        }

        $msg = $errType . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline . ', PHP ' . PHP_VERSION .
            ' (' . PHP_OS . ')';
        $this->logLn($msg);

        //echo 'previously defined handler:' . print_r($this->previousErrorHandler, true);

        if (!is_null($this->previousErrorHandler)) {
            return call_user_func($this->previousErrorHandler, $errno, $errstr, $errfile, $errline);
        } else {
            return false;
        }
    }

    /**
     *  Activate warning logger.
     *
     *  Sets the error handler and stores the previous to our error handler can bubble up warnings
     *
     *  @return  void
     */
    protected function activateWarningLogger()
    {
        $this->previousErrorHandler = set_error_handler(
            array($this, "warningHandler"),
            E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_USER_ERROR
        );
    }

    /**
     *  Deactivate warning logger.
     *
     *  Restores the previous error handler.
     *
     *  @return  void
     */
    protected function deactivateWarningLogger()
    {
        restore_error_handler();
    }
}
