<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('chapter_name');
            $table->string('title');
            $table->unsignedInteger('duration_minutes');
            $table->string('video_url');
            $table->string('thumbnail_url');
            $table->text('about_lesson');
            $table->json('what_you_will_learn');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
