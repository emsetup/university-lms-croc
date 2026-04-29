# Модуль 7. Контроль целостности файлов (Osec и Afick)

> **Примечание:** раньше здесь был черновик «Журналирование» — тема модуля **G** в LMS перенесена на FIM. Актуальный текст теории для сайта: `laravel-course-files/config/snippets/module_07_theory.md`; квизы — `module_07_theory_quiz_questions.php`, итоговый тест — `module_07_module_exam_questions.php`.

**Цель модуля:** понять уровни контроля целостности (`rpm -V` vs FIM), назначение **Osec** в Альт (ЦУС), переносимость **Afick**, порядок снятия эталона и обновления базы после плановых изменений; сравнение с Ред ОС / «Астра» (AIDE, Parsec).

## Практика

См. блок `practice` модуля 7 в `scripts/fixtures/course-recovered-from-stand-tgz.php` (или `config/course.php` на стенде): `rpm -V`, `apt-cache policy`, короткий комментарий про обновление эталона FIM.
