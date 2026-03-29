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
        Schema::create('order_media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->integer('type_id')->comment('1: odometer_image, 2: vehicle_plate, etc.');
            $table->string('vehicle_image_before')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('odometer_image')->nullable();
            $table->string('vehicle_image_after')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('fuel_orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_media');
    }
};
