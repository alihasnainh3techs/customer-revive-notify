<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Shopify customer data snapshot
            $table->string('customer_id');        // Shopify GID e.g. gid://shopify/Customer/123
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_name')->nullable();

            $table->enum('channel', ['email', 'whatsapp']);
            $table->enum('status', ['sent', 'failed']);
            $table->text('failure_reason')->nullable(); // only populated when status = failed

            $table->timestamp('sent_at')->nullable();   // only populated when status = sent
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_logs');
    }
};
