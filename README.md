# الباك اند — تطبيق المهام اليومية (Laravel)

خادم API لتطبيق المهام اليومية: مصادقة، مهام، خطط دراسية، جلسات رياضة، تصنيفات، إشعارات تذكير، تقدم مجمّع (Snapshots)، ومزامنة Offline-First.

- العقد الكامل للـ API: [`api_documentation.md`](api_documentation.md)
- مواصفات التنفيذ: [`backend-laravel-spec.md`](backend-laravel-spec.md)
- نموذج البيانات ومعايير الكود: [`project_overview.md`](project_overview.md) و [`code_standards.md`](code_standards.md)

## المتطلبات

| المكوّن | النسخة |
|---|---|
| PHP | 8.2+ (مع `pdo_mysql`) |
| Composer | 2.x |
| MySQL / MariaDB | MySQL 8 أو MariaDB 10.4+ (التطوير الحالي عبر XAMPP) |
| Redis | اختياري — للإنتاج (Queue/Cache). التطوير يستخدم `database` |
| Firebase (FCM) | اختياري — بدونها يعمل التذكير عبر Log Driver |

## الإعداد السريع

```bash
composer install
cp .env.example .env
php artisan key:generate

# أنشئ قاعدة البيانات (مثال MariaDB/XAMPP)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS daily_tasks CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
php artisan serve
```

بيانات تجريبية بعد `--seed`: المستخدم `demo@example.com` وكلمة المرور `Password1` (مع 7 أيام من سجل الدراسة والرياضة ومهام قادمة وتذكير يومي).

## المتغيرات البيئية الأساسية

```ini
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

# قاعدة البيانات
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=daily_tasks
DB_USERNAME=root
DB_PASSWORD=

# الطوابير والكاش (الإنتاج: redis)
QUEUE_CONNECTION=database
CACHE_STORE=database

# FCM (اختياري)
# FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
```

## تشغيل الخدمات

```bash
php artisan serve                                              # خادم التطوير
php artisan queue:work database --queue=notifications,default --tries=3
php artisan schedule:work                                      # في التطوير
```

### الإنتاج (Supervisor + Cron)

`/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/backend/artisan queue:work redis --queue=notifications,default --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2
```

`crontab -e` (يُشغّل المجدول: إرسال التذكيرات كل دقيقة + تنظيف التوكنات المنتهية يومياً):

```
* * * * * cd /var/www/backend && php artisan schedule:run >> /dev/null 2>&1
```

عند استخدام Redis في الإنتاج بدّل: `QUEUE_CONNECTION=redis` و `CACHE_STORE=redis` وثبّت امتداد `phpredis` أو حزمة `predis/predis`.

## الإشعارات (Push)

- بدون إعداد: يعمل `LogPushDriver` ويسجّل الإشعار في `storage/logs/laravel.log` (مناسب للتطوير).
- مع الإنتاج: `composer require kreait/laravel-firebase` ثم ضع ملف Service Account في `storage/app/firebase-credentials.json` واضبط `FIREBASE_CREDENTIALS` — سيُستخدم `FcmPushDriver` تلقائياً.
- الأجهزة تُسجَّل عبر `POST /api/v1/devices` (انظر `api_documentation.md` §12.6).

## حدود الطلب (1 MB)

الحد الأقصى لحجم الطلب حسب العقد هو **1 MB**. اضبطه على مستوى الخادم:

```ini
; php.ini
post_max_size = 1M
upload_max_filesize = 1M
```

```nginx
# nginx
client_max_body_size 1m;
```

## الاختبارات

```bash
php artisan test          # 74 اختباراً (SQLite in-memory)
vendor/bin/pint           # تنسيق الكود
```

## المزامنة (Sync)

- `POST /sync/push` — Last-Write-Wins مع حفظ التعارضات في `sync_conflicts`.
- `GET /sync/pull?since=&sinceId=` — سحب تدريجي مع مؤشر (Cursor) آمن حتى عند تساوي `updatedAt`.
- إدارة التعارضات: `GET /sync/conflicts`، `POST /sync/conflicts/{id}/resolve`، `POST /sync/conflicts/resolve-all`.

التفاصيل الكاملة في `api_documentation.md` §12.
"# backend_daily_tasks" 
