<?php

namespace {
    $pretend = [
        'functionsNotExisting' => [],
        'functionsExisting' => [],
        'extensionsNotExisting' => [],
        'extensionsExisting' => []
    ];
    $hasDeclaredMockFunctions = false;

    function reset_pretending()
    {
        global $pretend;
        $pretend = [
            'functionsNotExisting' => [],
            'functionsExisting' => [],
            'extensionsNotExisting' => [],
            'extensionsExisting' => []
        ];

    }
}

namespace WebPConvert\Convert\Converters {


    global $hasDeclaredMockFunctions;

    if(!$hasDeclaredMockFunctions)  {
        $hasDeclaredMockFunctions = true;
        function function_exists($function) {

            global $pretend;
            if (in_array($function, $pretend['functionsNotExisting'])) {
                return false;
            }
            if (in_array($function, $pretend['functionsExisting'])) {
                return true;
            }
            return \function_exists($function);
        }

        function extension_loaded($extension) {
            global $pretend;
            if (in_array($extension, $pretend['extensionsNotExisting'])) {
                return false;
            }
            if (in_array($extension, $pretend['extensionsExisting'])) {
                return true;
            }
            return \extension_loaded($extension);
        }
    }
}
