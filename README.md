# Bitrix Best Practice

Набор best practices для разработки на **1С-Битрикс** и **Битрикс24**, заточенный под работу с AI-агентами. Это не официальная документация Битрикс — здесь операционные правила: как писать, ревьюить и принимать решения в коде.

Официальная документация по продукту:
- [docs.1c-bitrix.ru](https://docs.1c-bitrix.ru/) — официальная документация "1С-Битрикс: Управление сайтом".
- [apidocs.bitrix24.ru](https://apidocs.bitrix24.ru/) — REST документация "Битрикс24".

## Skills

Примеры и правила поставляются как скиллы для AI-агентов.
Каждый skill — маршрутизатор: он помогает агенту понять, какой rule-файл открыть для задачи, эффективно используя токены и контекст.

Каталог:
- **`bitrix-best-practice-core`** — работа с базовыми сущностями продукта (контроллеры, роутинг, конфигурация и т.д.).
- **`bitrix-best-practice-sql`** — работы с базами данных и ORM.

### Как добавить

Через библиотеку оркестрации скиллов [skills](https://www.npmjs.com/package/skills), все скиллы сразу:
```bash
npx skills add bitrix-tools/best-practice
```

Или конкретные:
```bash
npx skills add bitrix-tools/best-practice --skill bitrix-best-practice-core
npx skills add bitrix-tools/best-practice --skill bitrix-best-practice-sql
```
