---
name: bitrix-best-practice-core
description: Use when designing, reviewing, or implementing any PHP code.
---

# Bitrix Core Best Practice

Скилл помогает понять, какие нужно использовать Bitrix/PHP best practices.

## Как использовать

1. Определи архитектурный слой, который затрагивает задача.
2. Открой только те rule-файлы, которые напрямую относятся к этому слою.
3. Сначала следуй более строгим правилам репозитория и ограничениям модуля.
4. Предпочитай framework-native паттерны Bitrix вместо собственных абстракций.

## Выбор rule-файла

<!-- rules-dictionary:start -->

### Когда читать `rules/controller.md`

Читай `rules/controller.md`, если задача затрагивает хотя бы одну из этих областей:

- класс, наследующий `Bitrix\Main\Engine\Controller` или его наследника;
- любой `*Action()`-метод;
- filters, attributes, prefilters и ответы Engine Controller.

### Когда читать `rules/error.md`

Читай `rules/error.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Error`, `Bitrix\Main\ErrorCollection` или прикладные error-классы поверх них;
- `getErrors()`, `getError()`, `getErrorCollection()` или `getErrorByCode()` в service, controller или response flow;
- выбор `code`, `customData` и публичного error-contract для UI, AJAX или другого клиента;
- перенос уже созданных ошибок между `Result`, controller lifecycle и `AjaxJson`.

### Когда читать `rules/result.md`

Читай `rules/result.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Result`, `isSuccess()`, `setData()`, `getData()`, `addError()` или `addErrors()`;
- возврат `Result` из service, command, handler или integration layer как outcome-contract;
- выбор между `Bitrix\Main\Result`, самодельным `*Result`-классом и неявным массивом как return DTO;
- состав payload в `Result::setData()` и граница между success-data и error flow.

### Когда читать `rules/request.md`

Читай `rules/request.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Request`, `HttpRequest`, `$this->getRequest()` или `Context::getCurrent()->getRequest()`;
- выбор между `get()`, `getQuery()`, `getPost()`, `getHeader()`, `getCookie()` или `getJsonList()`;
- замена `$_REQUEST`, `$_GET`, `$_POST`, `$_COOKIE` и `php://input` на framework-native request API;
- JSON body, `JsonPayload`, `decodeJson()` или `decodeJsonStrict()`.

### Когда читать `rules/response.md`

Читай `rules/response.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Response`, `HttpResponse`, `addHeader()`, `setStatus()`, `addCookie()` или `redirectTo()`;
- `Bitrix\Main\Engine\Response\Json`, `AjaxJson`, `Redirect`, `File`, `HtmlContent` или render-response helper'ы;
- замена ручного `header()`, `Set-Cookie`, `setcookie()` или `json_encode()` на штатный response layer Bitrix;
- выбор типа HTTP-ответа для controller action или другого infrastructure endpoint.

### Когда читать `rules/routing.md`

Читай `rules/routing.md`, если задача затрагивает хотя бы одну из этих областей:

- файл в `<module>/install/routes/` или регистрация маршрутов в `/bitrix/routes/` и `/local/routes/`;
- `RoutingConfigurator`, `prefix`, `group`, HTTP-методы маршрута, `where`, `default`, `name`;
- `PublicPageController` или перенос legacy URL с `urlrewrite.php` на modern routing;
- site-guard и маршруты для конкретного сайта в мультисайтовой установке;
- массив `[Controller::class, 'action']` в маршруте.

### Когда читать `rules/loader.md`

Читай `rules/loader.md`, если задача затрагивает хотя бы одну из этих областей:

- `Loader::includeModule()` или `Loader::requireModule()`;
- `CModule::IncludeModule()` или `CModule::IncludeModuleEx()`;
- optional module integration с fallback при отсутствии модуля;
- fail-fast dependency, где отсутствие модуля должно привести к ошибке, а не к тихому пропуску.

### Когда читать `rules/geo-ip.md`

Читай `rules/geo-ip.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Service\GeoIp\Manager`, `getRealIp()`, `getDataResult()` или convenience getters вроде `getCountryCode()` / `getCityName()`;
- `Bitrix\Main\Web\IpAddress` в контексте GeoIP lookup, range cache или различий между IPv4 и IPv6 для geodata;
- custom GeoIP handler, наследник `Bitrix\Main\Service\GeoIp\Base` или регистрация через `onMainGeoIpHandlersBuildList`;
- post-processing GeoIP результата через `onGeoIpGetResult`;
- выбор между простым string lookup и полным `Result`-based GeoIP lookup.
- определение геолокации

### Когда читать `rules/uri.md`

Читай `rules/uri.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Web\Uri`, `new Uri($url)`, `getQuery()`, `addParams()`, `deleteParams()`, `toAbsolute()` или `resolveRelativeUri()`;
- разбор, изменение или пересборка URL / URI / redirect URL в Bitrix-коде;
- выбор между `Uri`, `parse_url()` и `parse_str()` для query string, host, path, fragment или absolute URL;
- query-параметры с точками или пробелами, где важен `preserveDots`.

### Когда читать `rules/http-client.md`

Читай `rules/http-client.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Web\HttpClient`, `new HttpClient()`, `get()`, `post()`, `query()` или `download()`;
- outbound HTTP(S)-запросы, webhook sender, remote download/upload или external API integration;
- замена `file_get_contents($url)` / `stream_context_create()` для remote `http`/`https` URL;
- замена `curl_init`, `curl_setopt`, `curl_exec` и других raw `curl_*` вызовов на framework-native transport.

### Когда читать `rules/jwt.md`

Читай `rules/jwt.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Web\JWT`, `JWT::encode()`, `JWT::decode()`, `JWT::urlsafeB64Encode()` или `JWT::urlsafeB64Decode()`;
- `Bitrix\Main\Web\JWK`, `JWK::parseKeySet()` или `JWK::parseKey()`;
- выпуск, проверка или разбор JWT / JWK / JWKS / JOSE-compatible данных;
- выбор между framework-native JWT/JWK API и ручной сборкой токена, key parsing или Base64 URL-safe helper-ом.

### Когда читать `rules/date-time.md`

Читай `rules/date-time.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Type\Date` или `Bitrix\Main\Type\DateTime`;
- `createFromUserTime()`, `tryParse()`, `toUserTime()`, `toString()`, `createFromTimestamp()` или `createFromPhp()`;
- parsing, formatting или timestamp conversion для даты и времени в Bitrix-коде;
- выбор между Bitrix date types и `\DateTime` / `\DateTimeImmutable`.

### Когда читать `rules/option.md`

Читай `rules/option.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Config\Option`, `Option::get()`, `set()`, `getRealValue()`, `getForModule()` или `delete()`;
- `COption::GetOptionString()`, `SetOptionString()`, `GetOptionInt()` или `RemoveOption()` как legacy trigger;
- `default_option.php`, module `options.php`, site-specific setting или feature flag / policy в БД;
- выбор между постоянной конфигурацией в `Option`, deploy-time config в `.settings.php` и временным runtime-state.

### Когда читать `rules/logger.md`

Читай `rules/logger.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Diag\Logger`, `Bitrix\Main\Diag\LoggerFactory`, `LoggerRegistry`, `FileLogger` или `LogFormatter`;
- `Psr\Log\LoggerInterface`, PSR-3 levels (`info`, `warning`, `error`, `debug`) и structured `context`;
- регистрацию logger id в `.settings.php` через секцию `loggers` или DI через `constructorParams`;
- замену `AddMessage2Log()`, `Logger::create()` или ad hoc `file_put_contents()` / `error_log()` на framework-native logging path;
- выбор между именованным logger id, default logger fallback и legacy logging boundary.

### Когда читать `rules/uuid-generator.md`

Читай `rules/uuid-generator.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\UuidGenerator` или `UuidGenerator::generateV4()`;
- генерацию UUID v4 для session id, correlation id, upload token, public proxy id или другого random opaque identifier;
- выбор между `UuidGenerator`, `uniqid()`, `Random::getBytes()`, ручной сборкой UUID или локальным helper-генератором;
- legacy boundary, где нужен UUID в обертке вроде `{uuid}`, но canonical generator должен остаться единым.

### Когда читать `rules/validation.md`

Читай `rules/validation.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Validation\Rule\...` на параметрах `*Action()` или свойствах input object;
- `ValidationParameter`, `ValidationService`, `ValidationResult`, `ValidationError` или `ValidationGroup`;
- автоматическая валидация входа до входа в action или ручная валидация DTO / command в service layer;
- выбор между validation attributes, `ValidationParameter` и явным `ValidationService::validate()`;
- custom validators и custom validation attributes поверх `Bitrix\Main\Validation`.

### Когда читать `rules/service-locator.md`

Читай `rules/service-locator.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\DI\ServiceLocator`, `ServiceLocator::getInstance()`, `get()`, `has()`, `addInstance()` или `addInstanceLazy()`;
- `{module}/.settings.php`, service registration, service id, FQCN binding или interface binding для DI;
- выбор между action autowiring, explicit `ServiceLocator::get(...)` и ручным `new MyService()` для shared service;
- замена ad hoc создания service-класса на framework-native container path.

### Когда читать `rules/persistent-storage.md`

Читай `rules/persistent-storage.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Data\Storage\PersistentStorageInterface`, `StorageInterface`, `DeferredStorageDecorator` или `ServiceLocator::get(PersistentStorageInterface::class)`;
- `Bitrix\Main\Config\Option::get()` / `Option::set()` в сценарии, где нужно понять, конфигурация это или временное runtime-state;
- TTL state, progress/checkpoint, one-time token, upload/import session, rate-limit counter или другой временный server-side state между запросами;
- выбор между `Option`, persistent storage и cache (`Bitrix\Main\Data\Cache` / `ManagedCache`) для хранения данных.

### Когда читать `rules/cache.md`

Читай `rules/cache.md`, если задача затрагивает хотя бы одну из этих областей:

- `Bitrix\Main\Data\Cache`, `ManagedCache`, `TaggedCache`, `Cache::createInstance()`, `initCache()`, `startDataCache()`, `endDataCache()` или `abortDataCache()`;
- `Application::getInstance()->getCache()`, `getManagedCache()`, `getTaggedCache()` или container binding cache-сервисов в `main/.settings.php`;
- выбор между простым TTL-cache, managed invalidation по key/dir и tag-based invalidation;
- `CPHPCache`, `CCacheManager`, `$CACHE_MANAGER` или `CStackCacheManager` как legacy trigger;
- derived read-cache, который можно потерять и пересчитать, в отличие от runtime-state и постоянной конфигурации.

<!-- rules-dictionary:end -->
