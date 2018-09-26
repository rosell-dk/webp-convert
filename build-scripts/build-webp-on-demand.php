<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once('PHPMerger.php');
//use PHPMerger;

// Build "webp-on-demand-1.php" (for non-composer projects)
PhpMerger::generate([
    'destination' => '../build/webp-on-demand-1.inc',

    'jobs' => [
        [
            'root' => './',
            'files' => [
                // put base classes here
                '../src/WebPConvert.php',
                '../src/Serve/ServeBase.php',
                '../src/Serve/ServeExistingOrHandOver.php',
                //'webp-on-demand-script.inc',
            ],
            'dirs' => [
                // dirs will be required in specified order. There is no recursion, so you need to specify subdirs as well.
                //'.',
            ]
        ]
    ]
]);

// Build "webp-on-demand-2.inc"
PhpMerger::generate([
    'destination' => '../build/webp-on-demand-2.inc',

    'jobs' => [
        [
            'root' => '../src/',

            'files' => [
                // put base classes here
                'Exceptions/WebPConvertBaseException.php',
                'Loggers/BaseLogger.php'
            ],
            'dirs' => [
                // dirs will be required in specified order. There is no recursion, so you need to specify subdirs as well.
                //'.',
                '.',
                'Converters',
                'Exceptions',
                'Converters/Exceptions',
                'Loggers',
                'Serve',
            ],
            'exclude' => [
                '/Serve/ServeBase.php',
                '/Serve/ServeExistingOrHandOver.php',
                '/WebPConvert.php'
            ]
        ],
    ]
]);

// Build "webp-convert.inc", containing the entire library (for the lazy ones)
PhpMerger::generate([
    'destination' => '../build/webp-convert.inc',

    'jobs' => [
        [
            'root' => '../src/',

            'files' => [
                // put base classes here
                'Exceptions/WebPConvertBaseException.php',
                'Loggers/BaseLogger.php'
            ],
            'dirs' => [
                // dirs will be required in specified order. There is no recursion, so you need to specify subdirs as well.
                //'.',
                '.',
                'Converters',
                'Exceptions',
                'Converters/Exceptions',
                'Loggers',
                'Serve',
            ],
            'exclude' => [
            ]
        ],
    ]
]);
