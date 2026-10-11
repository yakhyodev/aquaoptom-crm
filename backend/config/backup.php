<?php

return [
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'drill_max_age_days' => max(1, (int) env('BACKUP_DRILL_MAX_AGE_DAYS', 30)),
    'offsite_disk' => env('BACKUP_OFFSITE_DISK'),
    'retention_days' => max(1, (int) env('BACKUP_RETENTION_DAYS', 14)),
];
