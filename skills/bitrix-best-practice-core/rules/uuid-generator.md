# Uuid Generator

## Boundaries

- Этот файл покрывает `Bitrix\Main\UuidGenerator` и выбор `UuidGenerator::generateV4()` как product default для случайных opaque identifier в прикладном Bitrix-коде.
- Этот файл помогает развести UUID v4 с `uniqid()`, прямым `Random::getBytes()`, ручной сборкой UUID и детерминированными ID, которые должны повторяться для одного и того же входа.
- Этот файл не описывает TTL, storage key design и выбор между runtime-state и конфигурацией; если UUID используется как ключ временного server-side state, переходи в `rules/persistent-storage.md`.
- Этот файл не описывает защиту client round-trip параметров и не заменяет boundary validation; если UUID уходит клиенту и потом возвращается назад как access-sensitive параметр, применяй repo-specific security rules и отдельно валидируй вход.

## Rules

- Для нового случайного идентификатора по умолчанию используй `UuidGenerator::generateV4()`, а не локальную helper-обертку, vendor UUID library или ручную сборку v4 из байтов.
- Используй `UuidGenerator::generateV4()`, когда нужен random opaque identifier: session id, correlation id, upload token, public proxy id, temporary entity GUID или другой ID, который не должен быть предсказуемым.
- Не используй `UuidGenerator::generateV4()`, если значение должно быть детерминированным и повторяться для одного и того же входа; в таком сценарии строй стабильный identifier из доменных данных.
- Не собирай UUID v4 вручную через `Random::getBytes()`, `random_bytes()`, `mt_rand()` или `uniqid()`; в прикладном коде это низкоуровневые детали, которые должен скрывать `UuidGenerator`.
- Считай выходной формат `UuidGenerator::generateV4()` частью контракта: lowercase, 36 символов, дефисы, без фигурных скобок.
- Если legacy contract требует формат `{uuid}` или другой wrapper, генерируй значение через `UuidGenerator::generateV4()` и преобразуй его только на boundary, не создавая отдельный альтернативный генератор.
- Если UUID хранится в БД как строка, держи явный string contract вокруг 36-символьного значения и, где это важно, подкрепляй его unique constraint или доменной гарантией.
- Если UUID пришел извне и участвует в lookup, доступе к данным или продолжении сценария, не доверяй ему как уже валидному только потому, что он похож на UUID; генерация и валидация - разные шаги.
- `uniqid()` допустим только как узкое исключение для локального page-level или request-level identifier, где не нужна криптографическая непредсказуемость, внешний контракт и межсистемная уникальность.

## Decision Guide

- Если нужен случайный opaque identifier, который будет жить дольше одного локального вызова или выйдет за пределы текущего стека, используй `UuidGenerator::generateV4()`.
- Если один и тот же вход должен давать один и тот же идентификатор, не используй `UuidGenerator::generateV4()`; выбери детерминированный доменный identifier.
- Если legacy или внешний contract требует строку вида `{uuid}`, сгенерируй обычный UUID через `UuidGenerator::generateV4()` и добавь wrapper только на boundary.
- Если нужен только локальный идентификатор для DOM, HTML container или другого короткоживущего page/request-level сценария, допустим более простой механизм вроде `uniqid()`.
- Если спор идет не о генерации UUID, а о том, где хранить ключ, сколько он живет и что означает его отсутствие, переходи в `rules/persistent-storage.md`.

## Example 1

Показывает: базовый рекомендуемый путь для random opaque identifier, который будет использоваться как correlation id между слоями.
Почему это good pattern: код берет готовый framework-native UUID v4 и не размножает по проекту локальные генераторы с разным форматом.

```php
use Bitrix\Main\UuidGenerator;

final class TraceContextFactory
{
	public function createContextId(): string
	{
		return UuidGenerator::generateV4();
	}
}
```

## Example 2

Показывает: UUID как string contract для ORM-поля с явным размером и unique-ограничением.
Почему это good pattern: генерация, формат строки и ограничение хранения согласованы между кодом и моделью.

```php
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\UuidGenerator;

final class TempFileTableMap
{
	public static function buildGuidField(): StringField
	{
		return (new StringField('GUID'))
			->configureUnique(true)
			->configureNullable(false)
			->configureSize(36)
			->configureDefaultValue(
				static fn(): string => UuidGenerator::generateV4(),
			);
	}
}
```

## Example 3

Показывает: узкий compatibility path, где внешний boundary требует формат `{uuid}`.
Почему это good pattern: legacy wrapper живет только на границе интеграции, а canonical generator внутри остается один.

```php
use Bitrix\Main\UuidGenerator;

final class LegacyPayloadBuilder
{
	public function buildExternalId(): string
	{
		return '{' . UuidGenerator::generateV4() . '}';
	}
}
```

## Example 4

Показывает: сценарий, где случайный UUID не нужен, потому что идентификатор должен быть стабильным для одинакового входа.
Почему это good pattern: код явно выражает детерминированную природу identifier и не маскирует ее под random UUID.

```php
final class NetworkUserIdentity
{
	public function buildStableId(int $portalId, int $userId): string
	{
		return hash('sha256', $portalId . ':' . $userId);
	}
}
```

## Example 5

Показывает: допустимое исключение для локального page-level identifier, который не выходит в storage, API или внешнюю интеграцию.
Когда уместно: нужен одноразовый DOM/container id внутри одного рендера, а криптографическая непредсказуемость и глобальный contract не требуются.
Почему это допустимо: `uniqid()` остается локальным utility для короткоживущего UI-сценария и не подменяет собой UUID как общий product default.

```php
final class UploadWidgetRenderer
{
	public function makeContainerId(): string
	{
		return 'upload_widget_' . uniqid('', false);
	}
}
```

## Checklist

- Для нового случайного identifier выбран `UuidGenerator::generateV4()`, а не новый локальный helper или ручная сборка UUID?
- Сценарий, где нужен стабильный identifier для одинакового входа, не решается случайным UUID по инерции?
- Legacy wrapper вроде `{uuid}` добавляется только на boundary, а не превращен в отдельный альтернативный генератор?
- Если UUID хранится в строковом поле или публичном contract, формат `36 chars + lowercase + dashes` не потерян по пути?
- Если UUID пришел извне и влияет на lookup или доступ, в коде есть отдельная boundary validation, а не слепое доверие к строке?
- `uniqid()` не стал новым default и остался только в локальном page/request-level exception-path?
