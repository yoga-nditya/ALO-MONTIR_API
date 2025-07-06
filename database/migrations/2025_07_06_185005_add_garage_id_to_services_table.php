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
    Schema::table('services', function (Blueprint $table) {
        $table->unsignedBigInteger('garage_id')->nullable()->after('amount');
        $table->foreign('garage_id')->references('id')->on('garages')->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('services', function (Blueprint $table) {
        $table->dropForeign(['garage_id']);
        $table->dropColumn('garage_id');
    });
}
};
