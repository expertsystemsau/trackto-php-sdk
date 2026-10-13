<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Tests;

use ExpertSystems\TrackTo\Laravel\TrackToServiceProvider;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;

final class PublishConfigTest extends TestCase
{
    private string $directory = '';

    protected function defineEnvironment($app): void
    {
        $this->directory = sys_get_temp_dir().'/trackto-publish-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
        $app->useConfigPath($this->directory);
    }

    protected function tearDown(): void
    {
        @unlink($this->directory.'/trackto.php');
        @rmdir($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_publishes_the_six_flat_keys_in_order(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'trackto-config'])->assertExitCode(0);

        $this->assertSame(
            ['api_key', 'base_url', 'version', 'connect_timeout', 'timeout', 'user_agent_suffix'],
            array_keys(require $this->directory.'/trackto.php'),
        );
    }

    #[Test]
    public function it_maps_the_package_config_to_the_app_config_path(): void
    {
        $paths = ServiceProvider::pathsToPublish(TrackToServiceProvider::class, 'trackto-config');

        $this->assertSame(
            [realpath(dirname(__DIR__).'/config/trackto.php') ?: '' => app()->configPath('trackto.php')],
            array_combine(array_map(fn (string $path): string => realpath($path) ?: $path, array_keys($paths)), $paths),
        );
    }
}
