<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Console;

use ExpertSystems\TrackTo\Exceptions\TrackToException;
use ExpertSystems\TrackTo\TrackToClient;
use Illuminate\Console\Command;

final class ShortenCommand extends Command
{
    use RendersTrackToFailure;

    protected $signature = 'trackto:shorten {url : The destination URL}';

    protected $description = 'Create a track.to short link and print its short URL';

    public function handle(): int
    {
        $url = $this->input->getArgument('url');

        try {
            // input->getArgument(), not argument(): Larastan types a declared {url} as string, which flags is_string() as always true. The non-string branch is unreachable: {url} is required.
            $link = $this->laravel->make(TrackToClient::class)->links()->create(destinationUrl: is_string($url) ? $url : '');
        } catch (TrackToException $e) {
            $this->renderFailure($e);

            return self::FAILURE;
        }

        $this->line($link->shortUrl);

        return self::SUCCESS;
    }
}
