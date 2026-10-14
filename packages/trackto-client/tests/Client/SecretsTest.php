<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\InvalidArgumentException;
use ExpertSystems\TrackTo\Exceptions\NotFoundException;
use ExpertSystems\TrackTo\Exceptions\ValidationException;
use ExpertSystems\TrackTo\Requests\CreateLinkRequest;
use ExpertSystems\TrackTo\Requests\GetLinkRequest;
use ExpertSystems\TrackTo\Requests\UpdateLinkRequest;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\Tests\Support\TestClient;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

function usedClient(): TrackToClient
{
    $client = TestClient::make(new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]));
    $client->links()->get('x');

    return $client;
}

it('keeps the API key out of var_dump, print_r and __debugInfo of a used client', function (): void {
    $client = usedClient();

    ob_start();
    var_dump($client);
    $dumped = (string) ob_get_clean();

    expect($dumped)->not->toContain(TestClient::API_KEY)
        ->and(print_r($client, true))->not->toContain(TestClient::API_KEY)
        ->and(json_encode($client->__debugInfo(), JSON_THROW_ON_ERROR))->not->toContain(TestClient::API_KEY);
});

it('keeps the API key out of var_export of an un-mocked client', function (): void {
    // var_export ignores __debugInfo; a mocked client would expose recorded requests, a test-only path.
    expect(var_export(new TrackToClient(apiKey: TestClient::API_KEY), true))->not->toContain(TestClient::API_KEY);
});

it('refuses to serialize a client', function (): void {
    expect(fn () => serialize(usedClient()))->toThrow(LogicException::class);
});

it('keeps the key out of the exception message for a bad suffix', function (): void {
    try {
        new TrackToClient(apiKey: TestClient::API_KEY, userAgentSuffix: "a\nb");
    } catch (Throwable $e) {
        expect($e->getMessage())->not->toContain(TestClient::API_KEY);

        return;
    }

    $this->fail('Expected an exception.');
});

it('keeps the API key out of a thrown exception and refuses to serialize it', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('not_found')]);

    try {
        TestClient::make($mock)->links()->get('x');
    } catch (NotFoundException $e) {
        ob_start();
        var_dump($e);
        $dumped = (string) ob_get_clean();

        expect($e->getMessage())->not->toContain(TestClient::API_KEY)
            ->and($dumped)->not->toContain(TestClient::API_KEY)
            ->and(print_r($e, true))->not->toContain(TestClient::API_KEY)
            ->and(fn () => serialize($e))->toThrow(LogicException::class);

        return;
    }

    $this->fail('Expected a NotFoundException.');
});

it('refuses async and pooled sending, which would leak the key through getPrevious()', function (): void {
    $client = new TrackToClient(apiKey: TestClient::API_KEY);

    expect(fn () => $client->sendAsync(new GetLinkRequest('x')))->toThrow(LogicException::class)
        ->and(fn () => $client->pool([new GetLinkRequest('x')]))->toThrow(LogicException::class)
        ->and(fn () => (new GetLinkRequest('x'))->setConnector($client)->sendAsync())->toThrow(LogicException::class);
});

const SECRET_LINK_PASSWORD = 'zq-link-pass-91c7';

it('keeps a link password out of the conflict exception trace and dumps', function (): void {
    $previous = ini_set('zend.exception_ignore_args', '0');

    try {
        TestClient::make(new MockClient([]))->links()->update(
            slug: 'x',
            destinationUrl: 'https://example.com/a',
            password: SECRET_LINK_PASSWORD,
            removePassword: true,
        );
    } catch (InvalidArgumentException $e) {
        // Dump only the SDK frames: the password is passed to them alone, while the test runner's frames
        // carry whole TestCase objects whose dump is hundreds of megabytes.
        $sdkFrames = array_values(array_filter(
            $e->getTrace(),
            static fn (array $f): bool => str_starts_with($f['class'] ?? '', 'ExpertSystems\\TrackTo\\'),
        ));
        ob_start();
        var_dump($sdkFrames);
        $dumped = (string) ob_get_clean();
        $frame = array_values(array_filter($sdkFrames, static fn (array $f): bool => $f['function'] === 'update'))[0] ?? [];

        expect($e->getMessage())->not->toContain(SECRET_LINK_PASSWORD)
            ->and(print_r($sdkFrames, true))->not->toContain(SECRET_LINK_PASSWORD)
            ->and($dumped)->not->toContain(SECRET_LINK_PASSWORD)
            ->and($frame['args'][6] ?? null)->toBeInstanceOf(SensitiveParameterValue::class);

        return;
    } finally {
        ini_set('zend.exception_ignore_args', $previous === false ? '' : $previous);
    }

    $this->fail('Expected an InvalidArgumentException.');
});

it('keeps a link password out of a ValidationException from update', function (): void {
    $mock = new MockClient([Fixtures::errorResponse('validation_error', ['password' => ['The password is too short.']])]);

    try {
        TestClient::make($mock)->links()->update(slug: 'x', destinationUrl: 'https://example.com/a', password: SECRET_LINK_PASSWORD);
    } catch (ValidationException $e) {
        ob_start();
        var_dump($e);
        $dumped = (string) ob_get_clean();

        expect($e->getMessage())->not->toContain(SECRET_LINK_PASSWORD)
            ->and($dumped)->not->toContain(SECRET_LINK_PASSWORD)
            ->and(print_r($e, true))->not->toContain(SECRET_LINK_PASSWORD);

        return;
    }

    $this->fail('Expected a ValidationException.');
});

it('keeps a link password out of var_dump and print_r of the request objects, yet still sends it', function (): void {
    $requests = [
        new CreateLinkRequest('https://example.com/a', password: SECRET_LINK_PASSWORD),
        new UpdateLinkRequest('x', 'https://example.com/a', password: SECRET_LINK_PASSWORD),
    ];

    foreach ($requests as $request) {
        ob_start();
        var_dump($request);
        $dumped = (string) ob_get_clean();

        expect($dumped)->not->toContain(SECRET_LINK_PASSWORD)
            ->and(print_r($request, true))->not->toContain(SECRET_LINK_PASSWORD);
    }

    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200), MockResponse::make(['data' => Fixtures::link()], 200)]);
    $links = TestClient::make($mock)->links();
    $links->create('https://example.com/a', password: SECRET_LINK_PASSWORD);
    $links->update('x', 'https://example.com/a', password: SECRET_LINK_PASSWORD);

    foreach ($mock->getRecordedResponses() as $response) {
        expect($response->getPendingRequest()->body()->all())->toMatchArray(['password' => SECRET_LINK_PASSWORD]);
    }
});
