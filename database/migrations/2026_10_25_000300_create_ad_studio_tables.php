<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Media selection, analysis results, on-screen text, voice lines/clips, formats (see AdScriptGenerator).
            $table->longText('config')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_project_id')->constrained()->cascadeOnDelete();
            $table->string('format', 30);
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->decimal('duration', 6, 2);
            $table->unsignedBigInteger('size');
            $table->boolean('has_voice')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_renders');
        Schema::dropIfExists('ad_projects');
    }
};
