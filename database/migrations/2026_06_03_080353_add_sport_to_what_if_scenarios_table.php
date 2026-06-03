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
        Schema::table('what_if_scenarios', function (Blueprint $table) {
            $table->string('sport', 50)->default('nba')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('what_if_scenarios', function (Blueprint $table) {
            $table->dropColumn('sport');
        });
    }
};
