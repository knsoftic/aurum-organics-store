<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('focus_keyword', 120)->nullable()->after('meta_description');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('content');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->string('focus_keyword', 120)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('focus_keyword'));
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn(['meta_title', 'meta_description', 'focus_keyword']));
    }
};
