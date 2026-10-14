# track.to Laravel client

Laravel integration for the track.to API. Supports Laravel 12 and 13 on PHP 8.4 or later. The service provider is auto-discovered.

This repository is a read-only split of [`expertsystemsau/trackto-php-sdk`](https://github.com/expertsystemsau/trackto-php-sdk). Open issues and pull requests there, not here.

## Compatibility

| Version | PHP | Laravel | track.to API (Track-Version) |
| --- | --- | --- | --- |
| 1.x | 8.4, 8.5 | 12, 13 | 2026-07-06 |

## Installation

```bash
composer require expertsystemsau/trackto-laravel-client
```

The service provider and the `TrackTo` facade are auto-discovered, so there is nothing to register.

## Configuration

Set your API key in `.env`:

```dotenv
TRACKTO_API_KEY=tk_...
```

Publish the config file if you want to edit it:

```bash
php artisan vendor:publish --tag=trackto-config
```

| Variable | Config key | Default |
| --- | --- | --- |
| `TRACKTO_API_KEY` | `trackto.api_key` | none, required |
| `TRACKTO_BASE_URL` | `trackto.base_url` | `https://api.track.to` |
| `TRACKTO_API_VERSION` | `trackto.version` | `2026-07-06` |
| `TRACKTO_CONNECT_TIMEOUT` | `trackto.connect_timeout` | `10` seconds |
| `TRACKTO_TIMEOUT` | `trackto.timeout` | `30` seconds |
| `TRACKTO_USER_AGENT_SUFFIX` | `trackto.user_agent_suffix` | none |

A blank optional variable means its default. A missing API key does not stop the app booting: the first use of the client throws a `ConfigurationException` naming `TRACKTO_API_KEY`.

## Usage

Use the facade:

```php
use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;

$link = TrackTo::links()->create(destinationUrl: 'https://example.com/landing');
```

Or type-hint `ExpertSystems\TrackTo\TrackToClient` for injection, or resolve `app('trackto')`. All three give the same client. See the [client package](https://github.com/expertsystemsau/trackto-php-client) for every operation.

### Per-tenant keys

Call `withApiKey()` to use a different key for one piece of work:

```php
TrackTo::withApiKey($team->tracktoKey)->links()->create(destinationUrl: 'https://example.com/landing');
```

Every `with*()` method returns a copy, and the shared client keeps its configured key.

Warning: methods the client inherits from Saloon (`authenticate()`, `withTokenAuth()`, `middleware()`, `debug()`) change the shared client in place. Never call them through the facade or on the container instance. Use `withApiKey()` instead.

## Testing

`TrackTo::fake()` swaps a client wired to Saloon's `MockClient` into the facade and the container, and returns the mock:

```php
use ExpertSystems\TrackTo\Requests\CreateLinkRequest;
use ExpertSystems\TrackTo\Testing\Fixtures;
use Saloon\Http\Faking\MockResponse;

$mock = TrackTo::fake([MockResponse::make(['data' => Fixtures::link()], 201)]);

// run the code under test

$mock->assertSent(CreateLinkRequest::class);
$mock->assertSentCount(1);
```

Read a request body with `$mock->getLastPendingRequest()?->body()?->all()`.

- A request with no matching mock response throws instead of reaching the network.
- No API key is needed while faked.
- Call `fake()` before the code under test resolves the client. An object that stored the client earlier keeps the real one.
- `TrackTo::shouldReceive()` cannot mock the client because it is `final`. Use `fake()`.
- To use `Saloon::fake()` instead, install `saloonphp/laravel-plugin` yourself.

## Commands

```bash
php artisan trackto:ping
```

Checks the configured API key with one authenticated request. Exits 0 when the key is accepted and 1 otherwise.

```bash
php artisan trackto:shorten https://example.com
```

Creates a link and prints only the short URL on stdout, so it can be captured in a script:

```bash
URL=$(php artisan trackto:shorten https://example.com) || exit 1
```

Both commands print failures to stderr and exit 1, so a failed run leaves `$URL` empty and the message still reaches your terminal or log.

## Octane

The client is immutable and safe to share across requests. To build it once per worker, add `ExpertSystems\TrackTo\TrackToClient::class` to `octane.warm` in `config/octane.php`. Warming resolves the client when the worker starts, so the API key must be valid then (a missing key throws `ConfigurationException` at worker start), and a changed key or config only takes effect after `php artisan octane:reload`.

See the [changelog](https://github.com/expertsystemsau/trackto-php-sdk/blob/main/CHANGELOG.md) for release history.

## License

MIT. See [LICENSE.md](LICENSE.md).
