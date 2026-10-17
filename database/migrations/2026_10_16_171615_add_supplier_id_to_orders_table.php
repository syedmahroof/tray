<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purchase orders point at a saved supplier instead of carrying its
     * details as free text. Suppliers already typed onto orders are moved
     * into the supplier list first.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        DB::table('orders')->whereNotNull('supplier_name')->orderBy('id')->each(function (object $order): void {
            $supplierId = DB::table('suppliers')->where('name', $order->supplier_name)->value('id')
                ?? DB::table('suppliers')->insertGetId([
                    'name' => $order->supplier_name,
                    'email' => $order->supplier_email,
                    'gst_number' => $order->supplier_gstin,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('orders')->where('id', $order->id)->update([
                'supplier_id' => $supplierId,
                'gstin' => $order->gstin ?: $order->supplier_gstin,
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'supplier_email', 'supplier_gstin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('supplier_name')->nullable()->after('customer_id');
            $table->string('supplier_email')->nullable()->after('supplier_name');
            $table->string('supplier_gstin', 20)->nullable()->after('supplier_email');
        });

        DB::table('orders')->whereNotNull('supplier_id')->orderBy('id')->each(function (object $order): void {
            $supplier = DB::table('suppliers')->find($order->supplier_id);

            DB::table('orders')->where('id', $order->id)->update([
                'supplier_name' => $supplier?->name,
                'supplier_email' => $supplier?->email,
                'supplier_gstin' => $supplier?->gst_number,
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
