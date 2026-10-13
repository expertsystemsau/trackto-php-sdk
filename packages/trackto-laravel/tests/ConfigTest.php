<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\Exceptions\ConfigurationException;
use ExpertSystems\TrackTo\Laravel\TrackToServiceProvider;
use ExpertSystems\TrackTo\Testing\Fixtures;
use ExpertSystems\TrackTo\TrackToClient;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Symfony\Component\Process\Process;

const LARAVEL_CONFIG_ENV = [
    'TRACKTO_API_KEY',
    'TRACKTO_BASE_URL',
    'TRACKTO_API_VERSION',
    'TRACKTO_CONNECT_TIMEOUT',
    'TRACKTO_TIMEOUT',
    'TRACKTO_USER_AGENT_SUFFIX',
];

/**
 * Evaluates config/trackto.php with exactly the given variables set and every other TRACKTO_* unset.
 *
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function laravelConfigFromEnv(array $env): array
{
    $previous = [];

    foreach (LARAVEL_CONFIG_ENV as $name) {
        $previous[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);

        if (array_key_exists($name, $env)) {
            $_ENV[$name] = $_SERVER[$name] = $env[$name];
            putenv($name.'='.$env[$name]);
        }
    }

    try {
        /** @var array<string, mixed> */
        return require dirname(__DIR__).'/config/trackto.php';
    } finally {
        foreach ($previous as $name => [$fromEnv, $fromServer, $fromProcess]) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            if ($fromEnv !== null) {
                $_ENV[$name] = $fromEnv;
            }

            if ($fromServer !== null) {
                $_SERVER[$name] = $fromServer;
            }

            if ($fromProcess !== false) {
                putenv($name.'='.$fromProcess);
            }
        }
    }
}

/**
 * Sends one request through the container client and returns what was built.
 */
function laravelConfigSentRequest(): PendingRequest
{
    $mock = new MockClient([MockResponse::make(['data' => Fixtures::link()], 200)]);

    app(TrackToClient::class)->withMockClient($mock)->links()->get('abc123');

    return $mock->getLastPendingRequest() ?? throw new RuntimeException('No request recorded.');
}

function laravelConfigFailure(): ConfigurationException
{
    try {
        app(TrackToClient::class);
    } catch (ConfigurationException $e) {
        return $e;
    }

    throw new RuntimeException('ConfigurationException was not thrown.');
}

it('binds the client at boot without resolving it', function (): void {
    expect(app()->bound(TrackToClient::class))->toBeTrue()
        ->and(app()->resolved(TrackToClient::class))->toBeFalse();
});

dataset('missing api keys', [
    'null' => [['api_key' => null]],
    'empty' => [['api_key' => '']],
    'whitespace' => [['api_key' => '   ']],
    'integer' => [['api_key' => 123]],
    'absent' => [[]],
]);

it('names the env variable and the config key when the API key is missing, without leaking a value', function (array $config): void {
    config()->set('trackto', $config);

    $message = laravelConfigFailure()->getMessage();

    expect($message)->toContain('TRACKTO_API_KEY')
        ->toContain('trackto.api_key')
        ->not->toContain('null')
        ->not->toContain('123');
})->with('missing api keys');

it('treats blank optional env strings as the package defaults', function (): void {
    config()->set('trackto', ['api_key' => 'tk_blank_env'] + laravelConfigFromEnv([
        'TRACKTO_BASE_URL' => '',
        'TRACKTO_API_VERSION' => '',
        'TRACKTO_USER_AGENT_SUFFIX' => '',
    ]));

    $pending = laravelConfigSentRequest();

    expect(app(TrackToClient::class)->resolveBaseUrl())->toBe(TrackToClient::DEFAULT_BASE_URL)
        ->and($pending->headers()->get('Track-Version'))->toBe(TrackToClient::DEFAULT_VERSION)
        ->and($pending->headers()->get('User-Agent'))->toMatch('#^trackto-php/\S+ php/\S+$#');
});

it('falls back to the default base URL when trackto.base_url is blanked at runtime', function (): void {
    config()->set('trackto.api_key', 'tk_runtime_blank');
    config()->set('trackto.base_url', '');

    expect(app(TrackToClient::class)->resolveBaseUrl())->toBe(TrackToClient::DEFAULT_BASE_URL);
});

it('reads timeouts from env as numeric strings', function (): void {
    config()->set('trackto', ['api_key' => 'tk_env_timeout'] + laravelConfigFromEnv(['TRACKTO_TIMEOUT' => '2.5']));

    $config = laravelConfigSentRequest()->config();

    expect($config->get('timeout'))->toBe(2.5)
        ->and($config->get('connect_timeout'))->toBe(10.0);
});

it('uses the 10 and 30 second defaults when no timeout is set', function (): void {
    config()->set('trackto', ['api_key' => 'tk_default_timeout'] + laravelConfigFromEnv([]));

    $config = laravelConfigSentRequest()->config();

    expect($config->get('connect_timeout'))->toBe(10.0)
        ->and($config->get('timeout'))->toBe(30.0);
});

it('names a non-numeric timeout config key without echoing the value', function (string $variable, string $key): void {
    config()->set('trackto', ['api_key' => 'tk_bad_timeout'] + laravelConfigFromEnv([$variable => 'abc']));

    $message = laravelConfigFailure()->getMessage();

    expect($message)->toContain($key)->not->toContain('abc');
})->with([
    'timeout' => ['TRACKTO_TIMEOUT', 'trackto.timeout'],
    'connect timeout' => ['TRACKTO_CONNECT_TIMEOUT', 'trackto.connect_timeout'],
]);

it('treats a blank timeout env as the client defaults', function (): void {
    config()->set('trackto', ['api_key' => 'tk_blank_timeout'] + laravelConfigFromEnv([
        'TRACKTO_CONNECT_TIMEOUT' => '',
        'TRACKTO_TIMEOUT' => '',
    ]));

    $config = laravelConfigSentRequest()->config();

    expect($config->get('connect_timeout'))->toBe(10.0)
        ->and($config->get('timeout'))->toBe(30.0);
});

it('treats a null timeout config as the client defaults', function (): void {
    config()->set('trackto', ['api_key' => 'tk_null_timeout', 'connect_timeout' => null, 'timeout' => null]);

    $config = laravelConfigSentRequest()->config();

    expect($config->get('connect_timeout'))->toBe(10.0)
        ->and($config->get('timeout'))->toBe(30.0);
});

it('resolves every default for a stale config that only has the key', function (): void {
    config()->set('trackto', ['api_key' => 'tk_stale_key']);

    $pending = laravelConfigSentRequest();

    expect(app(TrackToClient::class)->resolveBaseUrl())->toBe(TrackToClient::DEFAULT_BASE_URL)
        ->and($pending->headers()->get('Track-Version'))->toBe(TrackToClient::DEFAULT_VERSION)
        ->and($pending->config()->get('connect_timeout'))->toBe(10.0)
        ->and($pending->config()->get('timeout'))->toBe(30.0);
});

it('fills the missing keys on register and keeps the values the app already set', function (): void {
    config()->set('trackto', ['api_key' => 'tk_stale_key', 'timeout' => '5']);

    (new TrackToServiceProvider(app()))->register();

    $defaults = laravelConfigFromEnv([]);

    expect(array_keys((array) config('trackto')))->toEqualCanonicalizing(array_keys($defaults))
        ->and(config('trackto.timeout'))->toBe('5')
        ->and(config('trackto.api_key'))->toBe('tk_stale_key')
        ->and(config('trackto.base_url'))->toBe($defaults['base_url'])
        ->and(config('trackto.version'))->toBe($defaults['version'])
        ->and(config('trackto.connect_timeout'))->toBe($defaults['connect_timeout']);
});

it('sends values set before the first resolve', function (): void {
    config()->set('trackto', [
        'api_key' => 'tk_before_resolve',
        'base_url' => 'https://api.example.test',
        'version' => '2025-01-01',
        'connect_timeout' => '3',
        'timeout' => '4.5',
        'user_agent_suffix' => 'my-app/1.0',
    ]);

    $pending = laravelConfigSentRequest();

    expect(parse_url($pending->getUrl(), PHP_URL_HOST))->toBe('api.example.test')
        ->and($pending->headers()->get('Track-Version'))->toBe('2025-01-01')
        ->and($pending->config()->get('connect_timeout'))->toBe(3.0)
        ->and($pending->config()->get('timeout'))->toBe(4.5)
        ->and($pending->headers()->get('User-Agent'))->toEndWith(' my-app/1.0');
});

it('surfaces the client constructor error for a base URL it rejects', function (): void {
    config()->set('trackto', ['api_key' => 'tk_http_url', 'base_url' => 'http://example.com']);

    expect(laravelConfigFailure()->getMessage())->toContain('https');
});

it('keeps the config file serialisable the way config:cache writes it', function (): void {
    // In-process check of the file shape; the next test runs the real commands against temp cache paths.
    $config = laravelConfigFromEnv([]);
    $file = tempnam(sys_get_temp_dir(), 'trackto-config-cache-');

    try {
        file_put_contents($file, '<?php return '.var_export($config, true).';'.PHP_EOL);

        expect(require $file)->toBe($config)
            ->and($config['api_key'])->toBeNull()
            ->and($config['base_url'])->toBe(TrackToClient::DEFAULT_BASE_URL)
            ->and($config['version'])->toBe(TrackToClient::DEFAULT_VERSION);

        array_walk_recursive($config, static fn (mixed $value) => expect($value === null || is_scalar($value))->toBeTrue());
    } finally {
        @unlink($file);
    }
});

it('runs the real package:discover and config:cache with no key set and leaves the shared skeleton untouched', function (): void {
    $root = dirname(__DIR__, 3);
    $skeletonCache = $root.'/vendor/orchestra/testbench-core/laravel/bootstrap/cache';
    $checksums = static fn (): array => array_map(md5_file(...), glob($skeletonCache.'/*') ?: []);
    $before = $checksums();
    $dir = sys_get_temp_dir().'/trackto-config-cache-'.bin2hex(random_bytes(4));
    mkdir($dir);

    // Temp cache paths keep the run off vendor/; Testbench adds the root package to its own manifest, so the provider loads.
    $env = [
        'APP_CONFIG_CACHE' => $dir.'/config.php',
        'APP_PACKAGES_CACHE' => $dir.'/packages.php',
        'APP_SERVICES_CACHE' => $dir.'/services.php',
    ] + array_fill_keys(LARAVEL_CONFIG_ENV, false);
    $run = static function (string $command) use ($root, $env): Process {
        $process = new Process([PHP_BINARY, 'vendor/bin/testbench', $command], $root, $env);
        $process->run();
        expect($process->getExitCode())->toBe(0, $command.': '.$process->getOutput().$process->getErrorOutput());

        return $process;
    };

    try {
        $run('package:discover');
        $run('config:cache');

        /** @var array<string, array<string, mixed>> $cached */
        $cached = require $dir.'/config.php';

        expect($cached['trackto']['api_key'])->toBeNull()
            ->and($cached['trackto']['base_url'])->toBe(TrackToClient::DEFAULT_BASE_URL)
            ->and($cached['trackto']['version'])->toBe(TrackToClient::DEFAULT_VERSION);

        // Testbench ignores the cached file, so this only proves the app still boots with the cache present and no key.
        $run('about');

        $run('config:clear');

        expect(file_exists($dir.'/config.php'))->toBeFalse()
            ->and($checksums())->toBe($before);
    } finally {
        array_map(unlink(...), glob($dir.'/*') ?: []);
        @rmdir($dir);
    }
});
