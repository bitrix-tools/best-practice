# ORM Query And Filter

## Boundaries

- Этот файл покрывает чтение через `Bitrix\Main\ORM\Query\Query`, `DataManager::query()`, `ConditionTree`, object fetch и совместимый legacy read-path через `getList()` / `getRow()`.
- Этот файл помогает выбрать между `query()` и обертками `getList()` / `getRow()`, между `fetchObject()` / `fetchCollection()` и массивами `fetch()` / `fetchAll()`, а также между modern `ConditionTree` и legacy array filter.
- Этот файл покрывает runtime fields, `Query::expr()`, `buildFilterSql()`, `disableDataDoubling()`, private fields и ограничения object fetch при aggregation.
- Этот файл не описывает декларацию entity map и не задает правила записи через `add()` / `update()` / `deleteByFilter()`; для них переходи в `rules/orm-datamanager-map.md` и `rules/orm-persistence-write.md`.

## Rules

- Для нового кода начинай read-path с `DataManager::query()`, а не с `getList()`, `getRow()` или `getRowById()` по инерции.
- `getList()` и `getRow()` считай compatibility wrappers поверх `query()`, а не preferred API для нового query-building.
- Для нового фильтра предпочитай `Query::filter()` и `ConditionTree` с `where*`, `logic()` и вложенными subfilter, а не legacy массив с `'=FIELD' => $value`.
- Legacy array filter допустим как локальный compatibility path в старом коде или когда нужно аккуратно доработать уже существующий query без смены стиля файла.
- Для object-first read-path используй `fetchObject()` и `fetchCollection()`, если сценарий дальше работает с сущностью, relation graph или object API.
- Для плоской технической выборки, легкого списка или aggregation-пейлоада используй `fetch()` / `fetchAll()`, а не object fetch без реальной пользы.
- Если запрос строится с joins, runtime fields или non-trivial filters, выражай его через fluent API `setSelect()`, `where*()`, `registerRuntimeField()`, `setOrder()`, `setLimit()` и `exec()`, а не через разросшийся массив параметров.
- Для runtime fields передавай `Field` / `ExpressionField` объект в `registerRuntimeField()` или `select`, а не array-expression в `select`: array-формат для expression не является новым safe default.
- Если filter идет по 1:N relation и выборка начинает размножать строки, осознанно оцени `disableDataDoubling()` вместо борьбы с дубликатами уже после запроса.
- Не используй `disableDataDoubling()` как слепой default: это специальный инструмент для back-reference filters, а не универсальный ускоритель всех запросов.
- Object fetch не применяй к агрегирующим запросам: `fetchObject()` / `fetchCollection()` не являются корректным path для `GROUP BY`, aggregated expressions и похожих result shapes.
- Если query трогает private fields, разрешай их явно через `enablePrivateFields()`, а не рассчитывай, что ORM пропустит такой доступ по умолчанию.
- Для SQL fragment на основе ORM-filter, например в mass delete или custom update, используй `Query::buildFilterSql()` вместо ручной пересборки условий.

## Decision Guide

- Если задача начинается с новой выборки сущностей, выбирай `DataManager::query()`.
- Если сценарий дальше изменяет объект, читает relations или живет в Objectify, заверши query через `fetchObject()` или `fetchCollection()`.
- Если нужен только lightweight array payload без object lifecycle, заверши query через `fetch()` или `fetchAll()`.
- Если фильтр нетривиальный, вложенный или требует `OR`, `EXISTS`, `IN`, `BETWEEN`, column-to-column comparison или runtime expression, выбирай `ConditionTree`.
- Если задача лишь слегка правит старый `getList(['filter' => ...])` код и полная миграция не входит в scope, legacy array filter допустим как compatibility path.
- Если дальнейший шаг уже не про чтение, а про save, batch write, merge или delete-by-filter, переходи в `rules/orm-persistence-write.md`.

## Legacy / Exceptions

- `getList()` и `getRow()` допустимы в legacy-коде и для локального малого патча, если перевод всего surrounding query на fluent API не входит в задачу.
- Array filter допустим как compatibility path в существующем коде, но не должен появляться как новый preferred pattern рядом с `ConditionTree`.
- `fetch()` / `fetchAll()` остаются нормальным выбором для aggregation, lightweight payload и горячих read paths, где Objectify не дает полезного контракта.

## Related Rules

- `rules/orm-objectify.md` — для выбора между объектами и массивами после query и для дальнейшей работы с `EntityObject` / `Collection`.
- `rules/orm-persistence-write.md` — для write-path после чтения и для `buildFilterSql()` как мостика в mass write/delete сценарии.
- `rules/orm-datamanager-map.md` — для entity map, relations и field declarations, на которые опирается query.

## Example 1

Показывает: default path для нового чтения через `query()` и `ConditionTree`.
Почему это good pattern: query остается явно читаемым, а фильтр не зажат в legacy array format.

```php
use Bitrix\Main\ORM\Query\Query;

$filter = Query::filter()
	->logic('or')
	->where('ACTIVE', true)
	->where('PRIORITY', 'HIGH');

$tasks = TaskTable::query()
	->setSelect(['ID', 'TITLE', 'STATUS'])
	->where($filter)
	->setOrder(['ID' => 'DESC'])
	->fetchAll();
```

## Example 2

Показывает: отдельную сборку и вложение `ConditionTree` друг в друга до передачи в query.
Почему это good pattern: самостоятельные подфильтры можно читать, тестировать и переиспользовать отдельно, не превращая один `where(...)` в длинную нечитаемую цепочку.

```php
use Bitrix\Main\ORM\Query\Query;

$activeFilter = Query::filter()
	->where('ACTIVE', true)
	->whereNotNull('ASSIGNED_BY_ID');

$visibilityFilter = Query::filter()
	->logic('or')
	->where('CREATED_BY', $userId)
	->where('RESPONSIBLE_ID', $userId);

$finalFilter = Query::filter()
	->where($activeFilter)
	->where($visibilityFilter);

$tasks = TaskTable::query()
	->setSelect(['ID', 'TITLE', 'RESPONSIBLE_ID'])
	->where($finalFilter)
	->fetchAll();
```

## Example 3

Показывает: object fetch как основной path, когда дальше нужен `EntityObject`.
Почему это good pattern: query строится явно, а результат сразу приходит в форме, подходящей для Objectify lifecycle.

```php
$task = TaskTable::query()
	->setSelect(['*', 'PROJECT', 'COMMENTS'])
	->where('ID', $taskId)
	->fetchObject();

if ($task === null)
{
	return null;
}

return $task->require('PROJECT');
```

## Example 4

Показывает: runtime field и object collection fetch.
Почему это good pattern: runtime expression зарегистрирован как field object, а не как небезопасный array-expression в `select`.

```php
use Bitrix\Main\ORM\Fields\ExpressionField;

$collection = TaskTable::query()
	->registerRuntimeField(
		new ExpressionField('IS_OVERDUE', 'CASE WHEN %s < NOW() THEN 1 ELSE 0 END', ['DEADLINE'])
	)
	->setSelect(['*', 'IS_OVERDUE'])
	->where('PROJECT_ID', $projectId)
	->fetchCollection();
```

## Example 5

Показывает: `buildFilterSql()` как ORM-native способ построить SQL WHERE для соседнего low-level сценария.
Почему это good pattern: логика filter остается одной и той же между query-path и mass operation, а не переписывается вручную второй раз.

```php
use Bitrix\Main\ORM\Query\Query;

$filter = Query::filter()
	->where('PROJECT_ID', $projectId)
	->whereNotNull('ARCHIVED_AT');

$whereSql = Query::buildFilterSql(TaskTable::getEntity(), $filter);
```

## Example 6

Показывает: compatibility path для старого `getList()` с array filter.
Когда уместно: текущий файл уже живет в legacy style и задача не про полную миграцию query API.
Почему это допустимо: правило признает legacy boundary, но не подает ее как новый default.

```php
$result = TaskTable::getList([
	'select' => ['ID', 'TITLE'],
	'filter' => [
		'=PROJECT_ID' => $projectId,
		'=ACTIVE' => 'Y',
	],
	'order' => ['ID' => 'DESC'],
]);
```

## Checklist

- Новый read-path начинается с `query()`, а не с `getList()` / `getRow()` по инерции?
- Для нового фильтра используется `ConditionTree`, если логика уже сложнее простого compatibility-case?
- `getList()` и array filter оставлены только как legacy boundary, а не как новый recommended pattern?
- Между `fetchObject()` / `fetchCollection()` и `fetch()` / `fetchAll()` выбран осознанный result shape?
- Object fetch не используется для aggregation или другого result shape, который не является сущностью?
- Runtime fields регистрируются через field objects, а не через устаревший array-expression path?
- `disableDataDoubling()` применяется как специальный инструмент для relation filter, а не как слепой default?
- Доступ к private fields включается явно, если он действительно нужен?
- Для mass SQL-сценария используется `buildFilterSql()`, если нужно переиспользовать ORM filter contract?
