# Logging

## Boundaries

- Этот файл покрывает `Bitrix\Main\Diag\LoggerFactory`, `Bitrix\Main\Diag\Logger`, `LoggerRegistry`, `FileLogger`, formatter-ы из `Bitrix\Main\Diag\*` и использование `Psr\Log\LoggerInterface` для прикладного логирования.
- Этот файл помогает выбрать между `LoggerFactory::createById()`, DI через `LoggerInterface`, deprecated `Logger::create()` и legacy helper `AddMessage2Log()`.
- Этот файл не описывает audit/event logging contract через `CEventLog` как основной прикладной паттерн.

## Rules

- В новом коде предпочитай `Bitrix\Main\Diag\LoggerFactory` и `Psr\Log\LoggerInterface`, а не прямой `AddMessage2Log()`.
- Для классов с устойчивой logging-зависимостью пробрасывай `LoggerInterface` в конструктор или через container wiring, а не создавай logger заново внутри каждого бизнес-метода.
- Для runtime-resolved logging на boundary используй `LoggerFactory::createById('{module}.{area}.{name}')` с устойчивым logger id, а не ad hoc `new FileLogger(...)` в прикладном коде.
- Предпочитай именованные logger id и конфигурацию в `.settings.php`, чтобы уровень, formatter и sink настраивались без переписывания бизнес-кода.
- Используй `LoggerFactory::createById()` как default path для нового кода; `Logger::create()` считай только compatibility path, потому что он уже помечен как deprecated.
- Учитывай registry semantics: `createById()` по умолчанию проверяет `LoggerRegistry`, поэтому отключенный logger id может вернуть default logger, `NullLogger` или `null` в зависимости от параметров фабрики и fallback path.
- Если логирование для сценария опционально, проектируй код так, чтобы он корректно работал и с `NullLogger`, а лог не был скрытой обязательной зависимостью бизнес-логики.
- Используй PSR-3 levels и structured `context`, а не склеивай в одно длинное message-string сырые массивы, trace и отладочные дампы без структуры.
- Не логируй пароли, токены, session id, закрытые ключи, полный request body и другой чувствительный payload целиком; при необходимости логируй redacted summary или безопасные identifiers.
- `AddMessage2Log()` оставляй только как узкий legacy fallback для procedural/bootstrap/compatibility-патчей, где нормальная logger wiring не входит в задачу.
- Не вводи новый прикладной код на `echo`, `file_put_contents()`, `error_log()` или локальные самописные logger-helper'ы, если сценарий выражается через `LoggerFactory` и PSR-3.
- Не подменяй логами пользовательский contract ошибки, response или validation result; логирование дополняет flow, а не заменяет `addError()`, `Result` или HTTP response.

## Decision Guide

- Если сервис, command или handler логирует регулярно и живет как отдельная зависимость, пробрасывай `LoggerInterface` через DI.
- Если логгер нужен лениво и только на boundary, используй `LoggerFactory::createById()` с константным logger id в самом классе.
- Если нужно настроить sink, level или formatter, делай это через `.settings.php` `loggers`, а не через ручную сборку `FileLogger` в business code.
- Если задача сводится к маленькому legacy fix в старом procedural-файле и полноценная миграция logging-path не входит в scope, допустим узкий `AddMessage2Log()` как compatibility path.
- Если кажется, что нужен `Logger::create()`, сначала проверь, не может ли тот же сценарий быть выражен через `LoggerFactory`; deprecated helper не должен становиться новым default.

## Related Rules

- `rules/option.md` — для общего выбора между `Option` и другими storage/config API; здесь `LoggerRegistry` рассматривается только как logging-specific toggle.

## Example 1

Показывает: default path для service-level logging через `LoggerInterface`, который приходит из container wiring.
Почему это good pattern: service зависит от PSR-3 контракта, а выбор конкретного logger id и sink остается в конфигурации.

```php
use Psr\Log\LoggerInterface;

final class PortalSyncService
{
	public function __construct(
		private readonly LoggerInterface $logger,
	)
	{
	}

	public function sync(int $portalId): void
	{
		$this->logger->info('Portal sync started', [
			'portalId' => $portalId,
		]);

		// ...

		$this->logger->info('Portal sync finished', [
			'portalId' => $portalId,
		]);
	}
}
```

## Example 2

Показывает: logger wiring в `.settings.php` через `LoggerFactory::createById()` и отдельную секцию `loggers`.
Почему это good pattern: logger id, formatter и level настраиваются декларативно, а sink указывает на непубличный путь вне `document root`.

```php
use Bitrix\Main\Diag\FileLogger;
use Bitrix\Main\Diag\LoggerFactory;
use Bitrix\Main\Diag\LogFormatter;

return [
	'services' => [
		'value' => [
			PortalSyncService::class => [
				'className' => PortalSyncService::class,
				'constructorParams' => static function() {
					return [
						(new LoggerFactory())->createById('vendor.example.portal_sync'),
					];
				},
			],
			'vendor.example.log.formatter' => [
				'className' => LogFormatter::class,
				'constructorParams' => [false, 30],
			],
		],
		'readonly' => true,
	],
	'loggers' => [
		'value' => [
			'vendor.example.portal_sync' => [
				'className' => FileLogger::class,
				'constructorParams' => ['/var/log/php/portal_sync.log', 1048576],
				'level' => \Psr\Log\LogLevel::INFO,
				'formatter' => 'vendor.example.log.formatter',
			],
		],
		'readonly' => true,
	],
];
```

## Example 3

Показывает: lazy boundary-resolution logger через константный logger id.
Почему это good pattern: logger создается один раз в точке использования, а класс не откатывается к deprecated `Logger::create()` или `AddMessage2Log()`.

```php
use Bitrix\Main\Diag\LoggerFactory;
use Psr\Log\LoggerInterface;

final class QueueReceiver
{
	private const LOGGER_ID = 'vendor.example.queue_receiver';

	private ?LoggerInterface $logger = null;

	private function getLogger(): LoggerInterface
	{
		if ($this->logger === null)
		{
			$this->logger = (new LoggerFactory())->createById(self::LOGGER_ID);
		}

		return $this->logger;
	}

	public function handle(int $messageId): void
	{
		$this->getLogger()->warning('Message moved to retry queue', [
			'messageId' => $messageId,
			'queue' => 'retry',
		]);
	}
}
```

## Example 4

Показывает: structured context и redacted payload вместо сырого дампа чувствительных данных.
Почему это good pattern: лог остается полезным для диагностики, но не утаскивает в файл токены и полный request body.

```php
use Psr\Log\LoggerInterface;

final class TokenExchangeService
{
	public function __construct(
		private readonly LoggerInterface $logger,
	)
	{
	}

	public function exchange(string $portalId, string $token): void
	{
		$this->logger->info('Token exchange requested', [
			'portalId' => $portalId,
			'tokenLength' => mb_strlen($token),
			'hasToken' => $token !== '',
		]);
	}
}
```

## Example 5

Показывает: допустимое low-level исключение для инфраструктурного bootstrap-кода, где полноценное wiring логгера не входит в scope.
Когда уместно: legacy procedural-файл или маленький compatibility patch, который уже живет на `LOG_FILENAME` и не переносится сейчас на DI.
Почему это допустимо: `AddMessage2Log()` остается локальным fallback, а не становится новым default path для `lib/`-кода.

```php
<?php

// legacy bootstrap/update script
if ($migrationFailed)
{
	AddMessage2Log('Legacy migration step failed', 'vendor.example');
}
```

## Legacy / Exceptions

- `Logger::create()` уже deprecated и является compatibility wrapper над `LoggerFactory`, а не равноценным modern API.
- `AddMessage2Log()` внутри ядра пишет только в default logger path через `createDefault()` и `LOG_FILENAME`; это legacy fallback, а не module-specific configurable strategy.
- В маленьком патче внутри уже существующего legacy-файла допустимо не разворачивать полный DI-контур ради одной debug-записи, если задача не про миграцию logging architecture.
- Прямой `new FileLogger(...)` допустим в узком low-level infrastructure code или внутри самой logging-конфигурации, но не как новый default в прикладных service/command классах.

## Checklist

- Для нового кода выбран `LoggerFactory` или DI через `LoggerInterface`, а не прямой `AddMessage2Log()`?
- Logger не создается заново внутри каждого бизнес-метода, если у класса есть устойчивая logging-зависимость?
- Для прикладного сценария используется именованный logger id, а не ad hoc `FileLogger` в business code?
- `Logger::create()` не используется как новый default path, если тот же сценарий выражается через `LoggerFactory`?
- Код корректно работает и при отключенном logger id или `NullLogger`, а лог не является скрытой обязательной частью flow?
- В лог не попадают пароли, токены, session id и другой чувствительный payload целиком?
- Logging не подменяет `addError()`, `Result`, validation contract или HTTP response?
- Если в коде остался `AddMessage2Log()`, это действительно локальный legacy/compatibility boundary, а не новый паттерн для `lib/`-кода?
