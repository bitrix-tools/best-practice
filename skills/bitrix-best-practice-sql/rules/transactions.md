# Transactions

## Boundaries

- Этот файл покрывает `Bitrix\Main\DB\Connection::startTransaction()`, `commitTransaction()`, `rollbackTransaction()`, баланс уровней и вложенность в реализациях MySQL и PostgreSQL.
- Этот файл описывает ручной transaction API из `main/lib/db/`; он не вводит собственную callback-обёртку или `afterCommit` API.
- Для выполнения SQL и экранирования переходи в [query-execution.md](query-execution.md), для ORM-записи и её результатов — в [orm-persistence-write.md](orm-persistence-write.md).
- Этот файл не задаёт уровень изоляции, поведение DDL или гарантии конкретного движка таблиц. Поддержку отката самих операций проверяй отдельно для целевой БД.

## Rules

- Для нового кода управляй транзакцией через D7 `Connection`, полученное из `Application::getConnection()`, а не через ручные SQL `START TRANSACTION`, `COMMIT` или `ROLLBACK`: ручной SQL обходит счётчик уровней драйвера.
- Выполняй все операции одной транзакции через то же соединение; транзакция одного объекта `Connection` не управляет другим подключением.
- Назначай владельца каждого начатого уровня: он должен вызвать один `commitTransaction()` при успехе или один `rollbackTransaction()` при ошибке.
- Начинай уровень до `try`, который обрабатывает ошибки его операций: если `startTransaction()` завершился ошибкой, этот уровень ещё не открыт.
- Перехватывай `\Throwable`, если любая ошибка PHP внутри блока должна приводить к откату, а не только SQL-исключения.
- После отката передавай ошибку выше или явно возвращай неуспешный результат; не продолжай успешный сценарий по инерции.
- Проверяй неуспешный ORM `Result` явно, если он должен отменять операцию: transaction API не анализирует возвращаемые значения прикладного кода.
- В MySQL и PostgreSQL считай вложенный `startTransaction()` точкой сохранения внутри общей транзакции, а не независимой транзакцией.
- Не объявляй данные окончательно сохранёнными после внутреннего `commitTransaction()`: SQL `COMMIT` выполняет только завершение внешнего уровня.
- При внутреннем rollback MySQL учитывай, что после успешного `ROLLBACK TO SAVEPOINT` метод бросает `TransactionException('Nested rollbacks are unsupported.')`; не подавляй его ради продолжения внешней операции как успешной.
- При внутреннем rollback PostgreSQL учитывай, что метод откатывается к savepoint без дополнительного исключения; решение об отмене внешней операции остаётся у вызывающего кода.
- Не считай внутренний rollback полным откатом: внешний уровень остаётся открытым и требует собственного завершения.
- Не вызывай commit или rollback без соответствующего start: MySQL и PostgreSQL уменьшают счётчик ниже нуля и бросают `TransactionException('Transaction was not started.')` без автоматического восстановления счётчика.
- Отделяй ошибки операций от ошибок завершения уровня: в MySQL и PostgreSQL commit и rollback уменьшают счётчик до SQL. После ошибки самого commit/rollback не вызывай повторный rollback вслепую; успешный откат из такого состояния не гарантирован.
- Не переноси семантику вложенности MySQL/PostgreSQL на MSSQL и Oracle: в их методах транзакций нет такого счётчика и savepoint-механизма.

## Decision Guide

В `MysqlCommonConnection` (в том числе для `MysqliConnection`) и `PgsqlConnection` уровень хранится в `transactionLevel` объекта соединения. При успешном выполнении SQL переходы такие:

| Вызов | Уровень до → после | Действие |
| --- | --- | --- |
| Первый `startTransaction()` | 0 → 1 | `START TRANSACTION` |
| Вложенный `startTransaction()` | n → n + 1 | `SAVEPOINT TRANS<n>` |
| Внутренний `commitTransaction()` | n → n − 1, n > 1 | SQL не выполняется |
| Внешний `commitTransaction()` | 1 → 0 | `COMMIT` всей транзакции |
| Внутренний `rollbackTransaction()` | n → n − 1, n > 1 | `ROLLBACK TO SAVEPOINT TRANS<n − 1>`; в MySQL затем исключение |
| Внешний `rollbackTransaction()` | 1 → 0 | `ROLLBACK` всей транзакции |

Внутренний commit не выполняет `RELEASE SAVEPOINT`. Начало уровня увеличивает счётчик после SQL, а завершение уменьшает его до SQL.

## Example 1

Показывает: один владелец транзакции откатывает изменения при ошибке операций и передаёт ошибку выше.
Пример предполагает существующие транзакционные таблицы `b_example_item` и `b_example_audit` в одном соединении. Commit расположен после `catch`, чтобы ошибка самого commit не приводила к повторному уменьшению уровня через rollback.

```php
use Bitrix\Main\Application;

$connection = Application::getConnection();
$connection->startTransaction();

try
{
	$itemId = $connection->add('b_example_item', ['TITLE' => 'Draft']);
	$connection->add('b_example_audit', ['ITEM_ID' => $itemId]);
}
catch (\Throwable $exception)
{
	$connection->rollbackTransaction();
	throw $exception;
}

$connection->commitTransaction();
```

Если rollback сам бросит исключение, оно выйдет из этого блока; пример не обещает сохранить исходное исключение или успешно откатить изменения при отказе БД.

## Example 2

Показывает: вложенный вызов в MySQL/PostgreSQL завершает собственный уровень, а внешний владелец завершает свой.
Функция получает то же соединение. При ошибке записи внутренний обработчик откатывает один уровень, а внешний — оставшийся. Если внутренний start не удался, внутренний обработчик не вызывается и внешний откатывает только свой уровень. Пример предполагает успешное выполнение команд завершения транзакции; ошибки самих этих команд требуют отдельной диагностики.

```php
use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;

function addAudit(Connection $connection, int $itemId): void
{
	$connection->startTransaction(); // SAVEPOINT TRANS1 при внешнем уровне 1.
	try
	{
		$connection->add('b_example_audit', ['ITEM_ID' => $itemId]);
	}
	catch (\Throwable $exception)
	{
		$connection->rollbackTransaction();
		throw $exception;
	}
	$connection->commitTransaction(); // Уровень 2 → 1, SQL COMMIT ещё нет.
}

$connection = Application::getConnection();
$connection->startTransaction(); // START TRANSACTION, уровень 1.
try
{
	$itemId = $connection->add('b_example_item', ['TITLE' => 'Draft']);
	addAudit($connection, $itemId);
}
catch (\Throwable $exception)
{
	$connection->rollbackTransaction(); // Откат оставшегося внешнего уровня.
	throw $exception;
}
$connection->commitTransaction(); // Уровень 1 → 0, COMMIT обеих записей.
```

При ошибке записи в MySQL внутренний rollback заменит исходную ошибку на `TransactionException`, которая дойдёт до внешнего обработчика. В PostgreSQL после внутреннего rollback будет передано исходное исключение.

## Example 3

Показывает: MySQL откатывает внутренний уровень к savepoint и бросает исключение, после чего внешний владелец откатывает оставшийся уровень.
Пример использует заведомую прикладную ошибку, чтобы показать именно семантику вложенного rollback при исправной БД. Внутренний `startTransaction()` вынесен до `try`: внешний обработчик здесь знает, что оба уровня успешно открыты.

```php
use Bitrix\Main\Application;
use Bitrix\Main\DB\MysqlCommonConnection;

$connection = Application::getConnection();
if (!$connection instanceof MysqlCommonConnection)
{
	throw new \LogicException('This example requires MySQL.');
}

$connection->startTransaction(); // Уровень 1.
$connection->startTransaction(); // SAVEPOINT TRANS1, уровень 2.

try
{
	try
	{
		throw new \RuntimeException('Cancel the operation.');
	}
	catch (\Throwable $exception)
	{
		$connection->rollbackTransaction(); // 2 → 1, откат к TRANS1, TransactionException.
		throw $exception;
	}
}
catch (\Throwable $exception)
{
	$connection->rollbackTransaction(); // 1 → 0, полный ROLLBACK.
	throw $exception;
}
```

В MySQL наружу выйдет `TransactionException` от внутреннего rollback; строка `throw $exception` во внутреннем обработчике не будет достигнута. Это не означает, что откат к savepoint не произошёл. При SQL-ошибке самого rollback этот пример не гарантирует восстановление состояния соединения.

## Example 4

Показывает: PostgreSQL позволяет откатить внутренний уровень и явно завершить оставшийся внешний уровень.
Пример применим только к `PgsqlConnection`: переносить его как сценарий продолжения после вложенного rollback на MySQL нельзя.

```php
use Bitrix\Main\Application;
use Bitrix\Main\DB\PgsqlConnection;

$connection = Application::getConnection();
if (!$connection instanceof PgsqlConnection)
{
	throw new \LogicException('This example requires PostgreSQL.');
}

$connection->startTransaction();
$connection->startTransaction();
$connection->rollbackTransaction(); // 2 → 1, ROLLBACK TO SAVEPOINT TRANS1.
$connection->commitTransaction(); // 1 → 0, COMMIT внешнего уровня.
```

## Example 5

Показывает: неуспешный ORM `Result` явно превращается в ошибку операции, чтобы попасть в обработчик rollback.
Соединение выбирается по `getConnectionName()` ORM-модели, а не предполагается совпадающим с default connection. Детали ORM-записи остаются в соседнем правиле.

```php
use Bitrix\Main\Application;
use Bitrix\Main\UserTable;

function renameUser(int $userId, string $name): void
{
	$connection = Application::getConnection(UserTable::getConnectionName());
	$connection->startTransaction();
	try
	{
		$result = UserTable::update($userId, ['NAME' => $name]);
		if (!$result->isSuccess())
		{
			throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
		}
	}
	catch (\Throwable $exception)
	{
		$connection->rollbackTransaction();
		throw $exception;
	}
	$connection->commitTransaction();
}
```

## Related Rules

- [query-execution.md](query-execution.md) — выбор SQL API, соединения и безопасная сборка запросов.
- [orm-persistence-write.md](orm-persistence-write.md) — проверка результатов ORM-записи и persistence lifecycle.

## Checklist

- Все операции транзакции используют одно и то же соединение?
- У каждого успешно открытого уровня есть владелец его завершения при успехе и ошибке?
- Ошибка начала уровня не приводит к rollback неоткрытого уровня?
- Внутренний commit не считается окончательной фиксацией данных?
- При вложенном rollback учтены различия MySQL и PostgreSQL?
- После внутреннего rollback оставшийся внешний уровень явно завершается?
- Неуспешный ORM `Result`, требующий отмены операции, проверяется явно?
- После ошибки самого commit/rollback отсутствует слепой повторный rollback?
- Код не обходит счётчик уровней ручным transaction SQL и не завершает неоткрытый уровень?
- Семантика savepoint не переносится без проверки на другой драйвер?
