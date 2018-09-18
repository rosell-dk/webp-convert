<?php

namespace WebPConvert\Loggers;

class VoidLogger extends BaseLogger
{
    public function log($msg, $style = '')
    {
    }

    public function ln()
    {
    }
}
