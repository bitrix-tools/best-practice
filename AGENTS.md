# Инструкции для агентов

Источник правды — каталог `skills/`.
Каталог `specs/` собирается скриптом и не редактируется вручную (кроме статичной шапки в `specs/index.md`).

## Что редактировать, а что нет

| Путь | Действие |
|------|----------|
| `skills/<skill>/rules/*.md` | Редактировать — здесь живут rule-файлы |
| `skills/<skill>/SKILL.md` между `<!-- rules-dictionary:start -->` и `<!-- rules-dictionary:end -->` | Редактировать — добавлять блоки `### Когда читать rules/...` |
| `skills/<skill>/SKILL.md` вне блока `rules-dictionary` | Редактировать при необходимости |
| `specs/rules/*.md` | **Не редактировать** — копия из `skills/*/rules/` |
| `specs/index.md` между `<!-- sync-rules-dictionary:start -->` и `<!-- sync-rules-dictionary:end -->` | **Не редактировать** — собирается из `skills/*/SKILL.md` |
| `specs/index.md` выше блока `sync-rules-dictionary` | Можно редактировать — статичная шапка справочника |

## Добавление нового правила

После создания правила с помощью скилла `add-rule-to-best-practice` секция `### Когда читать rules/<name>.md` ДОЛЖНА быть **внутри** блока `rules-dictionary` в `skills/<skill>/SKILL.md`.

Затем запусти синхронизацию, если не было прямого запрета:
```bash
php sync-skills-rules-to-specs.php
```
