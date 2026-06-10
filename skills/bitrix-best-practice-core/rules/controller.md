# Controller

## Правило

- Наследуй контроллер от `Bitrix\Main\Engine\Controller` или профильного наследника вроде `JsonController`; не строй собственный controller lifecycle поверх произвольного базового класса.
- Держи публичный контракт контроллера в `*Action()`-методах; не прячь action-логику в `init()`, `__construct()`, `processBeforeAction()` или `processAfterAction()`.
- Настраивай доступ, HTTP-методы, CSRF и прочие входные ограничения через filters; если подходящего встроенного filter'а нет, выноси логику в собственный filter, а не дублируй ее по action'ам.
- Для конфигурации filters и action-ограничений предпочитай attributes из `Bitrix\Main\Engine\ActionFilter\Attribute\...`; `configureActions()` используй только там, где это действительно необходимо для совместимости или для сценариев, которые не выражаются атрибутами.
- Переопределяя `getDefaultPreFilters()`, расширяй `parent::getDefaultPreFilters()`, а не пересобирай базовую защиту с нуля без необходимости.
- В `*Action()` держи только orchestration: принять вход, вызвать прикладной код, собрать и вернуть результат.
- Возвращай ответ штатными механизмами Bitrix: plain data, объекты `HttpResponse` и их наследников, а также helper'ы вроде `renderView()`, `renderComponent()`, `renderComponentAjax()`, `renderExtension()` и `redirectTo()`.
- Ошибки добавляй через `addError()` и совместимые исключения lifecycle'а контроллера; не изобретай в контроллере отдельный протокол ошибок поверх стандартного.
- Не держи в контроллере основную бизнес-логику, тяжелые преобразования данных и неочевидные побочные эффекты; выноси их в отдельные классы и слои.
- Не инициализируй сервисы вручную в `init()` и не собирай их через ad hoc код в action'ах; зависимости пробрасывай через параметры `*Action()`-методов, `AutoWire` и контейнерные механизмы фреймворка.
- Для текущего пользователя используй `CurrentUser` через параметры `*Action()`-метода или `$this->getCurrentUser()`; не работай в контроллере через global `$USER`.
- Если action принимает связный набор входных данных, особенно с валидацией, маппингом, фильтрацией, пагинацией или вложенной структурой, выноси их в отдельный request object или DTO и пробрасывай через `AutoWire` или `ValidationParameter`, а не раздувай сигнатуру длинным списком scalar-параметров.
- Если задача упирается в сам error-contract, выбор `code`, `customData` или перенос `ErrorCollection` между слоями, переходи в правило [rules/error.md](./error.md).
- Для чтения query/post/header/cookie и JSON body используй request API Bitrix; про выбор между `getQuery()`, `getPost()`, `JsonPayload` и low-level input path читай в правиле [rules/request.md](./request.md).
- Для выбора типа ответа, cookies, redirect и low-level `HttpResponse` path читай в правиле [rules/response.md](./response.md).
- Про валидацию внутри контроллера читай в правиле [rules/validation.md](./validation.md)

## Пример 1

Ограничения заданы через attributes, зависимость приходит в параметры `*Action()`, action делает только orchestration, ошибки складываются в controller lifecycle, наружу возвращается либо `null`, либо ограниченный контракт из `Result::getData()`.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\Authentication;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\HttpMethod;
use Bitrix\Main\Result;

final class ExampleController extends Controller
{
	#[Authentication]
	#[HttpMethod([HttpMethod::METHOD_POST])]
	public function saveAction(ExampleService $service, int $id): ?array
	{
		/** @var Result $result */
		$result = $service->save($id);

		if (!$result->isSuccess())
		{
			foreach ($result->getErrors() as $error)
			{
				$this->addError($error);
			}

			return null;
		}

		return $result->getData();
	}
}
```

## Пример 2

Когда action должен вернуть render-response фреймворка, используй `render*` helper'ы контроллера вместо ручной сборки HTML и низкоуровневого `HttpResponse`.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\Authentication;
use Bitrix\Main\Engine\Response\Render\Component;
use Bitrix\Main\Engine\Response\Render\View;

final class ExampleRenderController extends Controller
{
	#[Authentication]
	public function detailsAction(PageProvider $provider, int $id): View
	{
		return $this->renderView('example/details', [
			'page' => $provider->getById($id),
		], withSiteTemplate: false);
	}

	#[Authentication]
	public function widgetAction(WidgetProvider $provider): Component
	{
		return $this->renderComponent('bitrix:example.widget', 'default', [
			'items' => $provider->getItems(),
		]);
	}
}
```

## Пример 3

Когда action должен явно управлять HTTP-семантикой ответа, например redirect, специализированным JSON response или другим самостоятельным `HttpResponse`, возвращай объект `HttpResponse` или его наследника.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Engine\Response\Redirect;
use Bitrix\Main\HttpResponse;

final class ExampleHttpResponseController extends Controller
{
	public function statusAction(StatusProvider $provider): HttpResponse
	{
		return new Json([
			'status' => 'ok',
			'serverTime' => $provider->getServerTime(),
		]);
	}

	public function openProfileAction(int $userId): HttpResponse
	{
		return new Redirect("/company/personal/user/{$userId}/");
	}
}
```

## Пример 4

Базовые prefilters расширяются через `parent::getDefaultPreFilters()`, а конкретный action адресно отключает и включает нужные filters через attributes.

```php
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\DisablePrefilters;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\EnablePrefilters;
use Bitrix\Main\Engine\Controller;

final class ExamplePrefilterController extends Controller
{
	protected function getDefaultPreFilters(): array
	{
		return [
			...parent::getDefaultPreFilters(),
			new WorkspaceContextFilter(),
		];
	}

	#[DisablePrefilters([ActionFilter\Csrf::class])]
	#[EnablePrefilters([
		new SignatureFilter(),
	])]
	public function webhookAction(array $payload): ?array
	{
		return [
			'accepted' => true,
		];
	}
}
```

## Пример 5

Текущий пользователь приходит как `CurrentUser`, а action работает с ним как с обычной framework-зависимостью, не читая глобальный `$USER`.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;

final class ExampleCurrentUserController extends Controller
{
	public function meAction(CurrentUser $currentUser, ProfileProvider $provider): ?array
	{
		$userId = (int)$currentUser->getId();
		if ($userId <= 0)
		{
			$this->addError(new Error('User is not authorized.'));

			return null;
		}

		return $provider->getShortProfile($userId);
	}

	public function permissionsAction(CurrentUser $currentUser): array
	{
		return [
			'userId' => (int)$currentUser->getId(),
			'isAdmin' => $currentUser->isAdmin(),
			'canEditSettings' => $currentUser->canDoOperation('edit_php'),
		];
	}
}
```

## Чеклист

- Контроллер наследуется от корректного базового controller-класса?
- Входные ограничения вынесены в attributes и filters?
- В `*Action()` осталась только orchestration-логика?
- Бизнес-логика не зашита в контроллер?
- Зависимости не создаются вручную, а приходят через action params, `AutoWire` или container?
- Текущий пользователь берется через `CurrentUser` или `$this->getCurrentUser()`, а не через global `$USER`?
- Связанные входные данные action сгруппированы в request object или DTO, если сигнатура начала разрастаться или требует валидации?
- Ответ возвращается через штатный response-механизм Bitrix?
- Ошибки оформляются через стандартный controller lifecycle?
