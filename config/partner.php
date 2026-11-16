<?php

return [
    'requests_per_minute' => 60,
    'webhook_hosts' => array_values(array_filter(array_map('trim', explode(',', env('PARTNER_WEBHOOK_HOSTS', ''))))),
];
