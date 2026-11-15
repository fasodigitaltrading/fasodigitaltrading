<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 40)->nullable();
        });

        Schema::table('contact_requests', function (Blueprint $table) {
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->string('privacy_notice_version', 40)->nullable();
        });
    }

    public function down(): void
    {
        $userConsentExists = DB::table('users')->where(function ($query) {
            $query->whereNotNull('terms_accepted_at')->orWhereNotNull('terms_version');
        })->exists();
        $contactConsentExists = DB::table('contact_requests')->where(function ($query) {
            $query->whereNotNull('privacy_accepted_at')->orWhereNotNull('privacy_notice_version');
        })->exists();

        if ($userConsentExists || $contactConsentExists) {
            throw new LogicException('Legal-consent evidence is in use. Back it up and create a forward migration instead.');
        }

        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropColumn(['privacy_accepted_at', 'privacy_notice_version']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
    }
};
