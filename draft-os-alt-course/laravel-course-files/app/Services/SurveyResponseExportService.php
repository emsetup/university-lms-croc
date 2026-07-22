<?php

namespace App\Services;

use App\Models\CourseQuizQuestion;
use App\Models\CourseSection;
use App\Models\CourseSurveySubmission;
use App\Models\Learner;
use App\Support\LearnerDisplay;

final class SurveyResponseExportService
{
    public function __construct(
        private SurveyResponseService $surveys,
        private CourseSectionService $sections,
    ) {}

    /**
     * @return array{anonymous:bool,columns:list<string>,rows:list<array<string,string>>}
     */
    public function tableForSection(CourseSection $section): array
    {
        $settings = $this->sections->mergedSettings($section);
        $anonymous = (bool) ($settings['anonymous'] ?? false);
        $bank = $this->surveys->bankForSection((int) $section->id);
        $questions = $bank
            ? CourseQuizQuestion::query()->where('quiz_bank_id', (int) $bank->id)->orderBy('sort')->orderBy('id')->get()
            : collect();

        $qCols = [];
        foreach ($questions as $qi => $q) {
            $label = $this->shortLabel((string) $q->question_text, $qi + 1);
            $qCols[] = $label;
        }

        $columns = $anonymous
            ? array_merge(['№ ответа', 'Дата'], $qCols)
            : array_merge(['Email', 'ФИО', 'Дата'], $qCols);

        $submissions = $this->surveys->submissionsForSection((int) $section->id);
        $rows = [];
        $n = 0;
        foreach ($submissions as $sub) {
            $n++;
            $answersByQ = $sub->answers->keyBy('question_id');
            $row = [];
            if ($anonymous) {
                $row['№ ответа'] = (string) $n;
                $row['Дата'] = $sub->submitted_at?->format('d.m.Y H:i') ?? '';
            } else {
                $learner = $sub->learner;
                $email = $learner instanceof Learner ? (string) ($learner->email ?? '') : '';
                $row['Email'] = $email;
                $row['ФИО'] = $learner instanceof Learner ? LearnerDisplay::portalDisplayName($learner) : '';
                $row['Дата'] = $sub->submitted_at?->format('d.m.Y H:i') ?? '';
            }
            foreach ($questions->values() as $qi => $q) {
                $label = $this->shortLabel((string) $q->question_text, $qi + 1);
                $ans = $answersByQ->get((int) $q->id);
                $row[$label] = $ans ? $this->surveys->formatAnswerDisplay($q, $ans, null) : '';
            }
            $rows[] = $row;
        }

        return ['anonymous' => $anonymous, 'columns' => $columns, 'rows' => $rows];
    }

    /**
     * @return array{anonymous:bool,submitted:bool,submitted_at:?string,items:list<array<string,string>>}
     */
    public function cardForLearner(CourseSection $section, int $learnerId): array
    {
        $settings = $this->sections->mergedSettings($section);
        $anonymous = (bool) ($settings['anonymous'] ?? false);
        $sub = $this->surveys->completeSubmissionForLearner((int) $section->id, $learnerId);
        if ($sub === null) {
            return ['anonymous' => $anonymous, 'submitted' => false, 'submitted_at' => null, 'items' => []];
        }
        if ($anonymous) {
            return [
                'anonymous' => true,
                'submitted' => true,
                'submitted_at' => $sub->submitted_at?->format('d.m.Y H:i'),
                'items' => [],
            ];
        }

        $items = [];
        foreach ($this->surveys->breakdownItems($sub) as $it) {
            $items[] = [
                'question' => $it['question_text'],
                'answer' => $it['display'],
            ];
        }

        return [
            'anonymous' => false,
            'submitted' => true,
            'submitted_at' => $sub->submitted_at?->format('d.m.Y H:i'),
            'items' => $items,
        ];
    }

    public function csvContent(CourseSection $section): string
    {
        $table = $this->tableForSection($section);

        return $this->csvFromTable($table['columns'], $table['rows']);
    }

    /**
     * @return array{
     *   anonymous:bool,
     *   columns:list<string>,
     *   rows:list<array<string, string>>
     * }
     */
    public function longFormForSection(CourseSection $section): array
    {
        $settings = $this->sections->mergedSettings($section);
        $anonymous = (bool) ($settings['anonymous'] ?? false);
        $columns = $anonymous
            ? ['№ ответа', 'Дата', 'Вопрос', 'Ответ']
            : ['Обучающийся', 'Email', 'Дата', 'Вопрос', 'Ответ'];

        $rows = [];
        $n = 0;
        foreach ($this->surveys->submissionsForSection((int) $section->id) as $sub) {
            $n++;
            $learner = $sub->learner;
            $email = $learner instanceof Learner ? (string) ($learner->email ?? '') : '';
            $name = $learner instanceof Learner ? LearnerDisplay::portalDisplayName($learner) : '';
            $date = $sub->submitted_at?->format('d.m.Y H:i') ?? '';
            $respondent = $anonymous
                ? 'Ответ '.$n
                : ($name !== '' ? $name : $email);

            foreach ($this->surveys->breakdownItems($sub) as $it) {
                $row = [];
                if ($anonymous) {
                    $row['№ ответа'] = (string) $n;
                    $row['Дата'] = $date;
                    $row['Вопрос'] = (string) ($it['question_text'] ?? '');
                    $row['Ответ'] = (string) ($it['display'] ?? '');
                } else {
                    $row['Обучающийся'] = $respondent;
                    $row['Email'] = $email;
                    $row['Дата'] = $date;
                    $row['Вопрос'] = (string) ($it['question_text'] ?? '');
                    $row['Ответ'] = (string) ($it['display'] ?? '');
                }
                $rows[] = $row;
            }
        }

        return ['anonymous' => $anonymous, 'columns' => $columns, 'rows' => $rows];
    }

    public function longFormCsvContent(CourseSection $section): string
    {
        $table = $this->longFormForSection($section);

        return $this->csvFromTable($table['columns'], $table['rows']);
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
        $body = implode("\r\n", $lines);

        return "\xEF\xBB\xBF".$body;
    }

    private function shortLabel(string $text, int $num): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($t === '') {
            return 'Вопрос '.$num;
        }
        if (mb_strlen($t) > 60) {
            return mb_substr($t, 0, 57).'…';
        }

        return $t;
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
