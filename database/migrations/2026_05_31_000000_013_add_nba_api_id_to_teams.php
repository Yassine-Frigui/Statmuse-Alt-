<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->bigInteger('nba_api_id')->nullable()->unique()->after('is_active');
            $table->string('nba_api_abbreviation', 10)->nullable()->after('nba_api_id');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['nba_api_id', 'nba_api_abbreviation']);
        });
    }
};
