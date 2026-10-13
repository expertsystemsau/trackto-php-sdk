<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

/**
 * The API rejected the input (`validation_error`, 422); see errors() and errorsFor().
 */
final class ValidationException extends ApiException {}
