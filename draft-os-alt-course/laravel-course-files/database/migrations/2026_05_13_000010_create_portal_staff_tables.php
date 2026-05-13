<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();
            $table->unique('learner_id');
        });

        Schema::create('portal_staff_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_staff_id')->constrained('portal_staff')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['portal_staff_id', 'course_id']);
        });

        $email = 'emednikov@croc.ru';
        $learnerId = DB::table('learners')->where('email', strtolower($email))->value('id');
        if ($learnerId !== null) {
            DB::table('portal_staff')->insert([
                'learner_id' => (int) $learnerId,
                'role' => 'portal_admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_staff_course');
        Schema::dropIfExists('portal_staff');
    }
};
