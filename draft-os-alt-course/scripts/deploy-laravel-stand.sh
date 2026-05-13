#!/usr/bin/env bash
# Публикация на стенд только тех частей Laravel, что лежат в репозитории (без полного дерева).
# По умолчанию: сниппеты практики/теории, practice_lab.php, страница практики — и сброс кэша конфигов.
#
#   export STAND_SSH=emednikov@172.26.76.216
#   bash scripts/deploy-laravel-stand.sh
#
# Каталог приложения на стенде (как в start-lab-daemon-stand.sh для .env):
#   LARAVEL_REMOTE=/var/www/os-alt-lab
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
REMOTE="${LARAVEL_REMOTE:-/var/www/os-alt-lab}"

STAND_SSH="${STAND_SSH:?Задайте STAND_SSH=user@host}"

if ! command -v rsync &>/dev/null; then
  echo "Нужен rsync в PATH." >&2
  exit 1
fi

echo "[deploy-laravel] ${LCF}/config/snippets/ -> ${STAND_SSH}:${REMOTE}/config/snippets/"
rsync -az \
  "${LCF}/config/snippets/" \
  "${STAND_SSH}:${REMOTE}/config/snippets/"

if [[ -f "${LCF}/config/practice_lab.php" ]]; then
  echo "[deploy-laravel] practice_lab.php"
  rsync -az "${LCF}/config/practice_lab.php" "${STAND_SSH}:${REMOTE}/config/practice_lab.php"
fi

if [[ -f "${LCF}/config/course_admin.php" ]]; then
  echo "[deploy-laravel] course_admin.php (COURSE_ADMIN_TOKEN — редактор теории)"
  rsync -az "${LCF}/config/course_admin.php" "${STAND_SSH}:${REMOTE}/config/course_admin.php"
fi

if [[ -f "${LCF}/config/course.php" ]]; then
  echo "[deploy-laravel] course.php (структура курса и модули)"
  rsync -az "${LCF}/config/course.php" "${STAND_SSH}:${REMOTE}/config/course.php"
fi

if [[ -f "${LCF}/config/oidc.php" ]]; then
  echo "[deploy-laravel] oidc.php (OpenID / ADFS)"
  rsync -az "${LCF}/config/oidc.php" "${STAND_SSH}:${REMOTE}/config/oidc.php"
fi

if [[ -f "${LCF}/routes/web.php" ]]; then
  echo "[deploy-laravel] routes/web.php"
  rsync -az "${LCF}/routes/web.php" "${STAND_SSH}:${REMOTE}/routes/web.php"
fi

for f in \
  app/Http/Controllers/Controller.php \
  app/Http/Controllers/EmailLoginController.php \
  app/Http/Controllers/OidcLoginController.php \
  app/Http/Controllers/AdminPanelController.php \
  app/Http/Controllers/AdminCoursesController.php \
  app/Http/Controllers/AdminCourseSettingsController.php \
  app/Http/Controllers/AdminCourseContentController.php \
  app/Http/Controllers/AdminPracticeImagesController.php \
  app/Http/Controllers/AdminLearnersController.php \
  app/Http/Controllers/AdminQuizController.php \
  app/Http/Controllers/AdminStaffController.php \
  app/Http/Controllers/AdminTheoryController.php \
  app/Http/Controllers/AssessmentController.php \
  app/Http/Controllers/CertificateController.php \
  app/Http/Controllers/DashboardController.php \
  app/Http/Controllers/FinalLabController.php \
  app/Http/Controllers/ModuleController.php \
  app/Http/Controllers/PracticeLabController.php \
  app/Http/Controllers/AccountController.php \
  app/Http/Controllers/PortalController.php \
  app/Http/Controllers/PortalEnrollController.php \
  app/Http/Controllers/TeacherCourseReportController.php \
  app/Http/Middleware/EnsureAdminCourseSelected.php \
  app/Http/Middleware/EnsureCourseSelected.php \
  app/Http/Middleware/EnsureLearner.php \
  app/Http/Middleware/EnsurePortalStaff.php \
  app/Http/Middleware/EnsureStaffAbility.php \
  app/Http/Middleware/DenyCourseTester.php \
  app/Http/Middleware/ValidateTeacherReportToken.php \
  app/Services/CourseScoringService.php \
  app/Services/CourseSectionService.php \
  app/Services/CourseModuleService.php \
  app/Services/CourseContentService.php \
  app/Services/PortalStaffAccess.php \
  app/Services/PracticeLabDaemonClient.php \
  app/Services/PracticeLabService.php \
  app/Services/TeacherCourseAnalyticsService.php \
  app/Services/ModuleAccessGate.php \
  app/Services/InstructorProgressResetService.php \
  app/Services/TeacherLearnerProfileDetailService.php \
  app/Support/AdminCourseContentInspector.php \
  app/Support/CourseQuizBankLoader.php \
  app/Support/CourseModuleMeta.php \
  app/Support/CourseTheoryPaths.php \
  app/Support/OidcIdentityClaims.php \
  app/Support/OidcSignInRedirect.php \
  app/Support/PortalWelcomeName.php \
  app/Models/Course.php \
  app/Models/CourseModule.php \
  app/Models/CourseModuleContent.php \
  app/Models/CourseQuizBank.php \
  app/Models/CourseQuizQuestion.php \
  app/Models/CourseQuizOption.php \
  app/Models/CourseQuizCorrectAnswer.php \
  app/Models/CourseQuizMatchPair.php \
  app/Models/CourseModulePracticeSetting.php \
  app/Models/CourseSection.php \
  app/Models/CourseSectionSetting.php \
  app/Models/CourseEnrollment.php \
  app/Models/Learner.php \
  app/Models/ModuleProgress.php \
  app/Models/PortalStaff.php \
  app/Models/PracticeSession.php \
  app/Models/FinalLabResult.php \
  app/Models/PracticeImage.php
do
  if [[ -f "${LCF}/${f}" ]]; then
    echo "[deploy-laravel] ${f}"
    rsync -az "${LCF}/${f}" "${STAND_SSH}:${REMOTE}/${f}"
  fi
done

if [[ -f "${LCF}/database/migrations/2026_04_15_000001_add_practice_m1_quest_to_module_progress_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…practice_m1_quest…"
  rsync -az "${LCF}/database/migrations/2026_04_15_000001_add_practice_m1_quest_to_module_progress_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_04_15_000001_add_practice_m1_quest_to_module_progress_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_04_30_000001_add_certificate_fields_to_final_lab_results_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…certificate_fields_final_lab_results…"
  rsync -az "${LCF}/database/migrations/2026_04_30_000001_add_certificate_fields_to_final_lab_results_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_04_30_000001_add_certificate_fields_to_final_lab_results_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_05_07_000004_add_course_id_to_final_lab_results_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…course_id_final_lab_results…"
  rsync -az "${LCF}/database/migrations/2026_05_07_000004_add_course_id_to_final_lab_results_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_05_07_000004_add_course_id_to_final_lab_results_table.php"
fi

for mf in \
  database/migrations/2026_05_06_000001_create_courses_table.php \
  database/migrations/2026_05_06_000002_create_course_enrollments_table.php \
  database/migrations/2026_05_06_100000_create_course_sections_tables.php \
  database/migrations/2026_05_07_000001_course_modules_progress_sections.php \
  database/migrations/2026_05_07_000003_add_is_archived_to_courses_table.php \
  database/migrations/2026_05_07_000005_create_practice_images_and_module_settings_tables.php \
  database/migrations/2026_05_07_000006_extend_practice_images_for_constructor.php \
  database/migrations/2026_05_07_000007_create_course_module_contents_table.php \
  database/migrations/2026_05_07_000008_create_course_quiz_tables.php \
  database/migrations/2026_05_13_000010_create_portal_staff_tables.php \
  database/migrations/2026_05_13_120000_expand_alt_os_features_course_summary.php
do
  if [[ -f "${LCF}/${mf}" ]]; then
    echo "[deploy-laravel] ${mf}"
    rsync -az "${LCF}/${mf}" "${STAND_SSH}:${REMOTE}/${mf}"
  fi
done

if [[ -d "${LCF}/resources/views/admin" ]]; then
  echo "[deploy-laravel] resources/views/admin/"
  rsync -az "${LCF}/resources/views/admin/" "${STAND_SSH}:${REMOTE}/resources/views/admin/"
fi

if [[ -d "${LCF}/resources/views/partials" ]]; then
  echo "[deploy-laravel] resources/views/partials/"
  rsync -az "${LCF}/resources/views/partials/" "${STAND_SSH}:${REMOTE}/resources/views/partials/"
fi

if [[ -d "${LCF}/resources/views/auth" ]]; then
  echo "[deploy-laravel] resources/views/auth/"
  rsync -az "${LCF}/resources/views/auth/" "${STAND_SSH}:${REMOTE}/resources/views/auth/"
fi

if [[ -d "${LCF}/resources/views/portal" ]]; then
  echo "[deploy-laravel] resources/views/portal/"
  rsync -az "${LCF}/resources/views/portal/" "${STAND_SSH}:${REMOTE}/resources/views/portal/"
fi

for v in hub.blade.php theory.blade.php theory-quiz.blade.php practice.blade.php exam.blade.php; do
  if [[ -f "${LCF}/resources/views/modules/${v}" ]]; then
    echo "[deploy-laravel] resources/views/modules/${v}"
    rsync -az "${LCF}/resources/views/modules/${v}" \
      "${STAND_SSH}:${REMOTE}/resources/views/modules/${v}"
  fi
done

if [[ -d "${LCF}/resources/views/modules/partials" ]]; then
  echo "[deploy-laravel] resources/views/modules/partials/"
  rsync -az "${LCF}/resources/views/modules/partials/" "${STAND_SSH}:${REMOTE}/resources/views/modules/partials/"
fi

for tv in assessment.blade.php certificate.blade.php dashboard.blade.php final-lab.blade.php teacher-course-report.blade.php teacher-learner-profile.blade.php teacher-learner-module.blade.php account.blade.php; do
  if [[ -f "${LCF}/resources/views/${tv}" ]]; then
    echo "[deploy-laravel] resources/views/${tv}"
    rsync -az "${LCF}/resources/views/${tv}" "${STAND_SSH}:${REMOTE}/resources/views/${tv}"
  fi
done

if [[ -f "${LCF}/resources/views/layouts/course.blade.php" ]]; then
  echo "[deploy-laravel] resources/views/layouts/course.blade.php"
  rsync -az "${LCF}/resources/views/layouts/course.blade.php" "${STAND_SSH}:${REMOTE}/resources/views/layouts/course.blade.php"
fi

echo "[deploy-laravel] remote: php artisan config:clear cache:clear view:clear migrate"
ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE}' && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan migrate --force --no-interaction"

echo "[deploy-laravel] готово. Обновите страницу практики в браузере (лучше с принудительным сбросом кэша)."
