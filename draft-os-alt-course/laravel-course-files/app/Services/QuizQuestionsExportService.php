<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseQuizBank;
use App\Models\CourseSection;
use Illuminate\Support\Facades\Schema;

final class QuizQuestionsExportService
{
    public function __construct(
        private CourseContentService $content,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  array{module?:string,section?:string,kind?:string}|null  $meta
     */
    public function csvForQuestions(array $questions, ?array $meta = null): string
    {
        $withMeta = $meta !== null;
        $columns = $withMeta
            ? ['Модуль', 'Раздел', 'Вид', '№', 'Тип', 'Текст', 'Варианты', 'Правильный ответ', 'Баллы']
            : ['№', 'Тип', 'Текст', 'Варианты', 'Правильный ответ', 'Баллы'];

        $rows = [];
        foreach (array_values($questions) as $i => $q) {
            if (! is_array($q)) {
                continue;
            }
            $n = $i + 1;
            $mapped = $this->mapQuestion($q, $n);
            if ($withMeta) {
                $rows[] = [
                    'Модуль' => (string) ($meta['module'] ?? ''),
                    'Раздел' => (string) ($meta['section'] ?? ''),
                    'Вид' => (string) ($meta['kind'] ?? ''),
                    '№' => (string) $n,
                    'Тип' => $mapped['type'],
                    'Текст' => $mapped['text'],
                    'Варианты' => $mapped['options'],
                    'Правильный ответ' => $mapped['correct'],
                    'Баллы' => $mapped['points'],
                ];
            } else {
                $rows[] = [
                    '№' => (string) $n,
                    'Тип' => $mapped['type'],
                    'Текст' => $mapped['text'],
                    'Варианты' => $mapped['options'],
                    'Правильный ответ' => $mapped['correct'],
                    'Баллы' => $mapped['points'],
                ];
            }
        }

        return $this->csvFromTable($columns, $rows);
    }

    public function csvForBank(CourseQuizBank $bank): string
    {
        return $this->csvForQuestions($this->content->questionsForBank($bank));
    }

    public function csvForCourse(Course $course): string
    {
        $columns = ['Модуль', 'Раздел', 'Вид', '№', 'Тип', 'Текст', 'Варианты', 'Правильный ответ', 'Баллы'];
        $rows = [];

        if (! Schema::hasTable('course_quiz_banks')) {
            return $this->csvFromTable($columns, $rows);
        }

        $banks = CourseQuizBank::query()
            ->where('course_id', (int) $course->id)
            ->orderBy('course_module_id')
            ->orderBy('course_section_id')
            ->orderBy('kind')
            ->orderBy('id')
            ->get();

        $modules = CourseModule::query()
            ->where('course_id', (int) $course->id)
            ->get()
            ->keyBy('id');

        $sections = Schema::hasTable('course_sections')
            ? CourseSection::query()
                ->where('course_id', (int) $course->id)
                ->get()
                ->keyBy('id')
            : collect();

        foreach ($banks as $bank) {
            $module = $bank->course_module_id ? $modules->get((int) $bank->course_module_id) : null;
            $section = $bank->course_section_id ? $sections->get((int) $bank->course_section_id) : null;
            $questions = $this->content->questionsForBank($bank);
            $meta = [
                'module' => $module instanceof CourseModule
                    ? (string) $module->title
                    : ($bank->kind === 'final_lab' ? 'Финальная лаборатория' : ''),
                'section' => $section instanceof CourseSection ? (string) $section->title : '',
                'kind' => $this->kindLabel((string) $bank->kind),
            ];
            foreach (array_values($questions) as $i => $q) {
                if (! is_array($q)) {
                    continue;
                }
                $n = $i + 1;
                $mapped = $this->mapQuestion($q, $n);
                $rows[] = [
                    'Модуль' => $meta['module'],
                    'Раздел' => $meta['section'],
                    'Вид' => $meta['kind'],
                    '№' => (string) $n,
                    'Тип' => $mapped['type'],
                    'Текст' => $mapped['text'],
                    'Варианты' => $mapped['options'],
                    'Правильный ответ' => $mapped['correct'],
                    'Баллы' => $mapped['points'],
                ];
            }
        }

        return $this->csvFromTable($columns, $rows);
    }

    /**
     * @param  array<string, mixed>  $q
     * @return array{type:string,text:string,options:string,correct:string,points:string}
     */
    private function mapQuestion(array $q, int $num): array
    {
        $type = $this->detectType($q);
        $text = $this->plainText((string) ($q['q'] ?? ''));
        if ($text === '') {
            $text = 'Вопрос '.$num;
        }

        $options = '';
        $correct = '';
        $points = isset($q['points']) && is_numeric($q['points']) ? (string) (int) $q['points'] : '';

        if ($type === 'match_drag' || ! empty($q['match_drag'])) {
            $left = is_array($q['left'] ?? null) ? $q['left'] : [];
            $right = is_array($q['right'] ?? null) ? $q['right'] : [];
            $pairs = [];
            $n = max(count($left), count($right));
            for ($i = 0; $i < $n; $i++) {
                $l = $this->plainText((string) ($left[$i] ?? ''));
                $r = $this->plainText((string) ($right[$i] ?? ''));
                $pairs[] = $l.' → '.$r;
            }
            $options = implode(' | ', $pairs);
            $correct = $options;
        } elseif ($type === 'open_text' || ! empty($q['open_text'])) {
            $ph = $this->plainText((string) ($q['placeholder'] ?? ''));
            $ml = isset($q['max_length']) && is_numeric($q['max_length']) ? (int) $q['max_length'] : null;
            $parts = [];
            if ($ph !== '') {
                $parts[] = 'подсказка: '.$ph;
            }
            if ($ml !== null) {
                $parts[] = 'макс. длина: '.$ml;
            }
            $options = implode('; ', $parts);
            $correct = 'открытый ответ';
        } else {
            $answers = is_array($q['a'] ?? null) ? $q['a'] : [];
            $labeled = [];
            foreach (array_values($answers) as $ai => $a) {
                $letter = chr(ord('A') + $ai);
                $labeled[] = $letter.') '.$this->plainText((string) $a);
            }
            $options = implode('; ', $labeled);

            $c = $q['c'] ?? null;
            if (is_array($c)) {
                $parts = [];
                foreach ($c as $idx) {
                    if (! is_numeric($idx)) {
                        continue;
                    }
                    $i = (int) $idx;
                    $letter = chr(ord('A') + $i);
                    $parts[] = $letter.(isset($answers[$i]) ? ' ('.$this->plainText((string) $answers[$i]).')' : '');
                }
                $correct = implode('; ', $parts);
            } elseif (is_numeric($c)) {
                $i = (int) $c;
                $letter = chr(ord('A') + $i);
                $correct = $letter.(isset($answers[$i]) ? ' ('.$this->plainText((string) $answers[$i]).')' : '');
            }

            if ($type === 'multi_other' || ! empty($q['multi_other'])) {
                $ph = $this->plainText((string) ($q['placeholder'] ?? ''));
                if ($ph !== '') {
                    $options = ($options !== '' ? $options.'; ' : '').'свой вариант: '.$ph;
                }
                if ($correct === '') {
                    $correct = 'без проверки правильности';
                }
            }
        }

        return [
            'type' => $this->typeLabel($type),
            'text' => $text,
            'options' => $options,
            'correct' => $correct,
            'points' => $points,
        ];
    }

    /**
     * @param  array<string, mixed>  $q
     */
    private function detectType(array $q): string
    {
        if (! empty($q['match_drag'])) {
            return 'match_drag';
        }
        if (! empty($q['open_text'])) {
            return 'open_text';
        }
        if (! empty($q['multi_other'])) {
            return 'multi_other';
        }
        $c = $q['c'] ?? null;
        if (is_array($c)) {
            return 'multi';
        }

        return 'single';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'multi' => 'Несколько ответов',
            'match_drag' => 'Сопоставление',
            'open_text' => 'Открытый ответ',
            'multi_other' => 'Смешанный',
            default => 'Один ответ',
        };
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'theory_quiz' => 'Тест',
            'module_exam' => 'Экзамен',
            'final_lab' => 'Финальная лаборатория',
            'survey' => 'Опрос',
            default => $kind,
        };
    }

    private function plainText(string $text): string
    {
        $t = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, string>>  $rows
     */
    private function csvFromTable(array $columns, array $rows): string
    {
        $lines = [];
        $lines[] = $this->csvRow($columns);
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = (string) ($row[$col] ?? '');
            }
            $lines[] = $this->csvRow($line);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }

    /**
     * @param  list<string>  $fields
     */
    private function csvRow(array $fields): string
    {
        $out = [];
        foreach ($fields as $f) {
            $f = str_replace('"', '""', (string) $f);
            $out[] = '"'.$f.'"';
        }

        return implode(';', $out);
    }
}
