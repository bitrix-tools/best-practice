# Option

## Boundaries

- Этот файл покрывает `Bitrix\Main\Config\Option`, legacy `COption`, а также связанные contract points вроде `default_option.php`, module `options.php` и site-specific options.
- Этот файл помогает выбрать между `Option::get()`, `getRealValue()`, `getForModule()`, `set()` и `delete()` для постоянной конфигурации модуля или портала.
- Этот файл не описывает `CUserOptions`, `CPageOption` и другие user- или request-scoped настройки интерфейса.
- Этот файл не описывает временное runtime-state с TTL, progress/checkpoint, one-time token или другой state между запросами; для них переходи в `rules/persistent-storage.md`.

## Rules

- Используй `Bitrix\Main\Config\Option` для постоянной конфигурации модуля или портала: `default_option.php`, `options.php`, site-specific setting, feature flag или product-policy без TTL.
- В новом коде предпочитай `Bitrix\Main\Config\Option`, а `COption` считай только legacy compatibility path.
- Используй `Option::get()` как default path для чтения, когда нужен обычный fallback на site value, global value, `default_option.php` или явный `$default`.
- Используй `Option::getRealValue()` только когда принципиально важно отличить "значение реально сохранено в БД" от "значение пришло из fallback/default". Не подменяй им `Option::get()` без необходимости.
- Используй `Option::getForModule()` только для bulk-сценариев вроде миграции, экспорта или просмотра всего набора настроек модуля. Для чтения одного ключа не вытаскивай весь набор опций.
- Храни module defaults в `default_option.php`, если значение является частью устойчивого конфигурационного контракта модуля. Не размазывай один и тот же default по нескольким классам в виде локальных magic strings.
- Выбирай scope записи осознанно: пустой `siteId` означает глобальную опцию, конкретный `siteId` означает site-specific override. Не полагайся на неявный текущий site, если нужна именно общая настройка модуля.
- Помни, что `Option` хранит строки. Явно приводи тип на границе чтения и записи, а не рассчитывай на legacy-обёртки вроде `GetOptionInt()` как на preferred path.
- Используй `Option::delete()` только для осознанного удаления или сброса значения и с явным фильтром `name` / `site_id`, если нужен точечный reset. Не заменяй обычное обновление паттерном "delete, потом set".
- Учитывай side effects записи: `Option::set()` сбрасывает cache по модулю, может загрузить `option_triggers.php` и шлёт `OnAfterSetOption`. Не используй `Option` как chatty storage для часто меняющегося operational state.
- Не сохраняй в `Option` временный progress, checkpoint, runtime timestamp, one-time token или другое состояние сценария, которое должно естественно истекать. Для такого state переходи в `rules/persistent-storage.md`.

## Decision Guide

- Если значение является deploy-time конфигом, secret или file-based настройкой окружения, используй `Configuration` / `.settings.php`, а не `Option`.
- Если значение является постоянной настройкой модуля или портала без TTL и должно переживать админское редактирование, используй `Option`.
- Если значение временное, высококардинальное или влияет на flow-state сценария между запросами, переходи в `rules/persistent-storage.md`.
- Если значение можно безопасно потерять и заново пересчитать из первичного источника, используй `Cache` или `ManagedCache`, а не `Option`.

## Related Rules

- `rules/persistent-storage.md` — для временного server-side state между запросами, который не должен становиться постоянной конфигурацией.

## Example 1

Показывает: default path для постоянной настройки модуля через `default_option.php` и `Bitrix\Main\Config\Option`.
Почему это good pattern: default живет в конфигурационном контракте модуля, а код читает и пишет настройку через один canonical API.

Файл `modules/vendor.example/default_option.php`:
```php
$vendor_example_default_option = [
	'sync_interval' => '60',
];
```

Использование опции:
```php
<?php
use Bitrix\Main\Config\Option;

final class SyncSettings
{
	public const MODULE_ID = 'vendor.example';

	public function getInterval(): int
	{
		return (int)Option::get(self::MODULE_ID, 'sync_interval');
	}

	public function setInterval(int $seconds): void
	{
		Option::set(self::MODULE_ID, 'sync_interval', (string)$seconds);
	}
}
```

## Example 2

Показывает: decision point между `get()` и `getRealValue()` для site-specific override.
Почему это good pattern: код явно отличает "на этом сайте нет собственного значения" от обычного fallback на global/default.

```php
<?php

use Bitrix\Main\Config\Option;

final class PortalTitleResolver
{
	public function resolveForSite(string $siteId): array
	{
		$exactSiteValue = Option::getRealValue('vendor.example', 'portal_title', $siteId);

		return [
			'hasSiteOverride' => $exactSiteValue !== null,
			'title' => $exactSiteValue ?? Option::get('vendor.example', 'portal_title', 'Company Portal', $siteId),
		];
	}
}
```

## Example 3

Показывает: соседний сценарий, где `Option` уже не подходит, потому что данные являются временным runtime-state.
Почему это good pattern: checkpoint живет в `PersistentStorageInterface` с TTL, а постоянная политика выполнения остается в `Option`.

```php
<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Storage\PersistentStorageInterface;
use Bitrix\Main\DI\ServiceLocator;

final class ImportRunner
{
	public function run(string $sessionId): void
	{
		$storage = ServiceLocator::getInstance()->get(PersistentStorageInterface::class);
		$chunkSize = (int)Option::get('vendor.example', 'import_chunk_size', '100');
		$checkpoint = $storage->get('vendor.example.import.' . $sessionId, []);

		$checkpoint = $this->runChunk($checkpoint, $chunkSize);
		$storage->set('vendor.example.import.' . $sessionId, $checkpoint, 86400);
	}

	private function runChunk(array $checkpoint, int $chunkSize): array
	{
		return $checkpoint;
	}
}
```

## Example 4

Показывает: допустимый compatibility path в маленьком патче внутри legacy-файла, где уже живет `COption`.
Почему это допустимо: патч не закрепляет `COption` как новый default, а лишь не смешивает два style-path в старом коде без необходимости.

```php
<?php

// legacy admin/update file
if (COption::GetOptionString('main', 'strong_update_check', 'Y') === 'Y')
{
	$this->runIntegrityCheck();
}
```

## Legacy / Exceptions

- `COption` уже помечен как `@deprecated` и является thin wrapper над `Bitrix\Main\Config\Option`, а не равноценным modern API.
- В небольшом патче внутри старого admin/update/legacy-файла допустимо оставить окружающий `COption`-style, если полная миграция файла не входит в задачу.
- Не переноси `COption` в новый `lib/`-код и не используй его в новых примерах как default path.
- `COption::GetOptionString(..., $bExactSite = true)` имеет compatibility-semantics с возвратом `false`, если exact stored value отсутствует. Для нового кода не копируй эту семантику; если нужен точный факт наличия значения, используй `Option::getRealValue()` и проверку на `null`.

## Common Mistakes

- Не использовать `Option` для progress/checkpoint, который должен истечь или иметь TTL.
- Не писать новый код на `COption`, если нет явной legacy-границы.
- Не рассчитывать на неявный current site там, где по смыслу нужна глобальная настройка модуля.

## Checklist

- Для новой постоянной настройки выбран `Bitrix\Main\Config\Option`, а не `COption`?
- `Option` используется именно для постоянной конфигурации без TTL, а не для временного runtime-state?
- Для обычного чтения выбран `Option::get()`, а `getRealValue()` оставлен только для сценариев, где важен факт реального сохранения значения?
- Дефолты, являющиеся частью контракта модуля, вынесены в `default_option.php`, а не размазаны по коду?
- Scope записи выбран осознанно: глобальная опция не записывается случайно в site-specific key или наоборот?
- `Option::delete()` не используется как грубая замена обычному обновлению значения?
- В коде учтено, что `Option::set()` вызывает cache invalidation и event/trigger side effects?
- Если данные временные или должны естественно истекать, код отправлен в `rules/persistent-storage.md`, а не сохраняет их в `Option`?
