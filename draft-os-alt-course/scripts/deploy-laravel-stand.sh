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

ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/app/Http/Controllers/Concerns' '${REMOTE}/app/Providers'"

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

if [[ -f "${LCF}/config/documentation.php" ]]; then
  echo "[deploy-laravel] documentation.php (раздел /docs)"
  rsync -az "${LCF}/config/documentation.php" "${STAND_SSH}:${REMOTE}/config/documentation.php"
fi

if [[ -f "${LCF}/config/portal_changelog.php" ]]; then
  echo "[deploy-laravel] portal_changelog.php (лента обновлений /adm)"
  rsync -az "${LCF}/config/portal_changelog.php" "${STAND_SSH}:${REMOTE}/config/portal_changelog.php"
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
  app/Http/Controllers/AdminIncidentLogsController.php \
  app/Http/Controllers/AdminSettingsController.php \
  app/Http/Controllers/AdminCoursesController.php \
  app/Http/Controllers/AdminCourseSettingsController.php \
  app/Http/Controllers/AdminCourseContentController.php \
  app/Http/Controllers/AdminPracticeImagesController.php \
  app/Http/Controllers/Concerns/ScopesPracticeImagesForStaff.php \
  app/Http/Controllers/AdminDockerLibraryController.php \
  app/Http/Controllers/AdminLearnersController.php \
  app/Http/Controllers/AdminCourseSurveysController.php \
  app/Http/Controllers/AdminSurveyResponsesController.php \
  app/Http/Controllers/SurveyController.php \
  app/Http/Controllers/SurveyQuickLinkController.php \
  app/Http/Controllers/AdminQuizController.php \
  app/Http/Controllers/AdminStaffController.php \
  app/Http/Controllers/AdminStaffGroupController.php \
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
  app/Http/Controllers/DocumentationController.php \
  app/Http/Controllers/TeacherCourseReportController.php \
  app/Services/DocumentationCatalog.php \
  app/Http/Middleware/EnsureAdminCourseSelected.php \
  app/Http/Middleware/EnsureCourseSelected.php \
  app/Http/Middleware/EnsureLearnerCourseActive.php \
  app/Http/Middleware/EnsureLearnerCertificateCourse.php \
  app/Http/Middleware/EnsureLearner.php \
  app/Http/Middleware/ApplyLearnerPreview.php \
  app/Http/Middleware/ApplyStaffAdminPreview.php \
  app/Http/Middleware/DenyStaffAdminPreviewWrites.php \
  app/Http/Middleware/RestrictInstructorCourseAccess.php \
  app/Http/Middleware/MaintenanceForUsers.php \
  app/Http/Middleware/EnsurePortalStaff.php \
  app/Http/Middleware/EnsureStaffAbility.php \
  app/Http/Middleware/DenyCourseTester.php \
  app/Http/Middleware/ValidateTeacherReportToken.php \
  app/Http/Middleware/SyncAdminCourseFromSlug.php \
  app/Http/Middleware/LogAdminActivity.php \
  app/Http/Middleware/LogPortalIncidents.php \
  app/Http/Middleware/EnsurePortalAdmin.php \
  app/Services/CourseScoringService.php \
  app/Services/CourseSectionService.php \
  app/Services/CourseModuleService.php \
  app/Services/CourseContentService.php \
  app/Services/LegacyAltCourseContentBootstrap.php \
  app/Services/LegacyAltPracticeImagesBootstrap.php \
  app/Support/LegacyAltPracticeImageCatalog.php \
  app/Support/PracticeImageWizardCatalog.php \
  app/Support/PracticeCheckOutputParser.php \
  app/Services/LearnerCourseAvailability.php \
  app/Services/PortalStaffAccess.php \
  app/Services/PortalStaffPermissionResolver.php \
  app/Services/PortalMaintenance.php \
  app/Services/MaintenanceActivityLogger.php \
  app/Services/PortalActivityLogger.php \
  app/Services/PortalActivityFeedService.php \
  app/Services/PortalIncidentLogger.php \
  app/Services/PortalIncidentFeedService.php \
  app/Services/PortalServerStatsService.php \
  app/Support/PortalIncidentBootstrap.php \
  app/Services/LearnerLastActivityService.php \
  app/Services/PracticeLabDaemonClient.php \
  app/Services/PracticeImageRecipeBootstrap.php \
  app/Services/PracticeImageRecipeGenerator.php \
  app/Services/PracticeImageBuildService.php \
  app/Services/PracticeLabService.php \
  app/Services/PracticeImageSandboxService.php \
  app/Services/TeacherCourseAnalyticsService.php \
  app/Services/ModuleAccessGate.php \
  app/Services/InstructorProgressResetService.php \
  app/Services/TeacherLearnerProfileDetailService.php \
  app/Services/AdminCourseLearnerDetailService.php \
  app/Services/CourseSurveyCatalogService.php \
  app/Services/SurveyResponseService.php \
  app/Services/SurveyResponseExportService.php \
  app/Services/SurveyQuickLinkService.php \
  app/Support/AdminCourseContentInspector.php \
  app/Support/AdminContentMarkdown.php \
  app/Support/CourseContentMarkdown.php \
  app/Support/CourseQuizBankLoader.php \
  app/Support/CourseModuleMeta.php \
  app/Support/SectionProgress.php \
  app/Support/LearnerRoute.php \
  app/Support/LoginReturnUrl.php \
  app/Support/CourseTheoryPaths.php \
  app/Support/AdminNavigation.php \
  app/Support/StaffImpersonation.php \
  app/Support/LearnerPreviewContext.php \
  app/Support/StaffAdminPreview.php \
  app/Support/StaffRoleGuide.php \
  app/Support/OidcIdentityClaims.php \
  app/Support/OidcSignInRedirect.php \
  app/Support/PortalWelcomeInitials.php \
  app/Support/PortalWelcomeName.php \
  app/Support/PortalChangelog.php \
  app/Support/LearnerDisplay.php \
  app/Support/TeacherQuizLabels.php \
  app/Support/CourseAudiencePlaque.php \
  app/Support/LearnerSsoDisplayNamePersistence.php \
  app/Support/LearnerPortalLoginPersistence.php \
  app/Support/PortalStaffPermissionCatalog.php \
  app/Support/PortalStaffFromEmail.php \
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
  app/Models/CourseSurveyLink.php \
  app/Models/Learner.php \
  app/Models/ModuleProgress.php \
  app/Models/PortalStaff.php \
  app/Models/PortalStaffGroup.php \
  app/Models/PortalStaffGroupPermission.php \
  app/Models/PortalActivityEvent.php \
  app/Models/PortalIncidentLog.php \
  app/Models/PracticeSession.php \
  app/Models/FinalLabResult.php \
  app/Models/PracticeImage.php \
  app/Providers/AppServiceProvider.php
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

if [[ -f "${LCF}/database/migrations/2026_06_02_120900_add_difficulty_flags_enabled_to_courses_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…difficulty_flags_enabled…"
  rsync -az "${LCF}/database/migrations/2026_06_02_120900_add_difficulty_flags_enabled_to_courses_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_02_120900_add_difficulty_flags_enabled_to_courses_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_08_120000_multi_sections_per_module.php" ]]; then
  echo "[deploy-laravel] database/migrations/…multi_sections_per_module…"
  rsync -az "${LCF}/database/migrations/2026_06_08_120000_multi_sections_per_module.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_08_120000_multi_sections_per_module.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_08_140000_drop_course_sections_type_unique.php" ]]; then
  echo "[deploy-laravel] database/migrations/…drop_course_sections_type_unique…"
  rsync -az "${LCF}/database/migrations/2026_06_08_140000_drop_course_sections_type_unique.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_08_140000_drop_course_sections_type_unique.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_08_100000_create_course_survey_tables.php" ]]; then
  echo "[deploy-laravel] database/migrations/…create_course_survey_tables…"
  rsync -az "${LCF}/database/migrations/2026_06_08_100000_create_course_survey_tables.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_08_100000_create_course_survey_tables.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_09_100000_create_course_survey_links_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…create_course_survey_links_table…"
  rsync -az "${LCF}/database/migrations/2026_06_09_100000_create_course_survey_links_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_09_100000_create_course_survey_links_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_09_120000_add_show_module_progress_to_courses_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…show_module_progress…"
  rsync -az "${LCF}/database/migrations/2026_06_09_120000_add_show_module_progress_to_courses_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_09_120000_add_show_module_progress_to_courses_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_09_130000_add_assessment_enabled_to_courses_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…assessment_enabled…"
  rsync -az "${LCF}/database/migrations/2026_06_09_130000_add_assessment_enabled_to_courses_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_09_130000_add_assessment_enabled_to_courses_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_09_140000_add_audience_plaque_to_courses_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…audience_plaque…"
  rsync -az "${LCF}/database/migrations/2026_06_09_140000_add_audience_plaque_to_courses_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_09_140000_add_audience_plaque_to_courses_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_03_100000_add_unlock_all_modules_to_courses_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…unlock_all_modules…"
  rsync -az "${LCF}/database/migrations/2026_06_03_100000_add_unlock_all_modules_to_courses_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_03_100000_add_unlock_all_modules_to_courses_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_02_000001_add_certificate_settings_to_courses_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…certificate_settings…"
  rsync -az "${LCF}/database/migrations/2026_06_02_000001_add_certificate_settings_to_courses_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_02_000001_add_certificate_settings_to_courses_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_02_000002_add_certificate_body_to_courses_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…certificate_body…"
  rsync -az "${LCF}/database/migrations/2026_06_02_000002_add_certificate_body_to_courses_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_02_000002_add_certificate_body_to_courses_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_02_130500_add_created_by_portal_staff_id_to_courses_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…created_by_portal_staff_id…"
  rsync -az "${LCF}/database/migrations/2026_06_02_130500_add_created_by_portal_staff_id_to_courses_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_02_130500_add_created_by_portal_staff_id_to_courses_table.php"
fi

if [[ -f "${LCF}/database/migrations/2026_06_02_140000_add_created_by_portal_staff_id_to_practice_images_table.php" ]]; then
  echo "[deploy-laravel] database/migrations/…practice_images created_by…"
  rsync -az "${LCF}/database/migrations/2026_06_02_140000_add_created_by_portal_staff_id_to_practice_images_table.php" \
    "${STAND_SSH}:${REMOTE}/database/migrations/2026_06_02_140000_add_created_by_portal_staff_id_to_practice_images_table.php"
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
  database/migrations/2026_05_13_200000_add_description_to_practice_images_table.php \
  database/migrations/2026_05_13_120000_expand_alt_os_features_course_summary.php \
  database/migrations/2026_05_14_000001_add_tags_to_courses_table.php \
  database/migrations/2026_05_14_000002_add_course_admin_settings_columns.php \
  database/migrations/2026_05_14_000003_add_sso_display_name_to_learners_table.php \
  database/migrations/2026_06_03_120000_add_last_login_at_to_learners_table.php \
  database/migrations/2026_06_03_140000_create_portal_staff_groups_tables.php \
  database/migrations/2026_06_03_150000_add_role_to_portal_staff_groups_table.php \
  database/migrations/2026_06_03_160000_create_portal_incident_logs_table.php \
  database/migrations/2026_06_03_161000_add_access_comment_to_portal_staff_table.php \
  database/migrations/2026_05_14_100000_seed_legacy_alt_os_course_content_to_database.php \
  database/migrations/2026_05_15_000001_create_portal_activity_events_table.php \
  database/migrations/2026_05_15_120000_seed_legacy_alt_practice_images.php
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

if [[ -d "${LCF}/resources/views/docs" ]]; then
  echo "[deploy-laravel] resources/views/docs/"
  rsync -az "${LCF}/resources/views/docs/" "${STAND_SSH}:${REMOTE}/resources/views/docs/"
fi

if [[ -d "${LCF}/resources/docs" ]]; then
  echo "[deploy-laravel] resources/docs/"
  rsync -az "${LCF}/resources/docs/" "${STAND_SSH}:${REMOTE}/resources/docs/"
fi

for v in hub.blade.php theory.blade.php theory-quiz.blade.php theory-quiz-result.blade.php practice.blade.php exam.blade.php exam-result.blade.php survey.blade.php; do
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

if [[ -f "${LCF}/resources/views/maintenance.blade.php" ]]; then
  echo "[deploy-laravel] resources/views/maintenance.blade.php"
  rsync -az "${LCF}/resources/views/maintenance.blade.php" "${STAND_SSH}:${REMOTE}/resources/views/maintenance.blade.php"
fi

if [[ -f "${LCF}/resources/views/layouts/admin.blade.php" ]]; then
  echo "[deploy-laravel] resources/views/layouts/admin.blade.php"
  rsync -az "${LCF}/resources/views/layouts/admin.blade.php" "${STAND_SSH}:${REMOTE}/resources/views/layouts/admin.blade.php"
fi

if [[ -f "${LCF}/resources/views/layouts/admin-preview.blade.php" ]]; then
  echo "[deploy-laravel] resources/views/layouts/admin-preview.blade.php"
  rsync -az "${LCF}/resources/views/layouts/admin-preview.blade.php" "${STAND_SSH}:${REMOTE}/resources/views/layouts/admin-preview.blade.php"
fi

if [[ -f "${LCF}/public/css/course.css" ]]; then
  echo "[deploy-laravel] public/css/course.css"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/css'"
  rsync -az "${LCF}/public/css/course.css" "${STAND_SSH}:${REMOTE}/public/css/course.css"
fi

if [[ -f "${LCF}/public/css/local-fonts.css" ]]; then
  echo "[deploy-laravel] public/css/local-fonts.css"
  rsync -az "${LCF}/public/css/local-fonts.css" "${STAND_SSH}:${REMOTE}/public/css/local-fonts.css"
fi

if [[ -f "${LCF}/public/css/portal-typography.css" ]]; then
  echo "[deploy-laravel] public/css/portal-typography.css"
  rsync -az "${LCF}/public/css/portal-typography.css" "${STAND_SSH}:${REMOTE}/public/css/portal-typography.css"
fi

if [[ -f "${LCF}/public/css/docs.css" ]]; then
  echo "[deploy-laravel] public/css/docs.css"
  rsync -az "${LCF}/public/css/docs.css" "${STAND_SSH}:${REMOTE}/public/css/docs.css"
fi

if [[ -f "${LCF}/public/css/survey.css" ]]; then
  echo "[deploy-laravel] public/css/survey.css"
  rsync -az "${LCF}/public/css/survey.css" "${STAND_SSH}:${REMOTE}/public/css/survey.css"
fi

if [[ -f "${LCF}/public/js/survey.js" ]]; then
  echo "[deploy-laravel] public/js/survey.js"
  rsync -az "${LCF}/public/js/survey.js" "${STAND_SSH}:${REMOTE}/public/js/survey.js"
fi

if [[ -f "${LCF}/public/css/course-surveys-admin.css" ]]; then
  echo "[deploy-laravel] public/css/course-surveys-admin.css"
  rsync -az "${LCF}/public/css/course-surveys-admin.css" "${STAND_SSH}:${REMOTE}/public/css/course-surveys-admin.css"
fi

if [[ -f "${LCF}/public/js/docs-lightbox.js" ]]; then
  echo "[deploy-laravel] public/js/docs-lightbox.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/docs-lightbox.js" "${STAND_SSH}:${REMOTE}/public/js/docs-lightbox.js"
fi

if [[ -d "${LCF}/public/images/docs" ]]; then
  echo "[deploy-laravel] public/images/docs/"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/images/docs'"
  rsync -az "${LCF}/public/images/docs/" "${STAND_SSH}:${REMOTE}/public/images/docs/"
fi

if [[ -d "${LCF}/public/vendor" ]]; then
  echo "[deploy-laravel] public/vendor/"
  rsync -az "${LCF}/public/vendor/" "${STAND_SSH}:${REMOTE}/public/vendor/"
fi

if [[ -d "${LCF}/public/fonts" ]]; then
  echo "[deploy-laravel] public/fonts/"
  rsync -az "${LCF}/public/fonts/" "${STAND_SSH}:${REMOTE}/public/fonts/"
fi

if [[ -f "${LCF}/public/css/admin-panel.css" ]]; then
  echo "[deploy-laravel] public/css/admin-panel.css"
  rsync -az "${LCF}/public/css/admin-panel.css" "${STAND_SSH}:${REMOTE}/public/css/admin-panel.css"
fi

if [[ -f "${LCF}/public/css/docker-sandbox.css" ]]; then
  echo "[deploy-laravel] public/css/docker-sandbox.css"
  rsync -az "${LCF}/public/css/docker-sandbox.css" "${STAND_SSH}:${REMOTE}/public/css/docker-sandbox.css"
fi

if [[ -f "${LCF}/public/css/practice-image-wizard.css" ]]; then
  echo "[deploy-laravel] public/css/practice-image-wizard.css"
  rsync -az "${LCF}/public/css/practice-image-wizard.css" "${STAND_SSH}:${REMOTE}/public/css/practice-image-wizard.css"
fi

if [[ -f "${LCF}/public/js/practice-image-check-wizard.js" ]]; then
  echo "[deploy-laravel] public/js/practice-image-check-wizard.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/practice-image-check-wizard.js" "${STAND_SSH}:${REMOTE}/public/js/practice-image-check-wizard.js"
fi

if [[ -f "${LCF}/public/js/section-edit-panel.js" ]]; then
  echo "[deploy-laravel] public/js/section-edit-panel.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/section-edit-panel.js" "${STAND_SSH}:${REMOTE}/public/js/section-edit-panel.js"
fi

if [[ -f "${LCF}/public/js/learners-course.js" ]]; then
  echo "[deploy-laravel] public/js/learners-course.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/learners-course.js" "${STAND_SSH}:${REMOTE}/public/js/learners-course.js"
fi

if [[ -f "${LCF}/public/js/course-surveys-admin.js" ]]; then
  echo "[deploy-laravel] public/js/course-surveys-admin.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/course-surveys-admin.js" "${STAND_SSH}:${REMOTE}/public/js/course-surveys-admin.js"
fi

if [[ -f "${LCF}/public/js/admin-create-course-modal.js" ]]; then
  echo "[deploy-laravel] public/js/admin-create-course-modal.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/admin-create-course-modal.js" "${STAND_SSH}:${REMOTE}/public/js/admin-create-course-modal.js"
fi

if [[ -f "${LCF}/public/js/admin-command-palette.js" ]]; then
  echo "[deploy-laravel] public/js/admin-command-palette.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/admin-command-palette.js" "${STAND_SSH}:${REMOTE}/public/js/admin-command-palette.js"
fi

if [[ -f "${LCF}/public/js/admin-activity-panel.js" ]]; then
  echo "[deploy-laravel] public/js/admin-activity-panel.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/admin-activity-panel.js" "${STAND_SSH}:${REMOTE}/public/js/admin-activity-panel.js"
fi

if [[ -f "${LCF}/public/js/admin-dash-changelog.js" ]]; then
  echo "[deploy-laravel] public/js/admin-dash-changelog.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/admin-dash-changelog.js" "${STAND_SSH}:${REMOTE}/public/js/admin-dash-changelog.js"
fi

if [[ -f "${LCF}/public/js/admin-settings-menu.js" ]]; then
  echo "[deploy-laravel] public/js/admin-settings-menu.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/admin-settings-menu.js" "${STAND_SSH}:${REMOTE}/public/js/admin-settings-menu.js"
fi

if [[ -f "${LCF}/public/js/admin-settings-impersonate.js" ]]; then
  echo "[deploy-laravel] public/js/admin-settings-impersonate.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/admin-settings-impersonate.js" "${STAND_SSH}:${REMOTE}/public/js/admin-settings-impersonate.js"
fi
if [[ -f "${LCF}/public/js/admin-settings-staff-preview.js" ]]; then
  echo "[deploy-laravel] public/js/admin-settings-staff-preview.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/admin-settings-staff-preview.js" "${STAND_SSH}:${REMOTE}/public/js/admin-settings-staff-preview.js"
fi

if [[ -f "${LCF}/public/js/admin-incident-logs.js" ]]; then
  echo "[deploy-laravel] public/js/admin-incident-logs.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/admin-incident-logs.js" "${STAND_SSH}:${REMOTE}/public/js/admin-incident-logs.js"
fi

if [[ -f "${LCF}/public/js/portal-incident-reporter.js" ]]; then
  echo "[deploy-laravel] public/js/portal-incident-reporter.js"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/js'"
  rsync -az "${LCF}/public/js/portal-incident-reporter.js" "${STAND_SSH}:${REMOTE}/public/js/portal-incident-reporter.js"
fi

if [[ -f "${LCF}/public/static/admin/admin.css" ]]; then
  echo "[deploy-laravel] public/static/admin/admin.css"
  ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE}/public/static/admin'"
  rsync -az "${LCF}/public/static/admin/admin.css" "${STAND_SSH}:${REMOTE}/public/static/admin/admin.css"
fi

echo "[deploy-laravel] remote: php artisan config:clear cache:clear view:clear migrate"
ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE}' && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan migrate --force --no-interaction"

echo "[deploy-laravel] remote: права storage/app/practice-images для php-fpm"
ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE}' && mkdir -p storage/app/practice-images && chgrp -R _webserver storage/app/practice-images 2>/dev/null || true && chmod -R g+rws storage/app/practice-images 2>/dev/null || true && (command -v chown >/dev/null && chown -R _php_fpm:_webserver storage/app/practice-images 2>/dev/null || chown -R www-data:www-data storage/app/practice-images 2>/dev/null || true)"

echo "[deploy-laravel] готово. Обновите страницу практики в браузере (лучше с принудительным сбросом кэша)."
