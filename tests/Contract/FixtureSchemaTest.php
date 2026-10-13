<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Contract\Support\Checks;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

const SPEC_URI = 'https://track.to/openapi.json';

/**
 * @param  array<string, mixed>  $data
 */
function validateAgainstSpec(array $data, string $pointer): ?string
{
    static $validator = null;

    if ($validator === null) {
        $validator = new Validator;
        $validator->resolver()->registerRaw(
            json_decode((string) file_get_contents(dirname(__DIR__, 2).'/spec/openapi.json'), false, 512, JSON_THROW_ON_ERROR),
            SPEC_URI,
        );
    }

    // Round-trip through JSON so an empty map becomes an object, not a list.
    $result = $validator->validate(json_decode(json_encode($data, JSON_THROW_ON_ERROR)), SPEC_URI.'#'.$pointer);

    return $result->isValid() ? null : json_encode((new ErrorFormatter)->format($result->error()));
}

const LINK_SCHEMA = '/components/schemas/LinkResource';
// RFC 6901 §6: braces in a URI fragment are percent-encoded; opis rejects them raw.
const SHOW_LINK_SCHEMA = '/paths/~1links~1%7Blink%7D/get/responses/200/content/application~1json/schema';
const ANALYTICS_SCHEMA = '/paths/~1links~1%7Blink%7D~1analytics/get/responses/200/content/application~1json/schema';
const BULK_SCHEMA = '/paths/~1links~1bulk/post/responses/200/content/application~1json/schema';
const LIST_LINKS_SCHEMA = '/paths/~1links/get/responses/200/content/application~1json/schema';
const FOLDER_SCHEMA = '/components/schemas/FolderResource';
const LIST_FOLDERS_SCHEMA = '/paths/~1folders/get/responses/200/content/application~1json/schema';
const CREATE_FOLDER_SCHEMA = '/paths/~1folders/post/responses/201/content/application~1json/schema';
const TAG_SCHEMA = '/components/schemas/TagResource';
const LIST_TAGS_SCHEMA = '/paths/~1tags/get/responses/200/content/application~1json/schema';
const CREATE_TAG_SCHEMA = '/paths/~1tags/post/responses/201/content/application~1json/schema';

it('validates link() against LinkResource', function (): void {
    expect(validateAgainstSpec(Fixtures::link(), LINK_SCHEMA))->toBeNull();
});

it('validates the single-link envelope', function (): void {
    expect(validateAgainstSpec(['data' => Fixtures::link()], SHOW_LINK_SCHEMA))->toBeNull();
});

it('validates analytics() against the analytics response', function (array $body): void {
    expect(validateAgainstSpec($body, ANALYTICS_SCHEMA))->toBeNull();
})->with([
    'default' => fn (): array => Fixtures::analytics(),
    'all empty' => fn (): array => Fixtures::analytics(['total' => 0, 'overTime' => [], 'topReferrers' => [], 'topCountries' => []]),
]);

it('validates bulk() against the bulk response', function (array $body): void {
    expect(validateAgainstSpec($body, BULK_SCHEMA))->toBeNull();
})->with([
    'default' => fn (): array => Fixtures::bulk(),
    'delete two' => fn (): array => Fixtures::bulk(['action' => 'delete', 'slugs' => ['a', 'b']]),
]);

it('rejects a bulk body with an action outside the spec enum', function (): void {
    expect(validateAgainstSpec(Fixtures::bulk(['action' => 'archive']), BULK_SCHEMA))->not->toBeNull();
});

it('validates page() shapes against the list response', function (array $page): void {
    expect(validateAgainstSpec($page, LIST_LINKS_SCHEMA))->toBeNull();
})->with([
    'empty' => fn (): array => Fixtures::page([]),
    'second of three' => fn (): array => Fixtures::page([Fixtures::link(), Fixtures::link(['id' => 2, 'slug' => 'b'])], 2, 3, 2, 5),
]);

it('validates error() for every spec error code', function (string $code): void {
    expect(validateAgainstSpec(Fixtures::error($code), '/components/schemas/ApiError'))->toBeNull()
        ->and(Fixtures::error($code))->not->toHaveKey('errors');
})->with(fn (): array => Checks::specErrorCodes(Checks::spec()));

it('validates a validation_error with field errors', function (): void {
    $error = Fixtures::error('validation_error', errors: ['destination_url' => ['The destination url field is required.']]);

    expect(validateAgainstSpec($error, '/components/schemas/ApiError'))->toBeNull();
});

it('rejects an invalid fixture, so the validator is live', function (): void {
    expect(validateAgainstSpec(Fixtures::link(['status' => 'archived']), LINK_SCHEMA))->not->toBeNull()
        ->and(validateAgainstSpec(['data' => Fixtures::link(['status' => 'archived'])], SHOW_LINK_SCHEMA))->not->toBeNull()
        ->and(validateAgainstSpec(['data' => []], LIST_LINKS_SCHEMA))->not->toBeNull()
        ->and(validateAgainstSpec(['data' => Fixtures::analytics()], ANALYTICS_SCHEMA))->not->toBeNull();
});

it('validates folder() against FolderResource and its list and create responses', function (): void {
    expect(validateAgainstSpec(Fixtures::folder(), FOLDER_SCHEMA))->toBeNull()
        ->and(validateAgainstSpec(['data' => [Fixtures::folder(), Fixtures::folder(['id' => 2, 'name' => 'B'])]], LIST_FOLDERS_SCHEMA))->toBeNull()
        ->and(validateAgainstSpec(['data' => []], LIST_FOLDERS_SCHEMA))->toBeNull()
        ->and(validateAgainstSpec(['data' => Fixtures::folder()], CREATE_FOLDER_SCHEMA))->toBeNull();
});

it('rejects a folder without links_count, so the folder schema check is live', function (): void {
    $folder = Fixtures::folder();
    unset($folder['links_count']);

    expect(validateAgainstSpec($folder, FOLDER_SCHEMA))->not->toBeNull();
});

it('validates tag() against TagResource and its list and create responses', function (): void {
    expect(validateAgainstSpec(Fixtures::tag(), TAG_SCHEMA))->toBeNull()
        ->and(validateAgainstSpec(['data' => [Fixtures::tag(), Fixtures::tag(['id' => 2, 'name' => 'b'])]], LIST_TAGS_SCHEMA))->toBeNull()
        ->and(validateAgainstSpec(['data' => []], LIST_TAGS_SCHEMA))->toBeNull()
        ->and(validateAgainstSpec(['data' => Fixtures::tag()], CREATE_TAG_SCHEMA))->toBeNull();
});

it('rejects a tag without links_count, so the tag schema check is live', function (): void {
    $tag = Fixtures::tag();
    unset($tag['links_count']);

    expect(validateAgainstSpec($tag, TAG_SCHEMA))->not->toBeNull();
});
