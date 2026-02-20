# InSquare Pimcore DeepL Bundle

Bundle for integrating Pimcore with DeepL. It enables translating:
- `localizedfields` in objects
- translated documents (full document)
- individual areablock blocks (only when the block is overridden)

## Requirements
- PHP 8.2
- Symfony 6.4
- Pimcore 11

## Installation (Composer)
1. Install the package:
```bash
composer require in-square/pimcore-deepl-bundle
```
2. If the bundle was not added automatically, register it in `config/bundles.php`:
```php
InSquare\PimcoreDeeplBundle\InSquarePimcoreDeeplBundle::class => ['all' => true],
```
3. Run `bin/console assets:install`.

## Configuration
Set the following Website Settings:
- `deepl_api_key` – DeepL API key
- `deepl_account_type` – `FREE` or `PRO`

Optional YAML configuration (e.g. `config/packages/in_square_pimcore_deepl.yaml`):
```yaml
in_square_pimcore_deepl:
  overwrite:
    documents: false
    objects: false
```

## License
GPL-3.0-or-later
