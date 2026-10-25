<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per browsing session (30 minutes of inactivity starts a new one).
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('sid', 40)->unique();
            $table->string('visitor_id', 40)->index();
            $table->string('ip', 45)->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->string('region', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('device', 10)->nullable();
            $table->string('browser', 30)->nullable();
            $table->string('os', 30)->nullable();
            $table->unsignedSmallInteger('screen_w')->nullable();
            $table->string('language', 16)->nullable();
            $table->string('timezone', 60)->nullable();
            $table->string('source', 40)->default('Direct')->index();
            $table->string('medium', 20)->default('direct');
            $table->string('referrer', 500)->nullable();
            $table->string('referrer_host', 120)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('click_id', 10)->nullable();
            $table->string('landing_path', 300)->nullable();
            $table->string('exit_path', 300)->nullable();
            $table->unsignedSmallInteger('pageviews')->default(0);
            $table->boolean('is_bot')->default(false);
            $table->text('user_agent')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('revenue', 12, 2)->default(0);
            $table->timestamp('started_at')->useCurrent()->index();
            $table->timestamp('last_seen_at')->useCurrent()->index();
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('path', 300);
            $table->string('title', 200)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Shop actions: add_to_cart, begin_checkout, purchase, whatsapp…
        Schema::create('visit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30)->index();
            $table->decimal('value', 12, 2)->nullable();
            $table->string('label', 200)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 191)->unique();
            $table->string('target_path', 300)->nullable();
            $table->string('group', 60)->nullable();
            $table->unsignedTinyInteger('priority')->default(2);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Daily ranking snapshots: from Google Search Console or typed in by hand.
        Schema::create('seo_keyword_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_keyword_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('source', 10)->default('gsc');
            $table->decimal('position', 6, 2)->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 6, 4)->default(0);
            $table->timestamps();
            $table->unique(['seo_keyword_id', 'date', 'source']);
        });

        // Latest Search Console report (all queries people used to find the site).
        Schema::create('seo_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query', 255);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 6, 4)->default(0);
            $table->decimal('position', 6, 2)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_queries');
        Schema::dropIfExists('seo_keyword_stats');
        Schema::dropIfExists('seo_keywords');
        Schema::dropIfExists('visit_events');
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('visits');
    }
};
