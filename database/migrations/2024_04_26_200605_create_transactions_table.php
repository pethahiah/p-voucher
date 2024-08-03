<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->unsignedBigInteger('beneficiary_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->enum('status', ['used', 'unused'])->default('unused')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->string('code_generation_method')->nullable();
            $table->timestamps();

            // Define foreign key constraints
            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('cascade');
            $table->foreign('beneficiary_id')->references('id')->on('beneficiaries')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });
    }


};
