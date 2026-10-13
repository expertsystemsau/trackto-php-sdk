<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

use Throwable;

/**
 * Marker for every exception the SDK throws. An interface because the API exceptions extend
 * Saloon's RequestException and PHP has single inheritance.
 */
interface TrackToException extends Throwable {}
