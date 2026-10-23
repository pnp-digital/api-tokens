# generic-laravel-helpers

### A collection of generic global helper functions for Laravel 9 and above

#### Adding the package to your laravel project

1. Add the following to `composer.json`:

```json
    "repositories": [
        {
            "type": "vcs",
            "url":  "git@github.com:pnp-digital/generic-laravel-helpers.git",
            "no-api": true
        }
    ],
```

2. Ensure you have a public key added which can access this SSH repository
3. Issue a composer require command: ```composer require pnp-digital/generic-laravel-helpers:dev-main```

## Included helper functions

The following helper functions are included in this package

### pretty_date

```php
    /**
     * Make a date follow the correct format for UX
     * @param string|DateTimeInterface|null $date Date to format
     * If null, an invalid date, or empty string is passed in, a dash ('-') is returned.
     * @return string Beautified date
     */
    function pretty_date(string|DateTimeInterface|null $date): string;
```

### str_ordinal

```php
    /**
     * Append an ordinal indicator to a numeric value.
     *
     * @param int|string $value
     * @param bool $superscript
     * @return string
     */
    function str_ordinal(int|string $value, bool $superscript = false): string;
```

### str_pos

```php
    /**
     * Version of built-in strpos function, except failure to find results in PHP_INT_MAX,
     * so we can grab the smallest length of multiple searches
     *
     * @param string $haystack
     * @param string $needle
     * @param int $offset
     * @return int
     */
    function str_pos(string $haystack, string $needle, int $offset = 0): int;
```

## Adding new helpers to this package

To add a helper to the package, simply create a standard laravel helper file under src/app/Helpers in the package. Then, go into `composer.json` for the package and add the path to the helper under the autoload -> files key in the JSON structure:

```json
    "autoload": {
        "classmap": [
            "src/"
        ],
        "psr-4": {
            "Pipeandpiper\\": "src/"
        },
        "files": [
           "src/app/Helpers/str_pos.php",
           "src/app/Helpers/str_ordinal.php",
           "src/app/Helpers/pretty_date.php"
           "src/app/Helpers/yournewhelper.php"
        ]
    }
```