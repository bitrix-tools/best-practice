# ORM DataManager Map

## Boundaries

- Этот файл покрывает декларацию ORM-сущности через `Bitrix\Main\ORM\Data\DataManager`: `getTableName()`, `getConnectionName()`, `getMap()`, `getUfId()`, `postInitialize()` и `setDefaultScope()`.
- Этот файл покрывает описание полей через `Bitrix\Main\ORM\Fields\Field` и наследников, а также relations `Reference`, `OneToMany` и `ManyToMany` именно на уровне map и metadata сущности.
- Этот файл помогает выбрать между typed field objects и legacy array definition в `getMap()`.
- Этот файл не описывает query-building, `ConditionTree`, object fetch, `EntityObject` / `Collection` lifecycle и операции записи; для них переходи в `rules/orm-query-filter.md`, `rules/orm-objectify.md` и `rules/orm-persistence-write.md`.

## Rules

- Для нового кода объявляй ORM-сущность отдельным классом `*Table extends DataManager`, а не собирай entity runtime-конфигурацией без необходимости.
- В `getMap()` предпочитай объекты полей `Field` и их наследников, а не legacy массивы с `'data_type'`, `'primary'`, `'required'` и другими ключами.
- Выражай primary key, required, autocomplete, nullable, default value, title и validators через `configure*()` и `add*()` методы field object, а не через неструктурированный массив параметров, если задача не про поддержку legacy-кода.
- Для scalar-полей выбирай конкретный тип (`IntegerField`, `StringField`, `BooleanField`, `DatetimeField` и т.д.), а не универсальную строковую декларацию `data_type`.
- Если полю нужны save/fetch modifiers или validators, вешай их на сам field object, чтобы поведение оставалось рядом с контрактом хранения, а не размазывалось по событиям и внешним helper'ам.
- Для relation на уровне map используй `Reference`, `OneToMany` и `ManyToMany`, а не ad hoc поля и ручные join-правила в query как замену нормальной декларации связи.
- В `Reference` задавай осмысленный reference filter и join type через `configureJoinType()`, если default `LEFT JOIN` не отражает контракт связи.
- Для `OneToMany` и `ManyToMany` явно конфигурируй cascade policy только когда она действительно соответствует lifecycle сущности; не превращай cascade в скрытый side effect по умолчанию.
- Если `ManyToMany` использует mediator с нестандартной таблицей, ключами или reference names, конфигурируй их через `configureMediator*()`, `configureLocal*()` и `configureRemote*()`, а не оставляй неявное поведение там, где схема уже отклоняется от default.
- `setDefaultScope()` используй для устойчивого entity-level ограничения выборок, а не для локальной фильтрации одного сценария; разовый query-specific filter должен жить в query-коде.
- `postInitialize()` используй только для entity-level донастройки metadata после сборки сущности; не переноси туда runtime-логику приложения.
- `getUfId()` объявляй только там, где сущность действительно интегрируется с UF, и не смешивай UF-поддержку с обычными scalar-полями в map как будто это один и тот же storage contract.
- Если сущности нужны кастомные Objectify-классы, задавай их через `getObjectClass()`, `getCollectionClass()` или соответствующие parent/object hooks на уровне `DataManager`, а не подменяй object model в query- или service-коде.

## Related Rules

- `rules/orm-query-filter.md` — для `query()`, `ConditionTree`, runtime fields и object fetch.
- `rules/orm-objectify.md` — для `EntityObject`, `Collection`, relation graph и выбора между объектами и массивами.
- `rules/orm-persistence-write.md` — для `add()`, `update()`, batch persistence, merge и delete-by-filter.

## Example 1

Показывает: рекомендуемый default для `DataManager` с typed field objects в `getMap()`.
Почему это good pattern: контракт хранения, ограничения и metadata объявлены явно и остаются рядом с полями, а не растворяются в legacy-массиве.

```php
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class ProjectTable extends DataManager
{
	public static function getTableName(): string
	{
		return 'b_example_project';
	}

	public static function getMap(): array
	{
		return [
			(new IntegerField('ID'))
				->configurePrimary()
				->configureAutocomplete()
			,
			(new StringField('TITLE'))
				->configureRequired()
				->configureSize(255)
				->configureTitle('Project title')
			,
			(new DatetimeField('CREATED_AT'))
				->configureDefaultValueNow()
			,
		];
	}
}
```

## Example 2

Показывает: декларацию relation в map, а не ручную сборку связи в каждом query.
Почему это good pattern: ORM знает о связи на уровне metadata, а query/objectify/persistence могут опираться на единый relation contract.

```php
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Query\Query;

final class TaskTable extends DataManager
{
	public static function getTableName(): string
	{
		return 'b_example_task';
	}

	public static function getMap(): array
	{
		return [
			(new IntegerField('ID'))
				->configurePrimary()
				->configureAutocomplete()
			,
			(new IntegerField('PROJECT_ID'))
				->configureRequired()
			,
			(new Reference(
				'PROJECT',
				ProjectTable::class,
				Join::on('this.PROJECT_ID', 'ref.ID'),
			))->configureJoinType(Join::TYPE_INNER),
			(new OneToMany('COMMENTS', TaskCommentTable::class, 'TASK')),
		];
	}
}
```

## Example 3

Показывает: compatibility path, где legacy array map допустим только как локальная граница старого кода.
Когда уместно: существующая сущность уже живет в legacy-формате и задача не включает её полноценную модернизацию.
Почему это допустимо: правило не ломает старый код ради косметики, но и не подает массив как новый default.

```php
final class LegacyItemTable extends \Bitrix\Main\ORM\Data\DataManager
{
	public static function getTableName(): string
	{
		return 'b_example_legacy_item';
	}

	public static function getMap(): array
	{
		return [
			'ID' => [
				'data_type' => 'integer',
				'primary' => true,
				'autocomplete' => true,
			],
			'CODE' => [
				'data_type' => 'string',
				'required' => true,
			],
		];
	}
}
```

## Checklist

- Сущность объявлена отдельным `DataManager`, а не собирается runtime-магией без необходимости?
- В `getMap()` для нового кода используются field objects, а не legacy arrays как default path?
- Тип каждого поля отражает реальный storage contract, а не выбран «универсально строковый» по инерции?
- PK, required, autocomplete, nullable, default values и validators выражены на уровне field object?
- Relations объявлены в map через `Reference` / `OneToMany` / `ManyToMany`, а не имитируются ручными join-правилами в каждом query?
- Cascade policy и join type настроены только там, где это действительно часть контракта сущности?
- `setDefaultScope()` и `postInitialize()` не используются как свалка для локальной бизнес-логики?
- Запись, query-path и Objectify lifecycle не смешаны с декларацией сущности в одном правиле или одном примере?
