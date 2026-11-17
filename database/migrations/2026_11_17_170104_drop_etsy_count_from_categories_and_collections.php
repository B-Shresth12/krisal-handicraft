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
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('etsy_count');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('etsy_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('etsy_count')->nullable();
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->unsignedInteger('etsy_count')->nullable();
        });
    }
};
