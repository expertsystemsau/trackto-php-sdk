# Release Notes

## [Unreleased](https://github.com/expertsystemsau/trackto-php-sdk/commits/main)

## v1.1.0 - 2026-10-14

### Changed

- `expertsystemsau/trackto-php-client` now supports PHP 8.2 and 8.3 as well as 8.4 and 8.5. Its class constants no longer declare a type, because typed constants need PHP 8.3. Their values are unchanged.
- `expertsystemsau/trackto-laravel-client` still requires PHP 8.4.

## v1.0.0 - 2026-10-14

The first stable release. The API is unchanged from v0.1.0. From this release both packages follow semantic versioning: a breaking change needs a new major version.

### Changed

- `expertsystemsau/trackto-laravel-client` now requires `expertsystemsau/trackto-php-client` `^1.0`.

### Verified

- Both packages were installed from Packagist into a plain PHP project and a fresh Laravel 13 app, and every operation was run against a live track.to instance: links, pagination, updates, analytics with recorded clicks, bulk actions, folders, tags, typed errors, the Artisan commands, and `TrackTo::fake()`.

## v0.1.0 - 2026-10-14

First release of the track.to PHP SDK: a framework-free client and a Laravel integration, released together under one version.

### Client

- 13 operations across links, folders and tags, including link analytics and bulk move, tag and delete.
- Typed exceptions, chosen from the `name` in the API's error response.
- Retries for HTTP 429 (honouring the server's wait, up to a 3-second cap), and for GET requests that fail with a 5xx or a connection error. Writes are never re-sent.
- Lazy pagination: iterate every link without loading all pages at once.
- `Testing\Fixtures` for building realistic API responses in your own tests.

### Laravel

- Auto-discovered service provider and the `TrackTo` facade.
- Publishable `config/trackto.php`.
- `TrackTo::fake()` to stub the API in tests.
- Artisan commands `trackto:ping` and `trackto:shorten`.

### Compatibility

- PHP 8.4 and 8.5.
- Laravel 12 and 13.
- API version header `Track-Version: 2026-07-06`.

### Known limitations

- Exception dumps (for example in an error page or log) can include the `Authorization` header, which carries your API key. Keep debug output away from untrusted readers.
- When a write returns a 2xx response whose body cannot be read, the write was applied. Do not repeat it.
- `sendAsync` and `pool` are not supported.
- Bulk calls are never retried.

See the [conventions guide](https://github.com/expertsystemsau/trackto-php-sdk/blob/main/docs/conventions.md) for the full behaviour of every call.
