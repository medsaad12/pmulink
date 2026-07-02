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
        Schema::create('fait_marquant_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fait_marquant_id')->constrained('faits_marquants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('fait_status_id')->constrained('fait_statuses')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->date('deadline')->nullable();
            $table->foreignId('responsable_action_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fait_marquant_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('fait_marquant_draft_prochaine_etape', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fait_marquant_draft_id')
                ->constrained('fait_marquant_drafts', 'id', 'fmdpe_draft_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'fmdpe_user_fk')->nullOnDelete();
            $table->foreignId('responsable_action_id')->nullable()->constrained('users', 'id', 'fmdpe_resp_fk')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->foreignId('etape_status_id')->nullable()->constrained('etape_statuses', 'id', 'fmdpe_status_fk')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('body');
            $table->timestamps();

            $table->index(['fait_marquant_draft_id', 'sort_order'], 'fmdpe_draft_sort_idx');
        });

        Schema::create('fait_marquant_draft_commentaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fait_marquant_draft_id')
                ->constrained('fait_marquant_drafts', 'id', 'fmdc_draft_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', 'id', 'fmdc_user_fk')
                ->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('fait_marquant_draft_id', 'fmdc_draft_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fait_marquant_draft_commentaire');
        Schema::dropIfExists('fait_marquant_draft_prochaine_etape');
        Schema::dropIfExists('fait_marquant_drafts');
    }
};
