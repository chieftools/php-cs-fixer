## Chief Tools PHP CS Fixer

[![Total Downloads](https://poser.pugx.org/chieftools/php-cs-fixer/downloads)](https://packagist.org/packages/chieftools/php-cs-fixer)
[![Monthly Downloads](https://poser.pugx.org/chieftools/php-cs-fixer/d/monthly)](https://packagist.org/packages/chieftools/php-cs-fixer)
[![Latest Stable Version](https://poser.pugx.org/chieftools/php-cs-fixer/v/stable)](https://packagist.org/packages/chieftools/php-cs-fixer)
[![License](https://poser.pugx.org/chieftools/php-cs-fixer/license)](https://packagist.org/packages/chieftools/php-cs-fixer)

Opinionated PHP CS Fixer configuration for Chief Tools projects.

### Installation

Install the package as a development dependency:

```bash
composer require --dev chieftools/php-cs-fixer
```

Allow the Composer plugin so the shared `.editorconfig` can be copied to your project:

```json
{
    "config": {
        "allow-plugins": {
            "chieftools/php-cs-fixer": true
        }
    }
}
```

Create a `.php-cs-fixer.php` file in your project:

```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/routes')
    ->in(__DIR__ . '/database')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return ChiefTools\PhpCsFixer\Config::make($finder);

```

### Custom project rules

Project rules may be merged on top of the Chief Tools defaults:

```php
return ChiefTools\PhpCsFixer\Config::make($finder, [
    'yoda_style'      => true,
    'ordered_imports' => [
        'sort_algorithm' => 'alpha',
    ],
]);
```

### EditorConfig

This package contains the canonical Chief Tools `.editorconfig` in the package root. When the Composer plugin is allowed, it copies that file into the consuming project on install and update.

The plugin:

- creates `.editorconfig` when missing
- skips it when it already matches the package version
- overwrites it when it differs from the package version

If plugins are disabled, copy it manually from:

```text
vendor/chieftools/php-cs-fixer/.editorconfig
```

### Custom rules

#### `ChiefTools/phpdoc_fqcn`

This rule expands imported class names inside PHPDoc annotations:

```diff
  use App\Models\User;
  use Illuminate\Database\Eloquent\Relations\HasMany;

- /** @return HasMany<Domain> */
+ /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Domain> */
```

Executable PHP type hints may still use normal imports.

#### `ChiefTools/nested_method_chaining_indentation`

This rule keeps nested argument method chains aligned with the first object operator on the expression line:

```diff
  return wrap(
      $service->items()
-         ->filter()
+             ->filter()
  );
```

Statement-level chains still use PHP CS Fixer's regular `method_chaining_indentation` behavior.
