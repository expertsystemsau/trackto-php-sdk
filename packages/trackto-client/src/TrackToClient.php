<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo;

use Composer\InstalledVersions;
use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\Exceptions\ConnectionException;
use ExpertSystems\TrackTo\Exceptions\ErrorMapper;
use ExpertSystems\TrackTo\Resources\LinksResource;
use LogicException;
use OutOfBoundsException;
use Saloon\Contracts\ArrayStore as ArrayStoreContract;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Repositories\ArrayStore;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use SensitiveParameter;
use SensitiveParameterValue;
use Throwable;

/**
 * track.to API connector. All state lives in private properties: headers() and config()
 * are rebuilt on every call so a clone never inherits a memoised store from its source.
 *
 * The with*() methods are the supported way to change a client. Inherited Saloon mutators
 * (authenticate(), withTokenAuth(), middleware(), debug(), the public $tries property) still
 * change the instance in place.
 */
final class TrackToClient extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public const string DEFAULT_BASE_URL = 'https://api.track.to';

    public const string DEFAULT_VERSION = '2026-07-06';

    private SensitiveParameterValue $apiKey;

    private ?string $userAgentSuffix;

    /**
     * @throws ConfigurationException When the key is blank or the suffix has control characters
     */
    public function __construct(
        #[SensitiveParameter] string $apiKey,
        private string $baseUrl = self::DEFAULT_BASE_URL,
        private ?string $version = self::DEFAULT_VERSION,
        private float $connectTimeout = 10.0,
        private float $timeout = 30.0,
        ?string $userAgentSuffix = null,
    ) {
        $this->apiKey = self::wrapApiKey($apiKey);
        $this->userAgentSuffix = self::normaliseSuffix($userAgentSuffix);
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Redirects are off, so any non-2xx (a 3xx included) is a failure.
     */
    public function hasRequestFailed(Response $response): bool
    {
        return ! $response->successful();
    }

    public function getRequestException(Response $response, ?Throwable $senderException): Throwable
    {
        return ErrorMapper::map($response);
    }

    /**
     * Converts a transport failure after Saloon's retry loop, so a retry hook still sees the original.
     * No previous exception: Guzzle's holds the PSR request and its Authorization header.
     *
     * @param  callable(Throwable, Request): bool|null  $handleRetry
     *
     * @throws ConnectionException
     */
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        try {
            return parent::send($request, $mockClient, $handleRetry);
        } catch (FatalRequestException $e) {
            throw new ConnectionException($e->getMessage(), (int) $e->getCode());
        }
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

    public function withApiKey(#[SensitiveParameter] string $apiKey): static
    {
        $copy = clone $this;
        $copy->apiKey = self::wrapApiKey($apiKey);

        return $copy;
    }

    public function withBaseUrl(string $baseUrl): static
    {
        $copy = clone $this;
        $copy->baseUrl = $baseUrl;

        return $copy;
    }

    /**
     * A null version omits the Track-Version header.
     */
    public function withVersion(?string $version): static
    {
        $copy = clone $this;
        $copy->version = $version;

        return $copy;
    }

    public function withConnectTimeout(float $seconds): static
    {
        $copy = clone $this;
        $copy->connectTimeout = $seconds;

        return $copy;
    }

    public function withTimeout(float $seconds): static
    {
        $copy = clone $this;
        $copy->timeout = $seconds;

        return $copy;
    }

    public function withUserAgentSuffix(?string $suffix): static
    {
        $copy = clone $this;
        $copy->userAgentSuffix = self::normaliseSuffix($suffix);

        return $copy;
    }

    /**
     * Lists non-secret options only, so var_dump() and print_r() never show the key.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'version' => $this->version,
            'connectTimeout' => $this->connectTimeout,
            'timeout' => $this->timeout,
            'userAgentSuffix' => $this->userAgentSuffix,
            'hasMockClient' => $this->hasMockClient(),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws LogicException Always
     */
    public function __serialize(): array
    {
        throw new LogicException('A TrackToClient cannot be serialized because it holds an API key.');
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

    private static function wrapApiKey(#[SensitiveParameter] string $apiKey): SensitiveParameterValue
    {
        if (trim($apiKey) === '') {
            throw new ConfigurationException('The track.to API key must not be blank.');
        }

        return new SensitiveParameterValue($apiKey);
    }

    /**
     * Null or blank means no suffix; control characters would allow header injection.
     */
    private static function normaliseSuffix(?string $suffix): ?string
    {
        if ($suffix === null) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $suffix) === 1) {
            throw new ConfigurationException('The User-Agent suffix must not contain control characters.');
        }

        $suffix = trim($suffix);

        return $suffix === '' ? null : $suffix;
    }

    private function userAgent(): string
    {
        $agent = 'trackto-php/'.$this->sdkVersion().' php/'.PHP_VERSION;

        return $this->userAgentSuffix === null ? $agent : $agent.' '.$this->userAgentSuffix;
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
