<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Records saved without a country are placed in India, the CRM's home
     * country, as new ones now are.
     */
    public function up(): void
    {
        $india = DB::table('countries')->where('code', 'IN')->value('id')
            ?? DB::table('countries')->where('name', 'India')->value('id');

        if ($india === null) {
            return;
        }

        foreach (['customers', 'contacts', 'builders', 'projects'] as $table) {
            DB::table($table)->whereNull('country_id')->update(['country_id' => $india]);
        }
    }

    public function down(): void
    {
        //
    }
};
