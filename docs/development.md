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

## Unit Testing
To run all the unit tests do this:
```
composer test
```

Individual test files can be executed like this:
```
composer test tests/Converters/WPCTest
composer test tests/Serve/ServeConvertedTest
```

## Coding styles
WebPConvert complies with the [PSR-2](https://www.php-fig.org/psr/psr-2/) coding standard.

To validate coding style of all files, do this:
```
composer phpcs src
```

To automatically fix the coding style of all files, using [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer), do this:
```
composer phpcbf src
```

Or, alternatively, you can fix with the use the [PHP-CS-FIXER](https://github.com/FriendsOfPHP/PHP-CS-Fixer) library instead:
```
composer cs-fix
```

## Running all tests in one command
The following script runs the unit tests, checks the coding styles and validates composer.json. Run this before pushing to github
```
composer ci
```
