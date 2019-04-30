<?php
namespace WebPConvert\Serve;

/**
 * Add / Set HTTP header.
 *
 * This class does nothing more than adding two convenience functions for calling the "header" function.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class Header
{
    public static function addHeader($header)
    {
        header($header, false);
    }

    public static function setHeader($header)
    {
        header($header, true);
    }
}
