# Service Locator

## Boundaries

- Этот файл покрывает выбор между `Bitrix\Main\DI\ServiceLocator`, action autowiring и ручным `new` при получении shared service-зависимостей в Bitrix-коде.
- Этот файл покрывает `ServiceLocator::getInstance()`, `get()`, `has()`, `addInstance()`, `addInstanceLazy()` и регистрацию сервисов через `{module}/.settings.php`.
- Этот файл помогает решить, когда зависимость должна приходить из контейнера по FQCN или service id, а когда допустимо создать объект вручную.
- Этот файл не описывает controller lifecycle, filters и общие правила для `*Action()`; для них переходи в `rules/controller.md`.
- Этот файл не описывает выбор между `PersistentStorageInterface`, `Option` и cache; для этого переходи в `rules/persistent-storage.md`.
- Этот файл не описывает validation pipeline и `ValidationService` как отдельную тему; для этого переходи в `rules/validation.md`.

## Rules

- Для shared domain/application service по умолчанию не создавай экземпляр вручную через `new`, если зависимость может прийти через action autowiring, конструктор или `ServiceLocator::get(...)`.
- В controller default path — dependency в параметрах `*Action()` или через `AutoWire`, а не ручной вызов `new` внутри action.
- В service, command, agent или другом non-controller коде предпочитай constructor dependency; если нужен production default без явного composition root, получай его через `ServiceLocator::getInstance()->get(...)`.
- Используй `ServiceLocator::get(...)` для shared framework/module service, который уже зарегистрирован в `.settings.php` или может быть разрешён по FQCN через typed constructor dependencies.
- Для interface или abstract dependency регистрируй binding в `{module}/.settings.php`; не рассчитывай, что `ServiceLocator` сможет корректно создать такую зависимость без явной регистрации.
- Если сервису нужны scalar, array или runtime-specific constructor arguments без стабильного container binding, не делай bare `ServiceLocator::get(ClassName::class)` default path; используй factory, `constructorParams` в `.settings.php` или явный composition root.
- Предпочитай FQCN как service key, когда нужен один понятный контракт класса или интерфейса; string service id оставляй для legacy service names или для случаев, где именно строковый ключ является canonical API.
- Помни, что `ServiceLocator` по умолчанию кеширует созданный service как singleton; если по контракту нужен новый экземпляр на каждый `get()`, объявляй это явно через container configuration, а не через хаотичный возврат к ручному `new`.
- Не прячь `ServiceLocator::get(...)` в глубоко вложенный ad hoc helper только ради того, чтобы скрыть контейнерный вызов; точка получения shared dependency должна оставаться явной и предсказуемой.
- Не используй `addInstance()` и `addInstanceLazy()` как обычный runtime-механизм внутри прикладного кода; это bootstrap/composition-root инструменты для регистрации сервисов, а не замена нормального dependency flow.
- Не объявляй тотальный запрет на `new`: value object, DTO, response object, filter, decorator, factory result и другой короткоживущий объект с локальным runtime-смыслом нормально создаётся вручную.
- Если объект создаётся как сценарный результат factory и требует runtime-context вроде `Request`, `userId`, токена или внешнего payload, оставляй `new` внутри factory вместо натягивания такого объекта на `ServiceLocator`.
- Если модуль не загружен, его `.settings.php` registration path ещё не применён; не отделяй использование `ServiceLocator` от корректного module loading через `Loader`.

## Decision Guide

- Если зависимость нужна в `*Action()` и выражается как обычный framework/service dependency, передавай её параметром action.
- Если shared service нужен вне controller и dependency должна оставаться подменяемой в тестах, принимай её в конструктор, а production fallback получай через `ServiceLocator`.
- Если dependency выражена интерфейсом или требует нестандартной сборки, регистрируй её в `.settings.php`.
- Если объект краткоживущий и сам по себе не является shared service, создавай его через `new`.
- Если объект требует runtime-аргументы, которые контейнер не должен угадывать, используй factory или явную сборку вместо прямого `get(ClassName::class)`.

## Related Rules

- `rules/controller.md` — для тонкого controller action, action parameter autowiring и общих container-friendly правил controller layer.
- `rules/persistent-storage.md` — для сценариев, где `ServiceLocator` используется только как путь к `PersistentStorageInterface`, а главный вопрос состоит в выборе storage-механизма.
- `rules/validation.md` — для случаев, где `ServiceLocator` лишь достаёт `ValidationService`, а главный выбор касается validation attributes, DTO и `ValidationParameter`.

## Example 1

Показывает: default path для controller, где shared service приходит в `*Action()`, а не создаётся вручную.
Почему это good pattern: controller остаётся тонким, а зависимость получает framework-native lifecycle вместо ad hoc `new`.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\Json;

final class PortalController extends Controller
{
	public function syncAction(PortalRegistrySyncService $service, int $portalId): Json
	{
		$result = $service->sync($portalId);

		return new Json([
			'success' => $result->isSuccess(),
		]);
	}
}
```

## Example 2

Показывает: default path вне controller через constructor dependency с production fallback на `ServiceLocator`.
Почему это good pattern: сервис остаётся тестируемым и не зашивает ручное создание shared dependency в бизнес-метод.

```php
use Bitrix\Main\Data\Storage\PersistentStorageInterface;
use Bitrix\Main\Data\Storage\StorageInterface;
use Bitrix\Main\DI\ServiceLocator;

final class RecoverAccessRequestService
{
	private readonly StorageInterface $storage;

	public function __construct(?StorageInterface $storage = null)
	{
		$this->storage = $storage
			?? ServiceLocator::getInstance()->get(PersistentStorageInterface::class);
	}

	public function markSent(int $userId): void
	{
		$this->storage->set('intranet.recover_access.' . $userId, ['sent' => true], 3600);
	}
}
```

## Example 3

Показывает: registration path для interface dependency через `{module}/.settings.php`.
Почему это good pattern: контейнер получает явный binding и не пытается угадывать реализацию интерфейса во время `get()`.

```php
return [
	'services' => [
		'value' => [
			\Bitrix\Main\Data\Storage\PersistentStorageInterface::class => [
				'className' => \Bitrix\Main\Data\Storage\ConnectionBasedPersistentStorage::class,
			],
			'main.validation.service' => [
				'className' => \Bitrix\Main\Validation\ValidationService::class,
			],
		],
	],
];
```

## Example 4

Показывает: registration path для сервиса, который должен возвращать новый экземпляр на каждый `get()`.
Когда уместно: сервис хранит внутреннее состояние на время одной операции и не должен переиспользоваться как singleton между вызовами.
Почему это good pattern: non-singleton поведение объявляется в container configuration, а calling code остаётся на `ServiceLocator`, не откатываясь к ручному `new`.

```php
use Bitrix\Main\DI\ServiceLocator;

return [
	'services' => [
		'value' => [
			ImportChunkProcessor::class => [
				'className' => ImportChunkProcessor::class,
				'singleton' => false,
			],
		],
	],
];

$locator = ServiceLocator::getInstance();
$first = $locator->get(ImportChunkProcessor::class);
$second = $locator->get(ImportChunkProcessor::class);

// $first !== $second
```

## Example 5

Показывает: допустимое исключение, где `new` остаётся правильным путём.
Когда уместно: объект живёт локально, не является shared service и требует runtime-данные конкретного сценария.
Почему это good pattern: правило не превращает container в замену каждому локальному объекту.

```php
use Bitrix\Main\HttpRequest;

final class InviteScenarioFactory
{
	public function create(HttpRequest $request, int $userId): InviteScenario
	{
		$payload = new InvitePayload(
			email: (string)$request->getPost('email'),
			name: (string)$request->getPost('name'),
		);

		return new InviteScenario($payload, $userId);
	}
}
```

## Legacy / Exceptions

- String service id вроде `'main.validation.service'` допустим, если это уже устоявшийся framework API и FQCN не является единственным canonical key.
- Legacy singleton вроде `Application::getInstance()` или `EventManager::getInstance()` может встречаться как часть framework integration, но для нового shared service предпочитай `ServiceLocator` или action autowiring.
- `addInstance()` и `addInstanceLazy()` допустимы в bootstrap / registration code, но не как массовый паттерн прикладной логики.

## Checklist

- Shared service не создаётся вручную через `new`, если его можно получить через action autowiring, constructor dependency или `ServiceLocator`?
- Для controller dependency выбран action parameter / `AutoWire`, а не ad hoc создание внутри action?
- Для interface или abstract dependency есть явная регистрация в `.settings.php`?
- `ServiceLocator::get(ClassName::class)` не используется для класса с runtime-specific scalar или нетипизированными constructor arguments без отдельной registration/factory стратегии?
- `new` оставлен только для локальных short-lived объектов, value objects, response/filter objects, decorator/factory cases или других осознанных исключений?
- `addInstance()` и `addInstanceLazy()` не используются как обычная замена dependency flow в прикладном коде?
