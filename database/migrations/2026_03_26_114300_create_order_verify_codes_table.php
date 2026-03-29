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
        Schema::create('order_verify_codes', function (Blueprint $table) {
             $table->bigIncrements('id');
            $table->unsignedBigInteger('fuel_order_id');
            $table->unsignedBigInteger('user_id');
            $table->string('code');
            $table->integer('status')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedBigInteger('service_provider_id');
            $table->timestamps();

            $table->foreign('fuel_order_id')->references('id')->on('fuel_orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_verify_codes');
    }
};
