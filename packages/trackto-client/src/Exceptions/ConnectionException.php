<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

use RuntimeException;

/**
 * The request never got a response (DNS, refused, timeout). Built without a previous exception:
 * Guzzle's holds the PSR request and its Authorization header.
 */
final class ConnectionException extends RuntimeException implements TrackToException {}
