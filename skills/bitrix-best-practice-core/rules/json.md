# JSON

## Boundaries

- Этот файл покрывает `Bitrix\Main\Web\Json`: сериализацию PHP-данных в JSON-строку, декодирование строки, проверку синтаксиса и выбор encoding options.
- Чтение JSON body запроса описано в [request.md](request.md), а HTTP-ответы через `Engine\Response\Json` / `AjaxJson` — в [response.md](response.md).
- JWT, JWK/JWKS и Base64 URL-safe helpers описаны в [jwt.md](jwt.md); не собирай сегменты токена через обычный JSON codec.

## Rules

- Для преобразования данных в JSON-строку и обратно по умолчанию используй `Json::encode()` / `Json::decode()` вместо прямых `json_encode()` / `json_decode()` или локального codec-helper.
- Для обычного кодирования вызывай `Json::encode($data)` без второго аргумента: framework задает `DEFAULT_OPTIONS`, включая `JSON_HEX_*`, `JSON_UNESCAPED_UNICODE` и `JSON_INVALID_UTF8_SUBSTITUTE`.
- Учитывай, что явно переданные options заменяют `DEFAULT_OPTIONS`; если нужно добавить флаг, объединяй его с `Json::DEFAULT_OPTIONS` через `|`.
- Считай ошибку encode/decode исключением `Bitrix\Main\ArgumentException`, а не результатом `false` или состоянием `json_last_error()`; обрабатывай её на границе, где определен error-contract.
- Не включай `JSON_PARTIAL_OUTPUT_ON_ERROR` для маскировки ошибки сериализации: частичный payload не гарантирует сохранность данных.
- После `Json::decode()` проверяй тип и структуру по прикладному контракту: JSON-объекты превращаются в ассоциативные массивы, но корневое значение может быть скаляром или `null`.
- Не считай результат `null` ошибкой декодирования: строка `null` является корректным JSON.
- Используй `Json::validate()`, если нужен только boolean-результат проверки синтаксиса; если данные нужны дальше, декодируй один раз и обрабатывай исключение вместо предварительного validate.
- Проверяй обязательные поля и допустимые значения отдельно: корректный JSON-синтаксис не подтверждает прикладную схему.
- При помещении JSON в HTML-атрибут дополнительно экранируй итоговую строку через `htmlspecialcharsbx()`; JSON encoding не заменяет экранирование HTML-контекста.
- Передавай исходные PHP-данные в штатный JSON response, а не заранее закодированную строку: повторное кодирование превратит payload в JSON string.

## Example 1

Показывает: сериализацию данных с настройками framework по умолчанию.
Почему это good pattern: JSON-строка формируется штатным codec без локального helper и ручного набора флагов.

```php
use Bitrix\Main\Web\Json;

$payload = ['title' => 'Отчёт', 'enabled' => true];
$json = Json::encode($payload);
```

## Example 2

Показывает: декодирование обязательного набора настроек с отдельной проверкой структуры.
Почему это good pattern: некорректный синтаксис и корректный JSON неподходящего типа дают явную ошибку, а не тихий fallback на пустой массив.

```php
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Web\Json;

function readSettings(string $json): array
{
	// Ошибка синтаксиса уже приводит к ArgumentException из Json::decode().
	$data = Json::decode($json);
	if (!is_array($data) || !isset($data['enabled']) || !is_bool($data['enabled']))
	{
		throw new ArgumentException('Expected settings with boolean enabled', 'json');
	}

	return $data;
}

$settings = readSettings('{"enabled":false}');
$emptyValue = Json::decode('null'); // null, без ошибки декодирования.
```

## Example 3

Показывает: проверку синтаксиса, когда decoded payload не нужен.
Почему это good pattern: используется boolean API без дублирующего decode и без проверки прикладной схемы под видом проверки JSON.

```php
use Bitrix\Main\Web\Json;

function hasJsonSyntax(string $value): bool
{
	return Json::validate($value);
}

$valid = hasJsonSyntax('{"enabled":true}'); // true
$invalid = hasJsonSyntax('{"enabled":}'); // false
```

## Example 4

Показывает: добавление форматирования к стандартным encoding options.
Почему это good pattern: `JSON_PRETTY_PRINT` не сбрасывает framework defaults, в том числе обработку UTF-8 и `JSON_HEX_*`.

```php
use Bitrix\Main\Web\Json;

$json = Json::encode(
	['title' => 'Отчёт'],
	Json::DEFAULT_OPTIONS | JSON_PRETTY_PRINT,
);
```

## Example 5

Показывает: JSON в HTML-атрибуте, заключенном в двойные кавычки.
Почему это good pattern: сначала применяется JSON encoding, затем экранирование для внешнего HTML-контекста.

```php
use Bitrix\Main\Web\Json;

$settings = ['title' => 'Отчёт', 'enabled' => true];
?>
<div data-settings="<?= htmlspecialcharsbx(Json::encode($settings)) ?>"></div>
```

## Checklist

- Для JSON-строк выбран `Bitrix\Main\Web\Json`, если его контракт покрывает сценарий?
- Дополнительные encoding flags объединены с `Json::DEFAULT_OPTIONS`, если defaults должны сохраниться?
- Ошибки encode/decode учитываются как `ArgumentException` без маскировки частичным выводом?
- После decode проверены ожидаемые тип и поля, а корректный `null` не принят за синтаксическую ошибку?
- Validate используется только для проверки синтаксиса и не дублирует последующий decode?
- JSON внутри HTML-атрибута дополнительно экранирован через `htmlspecialcharsbx()`?
- JSON response получает исходные данные без предварительного encode, а JWT/JWK остаются в зоне `jwt.md`?
