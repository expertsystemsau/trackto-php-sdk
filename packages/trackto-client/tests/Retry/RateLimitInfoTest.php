<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Support\RateLimitInfo;

const NOW = 1_792_000_000;

function parsed(array $headers): array
{
    $i = RateLimitInfo::fromHeaders($headers, NOW);

    return [$i->limit, $i->remaining, $i->reset, $i->retryAfter];
}

it('parses the real per-team 429 headers', function (): void {
    expect(parsed([
        'RateLimit-Policy' => '"default";q=100;w=1',
        'RateLimit' => '"default";r=0;t=1',
        'Retry-After' => '1',
    ]))->toBe([100, 0, 1, 1]);
});

it('leaves retryAfter null on a 200 with budget left', function (): void {
    expect(parsed(['RateLimit' => '"default";r=42;t=1']))->toBe([null, 42, 1, null]);
});

it('uses the reset as the wait when no budget is left and Retry-After is absent', function (): void {
    expect(parsed(['RateLimit' => '"default";r=0;t=2']))->toBe([null, 0, 2, 2]);
});

it('ignores Retry-After values that are not seconds or an HTTP date', function (string $value): void {
    expect(parsed(['Retry-After' => $value, 'RateLimit' => '"default";r=5;t=1'])[3])->toBeNull();
})->with(['negative' => '-5', 'decimal' => '1.5', 'word' => 'soon', 'empty' => '']);

it('reads an HTTP-date Retry-After relative to now', function (): void {
    $at = gmdate('D, d M Y H:i:s \G\M\T', NOW + 2);
    $past = gmdate('D, d M Y H:i:s \G\M\T', NOW - 60);

    expect(parsed(['Retry-After' => $at])[3])->toBe(2)
        ->and(parsed(['Retry-After' => $past])[3])->toBe(0);
});

it('parses header names in any case and array values', function (): void {
    expect(parsed([
        'ratelimit-policy' => ['"default";q=10;w=1'],
        'RATELIMIT' => ['"default";r=3;t=1'],
        'retry-after' => ['7'],
    ]))->toBe([10, 3, 1, 7]);
});

it('gives four nulls with no headers', function (): void {
    expect(parsed([]))->toBe([null, null, null, null]);
});
