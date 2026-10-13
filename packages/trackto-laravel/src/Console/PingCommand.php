<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Console;

use ExpertSystems\TrackTo\Exceptions\ApiException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\TrackToClient;
use Illuminate\Console\Command;

final class PingCommand extends Command
{
    protected $signature = 'trackto:ping';

    protected $description = 'Check that the configured track.to API key is accepted';

    public function handle(): int
    {
        try {
            $client = $this->laravel->make(TrackToClient::class);
            $client->links()->list(perPage: 1);
        } catch (TrackToException $e) {
            $this->renderFailure($e);

            return self::FAILURE;
        }

        $version = $client->headers()->get('Track-Version');

        $this->components->info(sprintf(
            'API key accepted by %s (Track-Version: %s).',
            $client->resolveBaseUrl(),
            is_string($version) ? $version : 'none',
        ));

        return self::SUCCESS;
    }

    private function renderFailure(TrackToException $e): void
    {
        $this->components->error(class_basename($e).': '.$e->getMessage());

        if ($e instanceof ApiException) {
            foreach (array_keys($e->errors()) as $field) {
                $this->line($field.': '.implode(' ', $e->errorsFor((string) $field)));
            }
        }
    }
}
