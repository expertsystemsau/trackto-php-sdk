<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Tests\Static;

use ExpertSystems\TrackTo\Data\LinkData;
use ExpertSystems\TrackTo\Data\LinkPage;
use ExpertSystems\TrackTo\Laravel\Facades\TrackTo;
use Saloon\Http\Faking\MockClient;

/**
 * Analysed by PHPStan, never executed: a facade call that stops type-checking fails the static analysis step.
 */
final class FacadeUsage
{
    public function create(): LinkData
    {
        return TrackTo::links()->create(destinationUrl: 'https://example.com/landing');
    }

    public function listForTenant(): LinkPage
    {
        return TrackTo::withApiKey('tk_static_key')->links()->list(perPage: 1);
    }

    public function fake(): MockClient
    {
        return TrackTo::fake();
    }
}
