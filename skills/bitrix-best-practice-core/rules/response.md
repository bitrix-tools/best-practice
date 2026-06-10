# Response

## Boundaries

- Этот файл покрывает `Bitrix\Main\Response`, `Bitrix\Main\HttpResponse` и основные `Bitrix\Main\Engine\Response\*` как штатный response layer в Bitrix-коде.
- Этот файл помогает выбрать между plain data из controller, `Json`, `AjaxJson`, `Redirect`, render-response, `File` и низкоуровневым `HttpResponse`.
- Этот файл помогает развести response API с ручным `header()`, `Set-Cookie`, `setcookie()` и ad hoc output protocol.
- Этот файл не описывает controller lifecycle, filters и orchestration action целиком; для этого переходи в `rules/controller.md`.
- Этот файл не превращается в каталог всех специализированных response-классов ядра и не описывает подробно legacy output buffering или cookie policy.

## Rules

- Предпочитай штатный response object или controller helper вместо прямого `header()` в прикладном коде.
- Если controller может вернуть plain data и этого достаточно для framework contract, не понижай код до ручного `HttpResponse` без необходимости.
- Заголовки добавляй через `addHeader()` или `getHeaders()`, а не через ad hoc `header('Name: value')`.
- HTTP status задавай через `setStatus()` или `getHeaders()->setStatus()`, а не через ручной `header('HTTP/1.1 ...')`.
- Для JSON по умолчанию используй `Bitrix\Main\Engine\Response\Json`, а не ручной `Content-Type: application/json` и `json_encode(...)` в controller.
- `Bitrix\Main\Engine\Response\AjaxJson` используй только когда нужен именно bitrix-формат envelope `status` / `data` / `errors`, а не как автоматический wrapper для любого JSON response.
- Для redirect используй `Redirect` или `redirectTo()`, а не ручной `Location` header.
- Для render-response используй `renderView()`, `renderComponent()`, `renderComponentAjax()` или `renderExtension()`, а не ручную сборку HTML в `setContent()`.
- Cookies ставь через `Bitrix\Main\Web\Cookie` и `addCookie()`, а не через ручной `Set-Cookie` header или `setcookie()` как default path.
- Не собирай вручную имя cookie, `secure`, `httpOnly`, `sameSite` и domain defaults, если их уже выражает `Bitrix\Main\Web\Cookie`.
- `allowPersistentCookies()` используй как специальный helper для consent-flow про persistent cookies, а не как общий cookie API для любых сценариев.
- `setLastModified()` используй, когда response действительно несет HTTP caching semantics; не добавляй `Last-Modified` декоративно.
- `Bitrix\Main\Engine\Response\File` используй для download / inline-file response, а не ручной пачки `Content-Type`, `Content-Disposition` и `Content-Length`.
- `setContent()`, `appendContent()` и `getContent()` считай low-level base API для кастомного response или infrastructure code; не делай их default path для обычного controller action.
- `copyHeadersTo()` используй только в bridging-сценариях между response objects; это исключение, а не обычный паттерн прикладного action.
- Ручной `header()` допустим только в legacy или очень низкоуровневом boundary-коде, который не живет в нормальном response lifecycle.

## Decision Guide

- Если action возвращает обычный сериализуемый payload и framework сам справляется с ответом, возвращай plain data.
- Если нужен явный JSON response object и контроль HTTP-семантики, используй `Json`.
- Если клиент ждет bitrix envelope со `status` / `errors`, используй `AjaxJson`.
- Если нужен redirect, используй `redirectTo()` или `Redirect`.
- Если нужен HTML/view/component response, используй `render*` helper или `Component` / `HtmlContent`.
- Если нужен file download, используй `Engine\Response\File`.
- Если хочется звать `setContent()` или `header()` из controller action, сначала проверь, не выражается ли сценарий штатным response type.

## Related Rules

- `rules/controller.md` — для того, как action возвращает response в общем controller lifecycle и где проходит граница между orchestration и response handling.
- `rules/request.md` — для чтения request headers, cookies и JSON body до сборки ответа.

## Example 1

Показывает: default path для JSON response через `Engine\Response\Json`.
Почему это good pattern: response сам ставит корректный `Content-Type`, а controller не дублирует низкоуровневую HTTP-логику.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\HttpResponse;

final class StatusController extends Controller
{
	public function pingAction(): HttpResponse
	{
		return new Json([
			'status' => 'ok',
			'timestamp' => time(),
		]);
	}
}
```

## Example 2

Показывает: JSON response в bitrix envelope через `AjaxJson`, когда клиентский contract ждет `status`, `data` и `errors`.
Почему это good pattern: используется готовый response format Bitrix, а не локальный самописный wrapper.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\HttpResponse;

final class SyncController extends Controller
{
	public function finishAction(): HttpResponse
	{
		return AjaxJson::createSuccess([
			'synced' => true,
		]);
	}
}
```

## Example 3

Показывает: redirect через framework-native response API.
Почему это good pattern: redirect оформляется штатным response object, а controller не собирает `Location` header вручную.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpResponse;

final class AuthController extends Controller
{
	public function completeAction(): HttpResponse
	{
		return $this->redirectTo('/company/personal/');
	}
}
```

## Example 4

Показывает: cookie через `Web\Cookie` и `addCookie()`.
Почему это good pattern: `Set-Cookie` оформляется object API Bitrix с framework-native defaults, а не строкой заголовка.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Web\Cookie;

final class PreferencesController extends Controller
{
	public function rememberAction(): HttpResponse
	{
		$response = new HttpResponse();
		$response->addCookie(
			new Cookie('timezone', 'Asia/Yekaterinburg', time() + 30 * 86400)
		);

		return $response;
	}
}
```

## Example 5

Показывает: render-response как соседний сценарий, где action возвращает view вместо JSON или ручного HTML body.
Почему это good pattern: HTML rendering остается в штатном response type, а action не опускается до `setContent()`.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\Render\View;

final class ReportController extends Controller
{
	public function detailsAction(int $reportId): View
	{
		return $this->renderView('report/details', [
			'reportId' => $reportId,
		], withSiteTemplate: false);
	}
}
```

## Example 6

Показывает: file response для download вместо ручной пачки заголовков.
Почему это good pattern: file-specific HTTP behavior живет в специализированном response type, а не размазывается по action.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\File;

final class ExportController extends Controller
{
	public function downloadAction(int $fileId): File
	{
		return (new File('/tmp/export.csv', 'export.csv', 'text/csv'))
			->showInline(false)
			->setCacheTime(0);
	}
}
```

## Example 7

Показывает: узкое low-level исключение для кастомного infrastructure-response, где все же нужен `setContent()` и ручной header.
Когда уместно: код работает на boundary вне обычного controller response lifecycle и не выражается готовым response type.
Почему это допустимо: low-level API изолирован в infrastructure-слое и не превращается в новый default path для action.

```php
use Bitrix\Main\HttpResponse;

final class HealthcheckResponseFactory
{
	public function buildPlainText(): HttpResponse
	{
		$response = new HttpResponse();
		$response->addHeader('Content-Type', 'text/plain; charset=UTF-8');
		$response->setStatus('200 OK');
		$response->setContent("ok\n");

		return $response;
	}
}
```

## Checklist

- Для ответа выбран штатный response mechanism Bitrix, а не ручной `header()` по инерции?
- JSON response оформлен через `Json` или `AjaxJson`, если нужен именно этот envelope, а не через ручной `json_encode()` + `Content-Type`?
- Redirect оформлен через `redirectTo()` или `Redirect`, а не через ручной `Location`?
- Cookies ставятся через `Web\Cookie` и `addCookie()`, а не через `Set-Cookie` string или `setcookie()` как default?
- `allowPersistentCookies()` используется только там, где сценарий действительно про consent на persistent cookies?
- Render/file scenarios не сведены к `setContent()` и ручным заголовкам, если у Bitrix уже есть подходящий response type?
- Low-level `setContent()` / `appendContent()` / `copyHeadersTo()` не стали общим паттерном для обычных controller action?
- Если в коде все же остался ручной `header()`, это действительно legacy или boundary-исключение, а не новый default?
