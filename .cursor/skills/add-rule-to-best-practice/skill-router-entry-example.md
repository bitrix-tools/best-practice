# Skill Router Entry Example

Этот reference показывает, как подключать новый `rules/*.md` в родительский `SKILL.md`, если target skill использует ручной router для выбора rule-файлов.

Используй его только для секций вида:

- `## Выбор rule-файла`
- `### Когда читать rules/<file>.md`

Не используй этот reference как шаблон самого `rules/*.md`; для этого смотри [rule-file-standard.md](rule-file-standard.md), [rule-file-example-minimal.md](rule-file-example-minimal.md) и [rule-file-example-complex.md](rule-file-example-complex.md).

## Базовый шаблон

```md
### Когда читать `rules/<file-name>.md`

Читай `rules/<file-name>.md`, если задача затрагивает хотя бы одну из этих областей:

- <сущность / класс / API / путь 1>;
- <сущность / класс / API / путь 2>;
- <сценарий / decision point 3>;
```

## Что должен делать router entry

- помогать выбрать нужный `rules/*.md`, а не пересказывать его содержимое;
- перечислять короткие trigger terms: сущности, классы, API, пути, сценарии;
- быть уже и короче самого rule-файла;
- разводить соседние rule-файлы, а не создавать overlap на все подряд.

## Minimal example

```md
### Когда читать `rules/request-dto.md`

Читай `rules/request-dto.md`, если задача затрагивает хотя бы одну из этих областей:

- request DTO, input object или request model для controller action;
- валидация и нормализация связанного набора входных данных до вызова service;
- разросшаяся сигнатура `*Action()`, где несколько параметров образуют один input contract.
```

## Complex example

```md
### Когда читать `rules/routing.md`

Читай `rules/routing.md`, если задача затрагивает хотя бы одну из этих областей:

- файл в `<module>/install/routes/` или регистрация маршрутов в `/bitrix/routes/` и `/local/routes/`;
- `RoutingConfigurator`, `prefix`, `group`, HTTP-методы маршрута, `where`, `default`, `name`;
- `PublicPageController`, `Closure`-handler в routing или перенос legacy URL с `urlrewrite.php` на modern routing;
- site-guard и маршруты для конкретного сайта в мультисайтовой установке;
- массив `[Controller::class, 'action']` в маршруте.
```

## Ограничения

- не дублируй весь `rules/*.md` в router entry;
- не перечисляй слишком общие триггеры вроде «роутинг», «контроллер», «битрикс» без уточнения;
- не смешивай в одном entry две разные абстракции, если для них уже есть отдельные rule-файлы;
- не добавляй примеры кода: router entry — это только markdown-блок выбора.
