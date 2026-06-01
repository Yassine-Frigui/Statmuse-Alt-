<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position', 10)->nullable();
            $table->string('height', 10)->nullable();
            $table->unsignedSmallInteger('weight')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('college')->nullable();
            $table->year('drafted_year')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('drafted_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
