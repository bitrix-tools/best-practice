# Command

## Boundaries

- Этот файл покрывает `Bitrix\Main\Command\CommandInterface`, `AbstractCommand`, `CommandValidationException` и `CommandException` из `main/lib/Command/`.
- Команда оформляет одну прикладную операцию с явными входными данными и результатом. Используй её, когда операции нужен отдельный вызываемый контракт; не выделяй команду для каждого вспомогательного метода. Границы доменов и устройство application layer определяй по соглашениям модуля.
- Это не CLI-команды из `Bitrix\Main\Cli\Command`: `CommandInterface` задаёт только `run(): Result`, без регистрации, очереди или command bus.
- За выбором validation attributes переходи в [validation.md](validation.md), за payload результата — в [result.md](result.md), за контрактом ошибок — в [error.md](error.md). HTTP-адаптацию описывает [controller.md](controller.md).

## Rules

- Для новой команды со штатным lifecycle наследуйся от `AbstractCommand` и реализуй `protected function execute(): Result`.
- Передавай входные данные явно через конструктор; для неизменяемых данных предпочитай типизированные `readonly`-свойства.
- Объявляй validation attributes на свойствах команды, в том числе promoted properties конструктора: стандартный `validate()` проверяет сам объект через `main.validation.service`.
- Запускай команду через `run()`: этот метод `final` и уже выполняет валидацию перед `beforeRun()` и `execute()`. Повторная ручная валидация перед обычным вызовом не нужна.
- Если переопределяешь `validate()` ради дополнительной проверки, сохраняй штатные проверки через `parent::validate()` и возвращай `ValidationResult`.
- Возвращай ожидаемый прикладной отказ через неуспешный `Result`; вызывающий код должен проверить `isSuccess()` перед чтением success-payload.
- Различай `CommandValidationException` и возвращённый `Result`: ошибки входной валидации доступны через `getValidationErrors()`. В Engine Controller их обрабатывает штатный lifecycle; вне него обработай исключение на вызывающей границе.
- Учитывай, что только `\Exception` из `execute()` оборачивается в `CommandException`; исходная причина доступна через `getPrevious()`, объект команды — через публичное `command`. Исключения из validation/hooks и PHP `\Error` этой обёрткой не покрываются.
- Из `beforeRun(): ?Result` возвращай `null` для продолжения; любой `Result`, даже успешный и без данных, завершает `run()` без `execute()` и `afterRun()`.
- Используй `afterRun(): void` только как хук после нормального возврата `execute()`: он вызывается и для неуспешного `Result`, но не при исключении. Для гарантированной очистки используй `finally` вокруг соответствующей операции.
- Если нужна атомарность нескольких изменений БД, задай транзакцию явно внутри `execute()` команды; `AbstractCommand` не открывает её автоматически.
- Делай rollback и при неуспешном `Result`, и при исключении; при cleanup учитывай `\Throwable`. Согласуй владение транзакцией с существующей транзакционной границей; не открывай вложенную транзакцию без проверки поведения соединения.

## Lifecycle

| Этап | Продолжение и завершение |
| --- | --- |
| `validate()` | Неуспех → `CommandValidationException`, последующие этапы не вызываются. |
| `beforeRun()` | `null` → `execute()`; любой `Result` → немедленный возврат этого результата. |
| `execute()` | Возвращённый `Result` → `afterRun()`; `\Exception` → `CommandException`. |
| `afterRun()` | После завершения хука `run()` возвращает результат `execute()`. Исключение хука прерывает возврат. |

## Example 1

Показывает: команду с валидируемыми входными данными и явно переданной зависимостью. Команда выполняет операцию через существующий прикладной контракт и сохраняет его `Result`; реализация хранилища остаётся за пределами примера.

```php
namespace Bitrix\Example\Command;

use Bitrix\Main\Command\AbstractCommand;
use Bitrix\Main\Result;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\PositiveNumber;

final class RenameItemCommand extends AbstractCommand
{
	public function __construct(
		#[PositiveNumber]
		public readonly int $id,
		#[Length(min: 1, max: 255)]
		public readonly string $title,
		private readonly ItemWriter $writer,
	)
	{
	}

	protected function execute(): Result
	{
		return $this->writer->rename($this->id, $this->title);
	}
}
```

## Example 2

Показывает: action контроллера сам создаёт `RenameItemCommand` из Example 1 и вызывает `run()`. `ItemWriter` приходит аргументом action из контейнера; реализация абстрактного класса должна быть зарегистрирована по `ItemWriter::class`, смотри [service-locator.md](service-locator.md). Прикладные ошибки берутся из возвращённого `Result`. `CommandValidationException` наследует `ValidationException`, поэтому ошибки входной валидации обрабатывает сам Engine Controller; отдельный `try/catch` в action не нужен.

```php
namespace Bitrix\Example\Controller;

use Bitrix\Example\Command\ItemWriter;
use Bitrix\Example\Command\RenameItemCommand;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\Authentication;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\HttpMethod;
use Bitrix\Main\Engine\Controller;

final class ItemController extends Controller
{
	#[Authentication]
	#[HttpMethod(['POST'])]
	public function renameAction(ItemWriter $writer, int $id, string $title): ?array
	{
		$command = new RenameItemCommand(id: $id, title: $title, writer: $writer);
		$result = $command->run();

		if (!$result->isSuccess())
		{
			$this->addErrors($result->getErrors());

			return null;
		}

		return $result->getData();
	}
}
```

## Example 3

Показывает: precondition в `beforeRun()`, которая возвращает отказ и не допускает запуск `execute()`. Возврат `new Result()` вместо `null` также остановил бы команду, но сообщил бы об успешном исходе.

```php
use Bitrix\Main\Command\AbstractCommand;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;

final class GuardedOperationCommand extends AbstractCommand
{
	public function __construct(
		private readonly bool $allowed,
		private readonly \Closure $operation,
	)
	{
	}

	protected function beforeRun(): ?Result
	{
		if (!$this->allowed)
		{
			return (new Result())->addError(new Error(
				Loc::getMessage('EXAMPLE_COMMAND_OPERATION_UNAVAILABLE'),
				'OPERATION_UNAVAILABLE',
			));
		}

		return null;
	}

	protected function execute(): Result
	{
		// Переданная операция должна возвращать Bitrix\Main\Result.
		return ($this->operation)();
	}
}
```

## Example 4

Показывает: команду, которая оркестрирует публикацию документа и запись истории через два сервиса. Обе операции изменяют БД и должны завершиться атомарно, поэтому общей транзакцией владеет команда. Сервисы используют то же соединение и не открывают собственных транзакций; реализации сервисов остаются за пределами примера.

```php
namespace Bitrix\Example\Command;

use Bitrix\Main\Application;
use Bitrix\Main\Command\AbstractCommand;
use Bitrix\Main\Result;
use Bitrix\Main\Validation\Rule\PositiveNumber;

final class PublishDocumentCommand extends AbstractCommand
{
	public function __construct(
		#[PositiveNumber]
		public readonly int $documentId,
		private readonly DocumentService $documents,
		private readonly PublicationHistoryService $history,
	)
	{
	}

	protected function execute(): Result
	{
		$connection = Application::getConnection();
		$connection->startTransaction();

		try
		{
			$publishResult = $this->documents->publish($this->documentId);
			if (!$publishResult->isSuccess())
			{
				$connection->rollbackTransaction();

				return $publishResult;
			}

			$historyResult = $this->history->recordPublication($this->documentId);
			if (!$historyResult->isSuccess())
			{
				// Откатываем и публикацию, выполненную первым сервисом.
				$connection->rollbackTransaction();

				return $historyResult;
			}

			$connection->commitTransaction();

			return (new Result())->setData(['documentId' => $this->documentId]);
		}
		catch (\Throwable $exception)
		{
			$connection->rollbackTransaction();

			throw $exception;
		}
	}
}
```

Валидация и `beforeRun()` выполняются до открытия транзакции, `afterRun()` — после её завершения. Изменения БД, которые должны откатываться вместе с операцией, держи внутри `execute()`. Письма и другие внешние эффекты rollback не отменяет.

## Checklist

- Команда реализует `execute(): Result`, а вызывающий код использует `run()`?
- Входные данные и validation attributes объявлены на свойствах команды?
- При переопределении `validate()` сохранены штатные проверки?
- Вызывающий код различает неуспешный `Result` и `CommandValidationException`?
- Success-payload читается после `isSuccess()`?
- `beforeRun()` возвращает `null` именно на пути продолжения?
- `afterRun()` не используется как гарантированная очистка или сигнал успешного commit?
- Для атомарной операции транзакция находится внутри `execute()` и откатывается при отказе и исключении?
