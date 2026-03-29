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
        Schema::create('fuel_pumps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fuel_order_id');
            $table->decimal('price', 15, 2);
            $table->decimal('quantity', 15, 2);
            $table->string('image')->nullable();
            $table->timestamps();

            $table->foreign('fuel_order_id')->references('id')->on('fuel_orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_pumps');
    }
};
