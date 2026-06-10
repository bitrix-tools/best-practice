# JWT And JWK

## Boundaries

- Этот файл покрывает `Bitrix\Main\Web\JWT` и `Bitrix\Main\Web\JWK` для выпуска и проверки JWT, разбора JWK/JWKS и Base64 URL-safe encode/decode в JOSE-совместимых сценариях.
- Этот файл помогает выбрать между framework-native `JWT` / `JWK` и ручной склейкой `header.payload.signature`, локальными `base64UrlEncode()` helper-ами, ручным разбором JWK-полей и ad hoc сборкой PEM.
- Этот файл не описывает auth-flow целиком, хранение секретов, ротацию ключей, транспортную безопасность и HTTP lifecycle endpoint'а; для boundary-кода переходи в соседние правила слоя.

## Rules

- Для выпуска подписанного JWT по умолчанию используй `JWT::encode()`, а не ручную сборку сегментов через `json_encode()`, `base64_encode()` и конкатенацию строки `header.payload.signature`.
- Для проверки JWT по умолчанию используй `JWT::decode()` и всегда передавай явный allowlist алгоритмов в третьем аргументе; не доверяй алгоритму из заголовка токена без собственного ограничения.
- Если код принимает JWK set или отдельный JWK и должен получить verification key material, по умолчанию используй `JWK::parseKeySet()` или `JWK::parseKey()`, а не ручной decode `n` / `e` и сборку PEM в прикладном коде.
- Используй `JWT::urlsafeB64Encode()` и `JWT::urlsafeB64Decode()`, когда протокол требует именно unpadded Base64 URL-safe формат, а не обычный `base64_encode()` / `base64_decode()`.
- Не создавай рядом с кодом новый локальный helper вроде `base64UrlEncode()` или `base64UrlDecode()`, если задача уже живет в JWT/JWK/JOSE-контексте и её покрывает `Bitrix\Main\Web\JWT`.
- Не используй `JWT::urlsafeB64Encode()` по инерции для generic Base64, MIME payload или произвольного бинарного формата, где URL-safe variant не является частью контракта.
- Если токен проверяется по набору ключей с `kid`, сохраняй keyed JWK set и передавай его в `JWT::decode()`, а не реализуй собственный key-selection поверх уже поддержанного сценария.
- Держи payload и header JWT JSON-safe: массивы, скаляры и простые структуры; не передавай в `JWT::encode()` сложные объекты, которые зависят от неявной сериализации.
- Рассматривай `JWK` как helper для public verification keys, а не как универсальный key-management API: в текущем виде он покрывает RSA public key path, а не private keys и не общий конструктор для всех `kty`.
- Если код уже находится на низком уровне JOSE-адаптера и ему нужны raw binary fragments вместо готового public key resource, допустимо использовать только `JWT::urlsafeB64Decode()` / `JWT::urlsafeB64Encode()` без `JWK`, но это exception-path, а не новый default для JWK parsing.

## Decision Guide

- Если нужно выпустить или проверить JWT, используй `JWT::encode()` / `JWT::decode()`.
- Если вход приходит как JWK set или отдельный JWK RSA public key и нужен usable verification key, используй `JWK::parseKeySet()` / `JWK::parseKey()`, а затем `JWT::decode()`.
- Если полноценный JWT/JWK parsing не нужен, но контракт требует unpadded Base64 URL-safe encode/decode для JOSE-совместимых фрагментов, используй `JWT::urlsafeB64Encode()` / `JWT::urlsafeB64Decode()`.
- Если задача на самом деле про хранение секретов, TTL токена, endpoint lifecycle или политику аутентификации, этот файл не является главным правилом.

## Related Rules

- `rules/controller.md` - для controller lifecycle, filters и HTTP boundary вокруг JWT/JWK endpoint'ов.
- `rules/request.md` - для выбора request API, если JWT/JWK/JOSE-данные приходят из query, header, cookie или JSON body.
- `rules/persistent-storage.md` - если спор идет не о формате токена, а о хранении server-side token state, nonce, one-time key или TTL-состояния между запросами.

## Example 1

Показывает: default path для выпуска и проверки JWT с явным алгоритмом и allowlist.
Почему это good pattern: код использует готовый framework-class для всех трех сегментов токена и не оставляет выбор алгоритма на усмотрение входного заголовка.

```php
use Bitrix\Main\Web\JWT;

final class AccessTokenCodec
{
	public function issue(int $userId, string $secret): string
	{
		return JWT::encode([
			'sub' => $userId,
			'iat' => time(),
			'exp' => time() + 3600,
		], $secret, 'HS256');
	}

	public function verify(string $token, string $secret): object
	{
		return JWT::decode($token, $secret, ['HS256']);
	}
}
```

## Example 2

Показывает: рекомендуемый путь, когда verification keys приходят как JWK set.
Почему это good pattern: `JWK` отвечает за разбор key set, а `JWT` - за верификацию токена; прикладной код не собирает PEM вручную и не декодирует `n` / `e` сам.

```php
use Bitrix\Main\Web\JWK;
use Bitrix\Main\Web\JWT;

final class OpenIdTokenVerifier
{
	public function verify(string $token, array $jwks): object
	{
		$keys = JWK::parseKeySet($jwks);

		return JWT::decode($token, $keys, ['RS256']);
	}
}
```

## Example 3

Показывает: отдельный сценарий, где нужен только Base64 URL-safe helper без выпуска полноценного токена.
Почему это good pattern: код использует штатный helper `JWT`, а не размножает по проекту локальные `strtr(base64_encode(...))` utility-функции с разными деталями padding.

```php
use Bitrix\Main\Web\JWT;

final class JoseSegmentCodec
{
	public function encode(string $binary): string
	{
		return JWT::urlsafeB64Encode($binary);
	}

	public function decode(string $encoded): string
	{
		return JWT::urlsafeB64Decode($encoded);
	}
}
```

## Example 4

Показывает: узкий exception-path для low-level JOSE/JWK adapter, которому нужны raw binary fragments, а не готовый public key.
Когда уместно: surrounding format уже разбирается своим кодом, и `JWK::parseKey()` слишком высокоуровнев для конкретного шага.
Почему это допустимо: даже в таком low-level path canonical helper для Base64 URL-safe decode остается `JWT`, а не новый локальный `base64UrlDecode()`.

```php
use Bitrix\Main\Web\JWT;

final class RsaKeyFragmentReader
{
	public function read(array $jwk): array
	{
		return [
			'modulus' => JWT::urlsafeB64Decode((string)$jwk['n']),
			'publicExponent' => JWT::urlsafeB64Decode((string)$jwk['e']),
		];
	}
}
```

## Legacy / Exceptions

- `JWK::parseKey()` и `JWK::parseKeySet()` не являются общим parser-ом для любых key types; не подменяй этим ограниченным helper-ом сценарии с private keys или неподдержанным `kty`.
- Наличие старого локального `base64UrlEncode()` в legacy-коде не делает его preferred path для нового кода рядом с `Bitrix\Main\Web\JWT`.
- Если код уже живет вне JOSE/JWT/JWK-контракта и ему нужен обычный Base64, не тащи `JWT` в этот сценарий только потому, что у класса есть похожий helper.

## Checklist

- Для выпуска и проверки JWT используются `JWT::encode()` / `JWT::decode()`, а не ручная сборка сегментов и подписи?
- В `JWT::decode()` передан явный allowlist алгоритмов, а не пустой или неявный набор?
- Для JWK/JWKS verification key material выбран `JWK::parseKeySet()` / `JWK::parseKey()`, если сценарий действительно укладывается в RSA public key path?
- Base64 URL-safe encode/decode выполняется через `JWT::urlsafeB64Encode()` / `JWT::urlsafeB64Decode()`, если это JOSE-compatible contract, а не generic Base64?
- В коде не появился новый локальный helper для base64url там, где уже подходит `Bitrix\Main\Web\JWT`?
- Exception-path с raw `n` / `e` decode не подменяет собой default path через `JWK::parseKey()` для обычного прикладного JWK parsing?
- Файл не подменяет собой правила хранения token state, request parsing и controller lifecycle?
