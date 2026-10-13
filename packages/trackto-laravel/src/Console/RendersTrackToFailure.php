<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Console;

use ExpertSystems\TrackTo\Exceptions\ApiException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Prints an SDK failure. The message and field errors come from the API, so they are escaped before the
 * console formatter can read them as style tags.
 *
 * @internal
 */
trait RendersTrackToFailure
{
    private function renderFailure(TrackToException $e): void
    {
        $this->components->error(OutputFormatter::escape(class_basename($e).': '.$e->getMessage()));

        if ($e instanceof ApiException) {
            foreach (array_keys($e->errors()) as $field) {
                $this->line(OutputFormatter::escape($field.': '.implode(' ', $e->errorsFor((string) $field))));
            }
        }
    }
}
