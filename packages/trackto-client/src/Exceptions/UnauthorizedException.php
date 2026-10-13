<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

/**
 * Missing or invalid API key (`unauthorized`, 401).
 */
final class UnauthorizedException extends ApiException {}
