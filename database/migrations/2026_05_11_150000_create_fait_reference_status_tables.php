<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fait_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->default('#7c3aed');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->default('#7c3aed');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('etape_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->default('#7c3aed');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('fait_statuses')->insert([
            ['name' => 'En bonne voie', 'color' => '#16a34a', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sous vigilance', 'color' => '#ca8a04', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Critique / À risque', 'color' => '#dc2626', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('statuses')->insert([
            ['name' => 'Ouvert', 'color' => '#6366f1', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Clôturé', 'color' => '#64748b', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Archivé', 'color' => '#94a3b8', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('etape_statuses')->insert([
            ['name' => 'À faire', 'color' => '#6366f1', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'En cours', 'color' => '#ca8a04', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Terminé', 'color' => '#16a34a', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('etape_statuses');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('fait_statuses');
    }
};
