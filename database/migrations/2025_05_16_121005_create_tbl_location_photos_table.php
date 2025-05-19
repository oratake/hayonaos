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
        Schema::create('tbl_location_photos', function (Blueprint $table) {
            $table->id('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('location_id');
            $table->foreign('location_id')->references('id')->on('tbl_locations');
            $table->string('photo_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_location_photos');
    }
};
