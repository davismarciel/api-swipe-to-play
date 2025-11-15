<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn(['max_price', 'prefer_free_to_play']);
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->decimal('max_price', 10, 2)->nullable()->after('avoid_nudity');
            $table->boolean('prefer_free_to_play')->default(false)->after('max_price');
        });
    }
};

