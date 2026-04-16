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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();

            // Relationship with User
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Basic Info
            $table->string('campaign_name');
            $table->enum('campaign_status', ['active', 'inactive']);
            $table->enum('campaign_type', ['discount', 'other']);
            $table->string('discount_code')->nullable();

            // Scheduling
            $table->enum('schedule_type', ['monthly', 'custom']);
            $table->string('monthly_frequency')->nullable();
            $table->string('monthly_validity')->nullable();
            $table->string('custom_start_date')->nullable();
            $table->string('custom_validity')->nullable();

            // Product Selection
            $table->json('selected_products')->nullable();

            // Rules and Filters
            $table->json('discount_rules')->nullable();
            $table->json('customer_filters')->nullable();

            // Template Relationships
            $table->foreignId('message_template_id')->nullable()->constrained('templates')->onDelete('set null');
            $table->foreignId('email_template_id')->nullable()->constrained('templates')->onDelete('set null');

            $table->timestamps();

            // Constraints
            $table->index('user_id');
            $table->unique(['user_id', 'campaign_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
