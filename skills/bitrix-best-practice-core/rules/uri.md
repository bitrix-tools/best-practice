# Uri

## Boundaries

- Этот файл покрывает `Bitrix\Main\Web\Uri` для разбора, чтения частей, изменения и пересборки URL/URI-строк в прикладном Bitrix-коде.
- Этот файл помогает выбрать между `Uri` и низкоуровневыми функциями вроде `parse_url()` и `parse_str()`, когда код работает с query string, host, path, fragment или абсолютным URL.
- Этот файл не описывает объявление HTTP-маршрутов и `RoutingConfigurator`; для этого переходи в `rules/routing.md`.

## Rules

- Если код работает с полным URL или URI и должен прочитать, изменить или пересобрать его части, по умолчанию используй `Bitrix\Main\Web\Uri`, а не ручную связку `parse_url()` и конкатенации строк.
- Используй `new Uri($url)` как точку входа, когда нужно безопасно взять `host`, `path`, `query`, `fragment`, `scheme` или собрать строку обратно через `(string)$uri`, `getLocator()` или `getUri()`.
- Для изменения query-параметров предпочитай `addParams()` и `deleteParams()`, а не ручной разбор строки через `explode('&', ...)`, `preg_replace()` или собственную склейку `?foo=...&bar=...`.
- Если нужно сохранить имена query-параметров с точками или пробелами, передавай `preserveDots: true` в `addParams()` или `deleteParams()`, а не полагайся на обычный `parse_str()` по умолчанию.
- Используй `toAbsolute()`, когда относительный URI нужно привести к абсолютному в контексте текущего запроса или известного host.
- Используй `resolveRelativeUri()` для нормального объединения относительного URI с base URI вместо ручной склейки base path и относительного пути.
- Используй `convertToPunycode()` или `convertToUnicode()`, если задача затрагивает IDN-host или сравнение/нормализацию домена в разных представлениях.
- Используй `isPathTraversal()`, если код валидирует пользовательский URI/path на попытки path traversal и логика должна опираться на framework-class, а не на ad hoc regex в каждом месте.
- Не навязывай `Uri`, если входные данные уже не являются URL, а представляют собой только raw query string или `application/x-www-form-urlencoded` payload без остальных частей URI.
- `parse_str()` допустим только как узкое исключение после `getQuery()` или для query-only payload, когда коду нужен именно массив параметров, а не объект для дальнейшей работы с URL.
- Не обещай от `Uri` публичного API, которого у него нет: у класса нет публичного `getQueryParams()` или другой штатной выдачи query как массива, поэтому чтение вложенной query-структуры может требовать отдельного `parse_str()`.

## Decision Guide

- Если нужно изменить URL и вернуть его обратно строкой, используй `Uri`.
- Если нужно только прочитать `host`, `path`, `query`, `fragment` или сделать URL абсолютным, используй `Uri`.
- Если нужен массив query-параметров для доменной логики, сначала возьми query через `Uri`, а потом локально примени `parse_str()` как exception-path.
- Если вход изначально является только строкой вида `foo=1&bar=2` и не несет остальных частей URI, допустимо работать без `Uri`.

## Example 1

Показывает: default path для работы с redirect URL через `Uri`.
Почему это good pattern: URL разбирается и пересобирается одним framework-объектом без ручной склейки query string.

```php
use Bitrix\Main\Web\Uri;

$redirectUri = new Uri('/company/personal/user/15/?tab=tasks');
$redirectUri->addParams([
	'from' => 'invite',
	'success' => 'Y',
]);

$url = (string)$redirectUri;
```

## Example 2

Показывает: изменение query-параметров с сохранением имен, в которых есть точки.
Почему это good pattern: `Uri` покрывает не только разбор URL, но и безопасную модификацию query без ручного regex или string replace.

```php
use Bitrix\Main\Web\Uri;

$uri = new Uri('/crm/deal/details/1/?IFRAME=Y&filter.status=won');
$uri->deleteParams(['IFRAME']);
$uri->addParams([
	'filter.status' => 'lost',
], true);

$url = (string)$uri;
```

## Example 3

Показывает: допустимое исключение, когда после `Uri` все равно нужен `parse_str()`.
Когда уместно: коду нужен именно массив query-параметров для чтения вложенной структуры, а `Uri` сам не отдает query в parsed form.
Почему это допустимо: `Uri` остается default для разбора URL, а `parse_str()` используется локально только там, где у класса нет публичного API.

```php
use Bitrix\Main\Web\Uri;

$uri = new Uri($request->getRequestUri());
$query = $uri->getQuery();

$queryParams = [];
if ($query !== '')
{
	parse_str($query, $queryParams);
}

$backUrl = (string)($queryParams['backurl'] ?? '');
```

## Legacy / Exceptions

- `parse_str()` не становится новым default только потому, что рядом уже есть legacy-код с ручным разбором query.
- Защищенный `parseParams()` внутри `Uri` не является внешним контрактом; не опирайся на него из прикладного кода через наследование или обходные трюки.
- Если задача сводится к одному `parse_url($url, PHP_URL_HOST)` без дальнейшей работы с URL-объектом, точечное использование `parse_url()` допустимо, но не расширяй этот локальный fallback в общий паттерн для сборки и модификации URL.

## Checklist

- `Uri` выбран там, где код реально работает с полным URL/URI, а не только с raw query string?
- Для изменения query используются `addParams()` и `deleteParams()`, а не ручная склейка строки?
- `preserveDots` включен там, где важны query-ключи с точками или пробелами?
- `parse_str()` остался только как локальное исключение для чтения query в массив или для query-only payload?
- В коде не предполагается несуществующий публичный API вроде `getQueryParams()` у `Uri`?
- Файл не подменяет собой правила маршрутов из `rules/routing.md` и controller/request правила из `rules/controller.md`?
