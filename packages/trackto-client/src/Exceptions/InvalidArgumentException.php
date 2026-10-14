<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

/**
 * Thrown before any request is sent when the caller passes invalid input.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements TrackToException {}
