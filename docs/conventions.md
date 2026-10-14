# SDK conventions

The shape every track.to SDK follows. The PHP client implements it first; the other language SDKs copy it. `tests/ConventionsDocTest.php` checks the examples below against the code, so a changed signature or default fails the suite until this page is updated.

## Client shape

`TrackToClient` is the Saloon connector. It is immutable by convention: the `with*()` methods return a copy and leave the receiver untouched.

| Method | Changes |
| --- | --- |
| `withApiKey()` | The bearer key |
| `withBaseUrl()` | The API host |
| `withVersion()` | The `Track-Version` header; `null` omits it |
| `withConnectTimeout()` | Connect timeout, in seconds |
| `withTimeout()` | Request timeout, in seconds |
| `withUserAgentSuffix()` | Text appended to the User-Agent |
| `withMockClient()` | A Saloon `MockClient`, for tests |
| `withRetry()` | Total attempts and the longest wait |
| `withoutRetry()` | One attempt only |

These are the supported way to change a client. Inherited Saloon mutators (`authenticate()`, `withTokenAuth()`, `middleware()`, `debug()` and the public `$tries` property) change the instance in place, so avoid them on a shared client. `sendAsync()` and `pool()` throw `LogicException`: a rejected async promise keeps Guzzle's exception, which holds the `Authorization` header, so only `send()` is supported.

Every constructor argument is passed by name:

```php
$client = new TrackToClient(
    apiKey: 'tk_...',
    baseUrl: 'https://api.track.to',
    version: '2026-07-06',
    connectTimeout: 10.0,
    timeout: 30.0,
    userAgentSuffix: 'my-app/1.2',
);
```

A blank key throws `ConfigurationException`, as does a base URL that is not `https` (plain `http` is accepted only for `localhost`, `*.localhost` and `127.0.0.1`), a timeout that is not a finite number above zero, and an API version with control characters.

## Resources and methods

`$client->links()` returns a fresh resource each call. Resources group operations by API area; Phase 12 adds `folders()` and `tags()` next to `links()`.

Method names come from one vocabulary: `list`, `all`, `get`, `create`, `update` and `delete`. Phase 12 adds `update`, `delete`, analytics and bulk actions. Inputs are named arguments, never input objects.

`list()` fetches one page and returns a `LinkPage`. `all()` returns a lazy iterator over every page; it can be iterated more than once, and each iteration makes fresh requests. It counts pages itself rather than trusting the server's `current_page`, and stops after 10,000 pages whatever the server reports.

```php
use ExpertSystems\TrackTo\Enums\LinkSort;

$page = $client->links()->list(
    search: 'launch',
    folderId: 4,
    tag: 9,
    sort: LinkSort::Recent,
    perPage: 50,
    page: 2,
);

foreach ($client->links()->all(search: 'launch', sort: LinkSort::Recent, perPage: 100) as $link) {
    echo $link->slug;
}

$link = $client->links()->get('007');

$created = $client->links()->create(
    destinationUrl: 'https://example.com/landing',
    slug: 'launch',
    expiresAt: new DateTimeImmutable('+30 days'),
    password: 'hunter2',
    folderId: 4,
    tags: ['launch', 'q4'],
);
```

Slugs are strings and are percent-encoded into the path. A blank slug throws before any request is made.

## Data objects

Data objects are `final readonly` classes built with `fromArray()`. Dates are `DateTimeImmutable`. An enum maps a value it does not know to its `Unknown` case instead of failing. Fields the SDK does not know are ignored, so additive API changes ship under the same API version.

Optional fields are read tolerantly: a date that is not an RFC 3339 timestamp becomes `null`, and a malformed `folder` or tag is dropped. A missing or mistyped required field (`id`, `slug`, `destination_url`, `short_url`, `has_password`, `click_count`) is not tolerated: a 2xx response that cannot be turned into a data object throws `UnexpectedResponseException`.

## Exceptions

Every exception the SDK throws implements the `TrackToException` interface, so one `catch` covers them all. It is an interface because the API exceptions extend Saloon's `RequestException`.

The naming rule for API errors is the Studly-cased error code plus `Exception`, with a trailing `_error` dropped: `rate_limited` becomes `RateLimitedException` and `validation_error` becomes `ValidationException`.

| Code | Class | Typical status |
| --- | --- | --- |
| `validation_error` | `ValidationException` | 422 |
| `unauthorized` | `UnauthorizedException` | 401 |
| `forbidden` | `ForbiddenException` | 403 |
| `missing_user_agent` | `MissingUserAgentException` | 403 |
| `unsupported_version` | `UnsupportedVersionException` | 400 |
| `not_found` | `NotFoundException` | 404 |
| `rate_limited` | `RateLimitedException` | 429 |
| `server_error` | `ServerException` | 500 |
| `team_suspended` | `TeamSuspendedException` | 403 |
| `bad_request` | `BadRequestException` | 400 |

The client picks the class from the error envelope's `name`, not from the status, because 403 carries three codes and 400 carries two. A code the SDK does not know falls back to the status:

| Status | Class |
| --- | --- |
| 401 | `UnauthorizedException` |
| 403 | `ForbiddenException` |
| 404 | `NotFoundException` |
| 422 | `ValidationException` |
| 429 | `RateLimitedException` |
| 500 and above | `ServerException` |
| other 4xx | `BadRequestException` |
| anything else | `UnexpectedResponseException` |

Three exceptions are not API errors:

- `ConnectionException`: the request never got a response (DNS, refused connection, timeout).
- `UnexpectedResponseException`: a response arrived but its body is unusable, such as an HTML error page or a 3xx. It carries the status.
- `ConfigurationException`: the client was built with a blank key, an insecure base URL, an invalid timeout, a version or suffix containing control characters, an invalid retry setting, or a request that cannot be built, such as invalid UTF-8 in a body field.

API exceptions expose:

| Accessor | Returns |
| --- | --- |
| `status()` | The HTTP status |
| `getMessage()` | The envelope's message |
| `errors()` | Field errors as `field => list of messages` |
| `errorsFor($field)` | The messages for one field, or an empty list |
| `rateLimit()` | A `RateLimitInfo` |
| `response()` | The Saloon `Response` |

`RateLimitInfo` has four nullable integer fields: `limit`, `remaining`, `reset` and `retryAfter`. A field is `null` when the server did not send the header it comes from. When `remaining` is 0 and no `Retry-After` header is present, `retryAfter` is the reset time.

## Updates (Phase 12)

Update methods take named arguments where an omitted argument means unchanged and `null` means clear. The default behind "omitted" is the `Omitted` enum, because `unset` is a reserved word in PHP and cannot name a class. `removePassword: true` is the only way to clear a password; passing `password: null` leaves it unchanged.

```php
$client->links()->update(
    slug: 'launch',
    destinationUrl: 'https://example.com/new',
    expiresAt: null,                 // clear the expiry
    folderId: 4,                     // move to folder 4
    removePassword: true,            // drop the password
);                                   // everything else is left unchanged
```

The provisional signature, which Phase 12 implements:

```php
public function update(
    string $slug,
    string $destinationUrl,
    string|Omitted $newSlug = Omitted::Value,
    DateTimeInterface|Omitted|null $expiresAt = Omitted::Value,
    int|Omitted|null $folderId = Omitted::Value,
    array|Omitted $tags = Omitted::Value,
    ?string $password = null,
    bool $removePassword = false,
): LinkData;
```

The semantics are settled. The parameter names are provisional until Phase 12 ships `update()`.

## Retry

- A 429 with `Retry-After` or `RateLimit` reset timing within the 3-second cap is retried for any method, because the request was not processed.
- Reads (`GET`) also retry on a 5xx and on a connection failure, waiting 0.5 seconds between attempts.
- No other 4xx is retried.
- A write is never re-sent after a 5xx or a connection failure, because the server may have processed it.
- A wait above the cap means no retry: the exception is thrown and `rateLimit()->retryAfter` holds the wait.
- A 429 with no timing information is not retried.

The default is 3 attempts in total. Configure it with a copy:

```php
$patient = $client->withRetry(tries: 5, maxWait: 10.0);
$once = $client->withoutRetry();
```

`withRetry()` throws `ConfigurationException` for fewer than one attempt or a `maxWait` that is negative, `NAN` or infinite. Typed exceptions still throw once the attempts run out.

## Timeouts and transport

The defaults are a connect timeout of 10 seconds and a request timeout of 30 seconds. Redirects are off, so a 3xx is a failure and a redirect can never carry the bearer key to another host.

The base URL is `https://api.track.to`. `Track-Version` is pinned to the API spec version the SDK was built against; `withVersion(null)` omits the header.

The User-Agent is `trackto-php/<version> php/<PHP_VERSION>`, followed by the suffix when one is set. A suffix containing control characters is rejected with `ConfigurationException`.

## Testing

`ExpertSystems\TrackTo\Testing\Fixtures` is public. `link()` builds a complete link body, `page()` a list response, `error()` an error envelope and `errorResponse()` a Saloon `MockResponse` for an error code. They are checked against the OpenAPI spec, so mocks cannot drift from the real API.

`withMockClient()` returns a copy of the client wired to a `MockClient`:

```php
$client = $client->withMockClient(new MockClient([
    MockResponse::make(Fixtures::page([Fixtures::link()])),
    Fixtures::errorResponse('rate_limited', headers: ['Retry-After' => '60']),
]));
```

## Security notes

The API key is held as a `SensitiveParameterValue`. `var_dump`, `print_r` and `__debugInfo` omit it, and `serialize()` throws.

One residual risk is accepted. An API exception keeps the Saloon `Response`, and that response's request carries the `Authorization` header. Dumping a thrown exception with `var_export` or Symfony's `dd()` can therefore print the key. Do not dump exceptions into logs in production; log `getMessage()` and `status()` instead.

`debug()` prints request headers, so keep it out of production.

## Contract harness

`spec/openapi.json` is a copy of the track.to OpenAPI document. Refresh it with `php bin/sync-openapi`, or `php bin/sync-openapi --source=/path/to/openapi.json` to read it from somewhere else.

The contract tests compare the SDK against that spec: every operation needs a request class, every response schema a data object, every error code an exception, and the default `Track-Version` must equal the spec version. Operations that are not built yet are listed in `tests/Contract/excluded-operations.php`; Phase 12 empties that list.
