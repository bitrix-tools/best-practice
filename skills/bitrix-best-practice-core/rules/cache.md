# Cache

## Boundaries

- Этот файл покрывает выбор между `Bitrix\Main\Data\Cache`, `Bitrix\Main\Data\ManagedCache` и `Bitrix\Main\Data\TaggedCache` для server-side read-cache, который можно безопасно потерять и пересчитать.
- Этот файл также покрывает modern entry points вроде `Cache::createInstance()`, `Application::getInstance()->getCache()`, `getManagedCache()`, `getTaggedCache()` и container-bound cache services из `main/.settings.php`, если главный вопрос состоит именно в выборе cache API.
- Этот файл фиксирует legacy-границу для `CPHPCache`, `CCacheManager`, `$CACHE_MANAGER` и `CStackCacheManager`.
- Этот файл не решает, нужно ли хранить runtime-state или постоянную конфигурацию вместо cache; для этого переходи в `rules/persistent-storage.md` и `rules/option.md`.
- Этот файл не описывает общий DI flow, service registration strategy и выбор между `ServiceLocator`, autowiring и ручным `new` как самостоятельную тему; для этого переходи в `rules/service-locator.md`.
- Этот файл не описывает HTTP/browser cache (`setCacheTime()`, cache headers, `Last-Modified`) и не заменяет component lifecycle API вроде `startResultCache()`; здесь важен именно выбор `Bitrix\Main\Data\*` cache layer.

## Rules

- Используй cache только для derived data, которую можно безопасно потерять и пересчитать из первичного источника без изменения бизнес-смысла сценария.
- Используй `Cache` как default path для локального TTL-cache одной вычисляемой выборки, агрегата или render-ready payload, когда достаточно `cacheId`, `cacheDir` и явной инвалидации по пути.
- Для data-only cache через `Cache` предпочитай связку `initCache()` / `getVars()` / `startDataCache()` / `endDataCache($vars)`, а output-buffer ветку используй только когда действительно кешируешь готовый вывод, а не просто массив данных.
- Для `Cache` всегда задавай устойчивый `cacheId`, namespaced `cacheDir` и осознанный TTL; не оставляй глобальные короткие ключи и не смешивай разные payload format под одним идентификатором без версионирования.
- Если после `startDataCache()` расчёт завершился ошибкой, ранним `return` или невалидным промежуточным состоянием, вызывай `abortDataCache()`, а не `endDataCache()` с частичным payload.
- Используй `ManagedCache` для shared registry-style или reference data, которая многократно читается в рамках одного hit и должна инвалидироваться по `uniqueId` или `tableId`, а не по доменным тегам.
- Для `ManagedCache` сначала вызывай `read($ttl, $uniqueId, $tableId)`, затем `get($uniqueId)` или `set($uniqueId, $value)`; не воспринимай `set()` как независимый generic key-value API без активного cache entry.
- После изменения первичных данных инвалидируй `ManagedCache` через `clean()` или `cleanDir()`, а не полагайся только на TTL там, где точка обновления источника известна.
- Используй `TaggedCache` только как механизм tag-based invalidation поверх обычного cache path; сам payload по-прежнему храни в `Cache` или в component result cache, а не в `TaggedCache`.
- Выбирай `TaggedCache`, когда одно доменное изменение должно сбросить несколько cache paths по общему тегу; если достаточно инвалидировать один `uniqueId` или одну группу `tableId`, предпочитай `ManagedCache`.
- Для `TaggedCache` оборачивай регистрацию тегов в `startTagCache($path)` / `registerTag($tag)` / `endTagCache()`, а при неуспешном построении payload вызывай `abortTagCache()`.
- Для локального `Cache` по умолчанию предпочитай `Cache::createInstance()`, чтобы получить свежий рабочий объект под конкретный cache flow без неявного разделения состояния между сценариями.
- В новом service/provider не доставай `ManagedCache` и `TaggedCache` через `Application::getInstance()` или `ServiceLocator` внутри самого класса, если этот service/provider может быть создан контейнером; передавай эти зависимости в конструктор и позволяй autowiring подставить их по FQCN из `main/.settings.php`.
- Если создаёшь конкретный service/provider через `ServiceLocator::get(Provider::class)`, ожидай, что `ManagedCache` и `TaggedCache` будут вставлены автоматически в его конструктор; не дублируй этот шаг ручными вызовами locator внутри провайдера.
- Если нужно явно получить cache manager в composition root, bootstrap, factory или другом внешнем entry point, используй `ServiceLocator::get(ManagedCache::class)` и `ServiceLocator::get(TaggedCache::class)` как container-native path.
- `Application::getInstance()->getManagedCache()` и `getTaggedCache()` оставляй для framework/static/legacy entry points, где constructor DI не является естественным default path.
- Учитывай, что `Application::getInstance()->getCache()` возвращает новый `Cache`, а `ServiceLocator` по умолчанию кеширует созданный service instance; поэтому для stateful `Cache` не подменяй локальный `Cache::createInstance()` вызовом locator внутри сервиса.
- В новом коде не используй `CPHPCache`, `CCacheManager`, `$CACHE_MANAGER` и `CStackCacheManager` как preferred path; это legacy wrappers или legacy infrastructure boundary, а не canonical modern API.
- Не обращайся напрямую к файлам под `cache` / `managed_cache` и не хардкодь file-engine assumptions; используй `Bitrix\Main\Data\*`, чтобы сохранить корректный engine selection, salt и invalidation semantics.

## Decision Guide

- Если нужен обычный TTL-cache для одной выборки или вычисления, выбирай `Cache`.
- Если данные читаются много раз за hit и у них есть понятный ключ или группа инвалидции, выбирай `ManagedCache`.
- Если одна доменная сущность должна сбрасывать несколько cache paths, выбирай `Cache` плюс `TaggedCache`.
- Если задача состоит именно в root-level получении shared cache manager из контейнера, используй `ServiceLocator::get(ManagedCache::class)` или `ServiceLocator::get(TaggedCache::class)`, а не ручную сборку этих зависимостей.
- Если главный вопрос не про read-cache, а про временное состояние сценария между запросами, переходи в `rules/persistent-storage.md`.
- Если значение является постоянной настройкой, а не derived data, переходи в `rules/option.md`.
- Если основной вопрос уже не про cache semantics, а про то, как именно dependency должна приходить через DI, переходи в `rules/service-locator.md`.

## Related Rules

- `rules/persistent-storage.md` — для границы между cache и временным runtime-state, который не должен теряться как обычный performance cache.
- `rules/option.md` — для постоянной конфигурации модуля или портала, где данные не являются derived cache.
- `rules/service-locator.md` — для общего выбора между `ServiceLocator`, autowiring и ручным созданием dependency.
- `rules/response.md` — для HTTP/browser cache и response-level cache headers.

## Example 1

Показывает: базовый data-only `Cache` для derived payload с явным `cacheId`, `cacheDir` и `abortDataCache()` на failure path.
Почему это good pattern: локальный cache flow получает свежий instance, а код не сохраняет частично собранные данные при исключении.

```php
use Bitrix\Main\Data\Cache;

final class DashboardSummaryProvider
{
	public function getSummary(int $userId): array
	{
		$cache = Cache::createInstance();
		$cacheId = 'vendor.example.dashboard.summary.v1.' . $userId;
		$cacheDir = '/vendor.example/dashboard_summary';

		if ($cache->initCache(300, $cacheId, $cacheDir))
		{
			return $cache->getVars();
		}

		if (!$cache->startDataCache())
		{
			return $this->buildSummary($userId);
		}

		try
		{
			$summary = $this->buildSummary($userId);
			$cache->endDataCache($summary);

			return $summary;
		}
		catch (\Throwable $exception)
		{
			$cache->abortDataCache();
			throw $exception;
		}
	}

	private function buildSummary(int $userId): array
	{
		return [
			'userId' => $userId,
			'total' => 42,
		];
	}
}
```

## Example 2

Показывает: `ManagedCache` для shared reference data, который приходит в provider через конструктор и затем используется для чтения и инвалидции.
Почему это good pattern: один и тот же key используется и для чтения, и для clean-path, а сам provider не тянет cache manager из `Application` внутри бизнес-методов.

```php
use Bitrix\Main\Data\ManagedCache;

final class DepartmentMapProvider
{
	private const CACHE_TTL = 3600;
	private const CACHE_KEY = 'vendor.example.department_map.v1';
	private const CACHE_DIR = 'vendor.example.department';

	public function __construct(
		private readonly ManagedCache $managedCache,
	)
	{
	}

	public function getAll(): array
	{
		if ($this->managedCache->read(self::CACHE_TTL, self::CACHE_KEY, self::CACHE_DIR))
		{
			return $this->managedCache->get(self::CACHE_KEY);
		}

		$items = $this->loadFromPrimarySource();
		$this->managedCache->set(self::CACHE_KEY, $items);

		return $items;
	}

	public function invalidate(): void
	{
		$this->managedCache->clean(self::CACHE_KEY, self::CACHE_DIR);
	}

	private function loadFromPrimarySource(): array
	{
		return [
			10 => ['id' => 10, 'name' => 'Sales'],
		];
	}
}
```

## Example 3

Показывает: `Cache` и `TaggedCache` в одном сценарии, где `TaggedCache` приходит в provider через конструктор, а payload хранится в обычном cache path.
Почему это good pattern: код не путает storage of data и registration of tags, не тянет `TaggedCache` из `Application` внутри метода и корректно сворачивает оба механизма на failure path.

```php
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Data\TaggedCache;

final class CompanyStructureProvider
{
	public function __construct(
		private readonly TaggedCache $taggedCache,
	)
	{
	}

	public function getByIblock(int $iblockId): array
	{
		$cache = Cache::createInstance();
		$cacheId = 'vendor.example.company_structure.v1.' . $iblockId;
		$cacheDir = '/vendor.example/company_structure';

		if ($cache->initCache(2592000, $cacheId, $cacheDir))
		{
			return $cache->getVars();
		}

		if (!$cache->startDataCache())
		{
			return $this->loadFromIblock($iblockId);
		}

		$this->taggedCache->startTagCache($cacheDir);

		try
		{
			$items = $this->loadFromIblock($iblockId);
			$this->taggedCache->registerTag('iblock_id_' . $iblockId);
			$this->taggedCache->endTagCache();
			$cache->endDataCache($items);

			return $items;
		}
		catch (\Throwable $exception)
		{
			$this->taggedCache->abortTagCache();
			$cache->abortDataCache();
			throw $exception;
		}
	}

	private function loadFromIblock(int $iblockId): array
	{
		return [
			['iblockId' => $iblockId, 'name' => 'Head Office'],
		];
	}
}
```

## Example 4

Показывает: допустимую legacy-границу в небольшом патче внутри старого component/admin-style кода.
Почему это допустимо: пример не делает legacy API новым default, а только признаёт, что маленький patch не обязан мигрировать весь окружающий файл на modern cache layer.

```php
<?php

global $CACHE_MANAGER;

$cache = new CPHPCache();
$cacheId = 'menu.sections.' . $iblockId;
$cacheDir = '/menu/sections';

if ($cache->InitCache(3600, $cacheId, $cacheDir))
{
	$sections = $cache->GetVars();
}
elseif ($cache->StartDataCache())
{
	$CACHE_MANAGER->StartTagCache($cacheDir);
	$CACHE_MANAGER->RegisterTag('iblock_id_' . $iblockId);
	$CACHE_MANAGER->EndTagCache();

	$sections = $this->loadSections($iblockId);
	$cache->EndDataCache($sections);
}
```

## Example 5

Показывает: service/provider создаётся через `ServiceLocator::get(PortalSnapshotCache::class)`, а `ManagedCache` и `TaggedCache` приезжают в его конструктор автоматически по FQCN из `main/.settings.php`.
Почему это good pattern: `ServiceLocator` создаёт сам provider, shared cache dependencies приходят через autowiring, а stateful `Cache` остаётся локальным инструментом конкретного метода.

```php
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Data\ManagedCache;
use Bitrix\Main\Data\TaggedCache;
use Bitrix\Main\DI\ServiceLocator;

final class PortalSnapshotCache
{
	public function __construct(
		private readonly ManagedCache $managedCache,
		private readonly TaggedCache $taggedCache,
	)
	{
	}

	public function rebuildForPortal(int $portalId): array
	{
		$this->managedCache->clean('vendor.example.portal_snapshot.' . $portalId, 'vendor.example.portal');

		$localCache = Cache::createInstance();
		$cacheId = 'vendor.example.portal_snapshot.payload.' . $portalId;
		$cacheDir = '/vendor.example/portal_snapshot';

		if ($localCache->initCache(600, $cacheId, $cacheDir))
		{
			return $localCache->getVars();
		}

		if ($localCache->startDataCache())
		{
			$this->taggedCache->startTagCache($cacheDir);
			$this->taggedCache->registerTag('portal_id_' . $portalId);
			$this->taggedCache->endTagCache();

			$payload = ['portalId' => $portalId];
			$localCache->endDataCache($payload);

			return $payload;
		}

		return ['portalId' => $portalId];
	}
}

$service = ServiceLocator::getInstance()->get(PortalSnapshotCache::class);
$payload = $service->rebuildForPortal($portalId);
```

## Example 6

Показывает: отдельный root-level path, где `ManagedCache` и `TaggedCache` достаются из контейнера напрямую, потому что код сам является composition root или внешним orchestration entry point.
Почему это good pattern: пример явно показывает container-native получение shared cache managers, но не переносит locator-вызовы внутрь самого provider и не делает `Cache::class` симметричным singleton-default.

```php
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Data\ManagedCache;
use Bitrix\Main\Data\TaggedCache;
use Bitrix\Main\DI\ServiceLocator;

$locator = ServiceLocator::getInstance();

$managedCache = $locator->get(ManagedCache::class);
$taggedCache = $locator->get(TaggedCache::class);

$managedCache->clean('vendor.example.portal_snapshot.42', 'vendor.example.portal');
$taggedCache->clearByTag('portal_id_42');

$localCache = Cache::createInstance();
```

## Legacy / Exceptions

- `CPHPCache` — thin wrapper над `Bitrix\Main\Data\Cache`, а `CCacheManager` / `$CACHE_MANAGER` — legacy wrapper над `ManagedCache` и `TaggedCache`; это compatibility boundary, а не preferred API для нового кода.
- В небольшом патче внутри legacy component/admin файла допустимо оставить окружающий `CPHPCache` / `$CACHE_MANAGER` style, если полная миграция файла не входит в задачу.
- `CStackCacheManager` может встречаться в старом ядре как отдельный legacy hot-path, но его не нужно использовать как образец для нового `lib/`-кода.
- `Application::getInstance()->getManagedCache()` и `getTaggedCache()` остаются валидными framework entry points; проблема начинается не в самом API, а когда container-created provider вручную тянет их внутрь вместо constructor DI.
- Container binding для `Cache::class` существует, но это не делает вызовы locator/Application внутри самого сервиса preferred path; для локального cache flow свежий instance через `createInstance()` обычно безопаснее и понятнее.

## Common Mistakes

- Вызывать `ManagedCache::set()` без предшествующего `read()` для того же key/table scope.
- Регистрировать теги без `startTagCache()` / `endTagCache()` или забывать `abortTagCache()` на failure path.
- Доставать `ManagedCache` или `TaggedCache` через `Application` / `ServiceLocator` внутри provider, который сам мог быть создан контейнером и получить эти зависимости через конструктор.
- Подменять flow-state cache-слоем только потому, что нужен key-value API с TTL.

## Checklist

- Данные действительно являются derived read-cache, а не runtime-state или постоянной настройкой?
- Для простого TTL-cache выбран `Cache`, а не `ManagedCache` или `TaggedCache` без дополнительной необходимости?
- Для shared registry-style cache выбран `ManagedCache`, если важны `uniqueId` / `tableId` и явная инвалидция?
- `TaggedCache` используется только вместе с обычным cache path и решает именно invalidation by tag, а не хранение payload?
- Для `Cache` заданы устойчивые `cacheId`, `cacheDir` и осознанный TTL?
- На failure path после `startDataCache()` вызывается `abortDataCache()`, если payload не должен быть сохранён?
- После изменения первичных данных есть явный `clean()` / `cleanDir()` / `clearByTag()` path?
- `ManagedCache` и `TaggedCache` не достаются вручную внутри provider, если сам provider может быть создан контейнером с autowiring?
- Если cache manager нужен в composition root или другом внешнем entry point, выбран понятный container-native path вроде `ServiceLocator::get(ManagedCache::class)` / `get(TaggedCache::class)`?
- Local `Cache` не насажен как безусловный shared singleton там, где коду нужен отдельный рабочий instance?
- Legacy API вроде `CPHPCache`, `CCacheManager` и `CStackCacheManager` не используются как новый default path вне явной legacy-границы?
