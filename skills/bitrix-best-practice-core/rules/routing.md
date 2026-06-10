# Routing

## Правило

- Объявляй маршруты модуля в `<module>/install/routes/<name>.php`; при установке модуль подключает файл в `/bitrix/routes/` (и при необходимости в `/local/routes/` через `.settings.php`). Файл должен возвращать callable, принимающий `RoutingConfigurator` — обычно `return static function (RoutingConfigurator $routes) { ... };`.
- В файле маршрутов оставляй только регистрацию URI: префиксы, группы, HTTP-методы, ограничения параметров, controller/handler. Не размещай там бизнес-логику, обращения к БД, тяжёлые вычисления и побочные эффекты; допустимы site-guard и вычисление `$siteDir` / `$sitePrefix` для текущего контекста сайта.
- Помни, что все файлы из `/bitrix/routes/` и `/local/routes/` сливаются в один `Bitrix\Main\Routing\Router`, а сопоставление запроса — **first-match-wins**: срабатывает первый маршрут, чей шаблон подошёл к пути и HTTP-методу; остальные не проверяются. Порядок объявления маршрутов внутри одного файла критичен; на порядок между разными файлами полагаться нельзя — каждый файл должен быть корректен сам по себе.
- Перед modern routing проверяются legacy-правила из `urlrewrite.php` (`$arUrlRewrite`). Если для того же URL есть legacy rewrite, modern-маршрут никогда не выполнится. Не дублируй один и тот же путь в legacy и в routing; при переносе URL убирай конфликтующее legacy-правило.
- Для нового кода и API-эндпоинтов веди маршрут на `Bitrix\Main\Engine\Controller` через массив `[SomeController::class, 'actionName']` и явные HTTP-методы (`get`, `post`, `put`, `patch`, `delete`); доступ, CSRF и прочие ограничения настраивай в контроллере по `rules/controller.md`. Не используй `any()` там, где достаточно одного метода — иначе тот же URL примет нежелательные GET-запросы к операциям изменения состояния.
- Для существующих legacy PHP-страниц (`index.php` в публичной части), которые ещё не переведены на Engine, используй `Bitrix\Main\Routing\Controllers\PublicPageController` как мост: маршрут только подключает целевой файл. Не добавляй новую прикладную логику через `PublicPageController`; новые фичи веди на Engine Controller, если нет жёсткой привязки к старой странице.
- В массиве `[Controller::class, 'actionName']` указывай имя action **без** суффикса `Action` (`view`, а не `viewAction`); `routing_index.php` при необходимости обрежет суффикс сам. После матча запрос уходит в `Application::runController()` с полным lifecycle контроллера (prolog, filters, `*Action()`).
- Для узкого одноразового handler без отдельного контроллера допустим `Closure`; параметры маршрута, `Bitrix\Main\Routing\Route` и `Bitrix\Main\HttpRequest` фреймворк подставит через AutoWire в `routing_index.php`. Closure подходит для простой orchestration; как только появляются права, CSRF, стабильный контракт ответа или рост логики — выноси в Engine Controller.
- Класс action, переданный строкой (`SomeAction::class`), используй только если он реализует `Bitrix\Main\Engine\Contract\RoutableAction`; иначе routing не сможет вызвать handler. В обычном случае предпочитай `[Controller::class, 'action']`.
- Если маршруты относятся только к конкретному сайту в мультисайтовой установке, в начале closure реализуй site-guard: определи текущий сайт по домену и пути запроса (например, через `SiteTable::getByDomain()`), проверь признак или опцию, по которой модуль понимает «свой» сайт, и при несовпадении делай `return;` из closure, не регистрируя маршруты. Без guard маршруты одного сайта окажутся зарегистрированы на другом.
- Маршруты, которые должны работать на всех сайтах (`.well-known`, `stssync`, сервисные пути), объявляй до site-guard или в отдельном файле без guard осознанно. Всё, что объявлено после `return` в ветке guard, на «чужом» сайте не зарегистрируется.
- Для `->prefix()` используй путь **без** ведущего слэша — обычно `$sitePrefix = ltrim($siteDir, '/')`. Для путей в `PublicPageController` используй `$siteDir` **с** ведущим слэшем от корня document root (`$siteDir . 'crm/deal/index.php'`). Путаница `$siteDir` в `prefix()` или `$sitePrefix` в `PublicPageController` даёт двойные или пропущенные слэши в URL и в include.
- Группируй связанные маршруты через `->prefix(...)->group(function (RoutingConfigurator $routes) { ... })`, а не копируй один и тот же префикс на десятках строк. Catch-all хвост `{any}` с `->where('any', '.*')` на уровне группы — стандартный способ отдать «остаток» пути legacy-странице; внутри группы сначала объявляй **узкие** маршруты, затем общий catch-all, иначе catch-all перехватит запросы раньше специфичных правил.
- Имена параметров в URI — только `{name}` в нижнем регистре, буквы, цифры и подчёркивание; фреймворк компилирует их в named capture groups. Для каждого сегмента path с ожидаемым форматом (идентификатор, подпись, код, slug, token и т.п.) задавай `->where('name', '...')` под этот формат: без `where` сегмент матчится как `[^/]+`, что обычно слишком широко. Отдельный параметр `{any}` с `->where('any', '.*')` — для хвоста пути; не подменяй им обычные сегменты и не используй на них паттерны вроде `[\w\W]+`. Примеры паттернов: `[0-9]+` для числового id, `[0-9a-f]{32}` для md5.
- Параметры, которых нет в path, но которые legacy-страница или handler ожидает в query (как `download=1` или флаги режима), задавай через `->default('parameter', $value)`; они попадут в `$_GET` / `$_REQUEST` вместе с сегментами из URI. Не полагайся на неявное поведение без `default`, если целевой `index.php` читает конкретное имя из superglobals.
- Для генерации URL по имени используй `->name('...')` и помни, что полное имя собирается из имён родительских групп; имена должны быть **уникальны среди всех загруженных файлов** маршрутов — дубликат перезапишет предыдущий маршрут в индексе и сломает `Router::route()`.
- У фиксированных leaf-путей без динамического сегмента в конце предпочитай завершающий слэш в шаблоне URI (`.../settings/`) или catch-all `{any}` в конце; иначе веб-сервер может попытаться отдать путь как статический файл и вернуть 404 до `routing_index.php`.
- `PublicPageController` подключает PHP-файл через `include` и завершает запрос через `die()`; prolog и engine filters для такого маршрута не выполняются на уровне routing. Значения параметров маршрута копируются в `$_GET` и `$_REQUEST` — целевой файл обязан сам валидировать вход, проверять права и CSRF. Не считай, что маршрут «защищён» только фактом регистрации в routing.
- Не регистрируй в файле маршрутов «чужого» сайта URL на разделы, для которых этот сайт не предназначен, без явной необходимости и без проверки прав в целевой странице или контроллере; site-guard не заменяет авторизацию внутри handler.
- **Не используй** `->middleware()` в routing для аутентификации, авторизации, CSRF и прочей защиты запроса: routing middleware — ранний callable до prolog или include, а не слой безопасности. Для маршрутов на `Bitrix\Main\Engine\Controller` настраивай доступ и ограничения через filters и attributes по `rules/controller.md`; для `PublicPageController` и legacy-страниц — внутри подключаемого PHP или в самом контроллере, но не через routing middleware.
- Compiled regex маршрутов кешируется (`CompileCache` по `mtime` файлов маршрутов); при отладке «маршрут не матчится» после правки файла учитывай сброс кеша routing. Это не повод отключать кеш в проде, но объясняет расхождение между кодом в репозитории и поведением на стенде.

## Пример 1

Каркас файла маршрутов модуля: только регистрация, callable с `RoutingConfigurator`, без прикладной логики.

```php
<?php

use Bitrix\Main\Routing\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
	// ...
};
```

## Пример 2

Новый API-эндпоинт на Engine Controller: явные HTTP-методы, action без суффикса `Action`, `where` на параметрах, именованный маршрут, завершающий слэш на leaf-пути. Защита запроса — в контроллере (`rules/controller.md`), не в файле маршрутов.

```php
<?php

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

			$routes
				->patch('{documentId}/', [DocumentController::class, 'update'])
				->where('documentId', '[0-9]+')
				->name('Example.Document.Update')
			;
		})
	;
};
```

## Пример 3

Legacy-страница через `PublicPageController`: `$sitePrefix` в `prefix()`, `$siteDir` в пути к файлу, группа с узкими маршрутами и catch-all, `where` и `default` для параметров, которые читает `index.php`.

```php
<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Routing\Controllers\PublicPageController;
use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\SiteTable;

return static function (RoutingConfigurator $routes) {
	$siteDir = '/';
	$request = Application::getInstance()->getContext()->getRequest();
	$site = SiteTable::getByDomain($request->getHttpHost(), $request->getRequestedPageDirectory());

	if (Option::get('example', 'portal_site_id') !== ($site['LID'] ?? ''))
	{
		return;
	}

	$siteDir = $site['DIR'] ?? '/';
	$sitePrefix = ltrim($siteDir, '/');

	$routes
		->prefix($sitePrefix . 'storage')
		->where('any', '.*')
		->group(function (RoutingConfigurator $routes) use ($siteDir) {
			$routes
				->any('file/{objectId}/download/', new PublicPageController($siteDir . 'storage/download.php'))
				->where('objectId', '[0-9]+')
				->default('download', '1')
			;

			$routes
				->any('shared/{hash}/', new PublicPageController($siteDir . 'storage/shared.php'))
				->where('hash', '[0-9a-f]{32}')
			;

			$routes->any('{any}', new PublicPageController($siteDir . 'storage/index.php'));
		})
	;
};
```

## Пример 4

Site-guard в начале closure: маршруты не регистрируются на «чужом» сайте. Сервисные маршруты, нужные на всех сайтах, объявляй до guard.

```php
<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Routing\Controllers\PublicPageController;
use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\SiteTable;

return static function (RoutingConfigurator $routes) {
	// Глобальные маршруты — до site-guard
	$routes->any('.well-known/{any}', new PublicPageController('/bitrix/groupdav.php'))
		->where('any', '.*')
	;

	$request = Application::getInstance()->getContext()->getRequest();
	$site = SiteTable::getByDomain($request->getHttpHost(), $request->getRequestedPageDirectory());

	if (Option::get('example', 'module_site_id') !== ($site['LID'] ?? ''))
	{
		return;
	}

	$siteDir = $site['DIR'] ?? '/';

	$routes->any('example/section/{any}', new PublicPageController($siteDir . 'example/section/index.php'))
		->where('any', '.*')
	;
};
```

## Пример 5

Простой `Closure` с AutoWire: подходит для короткой orchestration без отдельного контроллера. Как только нужны права, CSRF или устойчивый контракт ответа — переноси на Engine Controller (пример 2).

```php
<?php

use Bitrix\Main\HttpRequest;
use Bitrix\Main\Routing\Route;
use Bitrix\Main\Routing\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
	$routes->get('health/', function (HttpRequest $request, Route $route) {
		return ['status' => 'ok'];
	});
};
```

## Пример 6

Порядок маршрутов внутри группы: first-match-wins — сначала узкий шаблон, catch-all `{any}` в конце. Иначе catch-all перехватит запросы раньше специфичных правил.

```php
<?php

use Bitrix\Example\Infrastructure\Controller\ItemController;
use Bitrix\Main\Routing\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
	$routes
		->prefix('api/v1/items')
		->group(function (RoutingConfigurator $routes) {
			// Сначала специфичные
			$routes
				->get('search/', [ItemController::class, 'search'])
				->name('Example.Item.Search')
			;

			$routes
				->get('{itemId}/', [ItemController::class, 'get'])
				->where('itemId', '[0-9]+')
				->name('Example.Item.Get')
			;

			// Catch-all — последним
			$routes
				->any('{any}', [ItemController::class, 'fallback'])
				->where('any', '.*')
			;
		})
	;
};
```

## Пример 7

Не используй `->middleware()` для auth и CSRF. Маршрут только указывает на контроллер; `Authentication`, `HttpMethod` и остальное — в filters/attributes контроллера.

```php
<?php

// Файл маршрутов — только URI → handler
use Bitrix\Example\Infrastructure\Controller\WebhookController;
use Bitrix\Main\Routing\RoutingConfigurator;

return static function (RoutingConfigurator $routes) {
	$routes
		->post('api/v1/webhook/{token}/', [WebhookController::class, 'receive'])
		->where('token', '[0-9a-zA-Z_-]+')
	;
};

// Защита — в контроллере (rules/controller.md), не в ->middleware()
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\Authentication;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\Csrf;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\HttpMethod;
use Bitrix\Main\Engine\Controller;

final class WebhookController extends Controller
{
	#[Authentication]
	#[Csrf]
	#[HttpMethod([HttpMethod::METHOD_POST])]
	public function receiveAction(string $token): ?array
	{
		// ...
	}
}
```

## Чеклист

- Маршруты объявлены в `<module>/install/routes/` и файл возвращает callable с `RoutingConfigurator`?
- В файле маршрутов нет бизнес-логики, SQL и тяжёлых побочных эффектов — только регистрация URI?
- Для site-specific маршрутов есть site-guard с `return;` на «чужом» сайте; глобальные маршруты объявлены до guard?
- Нет конфликта с legacy `$arUrlRewrite` для того же URL?
- Новый API ведёт на `[Engine\Controller::class, 'action']`, а не на `PublicPageController`?
- Для операций изменения состояния указан явный HTTP-метод (`post`, `patch`, …), а не `any()` без необходимости?
- Имя action в маршруте без суффикса `Action`?
- Legacy-страница подключена через `PublicPageController`, а не через новую логику в файле маршрутов?
- `Closure` использован только для простого handler; при auth, CSRF или росте логики есть отдельный контроллер?
- `SomeAction::class` в маршруте использован только при реализации `RoutableAction`?
- `$sitePrefix` (без ведущего `/`) в `prefix()`, `$siteDir` (с `/`) в `PublicPageController`?
- Связанные маршруты сгруппированы через `prefix` + `group`, а не продублированы построчно?
- Узкие маршруты объявлены раньше catch-all `{any}` в той же группе?
- У каждого смыслового сегмента path с ожидаемым форматом данных (не у служебного `{any}`) задан `->where()` с узким паттерном, без `[\w\W]+` и `.*` на обычных параметрах?
- Параметры для legacy/handler, которых нет в path, заданы через `default`, если их ждут в `$_GET` / `$_REQUEST`?
- Именованные маршруты (`name`) уникальны среди всех загруженных файлов?
- У фиксированных leaf-путей есть завершающий `/` или catch-all `{any}` в конце?
- Для `PublicPageController` права, валидация и CSRF проверяются в подключаемом PHP, а не предполагаются из факта маршрута?
- Site-guard дополнен авторизацией в handler, где URL не должен быть доступен «чужим» пользователям?
- Auth, CSRF и доступ для Engine Controller настроены в filters/attributes (`rules/controller.md`), а не в `->middleware()` routing?
- При «маршрут не матчится» после правки проверен сброс кеша compiled routing?
