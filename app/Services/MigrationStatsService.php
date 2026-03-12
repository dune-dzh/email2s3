<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MigrationStatsService extends BaseService
{
    public function gather(): array
    {
        $total = (int) DB::table('emails')->count();
        $pending = (int) DB::table('emails')
            ->where('is_migrated_s3', 0)
            ->count();
        $migrating = (int) DB::table('emails')
            ->where('is_migrated_s3', 1)
            ->count();
        $migrated = (int) DB::table('emails')
            ->where('is_migrated_s3', 2)
            ->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'migrating' => $migrating,
            'migrated' => $migrated,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

