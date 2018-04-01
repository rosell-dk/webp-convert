<?php
$finder = Symfony\CS\Finder\DefaultFinder::create();
$finder->in([
    __DIR__
]);
$finder->exclude('Tests');

$config = Symfony\CS\Config\Config::create();
$config->finder($finder);
$config->level(Symfony\CS\FixerInterface::PSR2_LEVEL);

return $config;

