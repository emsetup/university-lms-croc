<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_staff_groups', function (Blueprint $table) {
            $table->string('role', 32)->default('course_tester')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('portal_staff_groups', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
