<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Exceptions;

use InvalidArgumentException;

/**
 * Invalid client construction input. Messages never contain the API key.
 */
final class ConfigurationException extends InvalidArgumentException implements TrackToException {}
