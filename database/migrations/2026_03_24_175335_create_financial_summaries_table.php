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
        Schema::create('financial_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->date('date')->index();

            $table->integer('orders_count')->default(0);
            $table->decimal('gross_revenue', 20, 2)->default(0);
            $table->decimal('discounts', 20, 2)->default(0);
            $table->decimal('returns_value', 20, 2)->default(0);
            $table->decimal('net_revenue', 20, 2)->default(0);

            $table->decimal('cogs', 20, 2)->default(0)->comment('Cost of Goods Sold');
            $table->decimal('gross_profit', 20, 2)->default(0);

            $table->decimal('other_revenues', 20, 2)->default(0);
            $table->decimal('total_expenses', 20, 2)->default(0);
            $table->decimal('net_profit', 20, 2)->default(0);

            $table->decimal('gross_margin_rate', 5, 2)->default(0);
            $table->decimal('net_margin_rate', 5, 2)->default(0);

            $table->timestamps();

            $table->unique(['organization_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_summaries');
    }
};
