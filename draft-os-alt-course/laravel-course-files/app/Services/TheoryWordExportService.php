<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Support\AdminCourseContentInspector;
use App\Support\CourseContentMarkdown;
use App\Support\CourseModuleMeta;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

final class TheoryWordExportService
{
    public function __construct(
        private CourseContentService $content,
    ) {}

    public function docHtml(string $title, string $markdown, ?string $subtitle = null): string
    {
        $body = CourseContentMarkdown::toHtml($markdown);
        $body = $this->absolutizeUrls($body);
        $h1 = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sub = $subtitle !== null && $subtitle !== ''
            ? '<p style="color:#64748b;margin:0 0 1.25rem">'.htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>'
            : '';

        return '<!DOCTYPE html>'
            .'<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
            .'<head><meta charset="UTF-8"><title>'.$h1.'</title>'
            .'<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View></w:WordDocument></xml><![endif]-->'
            .'<style>'
            .'body{font-family:Calibri,Arial,sans-serif;font-size:12pt;line-height:1.45;color:#111}'
            .'h1,h2,h3{color:#0f172a}'
            .'pre,code{font-family:Consolas,monospace;font-size:10pt}'
            .'img{max-width:100%}'
            .'table{border-collapse:collapse}'
            .'td,th{border:1px solid #cbd5e1;padding:4px 8px}'
            .'.callout,.theory-callout{border-left:4px solid #94a3b8;padding:0.5rem 0.75rem;margin:0.75rem 0;background:#f8fafc}'
            .'</style></head><body>'
            .'<h1>'.$h1.'</h1>'.$sub.$body
            .'</body></html>';
    }

    public function markdownForModule(Course $course, CourseModule $module): string
    {
        if (! $course->isLegacyAltCourse()) {
            $summary = AdminCourseContentInspector::databaseModuleContentSummary($course, $module);

            return (string) ($summary['theory_markdown'] ?? '');
        }

        $firstText = null;
        if (Schema::hasTable('course_sections')) {
            $firstText = CourseSection::query()
                ->where('course_module_id', (int) $module->id)
                ->where('type', CourseSection::TYPE_TEXT)
                ->orderBy('sort')
                ->orderBy('id')
                ->first();
        }
        if ($firstText !== null) {
            $md = $this->content->markdownForSection($firstText);
            if ($md !== '') {
                return $md;
            }
        }

        $meta = CourseModuleMeta::resolved($module->effectiveContentIndex());

        return (string) ($meta['theory'] ?? '');
    }

    public function markdownForSection(Course $course, CourseSection $section): string
    {
        if ($section->type !== CourseSection::TYPE_TEXT) {
            return '';
        }

        $md = $this->content->markdownForSection($section);
        if ($md !== '') {
            return $md;
        }

        if ($course->isLegacyAltCourse()) {
            $module = CourseModule::query()->find((int) $section->course_module_id);
            if ($module !== null) {
                $meta = CourseModuleMeta::resolved($module->effectiveContentIndex());

                return (string) ($meta['theory'] ?? '');
            }
        }

        return '';
    }

    /**
     * @return array{ok:bool,binary?:string,error?:string}
     */
    public function zipForCourse(Course $course): array
    {
        if (! class_exists(ZipArchive::class)) {
            return ['ok' => false, 'error' => 'Расширение PHP zip (ZipArchive) недоступно на сервере.'];
        }

        $modules = CourseModule::query()
            ->where('course_id', (int) $course->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($modules->isEmpty()) {
            return ['ok' => false, 'error' => 'В курсе нет модулей.'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'theory-doc-');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'Не удалось создать временный файл.'];
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'Не удалось создать ZIP.'];
        }

        $added = 0;
        foreach ($modules as $i => $module) {
            $md = $this->markdownForModule($course, $module);
            $title = (string) $module->title;
            $html = $this->docHtml($title, $md, (string) $course->title);
            $safe = $this->safeFilename(($i + 1).'-'.$title);
            $zip->addFromString($safe.'.doc', $html);
            $added++;
        }
        $zip->close();

        if ($added < 1) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'Нечего выгружать.'];
        }

        $binary = file_get_contents($tmp) ?: '';
        @unlink($tmp);

        return ['ok' => true, 'binary' => $binary];
    }

    private function absolutizeUrls(string $html): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            return $html;
        }

        return (string) preg_replace_callback(
            '/\b(src|href)="(\/[^"]*)"/i',
            static function (array $m) use ($base): string {
                return $m[1].'="'.$base.$m[2].'"';
            },
            $html
        );
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\-_ .]+/u', '_', $name) ?? 'theory';
        $name = trim($name, ' ._');
        if ($name === '') {
            $name = 'theory';
        }
        if (mb_strlen($name) > 80) {
            $name = mb_substr($name, 0, 80);
        }

        return $name;
    }
}
