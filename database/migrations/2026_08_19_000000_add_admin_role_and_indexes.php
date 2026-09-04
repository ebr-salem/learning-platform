<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->unique('student_code');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropIndex(['student_id', 'created_at']);
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};