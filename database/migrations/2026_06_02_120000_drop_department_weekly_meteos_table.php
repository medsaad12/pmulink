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
        Schema::dropIfExists('department_weekly_meteos');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('department_weekly_meteos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedTinyInteger('meteo');
            $table->timestamps();

            $table->unique(['department_id', 'week_start']);
        });
    }
};
