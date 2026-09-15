<?php

return [
    // No business limit is fabricated. Configure an approved positive value
    // before audit export is enabled in an environment.
    'export_max_rows' => env('AUDIT_EXPORT_MAX_ROWS'),
];
