<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo;

use Composer\InstalledVersions;
use ExpertSystems\TrackTo\Resources\LinksResource;
use OutOfBoundsException;
use Saloon\Contracts\ArrayStore as ArrayStoreContract;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Repositories\ArrayStore;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * track.to API connector. All state lives in private properties: headers() and config()
 * are rebuilt on every call so a clone never inherits a memoised store from its source.
 */
final class TrackToClient extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public const string DEFAULT_BASE_URL = 'https://api.track.to';

    public const string DEFAULT_VERSION = '2026-07-06';

    private SensitiveParameterValue $apiKey;

    public function __construct(
        #[SensitiveParameter] string $apiKey,
        private string $baseUrl = self::DEFAULT_BASE_URL,
        private ?string $version = self::DEFAULT_VERSION,
        private float $connectTimeout = 10.0,
        private float $timeout = 30.0,
        private ?string $userAgentSuffix = null,
    ) {
        $this->apiKey = new SensitiveParameterValue($apiKey);
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function links(): LinksResource
    {
        return new LinksResource($this);
    }

    /**
     * Returns a copy; the receiver is left untouched.
     *
     * @return static A copy carrying the mock, never the receiver
     */
    public function withMockClient(MockClient $mockClient): static
    {
        $copy = clone $this;
        $copy->mockClient = $mockClient;

        return $copy;
    }

    public function headers(): ArrayStoreContract
    {
        return new ArrayStore($this->defaultHeaders());
    }

    public function config(): ArrayStoreContract
    {
        return new ArrayStore($this->defaultConfig());
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        $key = $this->apiKey->getValue();

        return new TokenAuthenticator(is_string($key) ? $key : '');
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        $headers = ['User-Agent' => $this->userAgent()];

        if ($this->version !== null) {
            $headers['Track-Version'] = $this->version;
        }

        return $headers;
    }

    /**
     * Redirects stay off so a 3xx can never carry the bearer key to another host.
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'connect_timeout' => $this->connectTimeout,
            'timeout' => $this->timeout,
            'allow_redirects' => false,
        ];
    }

    private function userAgent(): string
    {
        $agent = 'trackto-php/'.$this->sdkVersion().' php/'.PHP_VERSION;
        $suffix = trim((string) $this->userAgentSuffix);

        return $suffix === '' ? $agent : $agent.' '.$suffix;
    }

    private function sdkVersion(): string
    {
        try {
            return ltrim(InstalledVersions::getPrettyVersion('expertsystemsau/trackto-php-client') ?? 'dev', 'v');
        } catch (OutOfBoundsException) {
            return 'dev';
        }
    }
}
