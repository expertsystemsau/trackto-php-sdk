<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Tests\Support;

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\TrackToClient;

/**
 * Exists only to prove that constructor injection receives the faked client.
 */
final class LinkCreator
{
    public function __construct(private TrackToClient $client) {}

    public function create(string $url): LinkData
    {
        return $this->client->links()->create(destinationUrl: $url);
    }
}
