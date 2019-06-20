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
    '/Exceptions/WebPConvertException.php',
    '/Exceptions/InvalidInputException.php',
    '/Exceptions/InvalidInput/InvalidImageTypeException.php',
    '/Exceptions/InvalidInput/TargetNotFoundException.php',
    '/Helpers/PathChecker.php',
    '/Helpers/InputValidator.php',
    '/Serve/Header.php',
    '/WebPConvert.php',
    //'../vendor/rosell-dk/image-mime-type-guesser/src/Detectors/AbstractDetector.php',
];

// Build "webp-on-demand-1.php" (for non-composer projects)

$success = PhpMerger::generate([
    'destination' => '../src-build/webp-on-demand-1.inc',

    'jobs' => [
        [
            'root' => '../src/',
            'files' => $filesInWod1,
            'dir-root' => '..',
            'dirs' => [
                // dirs will be required in specified order. There is no recursion, so you need to specify subdirs as well.
                'vendor/rosell-dk/image-mime-type-guesser/src',
                'vendor/rosell-dk/image-mime-type-guesser/src/Detectors',
                //'.',
            ]
        ]
    ]
]);
if (!$success) {
    exit(255);
}
//exit(0);
$jobsEverything = [
    [
        'root' => '../src/',
        'dir-root' => '../src',
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
            'Convert/Converters',
            'Convert/Converters/BaseTraits',
            'Convert/Converters/ConverterTraits',
            'Convert/Exceptions',
            'Convert/Exceptions/ConversionFailed',
            'Convert/Exceptions/ConversionFailed/ConverterNotOperational',
            'Convert/Exceptions/ConversionFailed/FileSystemProblems',
            'Convert/Exceptions/ConversionFailed/InvalidInput',
            'Convert/Helpers',
            'Convert',
            'Exceptions',
            'Exceptions/InvalidInput',
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
        'dir-root' => '../vendor/rosell-dk/image-mime-type-guesser/src',

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

// remove second job (ImageMimeTypeGuesser is included in wod-1)
unset($jobsWod2[1]);

// Build "webp-on-demand-2.inc"
// It must contain everything EXCEPT those classes that were included in 'webp-on-demand-1.inc'
$success = PhpMerger::generate([
    'destination' => '../src-build/webp-on-demand-2.inc',
    'jobs' => $jobsWod2
]);
if (!$success) {
    exit(255);
}
