# Error

## Boundaries

- Этот файл покрывает `Bitrix\Main\Error` и `Bitrix\Main\ErrorCollection` как стандартный контракт ошибки в Bitrix-коде.
- Этот файл помогает выбрать, как оформлять `message`, `code`, `customData`, как переносить уже собранные ошибки между слоями и когда использовать одну ошибку против нескольких.
- Этот файл не описывает целиком controller lifecycle с `addError()`, filters и `return null`; для этого переходи в `rules/controller.md`.

## Rules

- Используй `Bitrix\Main\Error` как wire-level объект ошибки с явными `message`, `code` и, при необходимости, ограниченным `customData`, а не как контейнер для произвольного внутреннего состояния.
- Если ошибка выходит в клиентский или межслойный контракт и по ней может ветвиться логика, задавай стабильный прикладной `code`; не оставляй `0` по умолчанию без необходимости.
- Предпочитай строковые прикладные коды вроде `USER_NOT_FOUND` или `ACCESS_DENIED`, а не случайные числа, HTTP status или текст сообщения как единственный идентификатор ошибки.
- Переноси уже созданные `Error`-объекты через `getErrors()` или `getErrorCollection()`, а не пересоздавай их заново без реальной причины.
- Пересоздавай `Error` только когда действительно меняешь публичный контракт: например, скрываешь внутреннюю причину, добавляешь безопасный `code` или прикрепляешь согласованный `customData`.
- Не отдавай наружу сырое `$exception->getMessage()` как пользовательское сообщение по умолчанию; внутренние исключения сначала маппь в безопасный `Error`.
- Используй `customData` только для маленького и заранее согласованного payload, который безопасно сериализовать в ответ; не складывай туда сырой transport response, stack trace или внутренние объекты.
- Для повторно используемых доменных ошибок предпочитай отдельный error-класс или фабрику поверх `Error`, а не дублирование одинаковых inline-конструкторов по разным контроллерам и сервисам.
- `getError()` используй только когда сценарий по контракту предполагает одну главную ошибку; если ошибок может быть несколько, работай с `getErrors()` или `getErrorCollection()`.
- `ErrorCollection::getErrorByCode()` используй для точечного доступа к уже собранной ошибке по стабильному коду, а не как замену нормальному flow с передачей всей коллекции.

## Decision Guide

- Если сервис или command уже сформировал ошибки, пробрасывай их дальше как есть через `addErrors($result->getErrors())` или `AjaxJson::createError($result->getErrorCollection())`.
- Если ошибка локальная и возникает на transport boundary, допустимо создать один `new Error(...)` прямо в контроллере.
- Если ошибка повторяется в нескольких местах или выражает доменное условие, выделяй отдельный тип ошибки вместо copy-paste inline-string path.
- Если клиенту нужен только первый failure reason, допустим `getError()`; если UI/API должен показать несколько причин, передавай всю коллекцию.
- Если задача не про сам контракт `Error`, а про то, как action завершает lifecycle и превращает ошибки в response, переходи в `rules/controller.md` и `rules/response.md`.

## Related Rules

- `rules/controller.md` — для `addError()`, `addErrors()`, filters, `return null` и общего controller lifecycle.
- `rules/response.md` — для выбора между auto-wrap path, `AjaxJson`, `Json` и другими response-типами.

## Example 1

Показывает: default path, где service возвращает `Result` с уже собранными ошибками, а controller только пробрасывает их в свой lifecycle.
Почему это good pattern: controller не теряет `code` и `customData`, не пересобирает error-contract вручную и остается тонким orchestration-слоем.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Result;

final class ExampleController extends Controller
{
	public function saveAction(ExampleService $service, int $id): ?array
	{
		/** @var Result $result */
		$result = $service->save($id);

		if (!$result->isSuccess())
		{
			$this->addErrors($result->getErrors());

			return null;
		}

		return $result->getData();
	}
}
```

## Example 2

Показывает: локальную transport-level ошибку в controller с явным прикладным кодом.
Почему это good pattern: inline `Error` остается узким guard-сценарием и не подменяет собой доменные ошибки service-слоя.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;

final class ProfileController extends Controller
{
	public function openAction(int $userId): ?array
	{
		if ($userId <= 0)
		{
			$this->addError(new Error('User id is invalid.', 'INVALID_USER_ID'));

			return null;
		}

		return [
			'userId' => $userId,
		];
	}
}
```

## Example 3

Показывает: самостоятельный response path, где action возвращает `AjaxJson::createError(...)` и пробрасывает всю `ErrorCollection`.
Когда уместно: action сам управляет типом ответа и не идет через обычный plain-data + auto-wrap путь.
Почему это good pattern: wire-format ошибок остается стандартным, а response собирается без локального самописного envelope.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Result;

final class ImportController extends Controller
{
	public function finishAction(ImportService $service): HttpResponse
	{
		/** @var Result $result */
		$result = $service->finish();

		if (!$result->isSuccess())
		{
			return AjaxJson::createError($result->getErrorCollection());
		}

		return AjaxJson::createSuccess($result->getData());
	}
}
```

## Checklist

- Для ошибок, уходящих в публичный контракт, задан стабильный прикладной `code`, а не оставлен `0` по инерции?
- Уже созданные `Error`-объекты пробрасываются как есть, без лишнего пересоздания и потери `customData`?
- Сырые тексты внутренних исключений не уходят наружу как пользовательское сообщение по умолчанию?
- `customData` ограничен безопасным и согласованным payload, а не превращен в dump внутреннего состояния?
- Для повторяющейся доменной ошибки выбран отдельный тип ошибки или единая фабрика, а не copy-paste inline-конструкторы?
- Между `getError()` и `getErrors()` / `getErrorCollection()` сделан осознанный выбор по контракту сценария?
