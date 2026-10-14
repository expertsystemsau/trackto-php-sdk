# track.to PHP SDK

Monorepo for the track.to PHP SDK. It holds two packages, each published to a read-only split repository:

| Path | Composer package | Split repository |
| --- | --- | --- |
| `packages/trackto-client` | `expertsystemsau/trackto-php-client` | [`expertsystemsau/trackto-php-client`](https://github.com/expertsystemsau/trackto-php-client) |
| `packages/trackto-laravel` | `expertsystemsau/trackto-laravel-client` | [`expertsystemsau/trackto-laravel-client`](https://github.com/expertsystemsau/trackto-laravel-client) |

The client is framework-agnostic. The Laravel package builds on it for Laravel 12 and 13.

## Install

Both packages are on Packagist at `1.x` and follow semantic versioning.

```bash
composer require expertsystemsau/trackto-php-client     # any PHP 8.4+ project
composer require expertsystemsau/trackto-laravel-client # Laravel 12 or 13
```

- Guide: [track.to PHP SDK docs](https://www.track.to/docs/guide/php-sdk)
- Packagist: [trackto-php-client](https://packagist.org/packages/expertsystemsau/trackto-php-client), [trackto-laravel-client](https://packagist.org/packages/expertsystemsau/trackto-laravel-client)
- Usage: the [client README](packages/trackto-client/README.md) and the [Laravel README](packages/trackto-laravel/README.md)
- Full behaviour contract: [docs/conventions.md](docs/conventions.md)
- API reference: [www.track.to/docs/api](https://www.track.to/docs/api)

## Development

```bash
composer install
composer test
```

`composer test` is the full gate. It runs, in order: PHPStan (Larastan level 7), Pint in check mode, Pest type coverage, the Pest unit suites, the package dependency drift check, the package archive check and the API key scan.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the release policy and repository conventions, and [CHANGELOG.md](CHANGELOG.md) for release history.

## License

MIT. See [LICENSE.md](LICENSE.md).
