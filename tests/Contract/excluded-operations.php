<?php

declare(strict_types=1);

/**
 * Spec operations the SDK has not built yet, all Phase 12. Matched to request classes by method and path.
 */
return [
    'POST /links/bulk', // R050
    'GET /folders', // R051
    'POST /folders', // R051
    'DELETE /folders/{folder}', // R051
    'GET /tags', // R051
    'POST /tags', // R051
    'DELETE /tags/{tag}', // R051
    'PATCH /links/{link}', // R049
    'DELETE /links/{link}', // R048
    'GET /links/{link}/analytics', // R048
];
