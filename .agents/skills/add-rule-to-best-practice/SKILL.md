---
name: add-rule-to-best-practice
description: Создает и усиливает rule-файлы для best-practice skills с каталогом `rules/`. Используй, когда нужно добавить новый раздел best-practice, переписать существующий `rules/*.md`, сделать rule-файл понятнее для AI и привести его к единому AI-first стандарту.
disable-model-invocation: true
metadata:
  internal: true
---

# Add Rule To Best Practice

Этот skill нужен для создания и улучшения `rules/*.md` внутри целевого best-practice skill directory.

Не используй его как общий guide по Markdown, по Bitrix-коду или по структуре всех project skills.
Сначала определи target directory, где лежит нужный skill с `SKILL.md` и каталогом `rules/`.
Если пользователь не указал путь явно и из контекста есть несколько кандидатов, сначала уточни target directory.
Не обновляй `SKILL.md`, или другие skill-файлы внутри target directory, если пользователь не попросил это отдельно.

## Что делает skill

- пишет новый rule-file для конкретной Bitrix-сущности или слоя;
- усиливает существующий rule-file до AI-first формата;
- удерживает единый уровень качества относительно `rules/controller.md` и `rules/routing.md`;
- помогает не смешивать соседние абстракции в одном файле.

## Обязательное чтение

Перед созданием или переписыванием rule-файла:

1. Прочитай `SKILL.md` в target directory, чтобы сохранить согласованность с родительским skill.
2. Прочитай [rule-file-standard.md](rule-file-standard.md).
3. Для default-структуры и формулировок примеров прочитай [rule-file-example-minimal.md](rule-file-example-minimal.md).
4. Если тема сложная, с branching, legacy или несколькими decision points, дополнительно прочитай [rule-file-example-complex.md](rule-file-example-complex.md).
5. Если target `SKILL.md` использует ручной router для rule-файлов, прочитай [skill-router-entry-example.md](skill-router-entry-example.md).
6. Если целевой `rules/*.md` уже существует в target directory, прочитай его целиком.
7. Прочитай 1-2 соседних `rules/*.md` в target directory, которые ближе всего по слою и ответственности.
9. Добери только тот framework context, который нужен именно для данной сущности.

## Workflow

1. Определи точную сущность rule-файла и его границы.
2. Отдели, что должно жить в этом файле, а что должно остаться в соседних rules.
3. Собери структуру по canonical sections из [rule-file-standard.md](rule-file-standard.md).
4. Выбери образец формы: [rule-file-example-minimal.md](rule-file-example-minimal.md) по умолчанию, [rule-file-example-complex.md](rule-file-example-complex.md) только для сложных тем.
5. Сначала напиши правила, затем примеры, затем бинарный чеклист.
6. Проверь, все ли самостоятельные decision points, edge cases, compatibility paths и исключения получили примеры; если нет, добавь ещё `## Example N`, не ограничиваясь двумя секциями.
7. Проверь, как `SKILL.md` в target directory маршрутизирует rule-файлы; если там есть явный router-блок вида «Когда читать <имя-файла>.md», добавь для нового rule-файла корректный trigger-блок по образцу из [skill-router-entry-example.md](skill-router-entry-example.md), чтобы он стал discoverable.
8. Удали общие рассуждения, повторы и пункты, где в одном bullet смешано несколько норм.
9. Проверь, что файл помогает агенту принимать решения в коде, а не превращается в мини-учебник по всему Bitrix.

## Требования к результату

- Каждое правило должно быть операционным и приводить к конкретному решению в коде или ревью.
- При пересечении с другой абстракцией явно отправляй в соседний `rules/*.md`.
- Примеры делай self-contained и поясняй, какую норму они демонстрируют.
- Для сложных тем число `## Example N` не фиксировано: покрывай все самостоятельные важные кейсы, а не останавливайся на двух примерах по инерции.
- Чеклист должен работать как self-review в формате `да/нет`.
- Предпочитай reference-файлы этого skill как источник стандарта, а не случайные текущие rule-файлы из target directory.
- Если target `SKILL.md` использует ручной router для rule-файлов, новый `rules/*.md` должен получить корректный trigger-блок в этом router.

## Когда остановиться и уточнить

Не угадывай и спроси пользователя, если:

- новый rule одинаково сильно пересекает две разные абстракции;
- project convention конфликтует с framework best practice и неясно, что здесь главнее;
- пользователь на самом деле хочет менять skill-router или глобальный шаблон, а не только целевой rule-file.
