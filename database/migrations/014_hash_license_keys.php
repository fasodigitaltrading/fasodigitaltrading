<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->char('license_key_hash', 64)->nullable()->unique();
            $table->text('legacy_license_key_encrypted')->nullable();
        });

        DB::table('licenses')->select(['id', 'license_key'])->orderBy('id')->chunk(100, function ($licenses) {
            foreach ($licenses as $license) {
                DB::table('licenses')->where('id', $license->id)->update([
                    'license_key_hash' => hash('sha256', $license->license_key),
                    'legacy_license_key_encrypted' => Crypt::encryptString($license->license_key),
                    'license_key' => 'NXT-HASHED-'.$license->id,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('licenses')->select(['id', 'legacy_license_key_encrypted'])->orderBy('id')->chunk(100, function ($licenses) {
            foreach ($licenses as $license) {
                if ($license->legacy_license_key_encrypted) {
                    DB::table('licenses')->where('id', $license->id)->update([
                        'license_key' => Crypt::decryptString($license->legacy_license_key_encrypted),
                    ]);
                }
            }
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropUnique(['license_key_hash']);
            $table->dropColumn(['license_key_hash', 'legacy_license_key_encrypted']);
        });
    }
};
