<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('users')
            ->selectRaw('LOWER(TRIM(email)) AS normalized_email')
            ->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_email');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot enforce case-insensitive email uniqueness while duplicate accounts exist. Back up the database and resolve these accounts before retrying this migration: '.
                $duplicates->implode(', '),
            );
        }

        match (DB::getDriverName()) {
            'sqlite' => DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_normalized_unique ON users (LOWER(TRIM(email)))'),
            'mysql' => DB::statement('CREATE UNIQUE INDEX users_email_normalized_unique ON users ((LOWER(TRIM(email))))'),
            'pgsql' => DB::statement('CREATE UNIQUE INDEX users_email_normalized_unique ON users ((LOWER(TRIM(email))))'),
            default => throw new RuntimeException('The active database driver does not support the required normalized email index.'),
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'sqlite', 'pgsql' => DB::statement('DROP INDEX IF EXISTS users_email_normalized_unique'),
            'mysql' => DB::statement('DROP INDEX users_email_normalized_unique ON users'),
            default => throw new RuntimeException('The active database driver does not support dropping the normalized email index.'),
        };
    }
};
