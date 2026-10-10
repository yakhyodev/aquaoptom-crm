<?php

return [
    'offsite_disk' => env('BACKUP_OFFSITE_DISK'),
    'retention_days' => max(1, (int) env('BACKUP_RETENTION_DAYS', 14)),
];
