<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ordered “pivot” rows: plusieurs prochaines étapes et plusieurs commentaires par fait marquant.
     */
    public function up(): void
    {
        Schema::create('fait_marquant_prochaine_etape', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fait_marquant_id')->constrained('faits_marquants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsable_action_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->foreignId('etape_status_id')->nullable()->constrained('etape_statuses')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('body');
            $table->timestamps();

            $table->index(['fait_marquant_id', 'sort_order']);
        });

        Schema::create('fait_marquant_commentaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fait_marquant_id')->constrained('faits_marquants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('fait_marquant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fait_marquant_commentaire');
        Schema::dropIfExists('fait_marquant_prochaine_etape');
    }
};
