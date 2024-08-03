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
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('sponsor_name')->nullable();
            $table->string('sponsor_registration_number')->nullable();
            $table->string('sponsor_description')->nullable();
            $table->enum('type', ['government', 'private']);
            $table->boolean('isSponsorVerified')->default(0);
            $table->softDeletes();
            $table->timestamps();

            // Define foreign key constraint
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sponsors');
    }
};
