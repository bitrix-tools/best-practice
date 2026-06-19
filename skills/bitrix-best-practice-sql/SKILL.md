---
name: bitrix-best-practice-sql
description: Use for Bitrix SQL and ORM patterns raw SQL or Connection queries, DataManager maps or field definitions, Objectify objects or collections, ORM queries or filters, ORM writes or batch persistence. Use when designing, reviewing, or implementing DB reads, writes, schema, or data access code.
---

# Bitrix Best Practice SQL

Скилл помогает понять, какие нужно использовать SQL/DB best practices.
Используй его только для задач, где есть работа с базой данных: схема, SQL-запросы, ORM-модели, выборки, запись, миграции данных, индексы, транзакции или другие DB-boundary решения.

## Как использовать

1. Определи, действительно ли задача затрагивает SQL, ORM или хранение данных.
2. Выдели конкретную DB-область задачи: чтение, запись, схема, миграция, производительность, консистентность или безопасность запроса.
3. Открой только те rule-файлы, которые напрямую относятся к этой DB-области.
4. Сначала следуй более строгим правилам репозитория и ограничениям модуля.
5. Предпочитай framework-native и Bitrix-native паттерны работы с данными вместо самодельных SQL- и storage-абстракций.

## Выбор rule-файла

<!-- rules-dictionary:start -->

### Когда читать `rules/query-execution.md`

Читай `rules/query-execution.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Application::getConnection()`, `Bitrix\Main\DB\Connection` или `getSqlHelper()`;
- `query()`, `queryScalar()`, `queryExecute()`, `add()`, `addMulti()` или `Bitrix\Main\DB\Result`;
- `Bitrix\Main\DB\SqlExpression`, placeholders `?s`, `?i`, `?f`, `?#`, `?@` или ручную сборку SQL из частей;
- `SqlHelper::quote()`, `forSql()`, `prepareInsert()`, `prepareUpdate()`, `convertToDb*()` или `getTopSql()`;
- замену legacy `$DB` / `CDatabase` на D7 DB API;
- выбор между raw SQL через `Connection` и более высокоуровневым framework-native DB path.

### Когда читать `rules/orm-datamanager-map.md`

Читай `rules/orm-datamanager-map.md`, если задача затрагивает хотя бы одну из этих областей:

- класс `*Table extends Bitrix\Main\ORM\Data\DataManager`;
- `getTableName()`, `getConnectionName()`, `getMap()`, `getUfId()`, `postInitialize()` или `setDefaultScope()`;
- описание полей через `Bitrix\Main\ORM\Fields\Field` и наследников вместо legacy array map;
- `Reference`, `OneToMany`, `ManyToMany`, `configureJoinType()`, `configureCascade*Policy()` именно на уровне декларации сущности;
- primary key, autocomplete, validators, save/fetch modifiers, title и default values в entity map;
- выбор между typed field objects и старым массивом в `getMap()`.

### Когда читать `rules/orm-objectify.md`

Читай `rules/orm-objectify.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\ORM\Objectify\EntityObject`, `Collection`, `createObject()`, `createCollection()`, `wakeUpObject()` или `wakeUpCollection()`;
- `get()`, `require()`, `remindActual()`, `fill()`, `isFilled()`, `isChanged()`, `collectValues()`;
- работу с relation graph через `addTo()`, `removeFrom()`, `removeAll()` или collection lifecycle;
- `Collection::walk()`, `filter()`, `find()`, `merge()`, `hasByPrimary()` или `getByPrimary()`;
- выбор между Objectify-объектами и массивами результата;
- object state (`RAW`, `ACTUAL`, `CHANGED`, `DELETED`) и in-memory поведение ORM.

### Когда читать `rules/orm-query-filter.md`

Читай `rules/orm-query-filter.md`, если задача затрагивает хотя бы одну из этих областей:

- `DataManager::query()`, `getList()`, `getRow()`, `getByPrimary()` или `getCount()`;
- `Bitrix\Main\ORM\Query\Query`, `Query::filter()`, `ConditionTree`, `where*`, `having*`, `logic()` или nested filters;
- `fetchObject()`, `fetchCollection()`, `fetch()`, `fetchAll()` и выбор между object fetch и массивами;
- runtime fields, `registerRuntimeField()`, `Query::expr()`, `ExpressionField` в query-path;
- `buildFilterSql()`, `disableDataDoubling()`, private fields, aggregation/object-fetch restrictions;
- выбор между modern query builder и legacy filter array.

### Когда читать `rules/orm-persistence-write.md`

Читай `rules/orm-persistence-write.md`, если задача затрагивает хотя бы одну из этих областей:

- `DataManager::add()`, `update()`, `delete()`, `addMulti()`, `updateMulti()` или object/collection `save()`;
- `DeleteByFilterTrait::deleteByFilter()`, `MergeTrait::merge()` или low-level ORM write helpers;
- `Bitrix\Main\ORM\Data\AddStrategy`, `InsertIgnore`, `Merge`, `MergeByDefaultTrait`, `InsertIgnoreByDefaultTrait`, `AddMergeTrait` или `AddInsertIgnoreTrait`;
- выбор между обычной ORM-записью, batch-операцией, merge/upsert и delete-by-filter;
- события ORM при записи, `ignoreEvents`, cache cleanup и caveats batch persistence;
- массовая запись или обновление нескольких строк через ORM lifecycle.

<!-- rules-dictionary:end -->
