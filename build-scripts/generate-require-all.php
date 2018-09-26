<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

//require_once('RequireGenerator.php');

//use RequireGenerator;

class RequireGenerator
{
    private static $required = [];

    private static function add_require_once($path)
    {
        if ($path[0] != '/') {
            $path = '/' . $path;
        }

        self::$required[] = $path;

        //echo 'require_once(__DIR__  . "' . $path . '");' . "\n";
    }
    public static function generate($def)
    {
        // load base classes, which are required for other classes
        foreach ($def['files'] as $file) {
            self::add_require_once('/' . $file);
        }

        // load dirs in defined order. No recursion.
        foreach ($def['dirs'] as $dir) {
            $dirAbs = __DIR__  . '/' . $def['dir'] . '/' . $dir;

            $files = glob($dirAbs . '/*.php');
            foreach ($files as $file) {
                //            echo $file . "\n";
                // only require files that begins with uppercase (A-Z)
                if (preg_match('/\/[A-Z][a-zA-Z]*\.php/', $file)) {
                    $file = str_replace(__DIR__ . '/' . $def['dir'], '', $file);
                    $file = str_replace('./', '', $file);
                    self::add_require_once($file);
                }
            }
        }

        // remove duplicates
        self::$required = array_unique(self::$required);

        // generate file content
        $data = '';
        $data .= "<?php\n";
        foreach (self::$required as $path) {
            $data .= 'require_once __DIR__  . "' . $path . '";' . "\n";
        }

        // generate file
        $my_file = __DIR__ . '/' . $def['destination'];
        $handle = fopen($my_file, 'w') or die('Cannot open file:  '.$my_file);
        fwrite($handle, $data);

        echo "'" . $my_file . "' was generated\n";
    }
}


RequireGenerator::generate([
    'dir' => '../src',
    'destination' => '../src/require-all.inc',
    'files' => [
        // put base classes here
        'Exceptions/WebPConvertBaseException.php',
        'Loggers/BaseLogger.php'
    ],
    'dirs' => [
        // dirs will be required in specified order. There is no recursion, so you need to specify subdirs as well.
        '.',
        'Converters',
        'Exceptions',
        'Converters/Exceptions',
        'Loggers',
        'Serve',
    ]
]);
