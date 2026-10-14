<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel;

use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\TrackToClient;
use SensitiveParameter;

/**
 * @internal Shared by the container binding and the test fake.
 */
final class ClientFactory
{
    /**
     * Builds a client from the `trackto` config array, falling back to the package default for any
     * blank or missing optional key.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws ConfigurationException
     */
    public static function make(#[SensitiveParameter] array $config, #[SensitiveParameter] ?string $fallbackKey = null): TrackToClient
    {
        $apiKey = $config['api_key'] ?? null;

        if (! is_string($apiKey) || trim($apiKey) === '') {
            $apiKey = $fallbackKey;
        }

        if ($apiKey === null) {
            throw new ConfigurationException('No track.to API key is configured. Set TRACKTO_API_KEY, or trackto.api_key in config/trackto.php.');
        }

        /** @var array{apiKey: string, baseUrl: string, version: string, userAgentSuffix: ?string, connectTimeout?: float, timeout?: float} $arguments */
        $arguments = [
            'apiKey' => $apiKey,
            'baseUrl' => self::string($config, 'base_url') ?? TrackToClient::DEFAULT_BASE_URL,
            'version' => self::string($config, 'version') ?? TrackToClient::DEFAULT_VERSION,
            'userAgentSuffix' => self::string($config, 'user_agent_suffix'),
        ];

        foreach (['connect_timeout' => 'connectTimeout', 'timeout' => 'timeout'] as $key => $parameter) {
            $value = $config[$key] ?? null;

            if ($value !== null && $value !== '') {
                $arguments[$parameter] = self::seconds($value, $key);
            }
        }

        return new TrackToClient(...$arguments);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function string(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new ConfigurationException("The trackto.{$key} config value must be a string.");
        }

        return $value;
    }

    private static function seconds(mixed $value, string $key): float
    {
        if (! is_numeric($value)) {
            throw new ConfigurationException("The trackto.{$key} config value must be a number of seconds.");
        }

        return (float) $value;
    }
}
