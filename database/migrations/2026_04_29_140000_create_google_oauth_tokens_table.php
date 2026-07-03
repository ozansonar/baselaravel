<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OAuth 2.0 user-flow auth for Google Search Console + Indexing API.
 *
 * Stores per-admin refresh + access tokens (encrypted at rest via Eloquent
 * casts). Coexists with the legacy Service Account JSON flow — when both are
 * configured, OAuth takes precedence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_oauth_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('google_email', 191);
            $table->text('access_token');           // encrypted via cast
            $table->text('refresh_token');          // encrypted via cast
            $table->string('token_type', 30)->default('Bearer');
            $table->text('scopes');                 // space-separated CSV from Google
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite unique: at most ONE active (deleted_at=NULL) row per user.
            // Disconnect soft-deletes; MySQL treats each NULL as distinct so multiple
            // historical rows can coexist with the same user_id once trashed.
            $table->unique(['user_id', 'deleted_at']);
            $table->index('google_email');
        });

        // Settings for OAuth client credentials — admin can fill these from
        // settings UI; .env can override at runtime via env() lookup.
        Setting::updateOrCreate(
            ['key' => 'google_oauth_client_id'],
            ['value' => null, 'group' => 'integrations', 'type' => 'text'],
        );
        Setting::updateOrCreate(
            ['key' => 'google_oauth_client_secret'],
            ['value' => null, 'group' => 'integrations', 'type' => 'password'],
        );
        Setting::clearSettingsCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('google_oauth_tokens');

        Setting::whereIn('key', ['google_oauth_client_id', 'google_oauth_client_secret'])->delete();
        Setting::clearSettingsCache();
    }
};
