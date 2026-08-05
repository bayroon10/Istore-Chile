<?php

return [
    'function_calling_enabled' => env('SANTI_FUNCTION_CALLING_ENABLED', true),
    'max_tool_rounds' => env('SANTI_MAX_TOOL_ROUNDS', 3),
    'max_tool_calls' => env('SANTI_MAX_TOOL_CALLS', 6),
    'draft_ttl_hours' => env('SANTI_DRAFT_TTL_HOURS', 48),
    'draft_max_per_hour' => env('SANTI_DRAFT_MAX_PER_HOUR', 10),
    'draft_max_subtotal_clp' => env('SANTI_DRAFT_MAX_SUBTOTAL_CLP', 5000000),
];
