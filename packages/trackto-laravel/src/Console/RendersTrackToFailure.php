<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Console;

use ExpertSystems\TrackTo\Exceptions\ApiException;
use ExpertSystems\TrackTo\Exceptions\TrackToException;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Prints an SDK failure to stderr, so a script capturing stdout never swallows it. The message and field
 * errors come from the API, so they are escaped before the console formatter can read them as style tags.
 *
 * @internal
 */
trait RendersTrackToFailure
{
    private function renderFailure(TrackToException $e): void
    {
        $output = $this->output->getOutput();
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        (new Factory(new OutputStyle($this->input, $stderr)))->error(OutputFormatter::escape(class_basename($e).': '.$e->getMessage()));

        if ($e instanceof ApiException) {
            foreach (array_keys($e->errors()) as $field) {
                $stderr->writeln(OutputFormatter::escape($field.': '.implode(' ', $e->errorsFor((string) $field))));
            }
        }
    }
}
