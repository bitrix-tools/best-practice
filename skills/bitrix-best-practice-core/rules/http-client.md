# HttpClient

## Boundaries

- Этот файл покрывает outbound HTTP(S)-запросы и remote download/upload через `Bitrix\Main\Web\HttpClient` в прикладном Bitrix-коде.
- Этот файл помогает выбрать между `HttpClient`, `file_get_contents()` с HTTP wrapper, `stream_context_create()` для сети и прямыми `curl_*` вызовами.
- Этот файл не описывает local file I/O без `http`/`https` scheme; чтение файлов по path, `file_get_contents(__DIR__ . '/file.json')`, `is_file()`-сценарии и другие filesystem reads сюда не входят.
- Этот файл не описывает inbound request body и `php://input`; для них переходи в `rules/request.md`.
- Этот файл не подменяет `rules/uri.md`: `Uri` отвечает за разбор и модификацию URL, а `HttpClient` — за выполнение сетевого запроса к этому URL.

## Rules

- Для новых outbound HTTP(S)-интеграций по умолчанию используй `Bitrix\Main\Web\HttpClient`, а не `file_get_contents()` с URL, `stream_context_create()` для HTTP wrapper или прямые `curl_*` вызовы.
- Не считай smell любой `file_get_contents()`: правило применяется только когда аргумент является remote `http`/`https` URL или код явно строит HTTP-запрос.
- Для простого `GET` или `POST`, когда нужен только response body, предпочитай `get()` и `post()`.
- Используй `query()` вместо `get()` или `post()`, когда нужен другой HTTP-метод, отдельный контроль над `bool`-результатом запроса или работа через `HttpClient::HTTP_*` constants.
- Проверяй transport failure через `=== false` и `getError()`, а не только по пустой строке ответа.
- После успешного transport layer отдельно проверяй HTTP-статус через `getStatus()`, а не считай любой непустой body признаком успеха.
- Для внешних интеграций обычно задавай явные `setTimeout()` и `setStreamTimeout()` или соответствующие constructor options, а не полагайся молча на defaults.
- Для JSON API и других явных контрактов выставляй нужные headers через `setHeader()` или `setHeaders()`, а не собирай ad hoc curl-конфигурацию.
- Для HTTP Basic auth используй `setAuthorization()`, а для Bearer и других схем — явный `Authorization` header через `setHeader()`.
- Для download в файл предпочитай `download()` или `setOutputStream()`, а не ручную склейку `file_get_contents($url)` и `file_put_contents($path, ...)`.
- Если URL полностью или частично контролируется пользователем, включай SSRF-safe режим через `setPrivateIp(false)` и не отключай SSL verification без действительно доверенного internal boundary.
- `disableSslVerification()` или constructor option `disableSslVerification => true` используй только как узкое исключение для доверенной internal integration; это не default path для внешних HTTP(S)-запросов.
- Для `multipart/form-data` upload используй `post($url, $postData, true)`, если сценарий укладывается в API `HttpClient`, вместо ручного `curl_*` только ради multipart.
- Не создавай новый локальный transport-wrapper поверх `curl_*`, если задача решается штатным API `HttpClient`; сначала используй framework-native клиент, и только потом добавляй узкую обертку вокруг него при реальной потребности reuse.

## Decision Guide

- Если код делает outbound запрос к `http` или `https`, выбирай `HttpClient`.
- Если код только разбирает или модифицирует URL без реального запроса, переходи в `rules/uri.md`.
- Если код читает local file path или `php://input`, это не задача для `HttpClient`.
- Если нужен `GET`/`POST` с body string как основным результатом, используй `get()` или `post()`.
- Если нужен `HEAD`, `PUT`, `PATCH`, `DELETE`, custom method или `bool`-результат выполнения запроса, используй `query()`.
- Если нужен download в файл или streaming большого ответа, используй `download()` или `setOutputStream()`.

## Related Rules

- `rules/request.md` — для inbound request body, `php://input`, `JsonPayload` и чтения входящих данных текущего HTTP-запроса.
- `rules/uri.md` — для разбора, изменения и сборки URL до того, как этот URL будет передан в `HttpClient`.

## Example 1

Показывает: базовый рекомендуемый path для JSON API через `HttpClient` с timeout, headers и раздельной проверкой transport/HTTP failures.
Почему это good pattern: outbound HTTP остается framework-native, а код не путает `false`, HTTP error status и application-level payload.

```php
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

$httpClient = new HttpClient([
	'socketTimeout' => 5,
	'streamTimeout' => 10,
]);
$httpClient->setHeader('Content-Type', 'application/json');
$httpClient->setHeader('Authorization', 'Bearer ' . $token);

$responseBody = $httpClient->post(
	'https://api.example.com/v1/events',
	Json::encode([
		'event' => 'invite.accepted',
		'userId' => $userId,
	])
);

if ($responseBody === false)
{
	throw new \RuntimeException(implode('; ', $httpClient->getError()));
}

if ($httpClient->getStatus() !== 200)
{
	throw new \RuntimeException('Unexpected status: ' . $httpClient->getStatus());
}

$payload = Json::decode($responseBody);
```

## Example 2

Показывает: замена network-only `file_get_contents($url)` на `HttpClient`.
Когда уместно: переменная содержит remote `http`/`https` URL, а не локальный путь к файлу.
Почему это good pattern: код получает timeout'ы, status/error inspection и не превращает HTTP wrapper в скрытый transport layer.

```php
use Bitrix\Main\Web\HttpClient;

$httpClient = new HttpClient();
$httpClient->setTimeout(3);
$httpClient->setStreamTimeout(5);
$httpClient->setPrivateIp(false);

$imageBody = $httpClient->get($imageUrl);
if ($imageBody === false)
{
	throw new \RuntimeException(implode('; ', $httpClient->getError()));
}

if ($httpClient->getStatus() !== 200)
{
	throw new \RuntimeException('Image request failed with status ' . $httpClient->getStatus());
}

// Для local path вроде __DIR__ . '/icon.png' это правило не применяется:
// там остается обычный filesystem API, а не HttpClient.
```

## Example 3

Показывает: замена raw `curl_*` на `HttpClient` для download-сценария.
Почему это good pattern: network transport, timeout'ы и файл назначения описываются штатным API без ручного curl setup и `file_put_contents()`.

```php
use Bitrix\Main\Web\HttpClient;

$httpClient = new HttpClient([
	'socketTimeout' => 10,
	'streamTimeout' => 30,
]);
$httpClient->setHeader('User-Agent', 'Bitrix Sync Bot/1.0');

$downloaded = $httpClient->download(
	'https://downloads.example.com/archive.zip',
	$targetFilePath
);

if (!$downloaded)
{
	throw new \RuntimeException(implode('; ', $httpClient->getError()));
}

if ($httpClient->getStatus() < 200 || $httpClient->getStatus() >= 300)
{
	throw new \RuntimeException('Unexpected status: ' . $httpClient->getStatus());
}
```

## Example 4

Показывает: узкое исключение для local file I/O, где `HttpClient` не нужен.
Когда уместно: код читает файл по filesystem path, а не делает outbound HTTP request.
Почему это допустимо: rule-file явно ограничен remote HTTP(S)-запросами и не должен ломать обычное чтение локальных файлов.

```php
$configJson = file_get_contents(__DIR__ . '/config/schema.json');
if ($configJson === false)
{
	throw new \RuntimeException('Cannot read local schema file');
}
```

## Legacy / Exceptions

- Не переписывай `vendor/` или встроенные third-party SDK только ради приведения их transport layer к `HttpClient`, если задача не про поддержку локального форка.
- `disableSslVerification()` не становится нормой только потому, что legacy-код уже использует self-signed endpoint.
- Если existing abstraction уже инкапсулирует `HttpClient`, переиспользуй ее вместо создания нового ad hoc клиента в каждом месте.

## Checklist

- Код действительно делает outbound `http`/`https` запрос, а не local file read или чтение `php://input`?
- Для нового или изменяемого сетевого запроса выбран `Bitrix\Main\Web\HttpClient`, а не `file_get_contents($url)`, `stream_context_create()` или raw `curl_*`?
- Между `get()` / `post()` и `query()` сделан осознанный выбор по сценарию запроса?
- Transport failure проверяется через `=== false` и `getError()`, а HTTP outcome отдельно через `getStatus()`?
- Для внешней интеграции заданы осознанные timeout'ы, если запрос может зависнуть или быть медленным?
- Для user-controlled URL включен SSRF-safe path через `setPrivateIp(false)`?
- `disableSslVerification()` не используется как удобный default для внешних запросов?
- Local file I/O и inbound request body не затянуты в это правило по ошибке?
