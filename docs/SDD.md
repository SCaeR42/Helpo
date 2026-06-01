# Software Design Document (SDD) — Helpo

> **Версия:** 1.0.0  
> **Дата:** 2026-06-01  
> **Статус:** MVP  
> **Автор:** Helpo Team

---

## 1. Обзор проекта

### 1.1. Назначение

**Helpo** — система управления обращениями в техническую поддержку, построенная на асинхронной архитектуре с использованием очередей сообщений. Пользователи создают обращения, которые обрабатываются в фоновом режиме, и могут отслеживать статус обработки в реальном времени через чат-интерфейс.

### 1.2. Цели MVP

- Реализовать полный цикл создания и обработки обращений
- Обеспечить асинхронную обработку через RabbitMQ
- Предоставить WEB-интерфейс для взаимодействия с системой
- Реализовать JWT-авторизацию пользователей

### 1.3. Ключевые технологии

| Компонент | Технология | Версия |
|-----------|------------|--------|
| Backend Framework | Slim Framework | 4.x |
| Frontend Framework | Vue.js | 3.x |
| Стилизация | Tailwind CSS | 4.x |
| GraphQL Client | Apollo Client | 3.x |
| Очереди | RabbitMQ | 3.x |
| Логирование | Monolog | 2.x/3.x |
| База данных | MySQL (mysqli) | 8.x |
| API Документация | Swagger/OpenAPI | 3.0 |
| Авторизация | JWT | - |

---

## 2. Архитектура системы

### 2.1. Общая схема

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT (Browser)                         │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Vue 3 SPA + Tailwind 4 + Apollo Client (GraphQL)         │  │
│  └───────────────────────────────────────────────────────────┘  │
└────────────────────────────┬────────────────────────────────────┘
                             │ HTTP/GraphQL
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      API GATEWAY (Slim 4)                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐ │
│  │ Auth Module │  │ Ticket API  │  │ Message/Chat API        │ │
│  │ (JWT)       │  │ (GraphQL)   │  │ (GraphQL)               │ │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘ │
│         │                │                      │               │
│         ▼                ▼                      ▼               │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Message Broker (RabbitMQ)                    │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌────────────────┐ │  │
│  │  │ auth_queue   │  │ ticket_queue │  │ message_queue  │ │  │
│  │  └──────────────┘  └──────────────┘  └────────────────┘ │  │
│  └──────────────────────────────────────────────────────────┘  │
│         │                │                      │               │
│         ▼                ▼                      ▼               │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Database (MySQL via mysqli)                  │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐  │  │
│  │  │ users    │ │ tickets  │ │ messages │ │ ticket_log │  │  │
│  │  └──────────┘ └──────────┘ └──────────┘ └────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                   WORKER (Background Process)                   │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Consumer: ticket_worker, message_worker, mock_status     │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2. Компоненты системы

#### 2.2.1. Backend API (Slim 4)

REST/GraphQL API сервер, обрабатывающий все клиентские запросы.

**Ответственности:**
- Аутентификация и авторизация (JWT)
- Валидация входящих GraphQL-запросов
- Публикация задач в RabbitMQ
- Чтение данных из MySQL
- Генерация Swagger-документации

**Структура модулей:**
```
backend/
├── src/
│   ├── Controllers/          # GraphQL Resolvers
│   │   ├── AuthController.php
│   │   ├── TicketController.php
│   │   └── MessageController.php
│   ├── Services/             # Бизнес-логика
│   │   ├── AuthService.php
│   │   ├── TicketService.php
│   │   ├── MessageService.php
│   │   └── QueueService.php
│   ├── Models/               # Data Models
│   │   ├── User.php
│   │   ├── Ticket.php
│   │   └── Message.php
│   ├── Middleware/           # HTTP Middleware
│   │   ├── JwtMiddleware.php
│   │   ├── CorsMiddleware.php
│   │   └── LoggingMiddleware.php
│   ├── Queue/                # RabbitMQ Publishers
│   │   ├── TicketPublisher.php
│   │   └── MessagePublisher.php
│   └── Utils/                # Утилиты
│       ├── Logger.php
│       └── ResponseFormatter.php
├── config/
│   ├── app.php               # Конфигурация приложения
│   ├── database.php          # Настройки MySQL
│   ├── rabbitmq.php          # Настройки RabbitMQ
│   └── jwt.php               # Настройки JWT
├── public/
│   └── index.php             # Entry point
├── logs/                     # Логи Monolog
└── composer.json
```

#### 2.2.2. Frontend (Vue 3 + Tailwind 4 + Apollo)

SPA-приложение для взаимодействия пользователей с системой.

**Ответственности:**
- Авторизация пользователя
- Отображение списка обращений
- Чат-интерфейс для коммуникации
- Отслеживание статусов в реальном времени

**Структура модулей:**
```
frontend/
├── src/
│   ├── components/           # Vue компоненты
│   │   ├── auth/
│   │   │   ├── LoginForm.vue
│   │   │   └── AuthGuard.vue
│   │   ├── tickets/
│   │   │   ├── TicketList.vue
│   │   │   ├── TicketCard.vue
│   │   │   └── TicketStatus.vue
│   │   ├── chat/
│   │   │   ├── ChatWindow.vue
│   │   │   ├── ChatMessage.vue
│   │   │   └── ChatInput.vue
│   │   └── common/
│   │       ├── AppHeader.vue
│   │       ├── AppSidebar.vue
│   │       └── StatusBadge.vue
│   ├── views/                # Страницы
│   │   ├── LoginView.vue
│   │   ├── DashboardView.vue
│   │   └── ChatView.vue
│   ├── composables/          # Composition API хуки
│   │   ├── useAuth.ts
│   │   ├── useTickets.ts
│   │   └── useChat.ts
│   ├── stores/               # Pinia stores
│   │   ├── auth.store.ts
│   │   ├── ticket.store.ts
│   │   └── chat.store.ts
│   ├── graphql/              # GraphQL запросы
│   │   ├── auth.graphql
│   │   ├── tickets.graphql
│   │   └── messages.graphql
│   ├── apollo/               # Apollo Client конфигурация
│   │   ├── client.ts
│   │   └── providers.ts
│   ├── router/               # Vue Router
│   │   └── index.ts
│   ├── types/                # TypeScript типы
│   │   ├── auth.types.ts
│   │   ├── ticket.types.ts
│   │   └── message.types.ts
│   ├── utils/                # Утилиты
│   │   └── api.ts
│   ├── App.vue
│   └── main.ts
├── public/
├── index.html
├── tailwind.config.ts
├── vite.config.ts
├── package.json
└── tsconfig.json
```

#### 2.2.3. Worker (Фоновые обработчики)

Процессы, потребляющие сообщения из RabbitMQ.

**Ответственности:**
- Обработка обращений (с моковыми данными для MVP)
- Обновление статусов
- Генерация тестовых сообщений
- Сохранение результатов в БД

**Структура:**
```
workers/
├── ticket_worker.php         # Обработчик обращений
├── message_worker.php        # Обработчик сообщений
├── mock_status_worker.php    # MVP: генератор статусов
└── base_worker.php           # Базовый класс воркера
```

---

## 3. База данных

### 3.1. Схема данных

```sql
-- Пользователи
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `login` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Обращения (тикетов)
CREATE TABLE `tickets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `section` ENUM('general', 'subscription', 'account', 'error', 'feature') NOT NULL,
    `comment` TEXT,
    `status_code` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `status_name` VARCHAR(100) NOT NULL DEFAULT 'Ожидает обработки',
    `queue_id` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Сообщения (чат)
CREATE TABLE `messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `sender_type` ENUM('user', 'system') NOT NULL DEFAULT 'user',
    `content` TEXT NOT NULL,
    `status_code` VARCHAR(50),
    `status_name` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_ticket_id` (`ticket_id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Лог обработки тикетов (MVP)
CREATE TABLE `ticket_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `status_code` VARCHAR(50) NOT NULL,
    `status_name` VARCHAR(100) NOT NULL,
    `message` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    INDEX `idx_ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2. Справочник статусов (MVP)

| Код статуса | Наименование | Описание |
|-------------|--------------|----------|
| `pending` | Ожидает обработки | Запрос принят в очередь |
| `processing` | В обработке | Запрос обрабатывается |
| `in_progress` | В работе | Начата работа по запросу |
| `review` | На проверке | Запрос на проверке |
| `completed` | Завершён | Обращение закрыто |

---

## 4. API Спецификация

### 4.1. GraphQL Schema

```graphql
# === AUTH ===
type User {
  id: ID!
  login: String!
  createdAt: String!
}

type AuthPayload {
  token: String!
  user: User!
}

input LoginInput {
  login: String!
  password: String!
}

# === TICKETS ===
enum TicketSection {
  GENERAL
  SUBSCRIPTION
  ACCOUNT
  ERROR
  FEATURE
}

type Ticket {
  id: ID!
  userId: ID!
  subject: String!
  section: TicketSection!
  comment: String
  statusCode: String!
  statusName: String!
  createdAt: String!
  updatedAt: String!
}

type TicketStatus {
  code: String!
  name: String!
  message: String
}

input CreateTicketInput {
  subject: String!
  section: TicketSection!
  comment: String
}

# === MESSAGES ===
enum SenderType {
  USER
  SYSTEM
}

type Message {
  id: ID!
  ticketId: ID!
  userId: ID!
  senderType: SenderType!
  content: String!
  statusCode: String
  statusName: String
  createdAt: String!
}

input CreateMessageInput {
  ticketId: ID!
  content: String!
}

# === QUERIES ===
type Query {
  # Получение списка обращений пользователя
  myTickets: [Ticket!]!
  
  # Получение статуса обращения
  ticketStatus(ticketId: ID!): TicketStatus!
  
  # Получение истории сообщений по обращению
  ticketMessages(ticketId: ID!): [Message!]!
  
  # Получение одного обращения
  ticket(id: ID!): Ticket
}

# === MUTATIONS ===
type Mutation {
  # Авторизация (login or register)
  login(input: LoginInput!): AuthPayload!
  
  # Создание нового обращения
  createTicket(input: CreateTicketInput!): Ticket!
  
  # Отправка сообщения в чат обращения
  sendMessage(input: CreateMessageInput!): Message!
}
```

### 4.2. REST Endpoints (для Swagger)

| Метод | Путь | Описание | Auth |
|-------|------|----------|------|
| POST | `/api/graphql` | GraphQL endpoint | Optional* |
| GET | `/api/health` | Health check | No |
| GET | `/api/docs` | Swagger UI | No |

> [!NOTE]  
> GraphQL endpoint требует JWT-токен в заголовке `Authorization: Bearer <token>` для всех мутаций и запросов, кроме `login`.

---

## 5. Очереди (RabbitMQ)

### 5.1. Конфигурация очередей

| Очередь | Exchange | Routing Key | Описание |
|---------|----------|-------------|----------|
| `ticket_queue` | `helpo.direct` | `ticket.create` | Создание нового обращения |
| `message_queue` | `helpo.direct` | `message.send` | Отправка сообщения в чат |
| `status_queue` | `helpo.direct` | `status.update` | Обновление статуса (MVP mock) |

### 5.2. Формат сообщений

**ticket.create:**
```json
{
  "ticket_id": 123,
  "user_id": 45,
  "subject": "Не работает оплата",
  "section": "error",
  "comment": "При оплате картой выдаёт ошибку 500",
  "created_at": "2026-06-01T12:00:00Z"
}
```

**message.send:**
```json
{
  "message_id": 789,
  "ticket_id": 123,
  "user_id": 45,
  "sender_type": "user",
  "content": "Добрый день, проблема сохраняется",
  "created_at": "2026-06-01T12:05:00Z"
}
```

**status.update (MVP mock):**
```json
{
  "ticket_id": 123,
  "step": 1,
  "status_code": "processing",
  "status_name": "В обработке",
  "message": "Ваш запрос принят в работу"
}
```

### 5.3. Логика MVP воркера (mock_status_worker)

Для MVP версии реализуется моковый воркер, который:
1. При получении нового тикета запускает серию из 5 шагов
2. Каждый шаг выполняется с интервалом 1 минута
3. За 5 шагов происходит 2 смены статуса
4. На каждом шаге генерируется произвольное сообщение

**Пример последовательности:**

| Шаг | Статус | Сообщение |
|-----|--------|-----------|
| 1 | `processing` | "Запрос передан специалисту" |
| 2 | `processing` | "Начат анализ проблемы" |
| 3 | `in_progress` | "Проблема идентифицирована" |
| 4 | `in_progress` | "Подготовка решения" |
| 5 | `completed` | "Обращение успешно закрыто" |

---

## 6. Авторизация (JWT)

### 6.1. Flow авторизации

```
┌──────────┐     login/password      ┌──────────┐
│  Client  │ ──────────────────────► │   API    │
│          │                         │  Server  │
│          │ ◄────────────────────── │          │
│          │   JWT Token + User Info │          │
└──────────┘                         └──────────┘
```

### 6.2. Логика

1. Клиент отправляет `login` и `password`
2. Сервер ищет пользователя по `login`
3. Если пользователь найден — проверяется пароль
4. Если пользователь **не найден** — создаётся новый пользователь с указанным логином и паролем
5. Генерируется JWT-токен с payload:
   ```json
   {
     "sub": "<user_id>",
     "login": "<user_login>",
     "iat": <timestamp>,
     "exp": <timestamp>
   }
   ```
6. Токен возвращается клиенту

### 6.3. Настройки JWT

| Параметр | Значение |
|----------|----------|
| Алгоритм | HS256 |
| Время жизни (TTL) | 24 часа |
| Refresh TTL | 7 дней |
| Issuer | `helpo-api` |

---

## 7. Логирование (Monolog)

### 7.1. Каналы логирования

| Канал | Уровень | Handler | Описание |
|-------|---------|---------|----------|
| `app` | INFO | RotatingFileHandler | Основные логи приложения |
| `error` | ERROR | RotatingFileHandler | Ошибки и исключения |
| `queue` | DEBUG | RotatingFileHandler | Логи очередей |
| `auth` | INFO | RotatingFileHandler | Логи авторизации |
| `sql` | DEBUG | StreamHandler (dev) | SQL-запросы (только dev) |

### 7.2. Формат логов

```
[%datetime%] %channel%.%level_name%: %message% %context% %extra%
```

### 7.3. Структура логов

```
logs/
├── app.log           # Основное логирование
├── error.log         # Ошибки
├── queue.log         # Очереди
├── auth.log          # Авторизация
└── sql.log           # SQL (dev only)
```

---

## 8. Frontend архитектура

### 8.1. Маршруты (Vue Router)

| Путь | Компонент | Auth | Описание |
|------|-----------|------|----------|
| `/login` | `LoginView` | Guest | Страница авторизации |
| `/dashboard` | `DashboardView` | Auth | Список обращений |
| `/chat/:ticketId` | `ChatView` | Auth | Чат обращения |
| `/` | Redirect | Any | Редирект на dashboard или login |

### 8.2. Pinia Stores

**auth.store.ts:**
```typescript
interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
}

// Actions: login(), logout(), restoreSession()
```

**ticket.store.ts:**
```typescript
interface TicketState {
  tickets: Ticket[];
  currentTicket: Ticket | null;
  loading: boolean;
}

// Actions: fetchTickets(), fetchTicket(), createTicket()
```

**chat.store.ts:**
```typescript
interface ChatState {
  messages: Message[];
  loading: boolean;
  pollingInterval: number | null;
}

// Actions: fetchMessages(), sendMessage(), startPolling(), stopPolling()
```

### 8.3. Apollo Client конфигурация

```typescript
// apollo/client.ts
import { ApolloClient, InMemoryCache, createHttpLink } from '@apollo/client/core';
import { setContext } from '@apollo/client/link/context';

const httpLink = createHttpLink({
  uri: '/api/graphql',
});

const authLink = setContext((_, { headers }) => {
  const token = localStorage.getItem('jwt_token');
  return {
    headers: {
      ...headers,
      authorization: token ? `Bearer ${token}` : '',
    }
  };
});

export const apolloClient = new ApolloClient({
  link: authLink.concat(httpLink),
  cache: new InMemoryCache(),
});
```

### 8.4. Компоненты UI

#### 8.4.1. LoginForm.vue
- Поля: login, password
- Кнопка: "Войти"
- Валидация: VeeValidate + Zod
- При успехе: редирект на `/dashboard`

#### 8.4.2. TicketList.vue
- Таблица/список обращений
- Колонки: ID, Тема, Раздел, Статус, Дата
- Клик по строке → переход в чат

#### 8.4.3. ChatWindow.vue
- Список сообщений (скролл вниз)
- Разделение по отправителю (user/system)
- Polling для обновления статуса (каждые 30 сек)

#### 8.4.4. ChatInput.vue
- Textarea для ввода сообщения
- Кнопка отправки
- Отправка через GraphQL мутацию

---

## 9. Развёртывание

### 9.1. Требования к окружению

| Компонент | Минимальная версия |
|-----------|-------------------|
| PHP | 8.1+ |
| Node.js | 18+ |
| MySQL | 8.0+ |
| RabbitMQ | 3.10+ |
| Composer | 2.5+ |
| npm/pnpm | 9+ |

### 9.2. Структура проекта

```
Helpo/
├── backend/              # Slim API
├── frontend/             # Vue 3 SPA
├── workers/              # PHP воркеры
├── docs/                 # Документация
│   ├── SDD.md            # Этот документ
│   └── swagger.yaml      # OpenAPI спецификация
├── docker/               # Docker конфигурация
│   ├── docker-compose.yml
│   ├── php/
│   ├── nginx/
│   └── rabbitmq/
├── .env.example          # Пример переменных окружения
└── README.md             # Основная документация
```

### 9.3. Переменные окружения

```env
# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=helpo
DB_USER=helpo_user
DB_PASSWORD=helpo_password

# RabbitMQ
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=helpo
RABBITMQ_PASSWORD=helpo
RABBITMQ_VHOST=/

# JWT
JWT_SECRET=your-secret-key-here
JWT_TTL=86400

# Logging
LOG_LEVEL=debug
LOG_PATH=./logs
```

---

## 10. Безопасность

### 10.1. Меры безопасности

| Аспект | Реализация |
|--------|------------|
| Пароли | bcrypt хеширование |
| JWT | HS256, короткий TTL |
| CORS | Настройка разрешённых origins |
| SQL Injection | Prepared statements (mysqli) |
| XSS | Экранирование в Vue templates |
| CSRF | JWT в Authorization header |
| Rate Limiting | (опционально) |

### 10.2. Middleware безопасности

- [`JwtMiddleware`](backend/src/Middleware/JwtMiddleware.php) — валидация токена
- [`CorsMiddleware`](backend/src/Middleware/CorsMiddleware.php) — CORS заголовки
- [`LoggingMiddleware`](backend/src/Middleware/LoggingMiddleware.php) — логирование запросов

---

## 11. Тестирование

### 11.1. Стратегия тестирования

| Тип | Инструмент | Покрытие |
|-----|------------|----------|
| Unit | PHPUnit | Services, Models |
| Integration | PHPUnit + Test containers | Controllers, Queue |
| E2E | Cypress | Frontend flows |
| API | Swagger Inspector | GraphQL endpoints |

### 11.2. MVP тест-кейсы

1. Авторизация существующего пользователя
2. Авторизация нового пользователя (auto-register)
3. Создание обращения
4. Получение списка обращений
5. Отправка сообщения в чат
6. Проверка смены статуса (mock)
7. Получение истории сообщений

---

## 12. План развития

### 12.1. MVP (Текущий)

- [x] Базовая архитектура
- [x] JWT авторизация
- [x] Создание обращений
- [x] Mock обработка статусов
- [x] Чат-интерфейс
- [x] RabbitMQ интеграция

### 12.2. Post-MVP

- [ ] Реальная обработка обращений операторами
- [ ] WebSocket для real-time обновлений
- [ ] Уведомления (email, push)
- [ ] Роли пользователей (user, operator, admin)
- [ ] Приоритеты обращений
- [ ] SLA таймеры
- [ ] Аналитика и отчёты
- [ ] Мобильная адаптация

---

## 13. Глоссарий

| Термин | Определение |
|--------|-------------|
| Ticket | Обращение пользователя в ТП |
| Queue | Очередь сообщений RabbitMQ |
| Worker | Фоновый процесс обработки |
| Mock | Имитация реальной логики для MVP |
| JWT | JSON Web Token — токен авторизации |
| GraphQL | Язык запросов для API |
| Apollo | GraphQL клиент для фронтенда |

---

## 14. Справочные материалы

- [Slim Framework Documentation](https://www.slimframework.com/docs/v4/)
- [Vue 3 Documentation](https://vuejs.org/guide/introduction.html)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Apollo Client Documentation](https://www.apollographql.com/docs/react/)
- [RabbitMQ Documentation](https://www.rabbitmq.com/documentation.html)
- [Monolog Documentation](https://github.com/Seldaek/monolog/blob/main/doc/01-usage.md)
- [GraphQL Specification](https://spec.graphql.org/)
- [JWT.io](https://jwt.io/introduction)

---

> [!IMPORTANT]  
> Данный документ является живым (living document) и должен обновляться по мере развития проекта.
