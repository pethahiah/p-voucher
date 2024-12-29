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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id');
            $table->string('description');
            $table->string('account_number');
            $table->string('bankName');
            $table->string('bankCode');
            $table->timestamp('transactionDate')->nullable()->default(null);
            $table->decimal('merchantReference')->nullable();
            $table->string('fiName')->nullable();
            $table->string('paymentMethod')->nullable();
            $table->decimal('payThruReference')->nullable();
            $table->string('paymentReference')->nullable();
            $table->string('responseCode')->nullable();
            $table->string('responseDescription')->nullable();
            $table->decimal('amount')->nullable();
            $table->string('status')->nullable();
            $table->decimal('commission')->nullable();
            $table->decimal('residualAmount')->nullable();
            $table->string('customerName')->nullable();
            $table->string('resultCode')->nullable();
            $table->string('providedName')->nullable();
            $table->string('providedEmail')->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();
        });
    }

   
};
