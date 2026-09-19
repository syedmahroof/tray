<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables that carry a location/route pair.
     *
     * @var list<string>
     */
    private const TABLES = ['builders', 'customers', 'contacts', 'projects', 'visit_reports'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $location = $table->foreignId('location_id')->nullable();

                // Visit reports have no country/state/district block to sit after.
                if (Schema::hasColumn($name, 'district_id')) {
                    $location->after('district_id');
                }

                $location->constrained()->nullOnDelete();

                $table->foreignId('route_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('route_id');
                $table->dropConstrainedForeignId('location_id');
            });
        }
    }
};
