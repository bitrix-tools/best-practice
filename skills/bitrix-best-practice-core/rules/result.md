# Result

## Boundaries

- Этот файл покрывает `Bitrix\Main\Result` из `main/lib/Result.php` как стандартный return-contract для service, command и integration layer.
- Этот файл помогает решить, когда возвращать именно `Result`, как собирать success/failure outcome через `setData()`, `addError()` и `addErrors()`, и какой payload допустимо класть в `getData()`.
- Этот файл не подменяет собой правила про `Bitrix\Main\Error`, `ErrorCollection`, `code`, `customData` и выбор между `getError()` и `getErrors()`; за error-contract переходи в `rules/error.md`.
- Этот файл не описывает целиком controller lifecycle и типы HTTP-ответов; за `addError()` / `return null` / `AjaxJson` переходи в `rules/controller.md` и `rules/response.md`.

## Rules

- Если межслойному коду нужен возвращаемый DTO или outcome-contract, возвращай `Bitrix\Main\Result`, а не самодельный `*Result`-класс, ассоциативный массив со служебными флагами или ad hoc container.
- Используй `Result` там, где вызывающему коду нужно различать success и failure вместе с ограниченным payload и коллекцией ошибок; если метод возвращает только один доменный объект без error-contract, не оборачивай его в `Result` по инерции.
- Создавай `Result` внутри метода, который формирует outcome, и возвращай его из этого же boundary; не передавай один mutable `Result` через длинную цепочку несвязанных helper-методов.
- `setData()` заполняй только согласованным payload, который реально нужен вызывающему коду; не складывай в `Result` сырой transport response, ORM row целиком или внутреннее состояние "на всякий случай".
- Если на failure-path вызывающему коду payload не нужен, после `addError()` или `addErrors()` предпочитай early return, чтобы не смешивать ошибочный исход и success-data в одном contract.
- Пробрасывай вложенный failure через `addErrors($nestedResult->getErrors())`, а не через схлопывание нескольких ошибок в одну строку или пересборку нового ad hoc сообщения.
- Читай `getData()` только после проверки `isSuccess()`, если только контракт сценария явно не допускает metadata на failure-path; такой exception должен быть редким и осознанным.
- Для повторно используемого return-contract по умолчанию предпочитай единый shape внутри `setData()` и маленькие getter-методы у вызывающего кода, а не отдельный класс-обертку ради пары полей.
- Если типизированный success-payload действительно делает контракт понятнее, допустимо унаследоваться от `Result` и дать ему доменный API поверх базового error-flow; не придумывай при этом отдельные `isSuccess()` / `error` поля и не строй parallel protocol рядом с `Result`.
- В наследнике `Result` допустимы фабричные методы вроде `createSuccess(...)` и `createError(...)`, если они остаются тонкой оберткой над стандартным `Result`-flow и не дублируют одни и те же данные одновременно в `setData()` и в отдельных полях.
- Не используй `Result` как универсальный мешок для любого выхода метода: если данные краткоживущие, локальные и не образуют межслойный contract, верни обычный scalar, массив внешнего API или доменный объект без лишнего envelope.

## Decision Guide

- Если метод должен вернуть и outcome, и payload, и ошибки, используй `Result`.
- Если метод возвращает только payload без error-contract, верни сам payload напрямую.
- Если хочется создать отдельный `FooResult` только ради пары полей и `isSuccess`, сначала проверь, не выражается ли тот же контракт обычным `Bitrix\Main\Result`.
- Если typed success-accessor заметно упрощает контракт, допустим наследник `Result` с `readonly` property или доменными getter-методами, но ошибки должны по-прежнему жить в базовом `Result`.
- Если задача упирается не в сам `Result`, а в публичный контракт ошибок, переходи в `rules/error.md`.
- Если вопрос про то, как controller завершает lifecycle после получения `Result`, переходи в `rules/controller.md` и `rules/response.md`.

## Related Rules

- `rules/error.md` — для `Error`, `ErrorCollection`, `code`, `customData`, `getError()` и `getErrors()`.
- `rules/controller.md` — для controller lifecycle после получения `Result`.
- `rules/response.md` — для `AjaxJson`, `Json` и других response paths поверх уже готового `Result`.

## Example 1

Показывает: базовый service path, где `Bitrix\Main\Result` используется как стандартный return DTO вместо самодельного outcome-класса.
Почему это good pattern: service явно отдает success/failure contract, ограниченный payload и не смешивает ошибочный путь с success-data.

```php
use Bitrix\Main\Error;
use Bitrix\Main\Result;

final class PortalSyncService
{
	public function sync(string $portalCode): Result
	{
		$result = new Result();

		if ($portalCode === '')
		{
			return $result->addError(new Error('Portal code is required.', 'PORTAL_CODE_REQUIRED'));
		}

		$portalId = $this->repository->findIdByCode($portalCode);
		if ($portalId === null)
		{
			return $result->addError(new Error('Portal was not found.', 'PORTAL_NOT_FOUND'));
		}

		$result->setData([
			'portalId' => $portalId,
		]);

		return $result;
	}
}
```

## Example 2

Показывает: composition path, где outer service пробрасывает ошибки вложенного `Result` как есть и не теряет их контракт.
Почему это good pattern: несколько ошибок не схлопываются в одну строку, а вызывающий слой получает тот же failure payload, который собрал inner boundary.

```php
use Bitrix\Main\Result;

final class InviteService
{
	public function resend(int $inviteId): Result
	{
		$result = new Result();
		$loadResult = $this->inviteLoader->load($inviteId);

		if (!$loadResult->isSuccess())
		{
			return $result->addErrors($loadResult->getErrors());
		}

		$invite = $loadResult->getData()['invite'];
		$this->sender->resend($invite);

		$result->setData([
			'inviteId' => $inviteId,
			'resent' => true,
		]);

		return $result;
	}
}
```

## Example 3

Показывает: соседний controller scenario, где action получает готовый `Result`, пробрасывает ошибки в lifecycle и наружу отдает только согласованный payload.
Почему это good pattern: controller не изобретает собственный result-protocol и не переносит в себя правила service layer.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Result;

final class PortalController extends Controller
{
	public function syncAction(PortalSyncService $service, string $portalCode): ?array
	{
		/** @var Result $result */
		$result = $service->sync($portalCode);
		if (!$result->isSuccess())
		{
			$this->addErrors($result->getErrors());

			return null;
		}

		return $result->getData();
	}
}
```

## Example 4

Показывает: допустимый typed wrapper над `Bitrix\Main\Result`, когда success-path удобнее выразить через `readonly` property и фабричные методы.
Почему это good pattern: класс не придумывает свой outcome-protocol, а лишь типизирует happy-path поверх стандартных `addError()` и `getErrors()`.

```php
use Bitrix\Main\Error;
use Bitrix\Main\Result;

final class TeamResult extends Result
{
	private function __construct(public readonly ?Team $team = null)
	{
		parent::__construct();
	}

	public static function createSuccess(Team $team): self
	{
		return new self($team);
	}

	public static function createError(Error $error): self
	{
		$result = new self();
		$result->addError($error);

		return $result;
	}
}
```

## Common Mistakes

- Не создавай отдельный `FooResult` только чтобы хранить `isSuccess`, payload и ошибки, если этот контракт уже покрывает `Bitrix\Main\Result`.
- Не заводи в наследнике `Result` собственные поля `error`, `errors` или отдельный `isSuccess()`, если тот же flow уже выражен базовым классом.
- Не складывай в `setData()` сырой внешний payload целиком, если вызывающему нужны только 1-2 согласованных поля.
- Не схлопывай `getErrors()` в одну строку перед пробросом между слоями.

## Checklist

- Если методу нужен return DTO или outcome-contract между слоями, выбран именно `Bitrix\Main\Result`, а не самодельный контейнер?
- `Result` используется только там, где действительно нужен success/failure contract с payload и ошибками, а не как универсальная обертка для любого метода?
- В `setData()` лежит минимальный и заранее понятный payload для вызывающего кода?
- Если используется наследник `Result`, он только типизирует контракт и не подменяет базовый error-flow собственными полями или своим `isSuccess()`?
- После `addError()` или `addErrors()` failure-path завершается без смешивания с success-data, если metadata на ошибке не оговорена явно?
- Вложенные ошибки пробрасываются через `addErrors($nestedResult->getErrors())`, а не схлопываются в одну строку?
- Граница между `Result`-правилом и error/controller/response rules сохранена без дублирования соседних тем?
