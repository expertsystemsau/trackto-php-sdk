# track.to PHP client

Framework-agnostic PHP client for the track.to API. Requires PHP 8.4 or later.

This repository is a read-only split of [`expertsystemsau/trackto-php-sdk`](https://github.com/expertsystemsau/trackto-php-sdk). Open issues and pull requests there, not here.

## Compatibility

| Version | PHP | Laravel | track.to API (Track-Version) |
| --- | --- | --- | --- |
| 0.1.x | 8.4, 8.5 | any (framework-agnostic) | 2026-07-06 |

## Install

```bash
composer require expertsystemsau/trackto-php-client
```

## Quick start

Build the client with named arguments and make one call per resource:

```php
use ExpertSystems\TrackTo\TrackToClient;

$client = new TrackToClient(apiKey: getenv('TRACKTO_API_KEY'));

$link = $client->links()->create(destinationUrl: 'https://example.com/landing', slug: 'launch');
echo $link->shortUrl;

$page = $client->links()->list(search: 'launch', perPage: 50);

foreach ($client->links()->all() as $link) {
    echo $link->slug;
}

$folder = $client->folders()->create(name: 'Campaigns');
$tag = $client->tags()->create(name: 'launch');
```

`list()` fetches one page. `all()` walks every page lazily.

## Errors

Every exception the SDK throws implements `TrackToException`. API errors expose `status()`, `errors()` and `rateLimit()`:

```php
use ExpertSystems\TrackTo\Exceptions\RateLimitedException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;

try {
    $client->links()->get('launch');
} catch (RateLimitedException $e) {
    sleep($e->rateLimit()->retryAfter ?? 1);
} catch (TrackToException $e) {
    error_log($e->getMessage());
}
```

## Testing

`withMockClient()` returns a copy of the client wired to Saloon's `MockClient`. `Fixtures` builds response bodies that match the API spec:

```php
use ExpertSystems\TrackTo\Testing\Fixtures;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

$client = $client->withMockClient(new MockClient([
    MockResponse::make(Fixtures::page([Fixtures::link()])),
]));
```

The full contract, including retries, pagination and update semantics, is in the [conventions](https://github.com/expertsystemsau/trackto-php-sdk/blob/main/docs/conventions.md).

See the [changelog](https://github.com/expertsystemsau/trackto-php-sdk/blob/main/CHANGELOG.md) for release history.

## License

MIT. See [LICENSE.md](LICENSE.md).
