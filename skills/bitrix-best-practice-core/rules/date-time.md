# Date And Time

## Boundaries

- Этот файл покрывает выбор и использование `Bitrix\Main\Type\Date` и `Bitrix\Main\Type\DateTime` в прикладном коде продукта.
- Этот файл помогает принять решение по date-only vs datetime значениям, culture-aware parsing/formatting, timestamp-конверсии и user-time semantics.
- Этот файл не описывает выбор ORM date/datetime-полей и не задает предметные правила календарных расчетов; для этих тем переходи в rule-файлы модели хранения или доменного контракта.

## Rules

- В новом прикладном коде используй `Bitrix\Main\Type\Date` и `Bitrix\Main\Type\DateTime`, а не `\DateTime` или `\DateTimeImmutable`, даже если Bitrix-обертки используют PHP date classes под капотом.
- Выбирай `Bitrix\Main\Type\Date`, когда значение содержит только дату без значимого времени; этот тип нормализует время к `00:00:00` и подходит для date-only контрактов.
- Выбирай `Bitrix\Main\Type\DateTime`, когда в значении важны часы, минуты, секунды, timezone behavior или преобразование в user time.
- Для пользовательского ввода даты и времени предпочитай `DateTime::createFromUserTime()` или `DateTime::tryParse()`, а не ручной `new \DateTime(...)` с догадками о формате и часовом поясе.
- Для date-only строк, которые живут в bitrix/culture contract, используй `new Date($value)` или `new Date($value, $format)` вместо прямого PHP parsing.
- Для вывода пользователю предпочитай `toString()`; `format()` оставляй для явных машинных контрактов и стабильных технических строк.
- Для перевода из Unix timestamp или готового PHP `\DateTime` используй `createFromTimestamp()` и `createFromPhp()`, а не продолжай тащить исходный PHP type как product default.
- Если код работает с локальным пользовательским временем, не моделируй смещение вручную; используй `createFromUserTime()`, `toUserTime()` и связанные semantics Bitrix-класса.
- `disableUserTime()` используй только для узких сценариев, где нужен исходный серверный момент без пользовательской timezone-конверсии; не превращай это в новый default для user-facing вывода.
- Допустимое исключение для `\DateTime` или `\DateTimeImmutable` существует только на границе с внешним API, vendor-кодом или legacy-контрактом; сразу после входа в продуктовый код переводи значение в `Bitrix\Main\Type\Date` или `Bitrix\Main\Type\DateTime`.

## Decision Guide

- Если значение содержит только дату без значимого времени, используй `Bitrix\Main\Type\Date`.
- Если в значении важны часы, минуты, секунды, timezone behavior или user-time semantics, используй `Bitrix\Main\Type\DateTime`.
- Если вход приходит от пользователя в culture-aware формате Bitrix, используй `DateTime::createFromUserTime()` или `DateTime::tryParse()`.
- Если нужно отдать значение пользователю, используй `toString()`; если нужен стабильный технический формат для интеграции или протокола, используй `format()`.
- Если граница уже отдает PHP `\DateTime` или Unix timestamp, сразу конвертируй значение в Bitrix type через `createFromPhp()` или `createFromTimestamp()`.

## Example 1

Показывает: базовый продуктовый путь для пользовательского datetime-ввода через Bitrix API.
Почему это good pattern: код не угадывает timezone вручную и дальше работает с `Bitrix\Main\Type\DateTime` как с product-native типом.

```php
use Bitrix\Main\Type\DateTime;

final class MeetingFormMapper
{
	public function mapStartAt(string $userInput): DateTime
	{
		return DateTime::createFromUserTime($userInput);
	}

	public function presentStartAt(DateTime $startAt): string
	{
		return $startAt->toString();
	}
}
```

## Example 2

Показывает: date-only контракт, где значение должно жить как `Bitrix\Main\Type\Date`, а не как datetime с искусственным временем.
Почему это good pattern: тип сразу выражает смысл данных и не разносит по коду ложную значимость времени.

```php
use Bitrix\Main\Type\Date;

final class VacationRequest
{
	public function setStartDate(string $value): Date
	{
		return new Date($value);
	}
}
```

## Example 3

Показывает: user-facing formatting через `toString()` вместо ручного `format()` по произвольной маске.
Почему это good pattern: вывод остается culture-aware и использует встроенный формат Bitrix.

```php
use Bitrix\Main\Type\DateTime;

final class ReminderPresenter
{
	public function present(DateTime $remindAt): string
	{
		return $remindAt->toString();
	}
}
```

## Example 4

Показывает: допустимый compatibility path, когда внешний контракт уже отдает PHP `\DateTime`.
Почему это good pattern: `\DateTime` не распространяется дальше по продуктовым слоям как новый default, а сразу конвертируется в Bitrix-тип.

```php
use Bitrix\Main\Type\DateTime;

final class ExternalEventMapper
{
	public function mapOccurredAt(\DateTime $externalValue): DateTime
	{
		return DateTime::createFromPhp($externalValue);
	}
}
```

## Example 5

Показывает: узкий сценарий, где нужен серверный момент без user-time конверсии, и `disableUserTime()` используется осознанно.
Когда уместно: нужен стабильный технический timestamp или машинный лог, а не user-facing вывод.
Почему это допустимо: это локальное исключение, а не новый default для отображения даты/времени пользователю.

```php
use Bitrix\Main\Type\DateTime;

final class AuditFormatter
{
	public function formatServerMoment(DateTime $createdAt): string
	{
		$value = clone $createdAt;
		$value->disableUserTime();

		return $value->format('Y-m-d H:i:s');
	}
}
```

## Checklist

- Для нового прикладного кода выбран `Bitrix\Main\Type\Date` или `Bitrix\Main\Type\DateTime`, а не новый `\DateTime` / `\DateTimeImmutable`?
- `Date` используется только для date-only значений, а `DateTime` выбран там, где время и timezone действительно важны?
- Пользовательский ввод даты/времени парсится через Bitrix API (`createFromUserTime()`, `tryParse()`, конструкторы `Date`), а не через ad hoc PHP parsing?
- Пользовательский вывод строится через `toString()`, если нужен culture-aware формат, а `format()` оставлен только для явных технических контрактов?
- Внешний `\DateTime` на границе сразу конвертируется в Bitrix date type, а не протаскивается глубже в продуктовый код?
- Смещение пользовательского времени не моделируется вручную там, где Bitrix date type уже дает `createFromUserTime()` / `toUserTime()` semantics?
- `disableUserTime()` не превращен в новый default и используется только в узком техническом сценарии?
