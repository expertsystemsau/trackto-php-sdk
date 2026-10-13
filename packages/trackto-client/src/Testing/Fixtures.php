<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Testing;

/**
 * Spec-valid mock response bodies for tests that use the SDK.
 */
final class Fixtures
{
    /**
     * A complete LinkResource body.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function link(array $overrides = []): array
    {
        return array_replace([
            'id' => 1,
            'slug' => 'abc123',
            'destination_url' => 'https://example.com/landing',
            'short_url' => 'https://track.to/abc123',
            'expires_at' => null,
            'created_at' => '2026-07-06T00:00:00+00:00',
            'folder' => ['id' => 1, 'name' => 'Campaigns'],
            'tags' => [['id' => 1, 'name' => 'launch', 'color' => '#2563eb']],
            'has_password' => false,
            'status' => 'active',
            'click_count' => 0,
        ], $overrides);
    }
}
