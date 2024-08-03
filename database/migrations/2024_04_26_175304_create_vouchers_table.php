<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVouchersTable extends Migration
{
    public function up()
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_code')->unique();
            $table->unsignedBigInteger('sponsor_id');
            $table->string('purpose')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('limit')->nullable();
            $table->decimal('voucher_amount', 10, 2)->nullable();
            $table->decimal('amount_per_code', 10, 2)->nullable();
            $table->string('location')->nullable();
            $table->enum('type', ['one_time', 'multiple_time'])->default('one_time');
            $table->enum('voucher_status', ['used', 'unused'])->default('unused');
            $table->enum('code_generation_method', ['sms', 'qr_code'])->default('qr_code');
            $table->softDeletes();
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('vouchers');
    }
}
