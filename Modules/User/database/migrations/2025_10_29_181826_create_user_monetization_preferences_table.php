<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_monetization_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Tolerância a modelos de monetização (escala 0-10)
            // 0 = recusa completamente, 10 = aceita totalmente
            $table->integer('tolerance_microtransactions')->default(5); // Microtransações
            $table->integer('tolerance_dlc')->default(7); // DLCs pagos
            $table->integer('tolerance_season_pass')->default(5); // Season Pass
            $table->integer('tolerance_loot_boxes')->default(3); // Loot Boxes
            $table->integer('tolerance_battle_pass')->default(5); // Battle Pass
            $table->integer('tolerance_ads')->default(2); // Anúncios in-game
            $table->integer('tolerance_pay_to_win')->default(0); // Pay to Win

            // Preferências específicas
            $table->boolean('prefer_cosmetic_only')->default(true); // Apenas cosméticos
            $table->boolean('avoid_subscription')->default(false); // Evitar assinaturas
            $table->boolean('prefer_one_time_purchase')->default(true); // Compra única

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_monetization_preferences');
    }
};

