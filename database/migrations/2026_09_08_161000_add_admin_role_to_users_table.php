<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['student', 'assistant', 'admin'])->default('student')->change();
        });

        DB::table('users')
            ->where('username', 'admin')
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });

        DB::table('users')
            ->where('username', 'admin')
            ->update(['role' => 'assistant']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['student', 'assistant'])->default('student')->change();
        });
    }
};
