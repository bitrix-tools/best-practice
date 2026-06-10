# ORM Persistence Write

## Boundaries

- Этот файл покрывает запись через `Bitrix\Main\ORM\Data\DataManager`, object/collection `save()`, batch-операции и low-level persistence helpers внутри ORM.
- Этот файл покрывает `add()`, `update()`, `delete()`, `addMulti()`, `updateMulti()`, `DeleteByFilterTrait`, `MergeTrait`, `AddStrategy` и связанные traits вроде `AddMergeTrait` и `InsertIgnoreByDefaultTrait`.
- Этот файл помогает выбрать между обычной ORM-записью, object save, batch persistence, merge/upsert и delete-by-filter.
- Этот файл не описывает декларацию entity map и не задает правила query/filter чтения; для них переходи в `rules/orm-datamanager-map.md` и `rules/orm-query-filter.md`.

## Rules

- Для обычной записи одной сущности через `DataManager` используй `add()` и `update()`, а не raw SQL или low-level trait path без причины.
- Если сценарий уже живет в `EntityObject` / `Collection` и опирается на state или relation graph, предпочитай `object->save()` и связанный Objectify lifecycle вместо ручной расклейки по `add()` / `update()`.
- Для массовой вставки и обновления нескольких однотипных строк используй `addMulti()` и `updateMulti()`, а не цикл по одиночным `add()` / `update()`, если lifecycle и events это допускают.
- Не считай batch-методы универсальной заменой object save: когда изменения затрагивают relation graph, collection removals или object state, write-path должен оставаться в Objectify.
- `deleteByFilter()` используй только для осознанной mass delete операции с узким фильтром; пустой filter здесь не допустим и не должен превращаться в скрытый truncate.
- `deleteByFilter()` и `merge()` выбирай только когда действительно нужен low-level bulk path вне обычного ORM lifecycle; это не новый default вместо `delete()` / `add()` / `update()`.
- Для upsert-like сценариев различай три path: `MergeTrait::merge()`, merge strategy как default `getAddStrategy()`, и helper methods `addMerge()` / `addMergeMulti()`; не смешивай их как будто это один и тот же API.
- `AddMergeTrait` и `AddInsertIgnoreTrait` используй только когда behavior без событий является осознанным контрактом сценария.
- `MergeByDefaultTrait` и `InsertIgnoreByDefaultTrait` подключай только если весь `add()` contract конкретной сущности действительно должен быть переопределен на merge/ignore-by-default path.
- При выборе between `InsertIgnore` и `Merge` смотри на бизнес-смысл конфликта: ignore подходит, когда дубликат нужно молча не вставлять, merge — когда дубликат должен обновить существующую строку.
- Учитывай `ignoreEvents` в `addMulti()` / `updateMulti()` как осознанный trade-off, а не как «ускорение на всякий случай».
- Для mass write после успешной ORM-модификации не дублируй вручную cache invalidation, если выбранный API уже вызывает `cleanCache()`; но и не обходи ORM write path так, будто cache очистится сам.

## Decision Guide

- Если нужно сохранить один объект с его state и relations, выбирай `object->save()`.
- Если нужно просто добавить или обновить одну строку массива данных, выбирай `add()` или `update()`.
- Если нужно вставить или обновить много однотипных строк, сначала оцени `addMulti()` или `updateMulti()`.
- Если нужен массовый delete по ORM filter без подъемa каждой сущности, выбирай `deleteByFilter()` с узким фильтром.
- Если нужен upsert по unique/primary key, сначала реши семантику конфликта: ignore или merge, а затем подбирай `InsertIgnore`/`Merge` strategy или dedicated helper method.
- Если задача уже не про запись, а про построение filters, object fetch или shape результата, возвращайся в `rules/orm-query-filter.md` и `rules/orm-objectify.md`.

## Legacy / Exceptions

- В существующем коде допустимо оставлять array-based `add()` / `update()` path, если файл не живет в Objectify и полная миграция на объекты не входит в задачу.
- `addMerge()` / `addInsertIgnore()` и их multi-варианты допустимы только как явно осознанный special-case path; отсутствие событий здесь не является мелкой внутренней деталью.
- `deleteByFilter()` допустим для bulk cleanup, но не должен становиться скрытой заменой нормального entity delete там, где важен per-object lifecycle.

## Related Rules

- `rules/orm-objectify.md` — для `EntityObject::save()`, relation graph и выбора object lifecycle перед записью.
- `rules/orm-query-filter.md` — для filter contract и `buildFilterSql()` как смежной read/filter абстракции.
- `rules/orm-datamanager-map.md` — для map, relations и metadata, которые определяют persistence contract сущности.

## Example 1

Показывает: базовый array-based write path через `add()` и `update()`.
Почему это good pattern: используется штатный ORM lifecycle, а не ручной SQL.

```php
$addResult = TaskTable::add([
	'TITLE' => $title,
	'PROJECT_ID' => $projectId,
]);

if (!$addResult->isSuccess())
{
	return $addResult->getErrors();
}

$taskId = $addResult->getPrimary()['ID'];

TaskTable::update($taskId, [
	'TITLE' => $newTitle,
]);
```

## Example 2

Показывает: object save как соседний persistence path для сущности с relation graph.
Почему это good pattern: запись идет через object state и relation lifecycle, а не через раздельные ручные операции.

```php
$task = TaskTable::createObject()
	->set('TITLE', $title)
	->set('PROJECT_ID', $projectId);

$task->addTo('COMMENTS', TaskCommentTable::createObject()
	->set('MESSAGE', $firstComment));

$result = $task->save();
```

## Example 3

Показывает: batch write через `addMulti()` и `updateMulti()`.
Почему это good pattern: массовая операция выражена ORM-native API, а не циклом из одиночных запросов.

```php
TaskTable::addMulti([
	['TITLE' => 'Task A', 'PROJECT_ID' => 10],
	['TITLE' => 'Task B', 'PROJECT_ID' => 10],
]);

TaskTable::updateMulti(
	[
		['ID' => 100],
		['ID' => 101],
	],
	[
		'STATUS' => 'ARCHIVED',
	]
);
```

## Example 4

Показывает: bulk delete через `deleteByFilter()` с узким ORM filter.
Почему это good pattern: операция явно помечена как mass delete и не маскируется под обычный entity delete.

```php
use Bitrix\Main\ORM\Query\Query;

final class ArchivedTaskTable extends \Bitrix\Main\ORM\Data\DataManager
{
	use \Bitrix\Main\ORM\Data\Internal\DeleteByFilterTrait;
}

ArchivedTaskTable::deleteByFilter(
	Query::filter()
		->where('PROJECT_ID', $projectId)
		->where('STATUS', 'ARCHIVED')
);
```

## Example 5

Показывает: отдельный decision point для merge/upsert behavior.
Когда уместно: конфликт по primary/unique key должен обновить существующую запись, а не упасть ошибкой.
Почему это допустимо: merge path выбран осознанно и не выдается за обычный `add()`.

```php
use Bitrix\Main\ORM\Data\Internal\MergeTrait;

final class PortalLinkTable extends \Bitrix\Main\ORM\Data\DataManager
{
	use MergeTrait;
}

PortalLinkTable::merge(
	[
		'PORTAL_ID' => $portalId,
		'EXTERNAL_ID' => $externalId,
		'TITLE' => $title,
	],
	[
		'TITLE' => $title,
	]
);
```

## Example 6

Показывает: special-case path через AddStrategy traits.
Когда уместно: duplicate handling нужен как отдельный explicit API и отсутствие событий является частью контракта.
Почему это допустимо: правило не подменяет обычный `add()` таким helper'ом по умолчанию.

```php
use Bitrix\Main\ORM\Data\AddStrategy\Trait\AddInsertIgnoreTrait;

final class SyncMarkerTable extends \Bitrix\Main\ORM\Data\DataManager
{
	use AddInsertIgnoreTrait;
}

SyncMarkerTable::addInsertIgnore([
	'ENTITY_ID' => $entityId,
	'MARKER' => $marker,
]);
```

## Checklist

- Для обычной записи выбран штатный ORM path (`add()`, `update()`, `save()`), а не low-level helper без причины?
- Object save используется там, где важны state и relations, а batch write не подменяет этот lifecycle?
- `addMulti()` / `updateMulti()` выбраны для массовой однородной операции, а не как слепой default для любого write?
- `deleteByFilter()` используется только с узким осмысленным фильтром и не маскирует собой обычный entity delete?
- Merge/ignore semantics выбраны осознанно, а не сведены к одному «upsert как-нибудь»?
- Traits `AddMergeTrait`, `AddInsertIgnoreTrait`, `MergeByDefaultTrait`, `InsertIgnoreByDefaultTrait` применяются только когда их behavioral contract действительно нужен?
- В коде учтено, что merge/insert-ignore helper paths не являются обычной event-friendly записью?
- Cache invalidation и lifecycle не обходятся вручную там, где ORM API уже делает это сам?
- Граница между query/filter, Objectify и persistence не смешана в одном примере и одном правиле?
