# Bitrix Best Practice

Набор "best practices" для разработки на **1С-Битрикс** и **Битрикс24**, заточенный под работу с AI-агентами. Это не официальная документация Битрикс — здесь операционные правила: как писать, ревьюить и принимать решения в коде.

Официальная документация по продукту:
- [docs.1c-bitrix.ru](https://docs.1c-bitrix.ru/) — официальная документация "1С-Битрикс: Управление сайтом".
- [apidocs.bitrix24.ru](https://apidocs.bitrix24.ru/) — REST документация "Битрикс24".

## Skills

Примеры и правила поставляются как скиллы для AI-агентов.
Каждый skill — маршрутизатор: он помогает агенту понять, какой rule-файл открыть для задачи, эффективно используя токены и контекст.

Каталог:
- **`bitrix-best-practice-core`** — работа с базовыми сущностями продукта (контроллеры, роутинг, конфигурация и т.д.).
- **`bitrix-best-practice-sql`** — работы с базами данных и ORM.

## Установка плагина

### В чате

Просто скажите агенту:
```txt
Установи плагин из https://github.com/bitrix-tools/best-practice.
```

### Codex CLI

В версиях CLI с поддержкой `plugin add`:

```bash
codex plugin marketplace add https://github.com/bitrix-tools/best-practice
codex plugin add bitrix-best-practice@bitrix-best-practice
codex plugin list
```

### Claude Code

```bash
claude plugin marketplace add https://github.com/bitrix-tools/best-practice
claude plugin install bitrix-best-practice@bitrix-best-practice --scope project
claude plugin list
```

Для установки пользователю замени `--scope project` на `--scope user`.

### Cursor

Cursor поддерживает корневой `plugin.json` стандарта Agent Plugins. Попросите агента [установить через чат](#в-чате).

Через интерфейс установка выполняется: `Customize` -> `Plugins` -> `From GitHub Repository`

## Установка скиллов


Если ваш harness не поддерживает плагины, то можно установить скиллы через CLI инструмент `skills`:
```bash
npx skills add bitrix-tools/best-practice
```

Для конкретного агента добавь опцию `--agent`:
```bash
npx skills add bitrix-tools/best-practice --agent cursor
```

Для установки пользователю добавь `--global` и используй тот же флаг при проверке списка. Устанавливай одним способом для каждого harness, чтобы избежать дубликатов.
