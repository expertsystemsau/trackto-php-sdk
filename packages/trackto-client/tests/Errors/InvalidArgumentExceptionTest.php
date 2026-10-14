<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\InvalidArgumentException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('is the SDK exception and still a built-in InvalidArgumentException for a blank slug', function (string $method, string $slug): void {
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    try {
        TestClient::make($mock)->links()->{$method}($slug);
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(InvalidArgumentException::class)
            ->toBeInstanceOf(\InvalidArgumentException::class)
            ->toBeInstanceOf(TrackToException::class)
            ->and($e->getMessage())->toBe('The link slug must not be blank.');
        $mock->assertNothingSent();

        return;
    }

    $this->fail('No exception thrown.');
})->with([
    'get empty' => ['get', ''],
    'get spaces' => ['get', '  '],
    'analytics empty' => ['analytics', ''],
]);

it('is caught by an existing catch of the built-in InvalidArgumentException', function (): void {
    $caught = static function (): bool {
        try {
            TestClient::make(new MockClient([]))->links()->get('');
        } catch (\InvalidArgumentException) {
            return true;
        }

        return false;
    };

    expect($caught())->toBeTrue();
});
