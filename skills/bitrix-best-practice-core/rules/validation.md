# Validation

## Boundaries

- Этот файл покрывает `Bitrix\Main\Validation`: attributes из `Bitrix\Main\Validation\Rule\...`, `ValidationService`, `ValidationResult`, `ValidationError`, `ValidationGroup`, `ValidationParameter` и `#[Validatable]`.
- Этот файл помогает выбрать между validation attributes на scalar-параметрах, request DTO через `ValidationParameter` и явным `ValidationService::validate()` вне controller AutoWire.
- Этот файл описывает built-in validation rules, class-level validation и написание custom validators / custom attributes.
- Этот файл не описывает controller lifecycle целиком, response handling, `addError()` и filters; для этого переходи в `rules/controller.md`.
- Этот файл не описывает URI-level ограничения; для этого переходи в `rules/routing.md`.
- Этот файл не описывает ORM field validators и другие не-`Bitrix\Main\Validation` validation pipelines как основной default.

## Rules

- Для framework-native валидации входных данных используй `Bitrix\Main\Validation`, а не разрозненные ручные `if`/`throw` до тех пор, пока вход можно выразить через attributes, `ValidationParameter` или `ValidationService`.
- Для одного-двух независимых scalar-параметров `*Action()` предпочитай validation attributes прямо на параметрах метода, а не искусственный DTO только ради одной проверки.
- Если action принимает связный набор входных данных с маппингом, нормализацией, несколькими правилами или вложенной структурой, группируй его в request DTO и пробрасывай через `ValidationParameter`.
- Держи request DTO сфокусированным на input contract: чтение из `Request`, простая нормализация, attributes на свойствах и больше ничего.
- Если объект создается или живет вне controller AutoWire, валидируй его явным `ValidationService::validate()` в месте, где формируется или принимается этот контракт.
- Используй built-in attributes из `Bitrix\Main\Validation\Rule\...` как default path до тех пор, пока контракт выражается штатными правилами без дополнительной ветвящейся логики.
- `Bitrix\Main\Validation\Rule\Range` используй для одной числовой границы `min..max`; не раскладывай такой контракт на соседние `Bitrix\Main\Validation\Rule\Min` и `Bitrix\Main\Validation\Rule\Max`, если нужен именно единый диапазон.
- `Bitrix\Main\Validation\Rule\PhoneOrEmail` используй только когда оба формата одинаково допустимы по контракту; не подменяй им более строгий доменный выбор между телефоном и email.
- `Bitrix\Main\Validation\Rule\ElementsType` используй для коллекций, где нужно ограничить тип каждого элемента scalar-type enum или class-name; если элементы сами несут validation rules, добавляй рядом `Bitrix\Main\Validation\Rule\Recursive\Validatable`, а не пытайся заменить им рекурсивную валидацию.
- `Bitrix\Main\Validation\Rule\AtLeastOnePropertyNotEmpty` и `Bitrix\Main\Validation\Rule\OnlyOneOfPropertyRequired` используй для инвариантов между несколькими свойствами, которые нельзя выразить независимой проверкой одного поля.
- Группы валидации используй только когда один и тот же object contract реально живет в нескольких режимах вроде `create` и `update`; не вводи `ValidationGroup` без второго самостоятельного сценария.
- Если built-in rule не выражает контракт без побочных условий, пиши custom `ValidatorInterface` и оборачивай его в custom attribute вместо переноса validation logic в controller или service.
- Custom property attribute по умолчанию строй на `AbstractPropertyValidationAttribute`; прямую реализацию `PropertyValidationAttributeInterface` используй только когда стандартная композиция validator-ов не покрывает нужную логику.
- Custom class-level rule строй на `AbstractClassValidationAttribute`, если проверка зависит от комбинации нескольких свойств объекта.
- `ValidationResult` и `ValidationError` считай основным контрактом ошибок валидации; не придумывай параллельный формат ошибок для тех же validation rules.
- Помни, что `ValidationService` сначала валидирует property attributes, затем class attributes, а неинициализированное non-nullable свойство даст ошибку еще до custom rule.

## Decision Guide

- Если вход состоит из нескольких независимых scalar-параметров и каждому нужна локальная проверка, ставь attributes прямо на `*Action()`-параметры.
- Если несколько параметров образуют один связный input contract, который нужно собрать из `Request`, нормализовать или рекурсивно валидировать, используй request DTO + `ValidationParameter`.
- Если объект создается вне controller binder, например в command, service или отдельном application flow, валидируй его через `ValidationService::validate()`.
- Если правило относится к одному значению, выбирай property attribute; если правило описывает отношение между несколькими полями, выбирай class-level attribute.
- Если built-in rules покрывают контракт без дополнительных веток, не пиши custom validator.
- Если нужно ограничить, какие rules работают в конкретном сценарии `create` / `update`, добавляй `groups` в attribute и передавай группу в `ValidationService`.

## Built-in Attributes Reference

Этот раздел - короткий reference по встроенным validation attributes: полный FQCN, когда использовать и какой validator или механизм стоит underneath. Реальные кодовые примеры смотри в `## Example N`.

### Property / Parameter Attributes

- `Bitrix\Main\Validation\Rule\NotEmpty`
  Когда использовать: значение обязательно и не должно быть empty.
  Underlying validator: `Bitrix\Main\Validation\Validator\NotEmptyValidator`.

- `Bitrix\Main\Validation\Rule\Email`
  Когда использовать: поле обязано содержать email; `strict` и `domainCheck` включай только если контракт реально строже дефолта.
  Underlying validator: `Bitrix\Main\Validation\Validator\EmailValidator`.

- `Bitrix\Main\Validation\Rule\Length`
  Когда использовать: строка должна удовлетворять ограничению по длине `min` и/или `max`.
  Underlying validator: `Bitrix\Main\Validation\Validator\LengthValidator`.

- `Bitrix\Main\Validation\Rule\Min`
  Когда использовать: числовое значение не должно быть меньше нижней границы.
  Underlying validator: `Bitrix\Main\Validation\Validator\MinValidator`.

- `Bitrix\Main\Validation\Rule\Max`
  Когда использовать: числовое значение не должно быть больше верхней границы.
  Underlying validator: `Bitrix\Main\Validation\Validator\MaxValidator`.

- `Bitrix\Main\Validation\Rule\Range`
  Когда использовать: одно числовое значение должно лежать в закрытом диапазоне `min..max`.
  Underlying validators: `Bitrix\Main\Validation\Validator\MinValidator` и `Bitrix\Main\Validation\Validator\MaxValidator`.

- `Bitrix\Main\Validation\Rule\PositiveNumber`
  Когда использовать: значение обязано быть положительным целым/числовым контрактом уровня `>= 1`.
  Underlying validator: `Bitrix\Main\Validation\Validator\MinValidator` с границей `1`.

- `Bitrix\Main\Validation\Rule\RegExp`
  Когда использовать: формат значения задается конкретным regex-паттерном.
  Underlying validator: `Bitrix\Main\Validation\Validator\RegExpValidator`.

- `Bitrix\Main\Validation\Rule\Url`
  Когда использовать: поле обязано быть валидным URL.
  Underlying validator: `Bitrix\Main\Validation\Validator\UrlValidator`.

- `Bitrix\Main\Validation\Rule\Phone`
  Когда использовать: поле обязано содержать телефон в формате, который понимает Bitrix phone parser.
  Underlying validator: `Bitrix\Main\Validation\Validator\PhoneValidator`.

- `Bitrix\Main\Validation\Rule\PhoneOrEmail`
  Когда использовать: контракт одинаково допускает либо телефон, либо email в одном и том же поле.
  Underlying validators: `Bitrix\Main\Validation\Validator\PhoneValidator` и `Bitrix\Main\Validation\Validator\EmailValidator`.

- `Bitrix\Main\Validation\Rule\Json`
  Когда использовать: поле обязано содержать JSON-строку.
  Underlying validator: `Bitrix\Main\Validation\Validator\JsonValidator`.

- `Bitrix\Main\Validation\Rule\InArray`
  Когда использовать: значение должно входить в заранее известный whitelist.
  Underlying validator: `Bitrix\Main\Validation\Validator\InArrayValidator`.

### Collection / Recursive Attributes

- `Bitrix\Main\Validation\Rule\ElementsType`
  Когда использовать: у коллекции нужно ограничить тип каждого элемента scalar-type enum из `Bitrix\Main\Validation\Rule\Enum\Type` или class-name.
  Underlying mechanism: отдельная логика в самом attribute, без выделенного built-in validator.

- `Bitrix\Main\Validation\Rule\Recursive\Validatable`
  Когда использовать: нужно рекурсивно провалидировать вложенный DTO или iterable коллекцию DTO, которые уже несут собственные validation attributes.
  Underlying mechanism: рекурсивный вызов `Bitrix\Main\Validation\ValidationService`.

### Class-Level Attributes

- `Bitrix\Main\Validation\Rule\AtLeastOnePropertyNotEmpty`
  Когда использовать: из нескольких полей хотя бы одно обязано быть заполнено.
  Underlying validator: `Bitrix\Main\Validation\Validator\AtLeastOneNotEmptyValidator`.

- `Bitrix\Main\Validation\Rule\OnlyOneOfPropertyRequired`
  Когда использовать: из нескольких полей должен быть заполнен ровно один вариант.
  Underlying mechanism: отдельная логика в самом attribute, без выделенного built-in validator.

## Related Rules

- `rules/controller.md` — для выбора controller pattern, lifecycle ошибок, `addError()` и того, где заканчивается ответственность action.
- `rules/routing.md` — для route-level ограничений вроде `where(...)`, HTTP-методов и legacy routing handler-ов.

## Example 1

Показывает: default path для property / parameter attributes прямо на `*Action()`-параметрах.
Почему это good pattern: простые ограничения видны в сигнатуре action и не требуют лишнего DTO.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\InArray;
use Bitrix\Main\Validation\Rule\Json;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\Max;
use Bitrix\Main\Validation\Rule\Min;
use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\Rule\Phone;
use Bitrix\Main\Validation\Rule\PhoneOrEmail;
use Bitrix\Main\Validation\Rule\PositiveNumber;
use Bitrix\Main\Validation\Rule\Range;
use Bitrix\Main\Validation\Rule\RegExp;
use Bitrix\Main\Validation\Rule\Url;

final class ContactController extends Controller
{
	public function createAction(
		#[PositiveNumber]
		int $userId,
		#[NotEmpty]
		#[Length(min: 3, max: 100)]
		string $name,
		#[Email(strict: true, domainCheck: true)]
		string $email,
		#[Phone]
		string $phone,
		#[PhoneOrEmail]
		string $contact,
		#[Url]
		string $profileUrl,
		#[Json]
		string $payload,
		#[InArray(['lead', 'client', 'partner'], strict: true)]
		string $type,
		#[Min(18)]
		#[Max(65)]
		int $age,
		#[Range(0, 100)]
		int $progress,
		#[RegExp('/^[A-Z0-9_-]+$/')]
		string $externalCode,
	): array
	{
		return [
			'userId' => $userId,
			'name' => $name,
		];
	}
}
```

## Example 2

Показывает: request DTO через `ValidationParameter`, collection / recursive attributes и class-level rules для межполеовых ограничений.
Почему это good pattern: связный input contract собирается один раз, а controller получает уже провалидированный объект.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Request;
use Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter;
use Bitrix\Main\Validation\Rule\AtLeastOnePropertyNotEmpty;
use Bitrix\Main\Validation\Rule\ElementsType;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\Rule\OnlyOneOfPropertyRequired;
use Bitrix\Main\Validation\Rule\Recursive\Validatable;

final readonly class InviteeDto
{
	public function __construct(
		#[NotEmpty]
		public string $name,
		#[Length(min: 3)]
		public string $role,
	)
	{
	}
}

#[AtLeastOnePropertyNotEmpty(['email', 'phone'], showPropertyNames: true)]
#[OnlyOneOfPropertyRequired(['groupId', 'departmentId'])]
final readonly class InviteRequest
{
	public function __construct(
		public ?string $email,
		public ?string $phone,
		public ?int $groupId,
		public ?int $departmentId,
		#[ElementsType(className: InviteeDto::class)]
		#[Validatable(iterable: true)]
		public array $invitees,
	)
	{
	}

	public static function createFromRequest(Request $request): self
	{
		$invitees = array_map(
			static fn(array $row): InviteeDto => new InviteeDto(
				name: (string)($row['name'] ?? ''),
				role: (string)($row['role'] ?? ''),
			),
			(array)$request->get('invitees')
		);

		return new self(
			email: $request->get('email') ?: null,
			phone: $request->get('phone') ?: null,
			groupId: $request->get('groupId') ? (int)$request->get('groupId') : null,
			departmentId: $request->get('departmentId') ? (int)$request->get('departmentId') : null,
			invitees: $invitees,
		);
	}
}

final class InviteController extends Controller
{
	public function getAutoWiredParameters(): array
	{
		return [
			new ValidationParameter(
				InviteRequest::class,
				fn(): InviteRequest => InviteRequest::createFromRequest($this->getRequest()),
			),
		];
	}

	public function sendAction(InviteRequest $request): array
	{
		return [
			'inviteesCount' => count($request->invitees),
		];
	}
}
```

## Example 3

Показывает: ручной вызов `ValidationService::validate()` с validation groups в service / command-like слое.
Почему это good pattern: один и тот же DTO можно валидировать по-разному в `create` и `update`, не привязывая его к controller AutoWire.

```php
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Validation\Group\ValidationGroup;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\ValidationResult;
use Bitrix\Main\Validation\ValidationService;

final readonly class UpsertArticle
{
	public function __construct(
		#[NotEmpty(groups: ['create'])]
		#[Length(min: 3, max: 255)]
		public string $title,
		#[Length(max: 5000)]
		public string $body,
	)
	{
	}
}

final class ArticleValidator
{
	public function validateForCreate(UpsertArticle $command): ValidationResult
	{
		/** @var ValidationService $service */
		$service = ServiceLocator::getInstance()->get('main.validation.service');

		return $service->validate($command, ValidationGroup::create('create'));
	}

	public function validateForUpdate(UpsertArticle $command): ValidationResult
	{
		/** @var ValidationService $service */
		$service = ServiceLocator::getInstance()->get('main.validation.service');

		return $service->validate($command, 'update');
	}
}
```

## Example 4

Показывает: custom validator и custom property attribute, когда built-in rules недостаточно.
Когда уместно: контракт не выражается built-in attributes без дополнительной доменной логики.
Почему это good pattern: custom validation logic остается внутри `Bitrix\Main\Validation`, а не расползается по controller и service.

```php
use Attribute;
use Bitrix\Main\Localization\LocalizableMessage;
use Bitrix\Main\Localization\LocalizableMessageInterface;
use Bitrix\Main\Validation\Rule\AbstractPropertyValidationAttribute;
use Bitrix\Main\Validation\Rule\ValidateByGroupInterface;
use Bitrix\Main\Validation\ValidationError;
use Bitrix\Main\Validation\ValidationResult;
use Bitrix\Main\Validation\Validator\ValidatorInterface;

final class SlugValidator implements ValidatorInterface
{
	public function validate(mixed $value): ValidationResult
	{
		$result = new ValidationResult();

		if (!is_string($value) || !preg_match('/^[a-z0-9-]+$/', $value))
		{
			$result->addError(new ValidationError(
				new LocalizableMessage('MAIN_VALIDATION_SLUG_INVALID')
			));
		}

		return $result;
	}
}

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Slug extends AbstractPropertyValidationAttribute implements ValidateByGroupInterface
{
	public function __construct(
		protected string|LocalizableMessageInterface|null $errorMessage = null,
		protected array $groups = [],
	)
	{
	}

	protected function getValidators(): array
	{
		return [
			new SlugValidator(),
		];
	}

	public function getGroups(): array
	{
		return $this->groups;
	}
}

final readonly class PublishRequest
{
	public function __construct(
		#[Slug]
		public string $slug,
	)
	{
	}
}
```

## Checklist

- Для простого scalar input использованы attributes на параметрах action вместо лишнего DTO?
- Для связного input contract выбран request DTO + `ValidationParameter`, если нужны маппинг, нормализация или несколько связанных правил?
- Built-in rules использованы как default path до написания custom validator?
- `#[Validatable]` применяется только к вложенным объектам или коллекциям объектов, которые несут собственные validation rules?
- Межполевая зависимость выражена через class-level rule, а не через ручную проверку в controller?
- `ValidationGroup` введен только там, где реально есть несколько самостоятельных режимов валидации?
- При custom validation логика оформлена через `ValidatorInterface` и attribute, а не вынесена в ad hoc проверки по месту использования?
- Ответственность validation не смешана с controller lifecycle, filters и route-level ограничениями?
