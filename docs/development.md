# Development

## Setting up the environment.

First, clone the repository:
```
cd whatever/folder/you/want
git clone https://github.com/rosell-dk/webp-convert.git
```

Then install the dev tools with composer:

```
composer install
```

## Coding style fixing

`WebPConvert` uses the [PHP-CS-FIXER](https://github.com/FriendsOfPHP/PHP-CS-Fixer) library (based on squizlabs' [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)) so all PHP files automagically comply with the [PSR-2](https://www.php-fig.org/psr/psr-2/) coding standard.

```
// Dry run - without making changes to any files
composer cs-dry

// Production mode
composer cs-fix
```

## Unit testing

Testing is done with Sebastian Bergmann's excellent testing framework [PHPUnit](https://github.com/sebastianbergmann/phpunit), like this:

```
composer test
```

Individual test files can be executed like this:

```
composer test tests/Converters/WPCTest
```
