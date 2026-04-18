<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('email_verified_at');
            $table->timestamp('deactivated_at')->nullable()->after('activated_at');
        });

        // Migrar dados existentes
        DB::table('users')->where('is_active', true)->update([
            'activated_at' => DB::raw('created_at'),
        ]);

        DB::table('users')->where('is_active', false)->update([
            'deactivated_at' => DB::raw('updated_at'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        // Restaurar dados
        DB::table('users')
            ->whereNotNull('activated_at')
            ->where(function ($query) {
                $query->whereNull('deactivated_at')
                    ->orWhere('deactivated_at', '>', now());
            })
            ->update(['is_active' => true]);

        DB::table('users')->whereNotNull('deactivated_at')->where('deactivated_at', '<=', now())->update(['is_active' => false]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activated_at', 'deactivated_at']);
        });
    }
};
