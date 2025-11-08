# 53. Admin SPA: экран входа
---
owner: "@frontend-team"
review_cycle_days: 90
last_reviewed: 2025-11-08
system_of_record: "code"
related_code:
  - "admin/src/pages/auth/LoginPage.tsx"
  - "admin/src/stores/auth.store.ts"
  - "admin/src/api/http.ts"
  - "admin/src/routes.tsx"
  - "admin/src/components/ui/Form.tsx"
  - "admin/src/components/ui/Alert.tsx"
---

## Контекст и цель
Экран входа в админку (React + TS + MobX): форма email/пароль, отправка на `POST /api/v1/auth/login` с cookie (credentials), обработка ошибок (RFC7807) и сохранение состояния аутентификации в MobX. Успешный вход редиректит на список страниц; ошибка показывает понятное сообщение.

## UX/flows
- Форма: `email`, `password`, чекбокс `remember` (опционально).
- Submit → `authStore.login({ email, password, remember })`.
- Cookies `cms_at/cms_rt` выставляет сервер (HttpOnly). SPA не читает токены.
- После `200` → `navigate(returnTo || "/entries")`.
- Ошибки:
  - `401` → «Неверный email или пароль».
  - `422` (валидация) → подсветка полей.
  - Иные (RFC7807) → `problem.title || "Ошибка входа"`.
- CORS/CSRF:
  - `fetch(..., { credentials: "include" })` всегда.
  - Если сервер требует CSRF на login и вернулся `419/403` → предварительно получить CSRF-cookie (см. задачу 40) и повторить запрос.

## Состояние (MobX)
- `authStore.isAuthenticated: boolean`
- `authStore.pending: boolean`
- `authStore.error?: string`
- Методы: `login()`, `logout()`, `check()` (опц., ping профиля), `setReturnTo(path)`

## Эндпоинты
- `POST /api/v1/auth/login` — вход, ставит cookies, `200`.
- `POST /api/v1/auth/logout` — выход, чистит cookies, `204`.
- (опц.) `POST /api/v1/auth/refresh` — фоновые обновления (на стороне store).
- (если нужно CSRF) `GET /api/v1/auth/csrf` — выдаёт CSRF cookie.

## Валидация формы (клиент)
- Email: `RFC 5322` простой паттерн, `<= 255`.
- Пароль: `required`, `min=6`.

---

## Интерфейсы и примеры кода

### HTTP-клиент (`admin/src/api/http.ts`)
```ts
export async function http<T>(input: RequestInfo, init: RequestInit = {}): Promise<T> {
  const res = await fetch(input, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(init.headers || {}),
    },
    credentials: 'include',
  });

  if (res.ok) return (await res.json()) as T;

  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/problem+json')) {
    const problem = await res.json();
    throw Object.assign(new Error(problem.title || 'Request failed'), { problem, status: res.status });
  }
  throw Object.assign(new Error('Request failed'), { status: res.status, raw: await res.text() });
}
```

### Auth store (`admin/src/stores/auth.store.ts`)
```ts
import { makeAutoObservable, runInAction } from 'mobx';
import { http } from '@/api/http';

type LoginDto = { email: string; password: string; remember?: boolean };

class AuthStore {
  isAuthenticated = false;
  pending = false;
  error: string | null = null;
  returnTo: string | null = null;

  constructor() { makeAutoObservable(this); }

  setReturnTo(path: string | null) { this.returnTo = path; }

  async login(dto: LoginDto) {
    this.pending = true; this.error = null;
    try {
      await http<unknown>('/api/v1/auth/login', {
        method: 'POST',
        body: JSON.stringify(dto),
      });
      runInAction(() => { this.isAuthenticated = true; });
      return true;
    } catch (e: any) {
      runInAction(() => {
        if (e?.status === 401) this.error = 'Неверный email или пароль';
        else if (e?.problem?.title) this.error = e.problem.title;
        else this.error = 'Ошибка входа';
      });
      return false;
    } finally {
      runInAction(() => { this.pending = false; });
    }
  }

  async logout() {
    await fetch('/api/v1/auth/logout', { method: 'POST', credentials: 'include' });
    runInAction(() => { this.isAuthenticated = false; });
  }
}

export const authStore = new AuthStore();
```

### Login page (`admin/src/pages/auth/LoginPage.tsx`)
```tsx
import { observer } from 'mobx-react-lite';
import { useState } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { authStore } from '@/stores/auth.store';

export const LoginPage = observer(() => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(true);
  const navigate = useNavigate();
  const { state } = useLocation() as { state?: { returnTo?: string } };

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    const ok = await authStore.login({ email, password, remember });
    if (ok) navigate(state?.returnTo || authStore.returnTo || '/entries', { replace: true });
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-6">
      <form onSubmit={onSubmit} className="w-full max-w-sm space-y-4">
        <h1 className="text-2xl font-semibold">Вход</h1>

        {authStore.error && <div role="alert" className="border p-2 rounded">{authStore.error}</div>}

        <label className="block">
          <span className="text-sm">Email</span>
          <input
            type="email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            className="mt-1 w-full border rounded p-2"
            required
          />
        </label>

        <label className="block">
          <span className="text-sm">Пароль</span>
          <input
            type="password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            className="mt-1 w-full border rounded p-2"
            required
          />
        </label>

        <label className="inline-flex items-center gap-2">
          <input type="checkbox" checked={remember} onChange={e => setRemember(e.target.checked)} />
          <span className="text-sm">Запомнить меня</span>
        </label>

        <button
          type="submit"
          disabled={authStore.pending}
          className="w-full py-2 rounded bg-black text-white disabled:opacity-50"
        >
          {authStore.pending ? 'Вход…' : 'Войти'}
        </button>

        <p className="text-center text-xs text-gray-500">
          Проблемы со входом? <Link to="#" className="underline">Обратитесь к администратору</Link>
        </p>
      </form>
    </div>
  );
});
```

### Route guard (фрагмент `admin/src/routes.tsx`)
```tsx
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { authStore } from '@/stores/auth.store';
import { observer } from 'mobx-react-lite';

const Private = observer(() => {
  const loc = useLocation();
  if (!authStore.isAuthenticated) {
    authStore.setReturnTo(loc.pathname + loc.search);
    return <Navigate to="/login" state={{ returnTo: loc.pathname + loc.search }} replace />;
  }
  return <Outlet />;
});

export const routes = [
  { path: '/login', element: <LoginPage /> },
  { element: <Private />, children: [
      { path: '/entries', element: <EntriesListPage /> },
    ] },
];
```

---

## Ошибки (ожидание от сервера)
- `401` — неверные учётные данные (Problem JSON).
- `422` — ошибки полей (валидатор). Пример `problem.errors.email`/`password`.
- `419/403` — нет CSRF (если включено) → получить CSRF-cookie и повторить.

## Тесты (Vitest/RTL, E2E)
**Unit/Component**
- `renders login form`
- `submits and redirects on 200`
- `shows 401 error message`
- `disables submit while pending`

**E2E (Playwright/Cypress)**
- Ввод правильных данных → редирект на `/entries`.
- Неверный пароль → остаётся на `/login`, видит сообщение.
- Страница `/entries` без `isAuthenticated` → редирект на `/login`.

## Приёмка (Acceptance)
- Успешный вход редиректит на список (`/entries`).
- Ошибка входа показывает сообщение из RFC7807 или дефолтное «Неверный email или пароль».
- Состояние аутентификации хранится в MobX (`isAuthenticated`, `pending`, `error`).
- Запросы к login выполняются с `credentials: include`; куки обрабатываются браузером.

## Заметки по безопасности
- Не сохранять пароль/почту в localStorage; максимум — последний email (опционально).
- Куки HttpOnly + Secure, SameSite=Strict — на сервере.
- При разлогине чистить все client-side состояния.
