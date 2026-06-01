<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corpus_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('category', 50)->nullable();
            $table->json('tags')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corpus_entries');
    }
};
