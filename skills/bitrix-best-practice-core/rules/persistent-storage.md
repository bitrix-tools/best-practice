# Persistent Storage

## Boundaries

- Этот файл покрывает выбор между `Bitrix\Main\Data\Storage\PersistentStorageInterface` и `Bitrix\Main\Config\Option`, когда коду нужно хранить server-side runtime-state между запросами.
- Этот файл также даёт короткую decision-границу с `Bitrix\Main\Data\Cache` и `Bitrix\Main\Data\ManagedCache`, чтобы не смешивать временное состояние сценария с performance cache.
- Этот файл не описывает session storage, client-server signing и маршрутизацию; для них переходи в соответствующие правила или repo-specific security rules.

## Rules

- Используй `PersistentStorageInterface`, когда данные должны пережить запрос, но по смыслу являются краткоживущим состоянием сценария, а не постоянной настройкой продукта.
- Используй `Option` только для конфигурации модуля или портала: `default_option.php`, `options.php`, site-specific setting, feature flag или product-policy без TTL.
- Не сохраняй временный флаг, checkpoint, runtime timestamp, one-time token, upload/import session или rate-limit state в `Option`, если значение должно естественно истечь.
- Для записи в `PersistentStorageInterface` всегда задавай явный TTL; `set()` без положительного TTL не является допустимым default path.
- Используй `PersistentStorageInterface`, если значение само является источником истины для временного поведения сценария: отсутствие записи должно менять бизнес-ветку, а не только замедлять код.
- Используй `Cache` или `ManagedCache`, если данные можно безопасно потерять и пересчитать из первичного источника без изменения бизнес-смысла сценария.
- Не переводи derived read-cache в `PersistentStorageInterface` только потому, что оба механизма похожи на key-value storage; cache остаётся механизмом ускорения чтения, а не авторитетным runtime-state.
- Для ключей в `PersistentStorageInterface` используй namespaced pattern вроде `{module}.{feature}.{entityId}` или другой устойчивый доменный префикс, а не короткие глобальные имена без контекста.
- Если runtime-state имеет высокую кардинальность или привязан к пользователю, токену, сессии загрузки, шагу фоновой задачи или конкретной операции, это дополнительный сигнал в пользу `PersistentStorageInterface`, а не `Option`.
- Предпочитай JSON-safe payload для `PersistentStorageInterface`: скаляры, массивы и простые структуры, которые естественно живут в `JsonField`, а не сложные объекты с неявной сериализацией.
- Если сервис должен оставаться тестируемым, принимай `StorageInterface` в конструктор, а production default получай через `PersistentStorageInterface` из `ServiceLocator`.
- Используй `DeferredStorageDecorator`, когда в рамках одного request lifecycle нужно накапливать несколько записей в storage и сбрасывать их батчем, а не ходить в backend после каждого `set()`.
- Не используй `clear()` как общий способ уборки временного состояния; для `PersistentStorageInterface` корректный путь — точечный `delete()` или `deleteMultiple()` по своим ключам.
- Если сценарий требует stateless round-trip защиту от подмены на клиенте, не подменяй её `PersistentStorageInterface`; server-side storage и signing решают разные задачи.

## Decision Guide

- Если значение является постоянной настройкой продукта или модуля, используй `Option`.
- Если значение временное, должно пережить запрос и влияет на flow-state сценария, используй `PersistentStorageInterface`.
- Если потерю значения можно безопасно пережить и просто пересчитать данные заново, используй `Cache` или `ManagedCache`.
- Если данные нужны только в рамках request batching и дальше всё равно должны уйти в persistent storage, используй `DeferredStorageDecorator` поверх `PersistentStorageInterface`.

## Related Rules

- `rules/option.md` — для постоянной конфигурации модуля или портала через `Bitrix\Main\Config\Option`, `default_option.php` и site-specific options.

## Example 1

Показывает: базовый рекомендуемый путь для временного server-side state, который переживает запрос и естественно истекает.
Почему это good pattern: сервис хранит runtime-флаг в storage с TTL, а не загрязняет `Option` временной информацией и остаётся тестируемым через `StorageInterface`.

```php
use Bitrix\Main\Data\Storage\PersistentStorageInterface;
use Bitrix\Main\Data\Storage\StorageInterface;
use Bitrix\Main\DI\ServiceLocator;

final class RecoverAccessRequestService
{
	private readonly StorageInterface $storage;

	public function __construct(?StorageInterface $storage = null)
	{
		$this->storage = $storage ?? ServiceLocator::getInstance()->get(PersistentStorageInterface::class);
	}

	public function markSent(int $userId): void
	{
		$this->storage->set(
			'intranet.otp.recover_access_request.' . $userId,
			['sentAt' => time()],
			3600,
		);
	}
}
```

## Example 2

Показывает: соседний сценарий, где в одном коде уместны и `Option`, и `PersistentStorageInterface`, но для разных смыслов.
Почему это good pattern: постоянная настройка шага выполнения остаётся в `Option`, а прерываемый progress/checkpoint живёт в storage с TTL.

```php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Storage\PersistentStorageInterface;
use Bitrix\Main\DI\ServiceLocator;

final class ReindexRunner
{
	public function run(bool $continue): void
	{
		$storage = ServiceLocator::getInstance()->get(PersistentStorageInterface::class);
		$maxExecutionTime = (int)Option::get('search', 'max_execution_time');

		$step = $continue ? $storage->get('search_reindex', []) : [];

		$step = $this->runChunk($step, $maxExecutionTime);
		$storage->set('search_reindex', $step, 86400);
	}

	private function runChunk(array $step, int $maxExecutionTime): array
	{
		return $step;
	}
}
```

## Example 3

Показывает: batching нескольких записей в рамках одного request через `DeferredStorageDecorator`.
Почему это good pattern: storage остаётся authoritative местом хранения, но код не делает лишний backend round-trip после каждого изменения контекста.

```php
use Bitrix\Main\Data\Storage\StorageInterface;
use Bitrix\Main\Data\Storage\DeferredStorageDecorator;
use Bitrix\Main\Data\Storage\PersistentStorageInterface;
use Bitrix\Main\DI\ServiceLocator;

final class UploadSessionContext
{
	private StorageInterface $storage;

	public function __construct()
	{
		$this->storage = new DeferredStorageDecorator(
			ServiceLocator::getInstance()->get(PersistentStorageInterface::class),
		);
	}

	public function save(string $sessionId, array $context): void
	{
		$this->storage->set(
			'main.upload_session.' . $sessionId,
			$context,
			3 * 24 * 3600,
		);
	}
}
```

## Example 4

Показывает: соседний случай, где нужен именно cache, а не persistent runtime-state.
Почему это good pattern: значение можно безопасно пересчитать из первичного источника, поэтому cache используется как ускорение чтения, а не как источник истины для сценария.

```php
use Bitrix\Main\Data\Cache;

final class CounterPresenter
{
	public function getSummary(int $userId): array
	{
		$cache = Cache::createInstance();
		$cacheId = 'dashboard.summary.' . $userId;
		$cacheDir = '/dashboard/summary';

		if ($cache->initCache(300, $cacheId, $cacheDir))
		{
			return $cache->getVars();
		}

		$summary = $this->buildSummary($userId);
		if ($cache->startDataCache())
		{
			$cache->endDataCache($summary);
		}

		return $summary;
	}

	private function buildSummary(int $userId): array
	{
		return ['userId' => $userId];
	}
}
```

## Checklist

- Временное server-side состояние, которое должно истечь, хранится в `PersistentStorageInterface`, а не в `Option`?
- `Option` используется только для постоянной конфигурации, feature flags, product-policy или site-specific settings без TTL?
- Для `PersistentStorageInterface` задан явный положительный TTL, а не неявное бессрочное хранение?
- Cache или `ManagedCache` не используется как источник истины для flow-state, если потеря значения меняет поведение сценария?
- `PersistentStorageInterface` не используется вместо cache там, где значение можно безопасно пересчитать из первичного источника?
- Ключи storage namespaced и выражают доменный контекст, а не являются короткими глобальными строками без префикса?
- Payload для storage остаётся JSON-safe и не тащит в `JsonField` сложные объекты с неявной сериализацией?
- Для request batching выбран `DeferredStorageDecorator`, если код делает много локальных `set()` перед финальным сохранением?
