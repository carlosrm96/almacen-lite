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
        Schema::table('products', function (Blueprint $table): void {
            // Nullable a propósito: `null` significa "moneda base". Así los
            // productos ya existentes siguen siendo válidos sin migrar datos y
            // el caso normal (todo en la moneda base) no obliga a rellenarlo.
            $table->foreignId('currency_id')
                ->nullable()
                ->after('precio_venta')
                ->constrained('currencies')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};
