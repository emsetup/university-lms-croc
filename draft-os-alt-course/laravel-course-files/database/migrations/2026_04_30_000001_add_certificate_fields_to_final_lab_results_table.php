<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_lab_results', function (Blueprint $table) {
            $table->string('certificate_full_name', 120)->nullable()->after('best_score');
            $table->string('certificate_serial', 64)->nullable()->after('certificate_full_name');
            $table->timestamp('certificate_issued_at')->nullable()->after('certificate_serial');
            $table->unique('certificate_serial');
        });
    }

    public function down(): void
    {
        Schema::table('final_lab_results', function (Blueprint $table) {
            $table->dropUnique(['certificate_serial']);
            $table->dropColumn([
                'certificate_full_name',
                'certificate_serial',
                'certificate_issued_at',
            ]);
        });
    }
};
