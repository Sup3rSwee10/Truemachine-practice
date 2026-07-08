# Платёжный календарь — развёртывание через Docker

Данный проект представляет собой финансовый модуль (платёжный календарь, заявки, реестры, отчёты) на стеке **Laravel (PHP) + React + PostgreSQL**. Все сервисы упакованы в Docker и запускаются одной командой.

## Требования

- **Docker Desktop** (с поддержкой WSL 2 на Windows) – [установка](https://www.docker.com/products/docker-desktop/)
- **Git** (для клонирования репозитория)
- Свободные порты: **80** (фронтенд), **8000** (бэкенд API), **5432** (PostgreSQL) – при необходимости порты можно изменить в `docker-compose.yml`.

## Структура проекта
.
├── backend/
│ ├── DB/ # Дамп базы данных
│ │ └── Payment_calendar_database_backup.backup
│ └── Laravel/ # Laravel-приложение
│ ├── app/
│ ├── bootstrap/
│ ├── config/
│ ├── database/
│ ├── public/
│ ├── resources/
│ ├── routes/
│ ├── storage/
│ ├── tests/
│ ├── vendor/
│ ├── .env
│ ├── Dockerfile
│ ├── composer.json
│ └── ... другие файлы
├── frontend/
│ └── frontend-calendar/
│ └── app/ # React-приложение
│ ├── src/
│ ├── Dockerfile
│ ├── nginx.conf
│ ├── package.json
│ └── ... другие файлы
├── docker/
│ └── nginx/
│ └── conf.d/ # Конфиги Nginx для бэкенда
│ └── default.conf
├── docker-compose.yml
└── README.md


## Развёртывание

### 1. Клонируйте репозиторий

bash
git clone <url-репозитория>
cd Truemachine-practice-main

2. Настройте переменные окружения (опционально)
Убедитесь, что в backend/Laravel/.env или в переменных окружения в docker-compose.yml заданы корректные параметры для подключения к БД. По умолчанию используются:
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=Payment_calendar_database
DB_USERNAME=postgres
DB_PASSWORD=123455
Эти значения уже прописаны в docker-compose.yml. При необходимости их можно изменить.

3. Запустите все контейнеры
Из корня проекта выполните:
docker-compose up -d --build

Эта команда:
соберёт образы для бэкенда и фронтенда;
поднимет контейнеры: PostgreSQL, Laravel (PHP-FPM), Nginx для бэкенда, React + Nginx;
автоматически восстановит базу данных из дампа (при первом запуске или после очистки тома db_data).
Процесс сборки может занять 3–5 минут (зависит от скорости интернета и мощности ПК).

4. Проверьте, что все контейнеры запущены
docker-compose ps
Вы должны увидеть 4 контейнера со статусом Up:

Контейнер	Сервис
finance_db	PostgreSQL
finance_backend	Laravel (PHP-FPM)
finance_nginx_backend	Nginx для бэкенда (прокси на PHP-FPM)
finance_frontend	React + Nginx

5. Восстановите базу данных (если дамп не применился автоматически)
Если при первом запуске дамп не восстановился (например, том уже существовал), выполните вручную:
docker exec -it finance_db pg_restore -U postgres -d Payment_calendar_database /docker-entrypoint-initdb.d/Payment_calendar_database_backup.backup

Если возникнет ошибка версии PostgreSQL, обновите в docker-compose.yml образ postgres:17-alpine и перезапустите с очисткой тома:
docker-compose down -v
docker-compose up -d

6. Создайте пользователя для входа (если его нет в дампе)
Проверьте, есть ли пользователи в БД:
docker exec -it finance_db psql -U postgres -d Payment_calendar_database -c "SELECT u.id, u.name, u.email, r.name as role FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id;"

Если записей нет, создайте администратора через Laravel Tinker:
docker exec -it finance_backend php artisan tinker
Вставьте следующий код (целиком):
use App\Models\User;
use Illuminate\Support\Facades\DB;

$user = User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
]);

// Назначаем роль admin (id=4, если роли уже созданы)
DB::table('user_roles')->insert([
    'user_id' => $user->id,
    'role_id' => 4,
]);

echo "Пользователь создан! ID: " . $user->id . "\n";
exit;
Выйдите из Tinker (exit).

7. Войдите в систему
Откройте браузер и перейдите на:
http://localhost/login

Введите учётные данные:

Email: admin@example.com

Пароль: password

После успешного входа вы попадёте в главное приложение.

Проверка работоспособности
Фронтенд: http://localhost

Бэкенд API (тест): http://localhost:8000/api/login (должен вернуть JSON-ответ, если отправить POST-запрос)

Доступ к БД извне (опционально): localhost:5432 (логин postgres, пароль 123455)

Управление контейнерами:

| Действие                          |	Команда                                             |
|-----------------------------------|-----------------------------------------------------|
|Запустить все контейнеры (в фоне)  |  docker-compose up -d                               |
|Остановить все контейнеры	        |  docker-compose down                                |
|Остановить и удалить тома (БД)	    |  docker-compose down -v                             |
|Пересобрать и перезапустить	      |  docker-compose up -d --build                       |
|Посмотреть логи всех сервисов 	    |  docker-compose logs -f                             |
|Посмотреть логи конкретного сервиса|	docker-compose logs -f <service> (например, backend)|


Устранение неполадок
Порт 80 уже занят
Если порт 80 занят другим приложением (IIS, Skype и т.п.), измените внешний порт для фронтенда в docker-compose.yml:

ports:
  - "8080:80"
Затем перезапустите контейнеры и открывайте http://localhost:8080.

Ошибка подключения к БД
Убедитесь, что в .env и в docker-compose.yml правильно указаны:
- DB_HOST=db (имя сервиса)
- DB_DATABASE, DB_USERNAME, DB_PASSWORD – соответствуют переменным в docker-compose.yml.

Ошибка при восстановлении дампа
- Проверьте версию PostgreSQL: в docker-compose.yml используйте образ postgres:17-alpine (или версию, соответствующую дампу).
- Попробуйте восстановить вручную через psql (если дамп в текстовом формате) или запросите актуальный дамп у команды.

Не удаётся войти
- Проверьте, что пользователь создан и ему назначена роль.
- Сбросьте пароль через Tinker: $user->password = bcrypt('newpassword'); $user->save();

Примечания
- Все суммы в БД хранятся в копейках (целые числа). Конвертация в рубли происходит на фронтенде.
- Для работы с Excel-экспортами используется библиотека OpenSpout (она уже установлена).
- В проекте отсутствует эндпоинт для журнала аудита (это ограничение API, см. README фронтенда).



Проект разработан в рамках летней практики УлГТУ, 2026 год
