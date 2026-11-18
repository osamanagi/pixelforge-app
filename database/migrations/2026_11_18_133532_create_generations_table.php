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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->uuid('idempotency_key')->unique();
            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->jsonb('reference_images')->default('[]');
            $table->string('status')->default('pending');
            $table->jsonb('rates')->nullable();
            $table->jsonb('usage')->nullable();
            $table->bigInteger('estimated_cost');
            $table->bigInteger('actual_cost')->nullable();
            $table->bigInteger('provider_cost')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->jsonb('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }
};
