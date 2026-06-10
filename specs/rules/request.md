# Request

## Boundaries

- Этот файл покрывает `Bitrix\Main\Request` / `Bitrix\Main\HttpRequest`, выбор между `get*()`-методами, чтение query/post/header/cookie и работу с JSON body.
- Этот файл помогает развести точные источники входных данных с глобальными `$_REQUEST`, `$_GET`, `$_POST`, `$_COOKIE` и прямым чтением `php://input`.
- Этот файл не описывает controller lifecycle, filters и action attributes целиком; для этого переходи в `rules/controller.md`.
- Этот файл не подменяет request DTO и validation pipeline; если спор идет о `ValidationParameter`, input object или rules на свойствах, переходи в `rules/validation.md`.
- Этот файл не описывает cookie policy, криптографию cookie и детали consent-flow, кроме короткой границы на специальные API вроде `getCookiesMode()`.

## Rules

- Предпочитай `$this->getRequest()`, `Context::getCurrent()->getRequest()` или DI request-объекта вместо прямого чтения `$_REQUEST`, `$_GET`, `$_POST` и `$_COOKIE`.
- Не используй `$_REQUEST` как default path, если источник данных известен: `getQuery*()` для query string, `getPost*()` для form body, `getHeader()` для заголовков и `getCookie*()` для cookies.
- Не смешивай query и body через общий `get()` или `$_REQUEST`, если контракт endpoint различает URI-параметры и тело запроса.
- `get()` допустим только как осознанный compatibility path, когда endpoint по контракту действительно принимает одно и то же имя из merged request parameters и точный источник не важен.
- Для чтения набора query-параметров используй `getQueryList()`, а не цикл по `$_GET`.
- Для чтения набора form-параметров используй `getPostList()`, а не цикл по `$_POST`.
- Для чтения cookies используй `getCookie()` и `getCookieList()`, а не `$_COOKIE`.
- `getCookieRaw()` и `getCookieRawList()` используй только когда действительно нужен raw cookie value до обычной обработки; это исключение, а не общий default.
- Для request headers используй `getHeader()` или `getHeaders()`, а не ручную работу с `$_SERVER['HTTP_*']`.
- Для JSON body используй `isJson()` вместе с `getJsonList()` или `Bitrix\Main\Engine\JsonPayload`, а не самостоятельный `json_decode(file_get_contents('php://input'), true)` в каждом action.
- `decodeJson()` используй как tolerant path, когда пустой или невалидный JSON не должен немедленно ронять flow и код сам умеет обработать отсутствие decoded payload.
- `decodeJsonStrict()` используй как fail-fast path, когда endpoint обязан принимать корректный `application/json` и отсутствие валидного JSON должно прервать сценарий ошибкой.
- `Bitrix\Main\Engine\JsonPayload` предпочитай в controller action, если JSON body должен приехать в action как самостоятельный framework-native input contract, а не как ad hoc разбор в теле метода.
- Для новых JSON-only controller endpoint не собирай вручную проверку `Content-Type` и map payload в action params, если это уже выражается через `JsonController` или `ActionFilter\ContentType`.
- `HttpRequest::getInput()` используй только для узких low-level сценариев, где действительно нужен raw body, а не decoded request contract.
- Если данные из запроса образуют связный input contract с нормализацией или валидацией, читай их через request API и сразу собирай в request DTO; не размазывай чтение superglobals по controller и service.

## Decision Guide

- Если нужен один query-параметр из URL, используй `getQuery()`.
- Если нужен один параметр form POST, используй `getPost()`.
- Если нужен cookie, используй `getCookie()`.
- Если нужен JSON payload целиком в controller action, используй `JsonPayload` или `getJsonList()`.
- Если endpoint обязан принимать только корректный JSON, выбирай strict path через `decodeJsonStrict()` или готовый controller/filter, который уже держит этот контракт.
- Если спор идет не о чтении входа, а о валидации и структуре request object, переходи в `rules/validation.md`.

## Related Rules

- `rules/controller.md` — для filters, `JsonController`, action lifecycle и того, где заканчивается ответственность controller method.
- `rules/validation.md` — для request DTO, `ValidationParameter` и automatic validation до входа в action.

## Example 1

Показывает: базовый рекомендуемый path для controller action, который читает query, form body, header и cookie через request API вместо superglobals.
Почему это good pattern: код явно разделяет источники входа и не маскирует контракт через `$_REQUEST`.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpRequest;

final class ProfileController extends Controller
{
	public function updateAction(): array
	{
		/** @var HttpRequest $request */
		$request = $this->getRequest();

		$userId = (int)$request->getQuery('userId');
		$name = trim((string)$request->getPost('name'));
		$language = (string)$request->getHeader('x-language');
		$timezone = (string)$request->getCookie('timezone');

		return [
			'userId' => $userId,
			'name' => $name,
			'language' => $language,
			'timezone' => $timezone,
		];
	}
}
```

## Example 2

Показывает: JSON endpoint на `JsonController`, где payload приходит в action как `JsonPayload`, а не читается вручную из `php://input`.
Почему это good pattern: `Content-Type` и разбор JSON остаются framework-native, а action работает с уже декодированными данными.

```php
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\JsonController;
use Bitrix\Main\Engine\JsonPayload;

final class ExampleToolbarController extends JsonController
{
	protected function getDefaultPreFilters(): array
	{
		return [
			...parent::getDefaultPreFilters(),
			new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
		];
	}

	public function minimizeAction(JsonPayload $payload): array
	{
		$data = $payload->getData();
		$data = is_array($data) ? $data : [];

		return [
			'context' => (string)($data['toolbar']['context'] ?? ''),
			'item' => (array)($data['item'] ?? []),
		];
	}
}
```

## Example 3

Показывает: strict path для endpoint, где body обязан быть валидным `application/json`, а decoded payload нужен как `ParameterDictionary`.
Почему это good pattern: обязательный JSON contract выражен явно и не размывается tolerant fallback-логикой.

```php
use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;

final class WebhookReceiver
{
	public function receive(): array
	{
		/** @var HttpRequest $request */
		$request = Context::getCurrent()->getRequest();
		$request->decodeJsonStrict();

		$payload = $request->getJsonList();

		return [
			'event' => (string)$payload->get('event'),
			'token' => (string)$payload->get('token'),
		];
	}
}
```

## Example 4

Показывает: узкое low-level исключение, где действительно нужен raw request body и raw cookie value.
Когда уместно: интеграционный boundary сравнивает исходную строку payload или raw cookie signature до дальнейшего разбора.
Почему это допустимо: low-level API используется только на boundary и не превращается в новый default path для обычных action.

```php
use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;

final class SignedCallbackVerifier
{
	public function verify(): bool
	{
		/** @var HttpRequest $request */
		$request = Context::getCurrent()->getRequest();

		$rawBody = HttpRequest::getInput();
		$rawSignature = (string)$request->getCookieRaw('signature');

		return $this->isValidSignature($rawBody, $rawSignature);
	}

	private function isValidSignature(string $rawBody, string $rawSignature): bool
	{
		return $rawBody !== '' && $rawSignature !== '';
	}
}
```

## Checklist

- Входные данные читаются через request API, а не через `$_REQUEST`, `$_GET`, `$_POST` или `$_COOKIE`?
- Для query, form body, headers и cookies выбран точный `get*()`-источник, а не merged lookup по инерции?
- `get()` используется только там, где merged source действительно является частью контракта, а не из лени?
- Для JSON body используется `getJsonList()` / `JsonPayload` / `decodeJson*()`, а не ручной `json_decode(file_get_contents('php://input'))` в action?
- Между tolerant и strict JSON path сделан осознанный выбор?
- `getCookieRaw()` или `HttpRequest::getInput()` не стали новым default и остались только в narrow boundary-scenario?
- Если вход образует связный contract с валидацией или нормализацией, чтение уже готово к передаче в request DTO или validation pipeline?
