<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once('PHPMerger.php');
//use PHPMerger;


/*$filesInWod1 = [
    '/Serve/ServeBase.php',
    '/Serve/ServeExistingOrHandOver.php',
    '/WebPConvert.php'
];*/

$filesInWod1 = [
    '/Serve/ServeConvertedWebP.php',
    '/Serve/ServeConvertedWebPWithErrorHandling.php',
    '/Serve/ServeFile.php',
    '/Serve/Header.php',
    '/WebPConvert.php'
];

// Build "webp-on-demand-1.php" (for non-composer projects)

$success = PhpMerger::generate([
    'destination' => '../src-build/webp-on-demand-1.inc',

    'jobs' => [
        [
            'root' => '../src/',
            'files' => $filesInWod1,
            'dirs' => [
                // dirs will be required in specified order. There is no recursion, so you need to specify subdirs as well.
                //'.',
            ]
        ]
    ]
]);
if (!$success) {
    exit(255);
}

$jobsEverything = [
    [
        'root' => '../src/',

        'files' => [
            // put base classes here
            'Options/Option.php',
            'Convert/Converters/AbstractConverter.php',
            'Exceptions/WebPConvertException.php',
            'Convert/Exceptions/ConversionFailedException.php',
            //'Convert/BaseConverters',
            //'Convert/Converters',
            //'Convert/Exceptions',
            //'Loggers',
            //'Serve',
        ],
        'dirs' => [
            // dirs will be required in specified order. There is no recursion, so you need to specify subdirs as well.
            // TODO: Implement recursion in PHPMerger.php,
            '.',
            'Options',
            'Convert/Converters/BaseTraits',
            'Convert/Converters/ConverterTraits',
            'Convert/BaseConverters',
            'Convert/Converters',
            'Convert/Exceptions',
            'Convert/Exceptions/ConversionFailed',
            'Convert/Exceptions/ConversionFailed/ConverterNotOperational',
            'Convert/Exceptions/ConversionFailed/FileSystemProblems',
            'Convert/Exceptions/ConversionFailed/InvalidInput',
            'Convert/Helpers',
            'Convert',
            'Exceptions',
            'Helpers',
            'Loggers',
            'Serve',
            'Serve/Exceptions',
        ],
        'exclude' => [
        ]
    ],
    [
        'root' => '../vendor/rosell-dk/image-mime-type-guesser/src/',

        'files' => [
            // put base classes here
            'Detectors/AbstractDetector.php',
        ],
        'dirs' => [
            // dirs will be required in specified order. There is no recursion, so you need to specify subdirs as well.
            //'.',
            '.',
            'Detectors',
        ],
        'exclude' => [
        ]
    ],
];

// Build "webp-convert.inc", containing the entire library (for the lazy ones)
$success = PhpMerger::generate([
    'destination' => '../src-build/webp-convert.inc',
    'jobs' => $jobsEverything
]);
if (!$success) {
    exit(255);
}

$jobsWod2 = $jobsEverything;
$jobsWod2[0]['exclude'] = $filesInWod1;

// Build "webp-on-demand-2.inc"
// It must contain everything EXCEPT those classes that were included in 'webp-on-demand-1.inc'
$success = PhpMerger::generate([
    'destination' => '../src-build/webp-on-demand-2.inc',
    'jobs' => $jobsWod2
]);
if (!$success) {
    exit(255);
}
