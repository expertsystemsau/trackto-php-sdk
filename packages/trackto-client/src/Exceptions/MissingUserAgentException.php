<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

/**
 * The request carried no User-Agent (`missing_user_agent`, 403).
 */
final class MissingUserAgentException extends ApiException {}
