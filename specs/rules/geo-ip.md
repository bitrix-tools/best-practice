# GeoIp

## Boundaries

- Этот файл покрывает `Bitrix\Main\Service\GeoIp\Manager` как framework-native facade для GeoIP lookup, его convenience getters, `getDataResult()`, `getRealIp()`, handler chain, cache и GeoIP-specific event hooks.
- Этот файл также покрывает `Bitrix\Main\Web\IpAddress` только в контексте GeoIP lookup, range cache и различий между IPv4 и IPv6 в `Manager`.

## Rules

- Используй `Bitrix\Main\Service\GeoIp\Manager` как canonical entry point для GeoIP lookup, а не дергай built-in handler-классы напрямую.
- Выбирай convenience getters (`getCountryCode()`, `getCountryName()`, `getCityName()`, `getTimezoneName()` и похожие) только когда вызывающему нужен один атрибут и допустима fallback-семантика пустой строки.
- Выбирай `Manager::getGeoPosition()` только когда вызывающему нужна именно пара `latitude`/`longitude` и допустим `null` вместо частично заполненного результата.
- Используй `Manager::getDataResult()` когда нужны несколько geo-полей сразу, `isSuccess()`, metadata результата, `handlerClass` или точечный `$required` filter по полям.
- Передавай явный `$ip` в `Manager`, если lookup делается не для текущего запроса. Не полагайся на пустой `$ip`, если сценарий работает с сохраненным, проксированным или чужим адресом.
- Используй пустой `$ip` только как осознанный shorthand для current-request scenario, где `Manager` сам должен перейти в `getRealIp()`.
- Используй `$required` в `getDataResult()` как способ отфильтровать handler'ы по `ProvidingData`, если сценарий зависит от конкретных полей вроде `cityGeonameId`, `latitude` или `timezone`.
- Не запрашивай `getDataResult()` без `$required`, если downstream-коду нужен один редкий field и отсутствие этого field должно сразу исключать handler.
- Учитывай разные miss-семантики: `getDataResult()` возвращает `null`, convenience getters обычно возвращают `''`, а `getGeoPosition()` возвращает `null`.
- Проверяй `if ($result && $result->isSuccess())` перед чтением geo-данных из `getDataResult()`. Не разыменовывай результат как будто он гарантированно существует.
- Не предполагай одинаковое поведение для IPv4 и IPv6. In-request cache работает для обоих, но `ManagedCache` path в `Manager` ограничен IPv4.
- Если сценарий чувствителен к источнику client IP, используй `Manager::getRealIp()` осознанно: метод сначала берет первый public IPv4 из `HTTP_X_FORWARDED_FOR`, а затем fallback'ится к `getRemoteAddress()`.
- Не дублируй вокруг `Manager` собственный ad hoc cache для тех же lookup-данных, пока нет отдельного обоснованного product-level cache boundary.
- Для сброса GeoIP cache используй `Manager::cleanCache()` или каскад через `HandlerTable`, а не ручную invalidation-логику по директории `geoip_manager`.
- Подключай custom GeoIP provider через `onMainGeoIpHandlersBuildList` и класс-наследник `Bitrix\Main\Service\GeoIp\Base`, а не через локальный bypass `Manager`.
- Используй `onGeoIpGetResult` только для post-processing уже полученного geo-результата. Не подменяй этим событием полноценный handler, если данные нужно получать из нового провайдера.

## Decision Guide

- Если нужен один строковый атрибут и пустая строка на miss допустима, используй convenience getter.
- Если нужны несколько полей, проверка `isSuccess()` или понимание, какой handler отдал данные, используй `getDataResult()`.
- Если lookup относится к текущему HTTP-клиенту, допустим пустой `$ip` и путь через `getRealIp()`.
- Если lookup относится к конкретному адресу из БД, события, captcha-flow или внешнего payload, передавай этот `$ip` явно.
- Если задача не про GeoIP facade, а про чтение запроса, scope options или общий cache-vs-storage выбор, переходи в соседние rules вместо расширения этого файла.

## Example 1

Показывает: default path для простого lookup одного поля через convenience getter.
Почему это good pattern: код использует `Manager` как facade и не тащит в обычный сценарий лишнюю работу с `Result`, если достаточно country code с пустой строкой на miss.

```php
use Bitrix\Main\Service\GeoIp\Manager;

final class RegistrationRegionResolver
{
	public function resolveCountryCode(): string
	{
		$countryCode = Manager::getCountryCode();

		return $countryCode !== '' ? $countryCode : 'unknown';
	}
}
```

## Example 2

Показывает: explicit-IP lookup через `getDataResult()` с `$required` и безопасной проверкой `null` / `isSuccess()`.
Почему это good pattern: сценарий не зависит от current request IP, а `cityGeonameId` явно входит в required contract.

```php
use Bitrix\Main\Service\GeoIp\Manager;

final class DeviceLocationResolver
{
	public function resolve(string $ip): ?array
	{
		$result = Manager::getDataResult($ip, '', ['cityGeonameId']);
		if (!$result || !$result->isSuccess())
		{
			return null;
		}

		$data = $result->getGeoData();

		return [
			'cityGeonameId' => $data->cityGeonameId,
			'cityName' => $data->cityName,
			'handlerClass' => $data->handlerClass,
		];
	}
}
```

## Example 3

Показывает: extension path для custom GeoIP handler через `onMainGeoIpHandlersBuildList`.
Когда уместно: `Manager` должен остаться единой facade, но данные нужно получать из нового провайдера.
Почему это good pattern: новый provider подключается в handler chain и не обходит встроенный cache/events lifecycle `Manager`.

```php
use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;

EventManager::getInstance()->addEventHandler(
	'main',
	'onMainGeoIpHandlersBuildList',
	static function (): EventResult {
		return new EventResult(EventResult::SUCCESS, [
			'Vendor\\Geo\\PortalGeoHandler' => 'local/lib/Geo/PortalGeoHandler.php',
		]);
	},
);
```

## Legacy / Exceptions

- `Manager::getRealIp()` предпочитает первый public IPv4 из `HTTP_X_FORWARDED_FOR`, но fallback в `getRemoteAddress()` может вернуть и не-IPv4 адрес; не считай docblock гарантией одинаковой cache-semantics для всех IP.
- `Option::get('main', 'collect_geonames', 'N')` и `Configuration::getValue('cache_flags')` влияют на side effects `Manager`, но выбор этих механизмов не делает `Option` или `ManagedCache` основным API текущего rule-файла.
- Прямой вызов конкретного handler-класса допустим только внутри самого handler implementation, admin/config code или очень узкого legacy boundary; для обычного прикладного кода это не default path.

## Common Mistakes

- Не вызывать handler-классы напрямую там, где достаточно `Manager`.
- Не разыменовывать результат `getDataResult()` без проверки на `null` и `isSuccess()`.
- Не считать IPv6 lookup полностью эквивалентным IPv4 по cache path только потому, что оба адреса принимает один и тот же facade.

## Checklist

- Для GeoIP lookup выбран `Bitrix\Main\Service\GeoIp\Manager`, а не прямой вызов конкретного handler-класса?
- Между convenience getter, `getGeoPosition()` и `getDataResult()` сделан осознанный выбор по return-semantics и объему данных?
- Если lookup выполняется не для текущего запроса, `$ip` передан явно, а не оставлен пустым по инерции?
- Если downstream-коду нужен конкретный geo-field, `$required` используется как часть contract, а не ignored by default?
- Результат `getDataResult()` проверяется на `null` и `isSuccess()` до чтения geo-данных?
- В коде не предполагается одинаковый `ManagedCache` path для IPv4 и IPv6?
- Custom provider подключается через `onMainGeoIpHandlersBuildList` и `Base`, а post-processing через `onGeoIpGetResult` не подменяет собой новый handler?
- Границы с `request`, `option`, `persistent-storage` и `loader` сохранены без дублирования соседних правил?
