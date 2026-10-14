# Release Notes

## [Unreleased](https://github.com/expertsystemsau/trackto-php-sdk/commits/main)

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
