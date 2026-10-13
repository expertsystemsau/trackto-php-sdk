<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel\Tests\Support;

use LogicException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Captures stdout and stderr separately, which Artisan::output() cannot.
 */
final class SplitOutput extends BufferedOutput implements ConsoleOutputInterface
{
    private BufferedOutput $stderr;

    public function __construct()
    {
        parent::__construct();

        $this->stderr = new BufferedOutput;
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this->stderr;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        throw new LogicException('The error output is fixed.');
    }

    public function section(): ConsoleSectionOutput
    {
        throw new LogicException('Sections are not supported.');
    }

    public function stderr(): string
    {
        return $this->stderr->fetch();
    }
}
