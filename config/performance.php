<?php

return [
    'query_budget' => (int) env('QUERY_BUDGET', 100),
    'query_budget_enabled' => filter_var(env('QUERY_BUDGET_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'slow_query_ms' => (float) env('SLOW_QUERY_MS', 100),
    'slow_query_logging_enabled' => filter_var(env('SLOW_QUERY_LOG_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
