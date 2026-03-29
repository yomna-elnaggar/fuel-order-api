<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fuel_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('fuel_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('service_provider_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('reference_number')->unique();
            $table->decimal('quantity', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->integer('status')->default(1);
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('odometer_number')->nullable();
            $table->boolean('pump_match_price')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_orders');
    }
};
