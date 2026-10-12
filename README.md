# track.to PHP SDK

Monorepo for the track.to PHP SDK. It holds two packages, each published to a read-only split repository:

| Path | Composer package | Split repository |
| --- | --- | --- |
| `packages/trackto-client` | `expertsystemsau/trackto-php-client` | [`expertsystemsau/trackto-php-client`](https://github.com/expertsystemsau/trackto-php-client) |
| `packages/trackto-laravel` | `expertsystemsau/trackto-laravel-client` | [`expertsystemsau/trackto-laravel-client`](https://github.com/expertsystemsau/trackto-laravel-client) |

The client is framework-agnostic. The Laravel package builds on it for Laravel 12 and 13.

## Development

```bash
composer install
composer test
```

`composer test` is the full gate. It runs, in order: PHPStan (Larastan level 7), Pint in check mode, Pest type coverage, the Pest unit suites, the package dependency drift check, the package archive check and the API key scan.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the release policy and repository conventions, and [CHANGELOG.md](CHANGELOG.md) for release history.

## License

MIT. See [LICENSE.md](LICENSE.md).
