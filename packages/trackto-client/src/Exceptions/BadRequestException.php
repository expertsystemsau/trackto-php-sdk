<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

/**
 * The request was malformed (`bad_request`, 400 and other 4xx).
 */
final class BadRequestException extends ApiException {}
