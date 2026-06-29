<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('practice_images')
            || ! Schema::hasColumn('practice_images', 'created_by_portal_staff_id')) {
            return;
        }

        $staffId = $this->resolveMednikovStaffId();
        if ($staffId === null) {
            return;
        }

        DB::table('practice_images')->update([
            'created_by_portal_staff_id' => $staffId,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $staffId = $this->resolveMednikovStaffId();
        if ($staffId === null
            || ! Schema::hasTable('practice_images')
            || ! Schema::hasColumn('practice_images', 'created_by_portal_staff_id')) {
            return;
        }

        DB::table('practice_images')
            ->where('created_by_portal_staff_id', $staffId)
            ->update([
                'created_by_portal_staff_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function resolveMednikovStaffId(): ?int
    {
        if (! Schema::hasTable('portal_staff') || ! Schema::hasTable('learners')) {
            return null;
        }

        $learnerId = DB::table('learners')
            ->where('email', 'emednikov@croc.ru')
            ->value('id');

        if ($learnerId === null && Schema::hasColumn('learners', 'sso_display_name')) {
            $learnerId = DB::table('learners')
                ->where('sso_display_name', 'like', '%Медников%')
                ->orderBy('id')
                ->value('id');
        }

        if ($learnerId === null) {
            return null;
        }

        $staffId = DB::table('portal_staff')
            ->where('learner_id', (int) $learnerId)
            ->value('id');

        return $staffId !== null ? (int) $staffId : null;
    }
};
