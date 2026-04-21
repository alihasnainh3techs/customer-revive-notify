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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->enum('status', ['connected', 'disconnected'])->default('disconnected');
            $table->boolean('enable_whatsapp')->default(true);
            $table->date('disconnected_at')->nullable();
            $table->string('session_id')->required();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
