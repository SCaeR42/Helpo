# Helpo — Система управления обращениями в ТП

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777BB4.svg)](https://php.net/)
[![Vue](https://img.shields.io/badge/vue-3.x-brightgreen.svg)](https://vuejs.org/)
[![RabbitMQ](https://img.shields.io/badge/rabbitmq-3.10+-ff6600.svg)](https://www.rabbitmq.com/)

> **Helpo** — асинхронная система управления обращениями в техническую поддержку, построенная на стеке Slim Framework + Vue 3 + RabbitMQ.

---

## 📋 Содержание

- [Описание](#-описание)
- [Архитектура](#-архитектура)
- [Технологический стек](#-технологический-стек)
- [Функционал](#-функционал)
- [Требования](#-требования)
- [Установка](#-установка)
- [Запуск](#-запуск)
- [Структура проекта](#-структура-проекта)
- [API Документация](#-api-документация)
- [Конфигурация](#-конфигурация)
- [Тестирование](#-тестирование)
- [Развёртывание](#-развёртывание)
- [Лицензия](#-лицензия)

---

## 📖 Описание

Helpo — это MVP-версия системы для работы с обращениями в техническую поддержку. Пользователи создают обращения через веб-интерфейс, которые асинхронно обрабатываются через очередь сообщений RabbitMQ. Статус обработки отслеживается в реальном времени через чат-интерфейс.

### Ключевые особенности

- ✅ **Асинхронная обработка** — все запросы проходят через RabbitMQ
- ✅ **JWT авторизация** — автоматическая регистрация при первом входе
- ✅ **GraphQL API** — гибкий и типизированный API
- ✅ **Чат-интерфейс** — коммуникация в контексте обращения
- ✅ **Mock-обработка** — имитация работы оператора для MVP
- ✅ **Swagger документация** — интерактивная API документация

---

## 🏗 Архитектура

```
┌──────────────┐      GraphQL       ┌──────────────┐
│   Vue 3 SPA  │ ◄────────────────► │  Slim 4 API  │
│  + Tailwind  │                    │   Server     │
│  + Apollo    │                    └──────┬───────┘
└──────────────┘                           │
                                           ▼
                                    ┌──────────────┐
                                    │  RabbitMQ    │
                                    │  (Queues)    │
                                    └──────┬───────┘
                                           ▼
                                    ┌──────────────┐
                                    │   Workers    │
                                    │ (Background) │
                                    └──────┬───────┘
                                           ▼
                                    ┌──────────────┐
                                    │    MySQL     │
                                    │   (mysqli)   │
                                    └──────────────┘
```

Подробная архитектура описана в [`docs/SDD.md`](docs/SDD.md).

---

## 🛠 Технологический стек

### Backend

| Компонент | Технология | Версия |
|-----------|------------|--------|
| Framework | Slim Framework | 4.x |
| Язык | PHP | 8.1+ |
| Очереди | RabbitMQ + php-amqplib | 3.x |
| Логирование | Monolog | 2.x/3.x |
| База данных | MySQL (mysqli) | 8.x |
| Авторизация | Firebase JWT | - |
| API Docs | Swagger/OpenAPI | 3.0 |

### Frontend

| Компонент | Технология | Версия |
|-----------|------------|--------|
| Framework | Vue.js | 3.x |
| Сборщик | Vite | 5.x |
| Стилизация | Tailwind CSS | 4.x |
| GraphQL Client | Apollo Client | 3.x |
| Роутинг | Vue Router | 4.x |
| Состояние | Pinia | 2.x |
| Валидация | VeeValidate + Zod | - |
| Язык | TypeScript | 5.x |

---

## ⚡ Функционал

### Серверная часть

| Функция | Описание |
|---------|----------|
| Авторизация | JWT с автоматической регистрацией |
| Создание обращения | Тема, раздел, комментарий |
| Очередь задач | Публикация в RabbitMQ |
| Mock обработка | 5 шагов с интервалом 1 минута |
| Статусы | 5 статусов с произвольными сообщениями |
| История | Логирование всех изменений |

### Фронтенд

| Функция | Описание |
|---------|----------|
| Авторизация | Форма login/password |
| Список обращений | Таблица с фильтрацией |
| Чат | Интерфейс сообщений по обращению |
| Отправка сообщений | Через GraphQL мутацию |
| Polling | Автообновление статуса |

---

## 📦 Требования

| Компонент | Минимальная версия |
|-----------|-------------------|
| PHP | 8.1+ |
| Node.js | 18+ |
| MySQL | 8.0+ |
| RabbitMQ | 3.10+ |
| Composer | 2.5+ |
| npm/pnpm | 9+ |
| Docker (опционально) | 24+ |

---

## 🔧 Установка

### Вариант 1: Локальная установка

#### 1. Клонирование репозитория

```bash
git clone https://github.com/your-org/helpo.git
cd helpo
```

#### 2. Настройка окружения

```bash
cp .env.example .env
# Отредактируйте .env под ваше окружение
```

#### 3. Backend установка

```bash
cd backend
composer install
```

#### 4. Frontend установка

```bash
cd frontend
npm install
```

#### 5. База данных

```bash
# Создайте базу данных
mysql -u root -p -e "CREATE DATABASE helpo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'helpo_user'@'localhost' IDENTIFIED BY 'helpo_password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON helpo.* TO 'helpo_user'@'localhost';"

# Примените миграции
mysql -u helpo_user -p helpo < backend/database/schema.sql
```

#### 6. RabbitMQ настройка

```bash
# Создайте vhost и пользователя
rabbitmqctl add_vhost /helpo
rabbitmqctl add_user helpo helpo_password
rabbitmqctl set_permissions -p /helpo helpo ".*" ".*" ".*"
```

### Вариант 2: Docker Compose

```bash
docker-compose up -d
```

---

## 🚀 Запуск

### 1. Запуск Backend API

```bash
cd backend
php -S localhost:8000 -t public
# или с PHP built-in server
php public/index.php
```

### 2. Запуск Frontend (dev mode)

```bash
cd frontend
npm run dev
```

Frontend будет доступен по адресу: `http://localhost:5173`

### 3. Запуск Workers

```bash
# Воркер обработки обращений
php workers/ticket_worker.php

# Воркер обработки сообщений
php workers/message_worker.php

# MVP: Mock воркер статусов
php workers/mock_status_worker.php
```

> [!IMPORTANT]  
> Воркеры должны работать в фоне. Для production используйте supervisor или systemd.

### 4. Запуск через Docker Compose

```bash
docker-compose up -d

# Сервисы:
# - api:8000        — Backend API
# - frontend:5173   — Frontend dev server
# - mysql:3306      — База данных
# - rabbitmq:5672   — RabbitMQ
# - rabbitmq:15672  — RabbitMQ Management UI
```

---

## 📁 Структура проекта

```
Helpo/
├── backend/                    # Slim Framework API
│   ├── src/
│   │   ├── Controllers/        # GraphQL контроллеры
│   │   ├── Services/           # Бизнес-логика
│   │   ├── Models/             # Модели данных
│   │   ├── Middleware/         # HTTP Middleware
│   │   ├── Queue/              # RabbitMQ publishers
│   │   └── Utils/              # Утилиты
│   ├── config/                 # Конфигурация
│   ├── database/               # Миграции и схема
│   ├── public/                 # Entry point
│   ├── logs/                   # Логи
│   └── composer.json
│
├── frontend/                   # Vue 3 SPA
│   ├── src/
│   │   ├── components/         # Vue компоненты
│   │   ├── views/              # Страницы
│   │   ├── composables/        # Composition API хуки
│   │   ├── stores/             # Pinia stores
│   │   ├── graphql/            # GraphQL запросы
│   │   ├── apollo/             # Apollo Client
│   │   ├── router/             # Vue Router
│   │   └── types/              # TypeScript типы
│   ├── public/
│   ├── index.html
│   ├── package.json
│   └── vite.config.ts
│
├── workers/                    # PHP фоновые обработчики
│   ├── base_worker.php
│   ├── ticket_worker.php
│   ├── message_worker.php
│   └── mock_status_worker.php
│
├── docs/                       # Документация
│   ├── SDD.md                  # Software Design Document
│   └── swagger.yaml            # OpenAPI спецификация
│
├── docker/                     # Docker конфигурация
│   ├── docker-compose.yml
│   ├── php/Dockerfile
│   └── nginx/default.conf
│
├── .env.example                # Пример переменных окружения
└── README.md                   # Этот файл
```

---

## 📚 API Документация

### GraphQL Endpoint

```
POST /api/graphql
```


### Swagger UI

```
GET /api/docs
```

http://localhost:8000/api/graphql

http://localhost:8000/api/docs

http://127.0.0.1:8000/api/health

### Основные запросы

```graphql
# Авторизация
mutation Login {
  login(input: { login: "user", password: "pass" }) {
    token
    user { id login }
  }
}

# Список обращений
query MyTickets {
  myTickets {
    id subject section statusCode statusName createdAt
  }
}

# Статус обращения
query TicketStatus {
  ticketStatus(ticketId: "1") {
    code name message
  }
}

# Сообщения обращения
query TicketMessages {
  ticketMessages(ticketId: "1") {
    id content senderType createdAt
  }
}

# Создание обращения
mutation CreateTicket {
  createTicket(input: {
    subject: "Не работает оплата"
    section: ERROR
    comment: "Ошибка 500"
  }) {
    id subject statusCode
  }
}

# Отправка сообщения
mutation SendMessage {
  sendMessage(input: {
    ticketId: "1"
    content: "Добрый день!"
  }) {
    id content senderType
  }
}
```

Полная документация API доступна в [`docs/swagger.yaml`](docs/swagger.yaml) и через Swagger UI.

---

## ⚙️ Конфигурация

### Переменные окружения

| Переменная | Описание | По умолчанию |
|------------|----------|--------------|
| `APP_ENV` | Окружение | `development` |
| `APP_DEBUG` | Режим отладки | `true` |
| `APP_URL` | URL приложения | `http://localhost:8080` |
| `DB_HOST` | Хост MySQL | `localhost` |
| `DB_PORT` | Порт MySQL | `3306` |
| `DB_NAME` | Имя базы данных | `helpo` |
| `DB_USER` | Пользователь БД | `helpo_user` |
| `DB_PASSWORD` | Пароль БД | `helpo_password` |
| `RABBITMQ_HOST` | Хост RabbitMQ | `localhost` |
| `RABBITMQ_PORT` | Порт RabbitMQ | `5672` |
| `RABBITMQ_USER` | Пользователь RabbitMQ | `helpo` |
| `RABBITMQ_PASSWORD` | Пароль RabbitMQ | `helpo` |
| `RABBITMQ_VHOST` | Vhost RabbitMQ | `/` |
| `JWT_SECRET` | Секретный ключ JWT | *(обязательно)* |
| `JWT_TTL` | Время жизни токена (сек) | `86400` |
| `LOG_LEVEL` | Уровень логирования | `debug` |
| `LOG_PATH` | Путь к логам | `./logs` |

---

## 🧪 Тестирование

### Backend тесты

```bash
cd backend
composer test
# или
vendor/bin/phpunit
```

### Frontend тесты

```bash
cd frontend
npm run test
# или
npm run test:unit
npm run test:e2e
```

### API тестирование

```bash
# Через Swagger UI
http://localhost:8000/api/docs

# Или через GraphQL Playground
http://localhost:8000/api/graphql
```

---

## 🐳 Развёртывание

### Production Docker Compose

```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  api:
    build:
      context: ./backend
      dockerfile: Dockerfile
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    depends_on:
      - mysql
      - rabbitmq

  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    depends_on:
      - api

  mysql:
    image: mysql:8.0
    volumes:
      - mysql_data:/var/lib/mysql

  rabbitmq:
    image: rabbitmq:3-management
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq

volumes:
  mysql_data:
  rabbitmq_data:
```

```bash
docker-compose -f docker-compose.prod.yml up -d
```

### Supervisor для воркеров

```ini
# /etc/supervisor/conf.d/helpo-workers.conf
[program:helpo-ticket-worker]
command=php /var/www/helpo/workers/ticket_worker.php
directory=/var/www/helpo
autostart=true
autorestart=true
stderr_logfile=/var/log/helpo/ticket-worker.err.log
stdout_logfile=/var/log/helpo/ticket-worker.out.log

[program:helpo-message-worker]
command=php /var/www/helpo/workers/message_worker.php
directory=/var/www/helpo
autostart=true
autorestart=true
stderr_logfile=/var/log/helpo/message-worker.err.log
stdout_logfile=/var/log/helpo/message-worker.out.log

[program:helpo-mock-worker]
command=php /var/www/helpo/workers/mock_status_worker.php
directory=/var/www/helpo
autostart=true
autorestart=true
stderr_logfile=/var/log/helpo/mock-worker.err.log
stdout_logfile=/var/log/helpo/mock-worker.out.log
```

---

## 📄 Лицензия

MIT License. Подробнее в файле [`LICENSE`](LICENSE).

---

## 📞 Поддержка

- 📧 Email: support@helpo.example.com
- 📖 Документация: [`docs/SDD.md`](docs/SDD.md)
- 🐛 Баг-репорты: GitHub Issues

---

> [!NOTE]  
> Это MVP-версия проекта. Некоторые функции могут работать в режиме имитации.
