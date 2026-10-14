<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

/**
 * A failed response with no usable error envelope, or a 2xx body that cannot be read.
 */
final class UnexpectedResponseException extends ApiException {}
