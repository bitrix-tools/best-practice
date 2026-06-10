# Rule File Example: Minimal

Этот reference показывает минимальную, но полноценную форму `rules/*.md`.

Используй этот образец как default для большинства новых rule-файлов.
Если тема не требует branching, legacy-оговорок или нескольких пограничных сценариев, ориентируйся именно на этот формат.

```md
# Request DTO

## Boundaries

- Этот файл покрывает request DTO, которые собирают и валидируют связанный набор входных данных до передачи в controller action или service.
- Этот файл не описывает валидационные атрибуты и filters как отдельные механизмы; здесь важен сам input contract, а не весь controller lifecycle.

## Rules

- Используй request DTO, когда action принимает связный набор входных данных с валидацией, нормализацией или вложенной структурой.
- Не раздувай сигнатуру action длинным списком scalar-параметров, если они образуют один логический input contract.
- Держи DTO сфокусированным на входных данных; не переноси в него orchestration, обращения к БД или побочные эффекты.
- Если данные краткоживущие и используются только локально в одном месте без собственного контракта, не создавай DTO без необходимости.

## Example 1

Показывает: базовый рекомендуемый паттерн для request DTO, который собирает и валидирует связанный input contract.
Почему это good pattern: controller получает один понятный объект вместо длинной и хрупкой сигнатуры.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Request;
use Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\NotEmpty;

final readonly class CreateItemRequest
{
	public function __construct(
		#[NotEmpty]
		#[Length(min: 3, max: 255)]
		public string $title,
		#[Length(max: 500)]
		public string $description = '',
	)
	{
	}

	public static function createFromRequest(Request $request): self
	{
		return new self(
			title: (string)$request->get('title'),
			description: (string)$request->get('description'),
		);
	}
}

final class ItemController extends Controller
{
	public function getAutoWiredParameters(): array
	{
		return [
			new ValidationParameter(
				CreateItemRequest::class,
				fn(): CreateItemRequest => CreateItemRequest::createFromRequest($this->getRequest()),
			),
		];
	}

	public function createAction(CreateItemRequest $request, ItemService $service): array
	{
		return $service->create($request);
	}
}
```

## Example 2

Показывает: соседний сценарий, где DTO не нужен, потому что input contract слишком мал и не образует отдельную абстракцию.
Почему это good pattern: правило не навязывает DTO там, где он только добавит шум.

```php
use Bitrix\Main\Engine\Controller;

final class SettingsController extends Controller
{
	public function openAction(int $groupId): array
	{
		return [
			'groupId' => $groupId,
		];
	}
}
```

## Checklist

- DTO вводится там, где действительно есть связный input contract, а не ради формальности?
- В DTO нет orchestration, обращений к БД и побочных эффектов?
- Controller получает один связный объект вместо разросшейся сигнатуры, если данные логически принадлежат одному запросу?
- Если сценарий слишком простой, DTO не добавлен без необходимости?
```
