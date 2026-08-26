<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk mailing: a subscriber list, campaigns, and one row per recipient.
 *
 * The recipient rows are the heart of it. A campaign is not "sent" in one go —
 * its audience is frozen into campaign_recipients when it starts, and the cron
 * drains that table a few rows at a time. That is what makes the hourly limit,
 * resuming after a crash, and per-person delivery status possible at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('status', 20)->default('subscribed');
            $table->string('source', 40)->nullable();
            // Lets someone unsubscribe from a mail without being logged in.
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Soft deleted rows keep their address, so uniqueness cannot be a
            // plain unique index — it is enforced in SubscriberService instead.
            $table->index('email');
            $table->index('status');
            $table->index('locale');
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('locale', 5)->nullable();

            $table->string('audience', 20);
            $table->json('audience_filter')->nullable();

            $table->string('status', 20)->default('draft');
            // When false the dispatcher ignores the per-run smoothing quota and
            // drains as fast as the hourly limit allows.
            $table->boolean('throttled')->default(true);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('scheduled_at');
        });

        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('status', 20)->default('pending');
            // Carried into the mail so each recipient gets their own opt-out.
            $table->string('unsubscribe_token', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The same address must not be queued twice for one campaign.
            $table->unique(['campaign_id', 'email']);
            // The dispatcher's hot query: next pending rows of a campaign.
            $table->index(['campaign_id', 'status']);
            // Counting what went out in the last hour, across all campaigns.
            $table->index(['status', 'sent_at']);
        });

        Schema::create('campaign_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_attachments');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('subscribers');
    }
};
