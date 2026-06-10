# Query Execution

## Boundaries

- Этот файл покрывает raw SQL и выполнение запросов через `Bitrix\Main\Application::getConnection()`, `Bitrix\Main\DB\Connection`, `Bitrix\Main\DB\Result`, `SqlHelper` и `SqlExpression`.
- Этот файл помогает выбрать между `query()`, `queryScalar()`, `queryExecute()`, `add()`, `addMulti()` и helper-методами для безопасной сборки SQL.
- Этот файл фиксирует границу между современным D7 DB API и legacy-механизмом `global $DB`, но не превращает legacy API в рекомендуемый path для нового кода.
- Этот файл не описывает ORM `DataManager`, `Query`, схему таблиц, DDL, миграции и транзакционную модель целиком; если спор уже не про raw SQL, а про ORM или schema-management, нужен отдельный rule.

## Rules

- Для нового кода получай подключение через `Bitrix\Main\Application::getConnection()`, а не через `global $DB`.
- Raw SQL используй только когда он действительно нужен; если сценарий уже естественно выражается через ORM, не понижай его до `Connection` без причины.
- Для запроса, который возвращает строки, используй `Connection::query()`.
- Для запроса, который должен вернуть одно скалярное значение, используй `Connection::queryScalar()`, а не ручной `fetch()` из `query()` по инерции.
- Для `INSERT`, `UPDATE` и `DELETE`, где результат набора строк не нужен, используй `Connection::queryExecute()`.
- Для простого `INSERT` одной или нескольких строк предпочитай `Connection::add()` и `Connection::addMulti()` вместо ручной сборки `INSERT`, если этого достаточно.
- Если `INSERT` или `UPDATE` все же собирается вручную, используй `SqlHelper::prepareInsert()`, `prepareUpdate()` или `prepareAssignment()`, а не склеивай `SET` и `VALUES` строками.
- Для значений используй `SqlHelper::forSql()`, `convertToDb()` или `convertToDb*()`; не пропускай пользовательские и внешние данные в SQL напрямую.
- Для имен таблиц, колонок и других SQL-идентификаторов используй `SqlHelper::quote()`, а не `forSql()`.
- Не используй `quote()` для значений и `forSql()` для идентификаторов: эти методы решают разные задачи и не заменяют друг друга.
- Если запрос собирается из нескольких частей, используй `Bitrix\Main\DB\SqlExpression` вместо конкатенации SQL со значениями.
- `SqlExpression` обязателен, когда в одном шаблоне смешиваются значения, идентификаторы, списки `IN (...)` или выражения вроде `CNT = CNT + 1`.
- Для `IN (...)` используй placeholder `?@` в `SqlExpression`, а не `implode()` по массиву значений.
- Не передавай в `?@` пустой массив: empty-list случай должен быть обработан на уровне PHP до сборки SQL.
- Для смешанных шаблонов выбирай точные placeholders `?s`, `?i`, `?f`, `?#`, `?@`, а не универсальную строковую конкатенацию.
- `SqlHelper::getTopSql()` и сигнатуру `Connection::query($sql, ..., $offset, $limit)` используй для pagination-ограничений; не добавляй второй `LIMIT` вручную поверх уже ограниченного запроса.
- `query($sql, $binds)` не считай portable prepared-statement API по умолчанию: на MySQL безопасность должна по-прежнему выражаться через `SqlHelper` и `SqlExpression`, а не через ожидание, что binds автоматически защитят строковый SQL.
- Методы `SqlHelper`, которые возвращают SQL-фрагменты и помечены как SQL unsafe, используй только с доверенными выражениями или уже подготовленными идентификаторами; не передавай туда пользовательский ввод напрямую.
- `executeSqlBatch()` не используй как обычный runtime-path для прикладного кода; это узкий инструмент для batch SQL и schema-oriented сценариев.

## Decision Guide

- Если нужен framework-native доступ к БД в новом коде, начинай с `Application::getConnection()`.
- Если нужна одна строка или набор строк, выбирай `query()`.
- Если нужно одно значение из первой колонки первой строки, выбирай `queryScalar()`.
- Если нужен side-effect запрос без result set, выбирай `queryExecute()`.
- Если задача сводится к вставке данных по известным колонкам, сначала проверь `add()` / `addMulti()` и `prepareInsert()`.
- Если запрос содержит динамические значения, `IN`, mixed identifiers или SQL-expression в `SET`, переходи на `SqlExpression`.
- Если спор уже не о query execution, а о богатой выборке, entity-mapping или lifecycle ORM, не продолжай углублять raw SQL rule и переходи в будущий ORM-specific rule.

## Legacy / Exceptions

- `global $DB` считай только legacy compatibility path, а не равноценным modern API рядом с `Application::getConnection()`.
- В небольшом патче внутри старого updater-, admin- или другого legacy-файла допустимо оставить окружающий `$DB`-style, если полная миграция файла не входит в задачу.
- Не переноси `global $DB` в новый `lib/`-код, новые сервисы и новые примеры как default path.
- Если legacy-сценарий уже привязан к `$DB` и `CDBResult`, допустимо локально оставить этот compatibility-layer, но не превращай его в образец для нового query-path.

## Example 1

Показывает: базовый рекомендуемый path для raw `SELECT` через `Connection` и `SqlExpression`.
Почему это good pattern: подключение и экранирование остаются framework-native, а запрос не собирается конкатенацией со значениями.

```php
use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlExpression;

$connection = Application::getConnection();

$sql = (new SqlExpression(
	'SELECT ID, EMAIL FROM ?# WHERE EMAIL = ?s AND ACTIVE = ?i',
	'b_user',
	$email,
	1,
))->compile();

$result = $connection->query($sql);

while ($row = $result->fetch())
{
	// ...
}
```

## Example 2

Показывает: `INSERT` через `SqlHelper::prepareInsert()` вместо ручной сборки `VALUES`.
Почему это good pattern: helper использует metadata таблицы и сам приводит значения к DB-формату.

```php
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

$connection = Application::getConnection();
$helper = $connection->getSqlHelper();

[$columns, $values] = $helper->prepareInsert('b_example_item', [
	'TITLE' => $title,
	'CREATED_AT' => new DateTime(),
]);

$sql = 'INSERT INTO ' . $helper->quote('b_example_item') . " ({$columns}) VALUES ({$values})";
$connection->queryExecute($sql);
```

## Example 3

Показывает: `UPDATE` со счетчиком, где `SqlExpression` нужен для выражения, а не строкового литерала.
Почему это good pattern: значение `CNT + 1` не превращается в строку в кавычках и не собирается через ручную конкатенацию SQL.

```php
use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlExpression;

$connection = Application::getConnection();
$helper = $connection->getSqlHelper();

[$update] = $helper->prepareUpdate('b_example_counter', [
	'CNT' => new SqlExpression('?# + ?i', 'CNT', 1),
]);

$sql = 'UPDATE ' . $helper->quote('b_example_counter') . ' SET ' . $update
	. ' WHERE ' . $helper->quote('ID') . ' = ' . (int)$counterId;

$connection->queryExecute($sql);
```

## Example 4

Показывает: безопасный `IN (...)` через placeholder `?@` и отдельную обработку empty-list случая.
Почему это good pattern: список значений не склеивается через `implode()`, а branch для пустого массива не оставляет сломанный SQL.

```php
use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlExpression;

if ($ids === [])
{
	return [];
}

$connection = Application::getConnection();

$sql = (new SqlExpression(
	'SELECT ID, TITLE FROM ?# WHERE ID IN (?@)',
	'b_example_item',
	$ids,
))->compile();

$rows = $connection->query($sql)->fetchAll();
```

## Common Mistakes

- Не собирай SQL через `"WHERE ID = {$id}"` или `implode()` по значениям.
- Не используй `global $DB` в новом коде только потому, что он короче.
- Не считай `query($sql, $binds)` универсальной заменой `SqlExpression` для MySQL-кода.

## Checklist

- Для нового кода выбран `Application::getConnection()`, а не `global $DB`?
- Raw SQL действительно нужен, а не появился по инерции там, где достаточно ORM?
- Для чтения строк используется `query()`, для одного значения `queryScalar()`, а для DML без result set `queryExecute()`?
- Для `INSERT` и `UPDATE` используются `add()` / `addMulti()` / `prepareInsert()` / `prepareUpdate()`, если запрос не требует ручной сборки?
- Значения и идентификаторы обрабатываются разными API: `forSql()` / `convertToDb*()` для values и `quote()` для identifiers?
- Если SQL собирается из частей, используется `SqlExpression`, а не строковая конкатенация со значениями?
- Сценарий `IN (...)` не строится через `implode()` и не допускает пустой `?@` список?
- В коде нет ложного ожидания, что `query($sql, $binds)` автоматически дает безопасные prepared statements для MySQL?
