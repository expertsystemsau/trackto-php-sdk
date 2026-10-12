<?php

declare(strict_types=1);

namespace ExpertSystems\TrackTo\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Binds nothing yet: this package requires only illuminate/support, so it cannot reference
 * TrackToClient without an undeclared dependency. The client binding, config and the sibling
 * require land together in Phase 13.
 */
class TrackToServiceProvider extends ServiceProvider {}
