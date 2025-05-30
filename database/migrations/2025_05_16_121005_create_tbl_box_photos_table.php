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
        Schema::create('tbl_box_photos', function (Blueprint $table) {
            $table->id('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('box_id');
            $table->foreign('box_id')->references('id')->on('tbl_box');
            $table->string('file_path')->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_box_photos');
    }
};
