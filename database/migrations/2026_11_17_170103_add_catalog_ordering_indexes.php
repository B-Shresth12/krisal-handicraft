<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These columns are used by the default table sort (sort_order) and the
     * stock status filter / low stock widget (stock_quantity).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->index('sort_order');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->index('sort_order');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('sort_order');
            $table->index('stock_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropIndex(['stock_quantity']);
        });
    }
};
