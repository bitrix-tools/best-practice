# Loader

## Boundaries

- Этот файл покрывает загрузку модулей через `Bitrix\Main\Loader::includeModule()` и `Bitrix\Main\Loader::requireModule()`, а также legacy-совместимость через `CModule::IncludeModule()` и `CModule::IncludeModuleEx()`.
- Этот файл помогает выбрать между optional dependency и mandatory dependency, когда код зависит от наличия другого модуля.
- Этот файл не описывает бизнес-логику внутри `include.php` модуля и не заменяет архитектурные правила слоя, в котором используется загрузка.

## Rules

- В новом коде предпочитай `Bitrix\Main\Loader`, а `CModule` рассматривай как legacy-API для совместимости со старым кодом.
- Используй `Loader::requireModule()` там, где модуль является обязательной зависимостью сценария и без него код не может корректно продолжить выполнение.
- Используй `Loader::includeModule()` там, где зависимость действительно опциональна и у кода есть явная, корректная ветка поведения при `false`.
- Не используй `Loader::includeModule()` как скрытый аналог `requireModule()`: если отсутствие модуля означает ошибку конфигурации или поломку сценария, выбирай fail-fast через `requireModule()`.
- Не вызывай `Loader::includeModule()` без обработки результата. Ветка `false` должна либо отключать дополнительное поведение, либо возвращать понятный fallback, либо завершать сценарий осознанно.
- Если модуль нужен для создания сервиса, интеграционного клиента, ORM-сценария или другого обязательного объекта, загружай его один раз ближе к границе сценария, а не размазывай одинаковые проверки по глубине вызовов.
- Не переключай глобально поведение `Loader::requireModule()` через `Loader::setRequireThrowException(false)` в прикладном коде, чтобы имитировать `includeModule()`. Если нужен bool-результат, вызывай `includeModule()` напрямую.
- Не вводи новый код на `CModule::IncludeModule()`, если нет жёсткой причины сохранять legacy-style в уже существующем файле или API.

## Decision Guide

- Если без модуля сценарий не имеет смысла и должен упасть сразу с явной ошибкой, используй `Loader::requireModule()`.
- Если модуль только расширяет поведение, добавляет интеграцию или включает необязательную ветку, используй `Loader::includeModule()` и явно обрабатывай `false`.
- Если код находится в legacy-файле, где уже используется `CModule::IncludeModule()`, не смешивай в одном маленьком изменении два style-path без необходимости; но для нового кода рядом всё равно предпочитай `Loader`.
- Если нужен shareware/demo status, не подменяй этот сценарий обычным `includeModule()`: legacy-путь для него живёт в `CModule::IncludeModuleEx()` / `Loader::includeSharewareModule()`.

## Example 1

Показывает: mandatory dependency, без которой сценарий не должен продолжаться.
Почему это good pattern: зависимость объявлена fail-fast, а код ниже не размазывает повторные bool-проверки там, где fallback все равно отсутствует.

```php
use Bitrix\Main\Loader;
use My\Module\Verificator;

final class PortalRegistrySyncService
{
	protected function verificationPayload(array $payload): bool
	{
		Loader::requireModule('my.module');

		$verificator = new Verificator();

		return $verificator->verify($payload)->isSuccess();
	}
}
```

## Example 2

Показывает: optional integration, которая выполняется только если внешний модуль доступен.
Почему это good pattern: `includeModule()` используется не как молчаливый ignore ошибки, а как явная развилка для дополнительного поведения.

```php
use Bitrix\Main\Loader;

final class Transport
{
	protected function prepareResponse(array $result): array
	{
		if (Loader::includeModule('my.module') && class_exists(Broadcast::class))
		{
			Broadcast::processBroadcastData($result['broadcast']);
		}

		return $result;
	}
}
```

## Legacy / Exceptions

- `CModule::IncludeModule()` в ядре является обёрткой над `Loader::includeModule()`. Для нового кода это не preferred path, а compatibility path.
- `CModule::IncludeModuleEx()` используй только в legacy-сценариях, где действительно нужен shareware/demo status (`MODULE_INSTALLED`, `MODULE_DEMO`, `MODULE_DEMO_EXPIRED`, `MODULE_NOT_FOUND`), а не обычный факт загрузки модуля.
- Если небольшой патч в legacy-файле уже живёт на `CModule`, допустимо не переписывать весь файл только ради stylistic migration. Но новый соседний код не должен закреплять `CModule` как новый default.

## Checklist

- Для нового кода выбран `Loader`, а не `CModule`, если нет явной legacy-причины?
- `Loader::requireModule()` используется только там, где модуль действительно обязателен для сценария?
- `Loader::includeModule()` оставлен только там, где есть реальная и корректная ветка поведения при `false`?
- В коде нет `if (Loader::includeModule(...))`, который лишь маскирует обязательную зависимость без осмысленного fallback?
- Поведение `requireModule()` не перенастраивается глобально через `setRequireThrowException(false)` ради обхода exception?
- `CModule::IncludeModuleEx()` не используется как замена обычной загрузки модуля, если shareware/demo semantics не нужны?
