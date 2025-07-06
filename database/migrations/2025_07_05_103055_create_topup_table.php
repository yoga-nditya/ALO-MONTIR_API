<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('top_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('order_id')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->enum('status', ['pending', 'success', 'failed', 'expired', 'cancelled', 'challenge'])->default('pending');
            $table->string('snap_token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index('order_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('top_ups');
    }
};
