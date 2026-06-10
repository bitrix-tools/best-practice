# Rule File Example: Complex

Этот reference показывает расширенную форму `rules/*.md` для сложных тем.

Используй его только когда минимального формата уже недостаточно: есть branching, legacy-оговорки, выраженные границы с соседними rules или несколько decision points, которые важно явно разделить.
В complex-формате число примеров не фиксировано: добавляй столько `## Example N`, сколько нужно для покрытия самостоятельных сценариев.

```md
# Routing

## Boundaries

- Этот файл покрывает объявление маршрутов в `<module>/install/routes/`, работу с `RoutingConfigurator`, route params, `PublicPageController` и выбор handler для routing.
- Этот файл не описывает filters, `*Action()` и lifecycle контроллера; для них переходи в `rules/controller.md`.

## Rules

- Для нового API веди маршрут на `[Controller::class, 'action']` с явным HTTP-методом.
- Держи в файле маршрутов только регистрацию URI, а не прикладную логику или обращения к БД.
- Для state-changing endpoint не используй `any()`, если достаточно `post()`, `patch()` или другого конкретного метода.
- Для path-параметров с ожидаемым форматом задавай узкий `->where(...)`, а не оставляй матчинг слишком широким по умолчанию.

## Decision Guide

- Если это новый endpoint с устойчивым контрактом ответа, используй `Bitrix\Main\Engine\Controller`.
- Если это bridge на существующую legacy-страницу, используй `Bitrix\Main\Routing\Controllers\PublicPageController`.
- Если handler одноразовый и очень короткий, допустим `Closure`, но только пока не появились auth, CSRF или рост логики.

## Related Rules

- `rules/controller.md` — для filters, `*Action()`, response lifecycle и ограничений доступа.

## Example 1

Показывает: default path для нового API-эндпоинта на Engine Controller.
Почему это good pattern: routing отвечает только за URI и выбор handler, а защита и lifecycle остаются в контроллере.

```php
use Bitrix\Example\Infrastructure\Controller\DocumentController;
use Bitrix\Main\Routing\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
	$routes
		->prefix('api/v1/documents')
		->group(function (RoutingConfigurator $routes) {
			$routes
				->get('{documentId}/', [DocumentController::class, 'get'])
				->where('documentId', '[0-9]+')
				->name('Example.Document.Get')
			;

			$routes
				->post('/', [DocumentController::class, 'create'])
				->name('Example.Document.Create')
			;
		})
	;
};
```

## Example 2

Показывает: соседний сценарий с legacy bridge через `PublicPageController`.
Когда уместно: существующая PHP-страница еще не переведена на Engine Controller и маршрут нужен как мост.
Почему это допустимо: routing по-прежнему остается тонким, а новая прикладная логика не переезжает в файл маршрутов.

```php
use Bitrix\Main\Routing\Controllers\PublicPageController;
use Bitrix\Main\Routing\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
	$routes
		->any('reports/{any}', new PublicPageController('/reports/index.php'))
		->where('any', '.*')
	;
};
```

## Example 3

Показывает: отдельный decision point для `Closure`-handler как узкого одноразового сценария.
Когда уместно: handler очень короткий, не несет собственного security contract и не требует отдельного controller lifecycle.
Почему это допустимо: complex rule-file покрывает не только default path и legacy bridge, но и дополнительные самостоятельные ветки выбора.

```php
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Routing\Route;
use Bitrix\Main\Routing\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
	$routes->get('health/', function (HttpRequest $request, Route $route) {
		return ['status' => 'ok'];
	});
};
```

## Legacy / Exceptions

- `Closure` допустим только для очень короткого handler без собственного security contract.
- Для legacy bridge права, валидация и CSRF должны проверяться в целевой странице, а не предполагаться из факта маршрута.

## Common Mistakes

- Не переноси бизнес-логику в файл маршрутов.
- Не используй `any()` для операций изменения состояния без реальной необходимости.

## Checklist

- Для нового API выбран `[Controller::class, 'action']`, а не legacy handler?
- В файле маршрутов осталась только регистрация URI, без прикладной логики?
- Для path-параметров заданы достаточно узкие `where(...)`, если формат данных известен?
- `Example 1` показывает default path, а следующие `## Example N` действительно покрывают отдельные соседние или edge сценарии, а не повторяют одну и ту же идею?
- Если в файле есть негативные ограничения, они оформлены без code examples и не превращаются в каталог плохих решений?
```
