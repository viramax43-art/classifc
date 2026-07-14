# ОКПД 2 — классификатор

Удобный веб-классификатор [ОКПД 2](https://data.mos.ru/opendata/2752) на Laravel с локальным кэшем данных из API [data.mos.ru](https://data.mos.ru/developers/documentation).

## Возможности

- Мгновенный поиск по коду, названию и описанию
- Фильтр по разделу (A–U)
- Дерево разделов и подкодов
- Хлебные крошки по иерархии
- Описания группировок (поле `Nomdescr`)
- Копирование кода в буфер обмена
- Прямые ссылки: `/?code=01.11.11.110`

## Установка

```bash
composer install
cp .env.example .env
php artisan key:generate
```

В `.env` укажите ключ API:

```env
DATAMOS_API_KEY=ваш_ключ
```

Ключ можно получить на [портале открытых данных Москвы](https://data.mos.ru/developers/documentation).

## Загрузка данных

### Через API (если apidata.mos.ru доступен из вашей сети)

```bash
php artisan migrate
php artisan okpd2:check    # диагностика подключения
php artisan okpd2          # параллельно, 6 потоков
php artisan okpd2 -c 10    # 10 параллельных запросов
```

### Если API недоступен (TCP timeout)

Сервер `apidata.mos.ru` часто недоступен из некоторых сетей — это **не решается** увеличением `DATAMOS_TIMEOUT`.

1. Откройте https://data.mos.ru/opendata/2752 в браузере
2. Скачайте набор в JSON или CSV
3. Импортируйте локально:

```bash
php artisan okpd2:import storage/okpd2.json
# или
php artisan okpd2 --file=storage/okpd2.json
```

Команда загрузит ~20 000 позиций.

## Запуск

```bash
php artisan serve
```

Откройте http://127.0.0.1:8000

## API

| Метод | URL | Описание |
|-------|-----|----------|
| GET | `/api/okpd2/sections` | Список разделов |
| GET | `/api/okpd2/children?section=A` | Корневые коды раздела |
| GET | `/api/okpd2/children?parent=01.11` | Дочерние коды |
| GET | `/api/okpd2/search?q=пшеница` | Поиск |
| GET | `/api/okpd2/{code}` | Детали кода |

## Обновление классификатора

Повторите синхронизацию:

```bash
php artisan okpd2:sync
```

## Деплой-пайплайн (production)

В проекте настроен единый скрипт деплоя на сервер `avaks.online`.

1. Установите зависимость для SSH-деплоя:

```bash
pip install paramiko
```

2. Запустите деплой:

```bash
python scripts/deploy-remote.py
```

Скрипт автоматически:
- собирает архив проекта (без `.env`, `vendor`, кешей и логов),
- загружает его на сервер,
- обновляет `/var/www/okpd2`,
- выполняет `composer install`, `migrate`, кеширование Laravel,
- проверяет и перезагружает nginx,
- выполняет smoke-тест `/okpd2/` и `/api/okpd2/meta`.

Пароль сервера берётся из переменной окружения `DEPLOY_PASSWORD`
(если не задана — используется текущее значение по умолчанию в скрипте).
