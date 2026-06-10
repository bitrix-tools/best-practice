# ORM Objectify

## Boundaries

- Этот файл покрывает in-memory модель Bitrix ORM через `Bitrix\Main\ORM\Objectify\EntityObject` и `Bitrix\Main\ORM\Objectify\Collection`.
- Этот файл покрывает `createObject()`, `createCollection()`, `wakeUpObject()`, `wakeUpCollection()`, object state, `get()` / `require()` / `fill()` и работу с relation graph.
- Этот файл помогает выбрать между Objectify-объектами и массивами результата ORM.
- Этот файл не описывает, как строить query и filter, и не задает правила для `DataManager::add()/update()` или `deleteByFilter()`; для них переходи в `rules/orm-query-filter.md` и `rules/orm-persistence-write.md`.

## Rules

- Если чтение не упирается в жесткие ограничения производительности и сценарий реально работает с доменной записью, relation graph или изменением состояния, предпочитай `EntityObject` и `Collection` вместо массивов `fetch()` / `fetchAll()`.
- Не превращай object-first рекомендацию в догму: для плоской технической выборки, агрегации, lightweight list API или горячего участка, где нужен только массив данных, массивы остаются допустимым path.
- Новый объект создавай через `DataManager::createObject()`, а не через ручной `new SomeEO_*`, чтобы сохранить contract сущности и default values.
- Существующую запись, уже известную как actual data, поднимай через `wakeUpObject()` или `wakeUpCollection()`, а не через `createObject()` с имитацией загруженного состояния.
- Для чтения значения используй `get()`, когда `null` допустим, и `require()`, когда отсутствие значения является ошибкой сценария, а не тихим fallback.
- Не считай `get()` lazy-loading API: если поле или relation еще не загружены, используй `fill()` с явным набором полей или заранее fetch object/collection через query.
- При работе с relation graph используй `addTo()`, `removeFrom()` и `removeAll()`, а не ручную синхронизацию foreign keys в обход объектной модели, если сценарий уже живет в Objectify.
- Для коллекций используй `walk()` для массовой mutation/orchestration над уже загруженными объектами, `find()` для поиска одного объекта по predicate, `filter()` для построения подмножества и `merge()` для объединения совместимых коллекций.
- Не вызывай `Collection::filter()` на измененной коллекции: у dirty collection это не безопасный default path и приводит к исключению.
- `Collection::merge()` применяй только к коллекциям одного класса и одной сущности; не смешивай чужие collection types как будто это обычные массивы.
- Для сериализации или передачи данных наружу используй `collectValues()`, а не случайный обход внутренних свойств object/collection.
- При выборе между object save и array-based persistence помни, что Objectify хорошо подходит для сценариев, где важны state, relations и cascade lifecycle; для массовой однородной batch-записи чаще нужен persistence rule.

## Related Rules

- `rules/orm-query-filter.md` — для `query()`, object fetch, `fetchObject()` / `fetchCollection()` и выбора query-path.
- `rules/orm-persistence-write.md` — для `save()`, `add()`, `update()`, batch persistence и delete semantics.
- `rules/orm-datamanager-map.md` — для объявления entity map, relations и Objectify classes на уровне metadata.

## Example 1

Показывает: default path, где объект и relation graph дают лучший контракт, чем массив.
Почему это good pattern: код опирается на типизированный объект, явное изменение состояния и relation API, а не на неявные ключи массива.

```php
$task = TaskTable::query()
	->setSelect(['*', 'PROJECT', 'COMMENTS'])
	->where('ID', $taskId)
	->fetchObject()
;

if ($task === null)
{
	return null;
}

$task->set('TITLE', $newTitle);
$task->addTo(
	'COMMENTS',
	TaskCommentTable::createObject()->set('MESSAGE', $commentMessage)
);

$task->save();
```

## Example 2

Показывает: `fill()` как явный путь к недогруженному relation/field.
Почему это good pattern: object API не притворяется lazy getter'ом и не скрывает I/O за обычным `get()`.

```php
$user = UserTable::query()
	->setSelect(['ID', 'NAME'])
	->where('ID', $userId)
	->fetchObject()
;

if ($user === null)
{
	return null;
}

$user->fill(['GROUPS']);
```

## Example 3

Показывает: `require()` как строгий доступ к уже загруженному relation.
Почему это good pattern: сценарий явно фиксирует, что значение обязательно для дальнейшей логики, а не молча работает с `null`.

```php
$user = UserTable::query()
	->setSelect(['ID', 'NAME', 'GROUPS'])
	->where('ID', $userId)
	->fetchObject()
;

if ($user === null)
{
	return null;
}

$groups = $user->require('GROUPS');

return $groups->find(
	static fn($group): bool => $group->get('STRING_ID') === 'ADMIN'
);
```

## Example 4

Показывает: `walk()` для массовой mutation/orchestration над уже загруженной коллекцией.
Почему это good pattern: intent читается прямо из API коллекции, а код не распадается на ручной цикл с внешним временным состоянием.

```php
$openTasks = TaskTable::query()
	->setSelect(['*'])
	->where('STATUS', 'OPEN')
	->fetchCollection();

$openTasks->walk(static function ($task) use ($currentUserId) {
	$task->set('UPDATED_BY', $currentUserId);
});
```

## Example 5

Показывает: `filter()` для построения подмножества коллекции.
Почему это good pattern: новый набор объектов выражается как отдельная коллекция, а не как ad hoc массив из ручного цикла.

```php
$openTasks = TaskTable::query()
	->setSelect(['*'])
	->where('STATUS', 'OPEN')
	->fetchCollection();

$urgentTasks = $openTasks->filter(
	static fn($task): bool => $task->get('PRIORITY') === 'HIGH'
);
```

## Example 6

Показывает: `find()` для поиска одного объекта по predicate.
Почему это good pattern: сценарий явно просит один объект, а не строит лишний цикл или временный массив.

```php
$urgentTasks = TaskTable::query()
	->setSelect(['*'])
	->where('STATUS', 'OPEN')
	->fetchCollection();

$firstOverdue = $urgentTasks->find(
	static fn($task): bool => $task->get('IS_OVERDUE') === true
);
```

## Example 7

Показывает: `merge()` для объединения двух совместимых коллекций одного типа.
Почему это good pattern: merge явно выражает контракт на уровне Objectify, а не маскирует смешивание коллекций под работу с обычными массивами.

```php
$openTasks = TaskTable::query()
	->setSelect(['*'])
	->where('STATUS', 'OPEN')
	->fetchCollection();

$allVisibleTasks = $openTasks->merge($externalTasksCollection);
```

## Example 8

Показывает: соседний сценарий, где массивы остаются уместными.
Когда уместно: нужен плоский lightweight payload без mutation, relations и object lifecycle.
Почему это good pattern: object-first default не навязывается там, где массив действительно дешевле и проще.

```php
$rows = TaskTable::query()
	->setSelect(['ID', 'TITLE'])
	->where('PROJECT_ID', $projectId)
	->fetchAll();

return array_map(
	static fn(array $row): array => [
		'id' => (int)$row['ID'],
		'title' => (string)$row['TITLE'],
	],
	$rows,
);
```

## Checklist

- Там, где код реально работает с сущностью и relations, выбраны Objectify-объекты, а не массивы по инерции?
- Там, где нужен только плоский lightweight payload, object model не введена без практической пользы?
- Новый объект создается через `createObject()`, а существующая запись поднимается через `wakeUp*()` или object fetch?
- `get()` и `require()` используются осознанно, а `get()` не притворяется lazy-loading API?
- Недогруженные поля и relations читаются через `fill()`, а не через скрытые side effects?
- Для relation graph используются `addTo()` / `removeFrom()` / `removeAll()`, если сценарий уже живет в Objectify?
- В коде осознанно используются `walk()`, `filter()`, `find()` и `merge()` там, где они читаются лучше массива и цикла?
- `Collection::filter()` не вызывается на измененной коллекции?
- Экспорт данных наружу делается через `collectValues()` или явный mapping, а не через чтение внутренних полей объекта?
