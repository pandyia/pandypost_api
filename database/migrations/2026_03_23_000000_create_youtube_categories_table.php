<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('youtube_categories', function (Blueprint $table) {
            $table->id(); // Este ID será o ID nativo da API do Google (ex: 22, 24, etc)
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_categories');
    }
};
