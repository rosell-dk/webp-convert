<?php

namespace WebPConvert\Serve {

    class MockedHeader {

        private static $headers = [];

        public static function header($header, $replace) {
            self::$headers[] = [$header, $replace];
            //echo $header;
        }

        public static function getHeaders() {
            return self::$headers;
        }

        public static function reset() {
            self::$headers = [];
        }

        public static function hasHeader($header)
        {
            return in_array($header, array_column(self::$headers, 0));
        }

        public static function hasHeaderContaining($headerText)
        {
            $headerValues = array_column(self::$headers, 0);
            foreach ($headerValues as $header) {
                if (false !== strpos($header, $headerText)) {
                    return true;
                }
            }

            return false;
        }
    }

    //use WebPConvert\Tests\Serve\MockedHeader;
    function header($h, $replace)
    {
        MockedHeader::header($h, $replace);
    }

}
