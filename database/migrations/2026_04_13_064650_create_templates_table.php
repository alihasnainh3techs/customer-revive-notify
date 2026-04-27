<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('source')->nullable();

            $table->string('name');
            $table->enum('type', ['email', 'message'])->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
