<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_staff', function (Blueprint $table) {
            $table->string('access_comment', 500)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('portal_staff', function (Blueprint $table) {
            $table->dropColumn('access_comment');
        });
    }
};
