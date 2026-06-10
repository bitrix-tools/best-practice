# Bitrix Best Practice

Набор best practices для разработки на **1С-Битрикс** и **Битрикс24**, заточенный под работу с AI-агентами. Это не официальная документация Битрикс — здесь операционные правила: как писать, ревьюить и принимать решения в коде.

Правила доступны в двух форматах:
1. **skills** - скиллы для агентов.
2. **specs** - спецификации с правилами агентов для работы в spec-driven development формате, либо при использовании `memory-bank`.

Официальная документация по продукту:
- [docs.1c-bitrix.ru](https://docs.1c-bitrix.ru/) — официальная документация "1С-Битрикс: Управление сайтом".
- [apidocs.bitrix24.ru](https://apidocs.bitrix24.ru/) — REST документация "Битрикс24".

[![skills.sh](https://skills.sh/b/bitrix-tools/bitrix-best-practice)](https://skills.sh/bitrix-tools/bitrix-best-practice)

## Skills

Скиллы лежат в каталоге [`skills/`](./skills/).
Каждый skill — маршрутизатор: он помогает агенту понять, какой rule-файл открыть для задачи.

### Установка

```bash
# оба skill'а из репозитория
npx skills add bitrix-tools/bitrix-best-practice

# или по одному
npx skills add bitrix-tools/bitrix-best-practice --skill bitrix-best-practice-core
npx skills add bitrix-tools/bitrix-best-practice --skill bitrix-best-practice-sql
```

### Каталог

- **`bitrix-best-practice-core`** — работа с базовыми сущностями продукта (контроллеры, роутинг, конфигурация и т.д.).
- **`bitrix-best-practice-sql`** — работы с базами данных и ORM.

## Specs

Specs — тот же контент rules, но в универсальной упаковке: плоский каталог файлов без skill-обёртки.
Подходит для любого AI-агента или workflow, где работа ведётся через spec-driven-development, либо на проекте используется memory-bank для долгосрочной памяти агента.

Специальный инструмент не нужен: достаточно положить содержимое `specs/` рядом с вашим проектом в директорию с другими спецификацями и упомянуть файл `index.md` в проекте.

### Как добавить

Скопируйте папку [`specs/`](specs/) из репозитория в удобное место в workspace. Или через git:
```bash
# sparse checkout — только specs/
git clone --filter=blob:none --sparse https://github.com/bitrix-tools/bitrix-best-practice.git
cd bitrix-best-practice && git sparse-checkout set specs

# submodule в свой Bitrix-проект
git submodule add https://github.com/bitrix-tools/bitrix-best-practice.git .bitrix-best-practice
# использовать .bitrix-best-practice/specs/
```

Инструкция для агента:
```markdown
## Работа с Bitrix

Сначала прочитай `specs/index.md` и читай только релевантные файлы из `specs/rules/` для решения поставленной задачи.
```
