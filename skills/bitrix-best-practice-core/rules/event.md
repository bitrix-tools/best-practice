# Event

## Boundaries

- Этот файл покрывает `Bitrix\Main\Event`, `EventResult` и `EventManager`: создание и отправку собственных событий, подписку на существующие, регистрацию и снятие обработчиков.
- Контракт конкретного события определяет отправитель: параметры, допустимые ответы, изменение данных, отмена операции и момент отправки относительно записи или транзакции.
- ORM-события используют отдельные `Bitrix\Main\ORM\Event` и `ORM\EventResult`; изменение полей и ошибки ORM сюда не входят. За lifecycle записи и `ignoreEvents` переходи в [orm-persistence-write.md](../../bitrix-best-practice-sql/rules/orm-persistence-write.md).
- `Main\EventResult` — ответ обработчика события, а не обычный service outcome `Main\Result`; за последним переходи в [result.md](result.md).

## Rules

- Классы новых событий генерируй через `php bitrix.php make:event <name> --module=<module-id>`.
- Классы обработчиков генерируй через `php bitrix.php make:eventhandler <name> --handler-module=<handler-module-id> --event-module=<event-module-id>`.
- Сохраняй структуру генератора: `final <Name>Event extends Event` в `Public\Event` и `final <Name>EventHandler` со статическим `handle(<Name>Event $event): EventResult` в `Internals\Integration\<EventModuleNamespace>\EventHandler`.
- Замени `param1` предметными типизированными параметрами.
- Импортируй класс события в обработчике через `use`.
- Фиксируй module id и имя в конструкторе класса события; отправляй новый экземпляр через `send()`. `readonly`-свойства генератора не попадают в `getParameter()` автоматически: читай их напрямую, а массив параметров передавай в `parent::__construct()` только если этого требует контракт.
- Перед подпиской на существующее событие прочитай место отправки и обработки результатов; имя вроде `OnBefore...` само по себе не задаёт право отмены или изменения данных.
- Для D7-контракта регистрируй обработчик через `addEventHandler()` или `registerEventHandler()`; типизируй аргумент конкретным классом события только если отправитель создаёт этот класс, иначе используй `Main\Event`. Совместимые позиционные сигнатуры оставляй для legacy-контракта.
- Для подписки в текущем процессе используй `addEventHandler()` в bootstrap или ограниченном сценарии; сохраняй возвращённый ключ, если подписку нужно снять через `removeEventHandler()`.
- Для подписки установленного модуля между запросами используй `registerEventHandler()` в установке или миграции и парный `unRegisterEventHandler()` при удалении; указывай module id назначения и доступный после загрузки модуля callable.
- Если порядок обработчиков существенен, задавай разные `sort`: меньшее значение вызывается раньше; не опирайся на порядок обработчиков с одинаковым `sort`.
- Создавай отдельный объект `Event` для каждой отправки: повторный `send()` того же объекта не очищает предыдущие результаты и исключения.
- Читай ответы через `$event->getResults()` после `send()`; сам `send()` не возвращает результат и выполняет обработчики синхронно.
- Возвращай `Main\EventResult` с согласованными типом и payload, если обработчик должен дать ответ; для уведомления без ответа допустим `void`.
- Проверяй и объединяй ответы в отправителе по явной политике: `EventResult::ERROR` не останавливает остальные обработчики и сам не отменяет операцию.
- Меняй параметры через `setParameter()` только при разрешённом контракте события: тот же объект виден следующим D7-обработчикам; иначе возвращай согласованный payload в `EventResult`.
- Учитывай исключения как отдельный путь отказа: в обычном режиме исключение обработчика прерывает `send()`; ожидаемый отказ по контракту выражай через `EventResult::ERROR`.

## Legacy / Exceptions

- Для позиционного callback используй `addEventHandlerCompatible()` или `registerEventHandlerCompatible()`: он получает значения параметров по порядку, без ключей. При `toMethodArg` дополнительные аргументы идут перед параметрами события.
- При переносе глобального `AddEventHandler()` сохраняй compatible-сигнатуру и проверяй аргументы: у глобальной функции четвёртый аргумент — `sort`, у метода менеджера — `includeFile`, а `sort` — пятый. `RegisterModuleDependences()` также регистрирует compatible-обработчик.
- Не переноси legacy `return false` как универсальный запрет в D7: менеджер не добавляет `false`, `0` и пустую строку в результаты, а другие ответы, не являющиеся `EventResult`, оборачивает в `UNDEFINED`. Сохраняй veto только в том dispatcher-контракте, где отправитель действительно его проверяет.
- Для изменения постоянной подписки снимай старое описание и регистрируй новое: повторный `registerEventHandler()` с другим `sort` не обновляет запись. При снятии сохраняй исходные class, method, path и `toMethodArg`; версия compatible/D7 не является фильтром удаления.
- Не включай debug-режим события для подавления ошибок приложения: он собирает `\Exception` и продолжает вызовы, но не перехватывает весь `\Throwable`.
- Если нужен module filter при создании `Event`, проверь состав подписок: фильтр работает по module id назначения и исключает runtime-обработчики без `TO_MODULE_ID`.

## Example 1

Сгенерируй событие и обработчик из каталога с `bitrix.php`:

```sh
php bitrix.php make:event OnOrderPublished --module=example.orders -n
php bitrix.php make:eventhandler OnOrderPublished --handler-module=example.audit --event-module=example.orders -n
```

Задай параметр `orderId` и импорт события. Размести каждый класс в отдельном сгенерированном файле.

```php
namespace Example\Orders\Public\Event;

use Bitrix\Main\Event;

final class OnOrderPublishedEvent extends Event
{
	public function __construct(
		public readonly int $orderId,
	)
	{
		parent::__construct('example.orders', 'OnOrderPublished');
	}
}
```

```php
namespace Example\Audit\Internals\Integration\Example\Orders\EventHandler;

use Bitrix\Main\EventResult;
use Example\Orders\Public\Event\OnOrderPublishedEvent;

final class OnOrderPublishedEventHandler
{
	public static function handle(OnOrderPublishedEvent $event): EventResult
	{
		$orderId = $event->orderId;
		// Точка вызова прикладного аудита завершённой публикации.

		return new EventResult(EventResult::SUCCESS);
	}
}
```

Отправитель после успешного завершения публикации создаёт новый объект; модуль события и модуль обработчика должны быть загружены для runtime-подписки:

```php
use Bitrix\Main\EventManager;
use Example\Audit\Internals\Integration\Example\Orders\EventHandler\OnOrderPublishedEventHandler;
use Example\Orders\Public\Event\OnOrderPublishedEvent;

$manager = EventManager::getInstance();
$handlerKey = $manager->addEventHandler(
	'example.orders',
	'OnOrderPublished',
	[OnOrderPublishedEventHandler::class, 'handle'],
);

try
{
	$event = new OnOrderPublishedEvent(orderId: 42);
	$event->send();
}
finally
{
	$manager->removeEventHandler('example.orders', 'OnOrderPublished', $handlerKey);
}
```

## Example 2

Событие проверки: отправитель запрещает продолжение при любом `ERROR`. Сгенерируй классы с именем `OnCheckOrderPublication`, модулем события `example.orders` и модулем обработчика `example.audit`. По контракту примера отсутствие подписчиков разрешает продолжение.

```php
namespace Example\Orders\Public\Event;

use Bitrix\Main\Event;

final class OnCheckOrderPublicationEvent extends Event
{
	public function __construct(
		public readonly int $orderId,
	)
	{
		parent::__construct('example.orders', 'OnCheckOrderPublication');
	}
}
```

```php
namespace Example\Audit\Internals\Integration\Example\Orders\EventHandler;

use Bitrix\Main\EventResult;
use Example\Orders\Public\Event\OnCheckOrderPublicationEvent;

final class OnCheckOrderPublicationEventHandler
{
	public static function handle(OnCheckOrderPublicationEvent $event): EventResult
	{
		if ($event->orderId <= 0)
		{
			return new EventResult(EventResult::ERROR, ['code' => 'INVALID_ORDER_ID']);
		}

		return new EventResult(EventResult::SUCCESS);
	}
}
```

Следующий фрагмент выполняется после регистрации обработчика `handle` через runtime или постоянную подписку:

```php
use Bitrix\Main\EventResult;
use Example\Orders\Public\Event\OnCheckOrderPublicationEvent;

$event = new OnCheckOrderPublicationEvent(orderId: 42);
$event->send();
$allowed = true;

foreach ($event->getResults() as $result)
{
	if ($result->getType() === EventResult::ERROR)
	{
		$allowed = false;
		break;
	}
}

// Отправитель продолжает публикацию только при $allowed === true.
```

## Example 3

В установке модуля `example.audit` зарегистрируй обработчик из Example 1, при удалении сними ту же подписку. Класс обработчика должен быть доступен через автозагрузку модуля назначения.

```php
namespace Example\Audit;

use Bitrix\Main\EventManager;
use Example\Audit\Internals\Integration\Example\Orders\EventHandler\OnOrderPublishedEventHandler;

final class EventSubscriptions
{
	public static function install(): void
	{
		EventManager::getInstance()->registerEventHandler(
			'example.orders',
			'OnOrderPublished',
			'example.audit',
			OnOrderPublishedEventHandler::class,
			'handle',
			100,
		);
	}

	public static function uninstall(): void
	{
		EventManager::getInstance()->unRegisterEventHandler(
			'example.orders',
			'OnOrderPublished',
			'example.audit',
			OnOrderPublishedEventHandler::class,
			'handle',
		);
	}
}
```

Для смены `sort` в миграции сначала вызывается `uninstall()`, затем регистрация с новым `sort`; один повторный `install()` существующий порядок не изменит.

## Example 4

Для существующего позиционного контракта используй compatible-callback. Передавай `sort` пятым аргументом метода. Пример воспроизводит позиционный вызов через `Main\Event`; при подключении к legacy-событию проверь его dispatcher.

```php
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;

$manager = EventManager::getInstance();
$received = [];
$handlerKey = $manager->addEventHandlerCompatible(
	'example.orders',
	'OnLegacyOrderPublished',
	static function (int $orderId, string $status) use (&$received): void {
		$received = ['orderId' => $orderId, 'status' => $status];
	},
	false,
	200,
);

try
{
	$event = new Event('example.orders', 'OnLegacyOrderPublished', [
		'orderId' => 42,
		'status' => 'published',
	]);
	$event->send();
}
finally
{
	$manager->removeEventHandler('example.orders', 'OnLegacyOrderPublished', $handlerKey);
}
```

## Example 5

Если контракт существующего события разрешает изменение параметров, задавай порядок нормализатора и читателя разными `sort`. Для каждой отправки создавай новый объект. `setParameter()` изменяет массив параметров, но не `readonly`-свойства класса события.

```php
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;

$manager = EventManager::getInstance();
$normalizeKey = $manager->addEventHandler(
	'example.orders',
	'OnPrepareOrderTitle',
	static function (Event $event): void {
		$event->setParameter('title', trim((string)$event->getParameter('title')));
	},
	false,
	100,
);
$titles = [];
$readKey = $manager->addEventHandler(
	'example.orders',
	'OnPrepareOrderTitle',
	static function (Event $event) use (&$titles): void {
		$titles[] = $event->getParameter('title');
	},
	false,
	200,
);

try
{
	foreach ([' First ', ' Second '] as $title)
	{
		$event = new Event('example.orders', 'OnPrepareOrderTitle', ['title' => $title]);
		$event->send();
		$preparedTitle = $event->getParameter('title');
	}
}
finally
{
	$manager->removeEventHandler('example.orders', 'OnPrepareOrderTitle', $readKey);
	$manager->removeEventHandler('example.orders', 'OnPrepareOrderTitle', $normalizeKey);
}
```

## Checklist

- Новые классы созданы через `make:event` / `make:eventhandler`, предметные параметры заданы, а импорт события в обработчике проверен?
- Для сгенерированного события обработчик читает типизированные свойства, а `getParameter()` используется только для явно заполненного массива параметров?
- Для существующего события проверены место отправки, параметры и политика обработки ответов?
- Выбор D7 или compatible соответствует сигнатуре callback и dispatcher-контракту?
- Временная подписка снимается по возвращённому ключу, если её область жизни ограничена?
- Постоянная подписка создаётся в lifecycle модуля и имеет симметричное снятие с тем же назначением?
- Для значимого порядка заданы разные `sort`, а изменение постоянного `sort` выполняется через снятие и регистрацию?
- Каждая отдельная отправка использует новый `Event`, а ответы читаются через `getResults()`?
- Если требуется отмена, отправитель сам проверяет результаты и блокирует операцию?
- Изменение параметров и исключения обработчиков соответствуют объявленному контракту события?
- Используются `Main\EventResult` для общих событий и отдельные ORM-типы для ORM-событий?
