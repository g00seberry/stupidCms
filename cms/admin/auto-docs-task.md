# Задача: Внедрить систему автодокументирования проекта (без CI/CD, без Python)

**Статус:** к постановке • **Исполнитель:** tbd • **Ревьюер:** tbd  
**Ограничения:** не использовать CI/CD; не использовать Python; локальные скрипты на Node.js.  
**Цель:** получить «непротухающую» документацию с чёткой навигацией и форматом, понятным LLM, которая собирается автоматически из кода и минимального ручного слоя.

---

## 1) Результат (что должно быть на выходе)

1. Статический сайт документации в папке `docs/` на базе **Astro + Starlight** с локальным поиском (Pagefind).
2. Автогенерируемые разделы:
   - `docs/refs/` — справочник публичного API из TypeScript *(или)* JSDoc-комментариев.
   - `docs/10-modules/` — «карточки модулей» с краткими, структурированными описаниями (LLM‑friendly).
   - `docs/30-components/` — если есть React-компоненты: их Props/слоты/события.
   - `docs/20-apis/` — если есть OpenAPI: отрендеренные спецификации.
3. Минимальный ручной слой:
   - `docs/00-overview/index.md` — «карта» документации (где что лежит).
   - `docs/00-overview/glossary.md` — словарь терминов (коротко).
4. Хуки, гарантирующие актуальность на коммит:
   - **husky + lint-staged**: автоген и проверки перед коммитом.
   - Линтеры: **markdownlint**, **remark-lint**, **markdown-link-check**.
5. Команды в `package.json` для dev/генерации/проверок/сборки.
6. Док‑покрытие публичных сущностей ≥ 80% (однострочные summary).

---

## 2) Архитектура решения

- **Источник истины** — код (TS/JS) + OpenAPI (если есть).  
- **Генераторы:**
  - TypeScript → **TypeDoc** (+ `typedoc-plugin-markdown`) *или* JS → **documentation.js**.
  - React → **react-docgen-typescript** (при наличии `.tsx`).
  - OpenAPI → **starlight-openapi** *или* **Redoc** (viewer) по выбору.
- **Сборка сайта** — Astro Starlight.
- **Навигация** — авто‑сайдбар от структуры + `_sidebar.yml` (алфавит/приоритеты), генерится скриптом.
- **Без воды** — каждая публичная сущность получает компактную «карточку» по одному шаблону.

---

## 3) Структура каталогов (после внедрения)

```
/docs
  /00-overview
    index.md            # карта (ручной минимум)
    glossary.md         # словарь (минимум)
  /10-modules           # автоген: карточки модулей
  /20-apis              # автоген: OpenAPI (если есть)
  /30-components        # автоген: React-компоненты (если есть)
  /refs                 # автоген: справочник API
  _sidebar.yml          # автоген: алфавит и порядок
/scripts
  docgen.ts             # генератор и «склейщик»
typedoc.json            # если используем TS
astro.config.mjs        # конфиг Astro
starlight.config.mjs    # конфиг Starlight (если вынесен отдельно)
```

---

## 4) Формат «карточки модуля» (LLM‑friendly, строгий, короткий)

Каждый публичный модуль → файл `docs/10-modules/<slug>.md`:

```md
---
id: <slug>
title: <ModuleName>
owner: team-xyz
stability: stable|experimental
tags: [domain-x, infra]
---

## Summary
Одно короткое предложение.

## Inputs
- …

## Outputs
- …

## Side effects
- …

## Dependencies
- …

## Entrypoints
- `src/.../index.ts:exportedFn()`

## Errors
- …

## Examples
```ts
// минимальный рабочий пример
```
```

---

## 5) Подробные шаги реализации

### A. Подготовка проекта документации
- [ ] Установить Astro + Starlight в подпапку `docs/`:
  ```bash
  npm create astro@latest -- --template starlight --install
  ```
  *Либо добавить Starlight в существующий Astro.*
- [ ] Включить встроенный поиск Pagefind (по умолчанию в Starlight).
- [ ] Создать `docs/00-overview/index.md` и `docs/00-overview/glossary.md` (пустые заготовки).

### B. Зависимости и скрипты (Node, без Python)
- [ ] Добавить dev‑зависимости:
  ```bash
  npm i -D typedoc typedoc-plugin-markdown documentation react-docgen-typescript          husky lint-staged markdownlint-cli remark remark-cli remark-preset-lint-recommended          markdown-link-check ts-node typescript
  ```
  *Если проект чисто JS — `typescript`/`ts-node` остаются для скрипта `docgen.ts` (или заменить на `.mjs`).*
- [ ] Добавить команды в `package.json`:
  ```json
  {
    "scripts": {
      "docs:dev": "astro dev docs",
      "docs:build": "astro build docs",
      "docs:gen:typedoc": "typedoc --options typedoc.json --json tmp/typedoc.json",
      "docs:gen:jsdoc": "documentation build "src/**" -f json -o tmp/jsdoc.json",
      "docs:gen:react": "react-docgen-typescript --propFilter skipNodeModules "src/**/*.tsx" > tmp/react.json",
      "docs:gen": "node --loader ts-node/esm scripts/docgen.ts",
      "docs:check": "node --loader ts-node/esm scripts/docgen.ts --check && markdownlint "docs/**/*.md" && markdown-link-check -q docs/**/*.md"
    },
    "lint-staged": {
      "*.{ts,tsx,js,jsx,md}": [
        "npm run docs:gen",
        "npm run docs:check",
        "git add"
      ]
    }
  }
  ```
- [ ] Инициализировать **husky**:
  ```bash
  npx husky init
  # в .husky/pre-commit добавить:
  # npm run docs:gen && npm run docs:check
  ```

### C. Конфигурации

**TypeDoc (`typedoc.json`)** — если используем TypeScript:
```json
{
  "$schema": "https://typedoc.org/schema.json",
  "entryPoints": ["src/index.ts"],
  "excludePrivate": true,
  "excludeInternal": true,
  "excludeExternals": false,
  "readme": "none",
  "categorizeByGroup": false,
  "plugin": ["typedoc-plugin-markdown"]
}
```

**markdownlint (`.markdownlint.json`)** — строгий, но короткий набор:
```json
{
  "default": true,
  "MD013": false,   // длина строки
  "MD033": false    // raw HTML в редких случаях
}
```

**remark (`.remarkrc.mjs`)** — рекомендованный пресет:
```js
export default {
  plugins: [
    ["remark-preset-lint-recommended", {}]
  ]
};
```

### D. Генератор `scripts/docgen.ts` (Node + TS)
- Читает `tmp/typedoc.json` (или `tmp/jsdoc.json`), а также `tmp/react.json` (если есть).
- Создаёт/обновляет: `docs/refs/*`, `docs/10-modules/*`, `docs/30-components/*`, `docs/_sidebar.yml`.
- Проверяет «doc coverage» (доля публичных сущностей с `summary ≥ 1` строка).

Минимальный рабочий скелет:
```ts
// scripts/docgen.ts
import fs from "node:fs/promises";
import path from "node:path";

type TDNode = {
  id: number;
  name: string;
  kindString?: string;
  comment?: { summary?: { text: string }[] };
  children?: TDNode[];
  signatures?: TDNode[];
};

const root = process.cwd();
const outRefs = path.join(root, "docs/refs");
const outCards = path.join(root, "docs/10-modules");
const outComps = path.join(root, "docs/30-components");
const tmp = path.join(root, "tmp");

const md = (s = "") => s.replace(/\t/g, "  ").trim() + "\n";

const readJSON = async <T>(p: string): Promise<T | null> => {
  try { return JSON.parse(await fs.readFile(p, "utf8")) as T; }
  catch { return null; }
};

const ensure = async (p: string) => fs.mkdir(p, { recursive: true });
const summaryOf = (n: TDNode) =>
  n?.comment?.summary?.map(x => x.text.trim()).join(" ").trim() || "";
const isPublic = (n: TDNode) =>
  ["Class", "Function", "Interface", "Type alias", "Variable"].includes(n.kindString || "");

const flatten = (n: TDNode): TDNode[] =>
  [n, ...(n.children?.flatMap(flatten) ?? []), ...(n.signatures ?? [])];

const toSlug = (name: string) =>
  name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");

async function writeCard(n: TDNode) {
  const slug = toSlug(n.name);
  const body = md(`
---
id: ${slug}
title: ${n.name}
owner: tbd
stability: stable
tags: []
---

## Summary
${summaryOf(n) || "—"}

## Inputs
- —

## Outputs
- —

## Side effects
- —

## Dependencies
- —

## Entrypoints
- src/**/${n.name}

## Errors
- —

## Examples
\\`\\`\\`ts
// ${n.name} — пример использования
\\`\\`\\`
  `);
  await fs.writeFile(path.join(outCards, `${slug}.md`), body, "utf8");
}

async function writeRef(n: TDNode) {
  const slug = toSlug(n.name);
  const body = md(`
# ${n.name}
**Kind:** ${n.kindString || "—"}

${summaryOf(n) ? `> ${summaryOf(n)}` : ""}

<!-- Здесь можно добавить автосгенерированные сигнатуры/параметры -->
  `);
  await fs.writeFile(path.join(outRefs, `${slug}.md`), body, "utf8");
}

async function main() {
  await ensure(outRefs);
  await ensure(outCards);
  await ensure(outComps);

  const td = await readJSON<TDNode>(path.join(tmp, "typedoc.json"))
        || await readJSON<TDNode>(path.join(tmp, "jsdoc.json"));

  if (!td) {
    console.error("Нет tmp/typedoc.json или tmp/jsdoc.json. Запустите docs:gen:typedoc или docs:gen:jsdoc.");
    process.exit(2);
  }

  const all = flatten(td).filter(isPublic);

  for (const n of all) await writeCard(n);
  for (const n of all) await writeRef(n);

  // навигация
  const entries = all.map(n => `- ${n.name}`).sort().join("\n");
  await fs.writeFile(path.join("docs", "_sidebar.yml"), md(entries), "utf8");

  if (process.argv.includes("--check")) {
    const documented = all.filter(n => summaryOf(n)).length;
    const ratio = documented / Math.max(all.length, 1);
    if (ratio < 0.8) {
      console.error(`Docs coverage ${Math.round(ratio * 100)}% < 80%`);
      process.exit(3);
    }
  }
}

main().catch(e => { console.error(e); process.exit(1); });
```

### E. Интеграция OpenAPI (опционально)
- [ ] Если спецификация есть — положить `.yml/.json` в `docs/20-apis/`.
- [ ] Подключить один из вариантов:
  - **starlight-openapi** для встроенного рендера,
  - или **Redoc** как виджет/страницу.
- [ ] Добавить пример `openapi.petstore.yml` для smoke‑теста.

### F. Навигация и поисковая индексация
- [ ] Генератор обновляет `docs/_sidebar.yml` (алфавит).
- [ ] Проверить, что все страницы имеют уникальные заголовки и метаданные (front‑matter).

### G. Правила для авторов (обязательные)
- [ ] Каждый публичный экспорт должен иметь однострочный `summary` (TSDoc/JSDoc).
- [ ] Пример использования — минимальный, компилируемый.
- [ ] При изменении публичного API/ENV/схем — запуск `npm run docs:gen`, коммит вместе с `docs/*`.

---

## 6) Команды и пользовательские сценарии

- **Запуск сайта локально**: `npm run docs:dev` → открыть локальный адрес Astro.
- **Полная генерация**:  
  - TS: `npm run docs:gen:typedoc && npm run docs:gen`  
  - JS: `npm run docs:gen:jsdoc && npm run docs:gen`
- **Проверки**: `npm run docs:check`
- **Сборка статического сайта**: `npm run docs:build`

---

## 7) Критерии приёмки (DoD)

1. В репозитории присутствуют каталоги и файлы согласно структуре из раздела 3.
2. Команды:
   - `npm run docs:gen:typedoc` *(или `:jsdoc`)* завершаются **0**.
   - `npm run docs:gen` создаёт/обновляет `docs/refs/*`, `docs/10-modules/*`, `docs/_sidebar.yml`.
   - `npm run docs:check` завершается **0** и валидирует:
     - doc‑coverage ≥ 80%;
     - `markdownlint` без ошибок;
     - `markdown-link-check` без «dead links».
   - `npm run docs:dev` поднимает сайт; поиск работает.
   - `npm run docs:build` собирает прод‑артефакты без ошибок.
3. «Карточки модулей» существуют для всех публичных сущностей и читаются без «воды» (≤ 1–2 предложения в Summary).
4. Навигация предсказуема: алфавитный `_sidebar.yml`, уникальные слуги (`id`).
5. (Если есть React) для как минимум одного компонента сгенерирован файл в `docs/30-components/`.
6. (Если есть OpenAPI) спецификация отрендерена в `docs/20-apis/` одной из поддерживаемых вьюх.

---

## 8) Риски и меры

- **Нехватка описаний в коде** → проваливается doc‑coverage.  
  *Мера:* включили ≥80% как «красную линию» в `docs:check`.
- **Слишком тяжёлые страницы** → деградация поиска.  
  *Мера:* карточки короткие; референс вынесен в `refs/`.
- **Смешанный JS/TS** → несовместимость инструментов.  
  *Мера:* предусмотрены оба пути (TypeDoc и documentation.js).
- **Нет CI/CD** → пропуск автогенерации.  
  *Мера:* `husky`/`lint-staged` на pre-commit, локально.

---

## 9) Материалы к ревью (что показать ревьюеру)

- Снимок директории `docs/` после генерации.
- Список публичных API (алфавит) из `_sidebar.yml`.
- Скрин поиска и выдачи по 1–2 запросам.
- Пример карточки модуля и файла из `refs/`.
- Логи успешного `npm run docs:check`.

---

## 10) Постановка подзадач (чек-листы для таск‑трекера)

- [ ] Инициализация Astro + Starlight.
- [ ] Добавление зависимостей и `package.json` скриптов.
- [ ] Создание `typedoc.json` / конфигов линтеров.
- [ ] Реализация `scripts/docgen.ts` (скелет как в задаче).
- [ ] Заготовки `docs/00-overview/*`.
- [ ] Пробный прогон для TS (`docs:gen:typedoc`) и/или JS (`docs:gen:jsdoc`).
- [ ] Генерация карточек и справочника.
- [ ] Включение `husky`/`lint-staged`.
- [ ] Проверка критериев DoD.
- [ ] Документация для авторов (1 страница «Как писать summary и примеры»).

---

## 11) Примечания

- Лицензии инструментов — открытые (MIT/Apache/ISC). Платных обязательных зависимостей нет.
- Можно позже допилить экспорт в «LLM‑пакеты» (архивы с index + короткими карточками) — вне объёма текущей задачи.