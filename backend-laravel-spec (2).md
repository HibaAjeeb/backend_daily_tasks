# مواصفات الباك اند — تطبيق المهام اليومية (Laravel + PHP)

> هذا الملف موجّه لتنفيذ Backend كامل جاهز للتشغيل، مطابق تماماً لـ `api_documentation.md` (نفس الـ endpoints، أكواد الأخطاء، وصيغة الاستجابة الموحّدة)، ويأخذ بعين الاعتبار: عزل بيانات كل مستخدم عن الآخر، آلية موثوقة للإشعارات (Reminders)، ولقطات تقدم منفصلة (Progress Snapshots) لتغذية الخط البياني بدون إعادة حساب مكلفة.

---

## 0. القرارات المعمارية الأساسية

| القرار | لماذا |
|---|---|
| **Laravel 11** + PHP 8.3 | أحدث نسخة LTS عملياً، أداء أفضل، `invokable` routes، Pruning مدمج |
| **MySQL 8** (InnoDB) | علائقي بطبيعته، يدعم Foreign Keys الصارمة، مناسب لبيانات مستخدمين متعددين مع علاقات (Task ↔ Category ↔ StudyPlan) |
| **Laravel Sanctum** (Personal Access Tokens) | يُنفّذ Bearer Token فعلياً كما في الوثيقة (access + refresh) دون تعقيد JWT الكامل، مع تحكم كامل بالـ expiry |
| **عزل البيانات: `user_id` + Global Scope** | كل جدول قابل للمزامنة يحمل `user_id` إلزامي مع Scope تلقائي — يمنع تسرّب بيانات مستخدم لآخر دون الحاجة لتذكّر الفلترة يدوياً في كل Query |
| **Soft Deletes + `updated_at`** | أساس Last-Write-Wins في المزامنة، ويطابق سياسة "Soft Delete محلياً قبل الإرسال" في الوثيقة |
| **Laravel Queue (Redis driver) + Scheduler** | تنفيذ الإشعارات (Reminders) في وقتها الفعلي دون تحميل الطلبات العادية |
| **جدولان مستقلان لـ Progress** | `study_progress_snapshots` و `sport_progress_snapshots` — يُكتَبان فقط عبر `ProgressService`، ولا يُعاد حسابهما من `tasks`/`sport_sessions` عند كل طلب (نفس قرار الوثيقة) |
| **Redis للـ Rate Limiting + Cache** | لتطبيق حد 100 طلب/دقيقة بدقة، ولتسريع استعلامات `/progress/*` المتكررة |

---

## 1. هيكل المشروع (Folder Structure)

```
app/
  Console/
    Commands/
      DispatchDueReminders.php        # يعمل كل دقيقة عبر Scheduler
      PruneExpiredTokens.php
  Exceptions/
    Handler.php                        # تحويل كل Exception إلى Envelope موحّد
    ApiException.php                   # استثناء مخصص يحمل code + status + details
  Http/
    Controllers/Api/V1/
      AuthController.php
      TaskController.php
      StudyPlanController.php
      SportSessionController.php
      CategoryController.php
      ReminderController.php
      ProgressController.php
      SyncController.php
      DeviceController.php
    Requests/
      Auth/RegisterRequest.php
      Auth/LoginRequest.php
      Task/StoreTaskRequest.php
      Task/UpdateTaskRequest.php
      StudyPlan/StoreStudyPlanRequest.php
      SportSession/StoreSportSessionRequest.php
      Category/StoreCategoryRequest.php
      Reminder/StoreReminderRequest.php
      Sync/PushSyncRequest.php
      Device/StoreDeviceRequest.php
    Resources/
      TaskResource.php
      StudyPlanResource.php
      SportSessionResource.php
      CategoryResource.php
      ReminderResource.php
      ProgressPointResource.php
    Middleware/
      ForceJsonResponse.php
      SetLocaleFromHeader.php
  Models/
    User.php
    Task.php
    StudyPlan.php
    SportSession.php
    Category.php
    Reminder.php
    StudyProgressSnapshot.php
    SportProgressSnapshot.php
    SyncConflict.php
    Device.php
  Notifications/
    TaskReminderNotification.php       # Channels: database + fcm
  Services/
    ProgressService.php                # المصدر الوحيد لتحديث/قراءة الـ Snapshots
    SyncService.php                    # منطق Push/Pull + حل التعارضات
    ReminderDispatchService.php
  Scopes/
    OwnedByUserScope.php               # Global Scope لعزل بيانات المستخدم
  Traits/
    ApiResponse.php                    # success()/error()/paginated() موحّدة
    SyncableModel.php                  # updatedAt + syncStatus + softDeletes مشترك

routes/
  api.php

database/
  migrations/ (تفصيل كامل في القسم 3)
  seeders/
    DemoDataSeeder.php

config/
  sanctum.php
  cors.php
```

---

## 2. تنسيق الاستجابة الموحّد (Trait `ApiResponse`)

كل الـ Controllers تُرجع الاستجابة عبر هذا الـ Trait لضمان تطابق 100% مع الوثيقة:

```php
<?php

namespace App\Traits;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

trait ApiResponse
{
    protected function success($data = null, int $status = 200, array $extraMeta = []): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge(['timestamp' => now()->toIso8601String()], $extraMeta),
        ], $status);
    }

    protected function paginated(AnonymousResourceCollection $resource): \Illuminate\Http\JsonResponse
    {
        $paginator = $resource->resource;

        return response()->json([
            'success' => true,
            'data' => $resource->collection,
            'meta' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'totalItems' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    protected function error(string $code, string $message, int $status, array $details = []): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
            ]),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'requestId' => 'req_' . Str::random(8),
            ],
        ], $status);
    }
}
```

### استثناء موحّد + معالجة مركزية (`ApiException` + `Handler`)

```php
<?php
// app/Exceptions/ApiException.php
namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
```

```php
<?php
// app/Exceptions/Handler.php (مقتطف render())
use App\Exceptions\ApiException;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

public function render($request, Throwable $e)
{
    if (!$request->is('api/*')) {
        return parent::render($request, $e);
    }

    return match (true) {
        $e instanceof ApiException => $this->apiError($e->errorCode, $e->getMessage(), $e->status, $e->details),
        $e instanceof ValidationException => $this->apiError('VALIDATION_ERROR', 'بيانات الطلب غير صحيحة', 422,
            collect($e->errors())->map(fn ($msgs, $field) => ['field' => $field, 'issue' => $msgs[0]])->values()->all()),
        $e instanceof AuthenticationException => $this->apiError('UNAUTHORIZED', 'التوكن مفقود أو غير صالح', 401),
        $e instanceof ModelNotFoundException => $this->apiError('NOT_FOUND', 'المورد غير موجود', 404),
        $e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException => $this->apiError('RATE_LIMIT_EXCEEDED', 'تجاوزت الحد المسموح من الطلبات', 429),
        default => app()->hasDebugModeEnabled()
            ? parent::render($request, $e)
            : $this->apiError('INTERNAL_ERROR', 'حدث خطأ غير متوقع', 500),
    };
}

private function apiError(string $code, string $message, int $status, array $details = [])
{
    // نفس منطق ApiResponse::error() لكن Static context
    return response()->json([
        'success' => false,
        'error' => array_filter(['code' => $code, 'message' => $message, 'details' => $details ?: null]),
        'meta' => ['timestamp' => now()->toIso8601String(), 'requestId' => 'req_' . \Illuminate\Support\Str::random(8)],
    ], $status);
}
```

**القاعدة الذهبية**: أي كود خطأ جديد يُستحدث في الكود يجب أن يُضاف أولاً إلى جدول "أكواد الأخطاء" في `api_documentation.md` — تماماً كما ينص `ai_workflow.md` و `code_standards.md`.

---

## 3. قاعدة البيانات (Schema + عزل البيانات)

### 3.1 مبدأ عزل البيانات ومنع التداخل

1. **كل جدول قابل للمزامنة** (`tasks`, `study_plans`, `sport_sessions`, `categories`, `reminders`) يحمل عمود `user_id` **إلزامي** (`foreignId->constrained()->cascadeOnDelete()`).
2. **Global Scope** (`OwnedByUserScope`) يُطبَّق تلقائياً على هذه الـ Models فيُضيف `WHERE user_id = auth()->id()` على كل استعلام — لا حاجة لتذكّر الفلترة يدوياً، ويمنع أي احتمال لتسرّب بيانات مستخدم آخر حتى لو نسي مطوّر الفلترة.
3. **Route Model Binding + Policy**: أي وصول لمورد بمعرّف (`/tasks/{id}`) يمرّ عبر `Policy` تتحقق أن `$task->user_id === $user->id`، وإلا `403 FORBIDDEN` — طبقة حماية ثانية مستقلة عن الـ Scope (Defense in Depth).
4. **قيود Unique مركّبة**: مثلاً `categories` لها `UNIQUE(user_id, name)` وليس `UNIQUE(name)` — لتفادي تعارض كاذب بين مستخدمين مختلفين مع الحفاظ على منع التكرار لنفس المستخدم (`DUPLICATE_CATEGORY`).
5. **Transactions** على كل عملية تمسّ أكثر من جدول (مثال: إكمال مهمة → تحديث Snapshot) عبر `DB::transaction()` لضمان عدم تعارض البيانات عند فشل جزئي.

```php
<?php
// app/Scopes/OwnedByUserScope.php
namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OwnedByUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder->where($model->getTable() . '.user_id', auth()->id());
        }
    }
}
```

```php
// في كل Model قابل للمزامنة
protected static function booted(): void
{
    static::addGlobalScope(new OwnedByUserScope());
    static::creating(fn ($model) => $model->user_id ??= auth()->id());
}
```

### 3.2 Migrations الكاملة

```php
// database/migrations/xxxx_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamp('email_verified_at')->nullable();
    $table->timestamps();
});

// personal_access_tokens تُنشأ تلقائياً عبر Sanctum
```

```php
// xxxx_create_categories_table.php
Schema::create('categories', function (Blueprint $table) {
    $table->uuid('id')->primary();          // uuid لتفادي تعارض IDs عند المزامنة offline
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name', 100);
    $table->string('color_value', 9)->nullable();  // #RRGGBBAA
    $table->string('icon', 50)->nullable();
    $table->string('sync_status', 20)->default('synced');
    $table->timestamp('deleted_at')->nullable();   // soft delete للمزامنة
    $table->timestamps();

    $table->unique(['user_id', 'name']);
});
```

```php
// xxxx_create_study_plans_table.php
Schema::create('study_plans', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('subject_name', 150);
    $table->string('goal')->nullable();
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->string('sync_status', 20)->default('synced');
    $table->timestamp('deleted_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'end_date']);
});
```

```php
// xxxx_create_tasks_table.php
Schema::create('tasks', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title', 150);
    $table->text('description')->nullable();
    $table->enum('type', ['study', 'sport', 'other']);
    $table->date('due_date')->nullable();
    $table->time('scheduled_time')->nullable();
    $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
    $table->boolean('is_completed')->default(false);
    $table->timestamp('completed_at')->nullable();
    $table->boolean('is_recurring')->default(false);
    $table->string('recurrence_rule', 100)->nullable();     // RRULE مبسّط
    $table->uuid('category_id')->nullable();
    $table->uuid('parent_plan_id')->nullable();
    $table->string('sync_status', 20)->default('synced');
    $table->timestamp('deleted_at')->nullable();
    $table->timestamps();

    $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
    $table->foreign('parent_plan_id')->references('id')->on('study_plans')->nullOnDelete();

    // فهارس تخدم فلاتر GET /tasks الأكثر استخداماً
    $table->index(['user_id', 'type', 'is_completed']);
    $table->index(['user_id', 'due_date']);
    $table->index(['user_id', 'category_id']);
    $table->index(['user_id', 'parent_plan_id']);
    $table->index('updated_at');   // أساسي لأداء sync/pull
});
```

```php
// xxxx_create_sport_sessions_table.php
Schema::create('sport_sessions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('exercise_name', 150);
    $table->date('date');
    $table->time('scheduled_time')->nullable();
    $table->unsignedInteger('duration_minutes');
    $table->unsignedInteger('actual_duration_minutes')->nullable();
    $table->boolean('is_completed')->default(false);
    $table->timestamp('completed_at')->nullable();
    $table->string('sync_status', 20)->default('synced');
    $table->timestamp('deleted_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'date', 'is_completed']);
    $table->index('updated_at');
});
```

```php
// xxxx_create_reminders_table.php
Schema::create('reminders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->uuid('task_id');
    $table->timestamp('scheduled_for');
    $table->string('message', 255);
    $table->enum('repeat', ['none', 'daily', 'weekly'])->default('none');
    $table->boolean('is_enabled')->default(true);
    $table->boolean('is_dispatched')->default(false);   // يمنع إرسال الإشعار مرتين
    $table->timestamp('dispatched_at')->nullable();
    $table->string('sync_status', 20)->default('synced');
    $table->timestamp('deleted_at')->nullable();
    $table->timestamps();

    $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
    // فهرس أساسي لعامل Scheduler الذي يبحث كل دقيقة عن المستحقّ
    $table->index(['is_enabled', 'is_dispatched', 'scheduled_for']);
});
```

```php
// xxxx_create_devices_table.php  (لدعم sync + push notifications)
Schema::create('devices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('device_id', 100);           // deviceId من الوثيقة
    $table->string('fcm_token', 255)->nullable();
    $table->string('platform', 20)->nullable(); // android | ios
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'device_id']);
});
```

```php
// xxxx_create_study_progress_snapshots_table.php
// لقطة يومية واحدة لكل (user, date) — لا يوجد OLTP إعادة حساب هنا
Schema::create('study_progress_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->unsignedInteger('tasks_planned')->default(0);
    $table->unsignedInteger('tasks_completed')->default(0);
    $table->unsignedInteger('study_minutes')->default(0);
    $table->timestamps();

    $table->unique(['user_id', 'date']);   // upsert آمن، بدون تكرار لليوم نفسه
});
```

```php
// xxxx_create_sport_progress_snapshots_table.php
Schema::create('sport_progress_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->unsignedInteger('sessions_planned')->default(0);
    $table->unsignedInteger('sessions_completed')->default(0);
    $table->unsignedInteger('sport_minutes')->default(0);
    $table->timestamps();

    $table->unique(['user_id', 'date']);
});
```

```php
// xxxx_create_sync_conflicts_table.php
Schema::create('sync_conflicts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('device_id', 100);
    $table->string('entity', 30);      // task | study_plan | sport_session | category | reminder
    $table->uuid('entity_id');
    $table->json('client_data');
    $table->json('server_data');
    $table->enum('status', ['pending', 'resolved'])->default('pending');
    $table->string('resolution', 20)->nullable();
    $table->timestamp('detected_at');
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'device_id', 'status']);
});
```

> **لماذا UUID للكيانات القابلة للمزامنة؟** التطبيق Offline-First (`sqflite` هو مصدر الحقيقة محلياً) — المعرّفات تُولَّد على الجهاز نفسه قبل أي اتصال بالخادم. لو استُخدم `auto-increment`، سيحدث تصادم IDs عند دمج بيانات جهازين مختلفين لنفس المستخدم. الـ UUID يحل هذه المشكلة جذرياً ويمنع أي تداخل بيانات بين السجلات.

---

## 4. المصادقة (Auth) — مطابقة تامة لقسم 5 في الوثيقة

```php
<?php
// app/Http/Controllers/Api/V1/AuthController.php
namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Auth\{RegisterRequest, LoginRequest};
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Hash;

class AuthController
{
    use ApiResponse;

    public function register(RegisterRequest $request)
    {
        if (User::where('email', $request->email)->exists()) {
            throw new ApiException('EMAIL_ALREADY_EXISTS', 'البريد الإلكتروني مستخدم مسبقاً', 409);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return $this->success([
            'userId' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'accessToken' => $user->createToken('access', ['*'], now()->addMinutes(60))->plainTextToken,
            'refreshToken' => $user->createToken('refresh', ['refresh'], now()->addDays(30))->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw new ApiException('INVALID_CREDENTIALS', 'البريد الإلكتروني أو كلمة المرور غير صحيحة', 401);
        }

        return $this->success([
            'accessToken' => $user->createToken('access', ['*'], now()->addMinutes(60))->plainTextToken,
            'refreshToken' => $user->createToken('refresh', ['refresh'], now()->addDays(30))->plainTextToken,
            'expiresIn' => 3600,
        ]);
    }

    public function refresh(\Illuminate\Http\Request $request)
    {
        $request->validate(['refreshToken' => 'required|string']);

        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->refreshToken);

        if (!$token || !$token->can('refresh') || $token->expires_at?->isPast()) {
            throw new ApiException('TOKEN_EXPIRED', 'انتهت صلاحية refresh token، يتطلب تسجيل دخول جديد', 401);
        }

        $user = $token->tokenable;

        return $this->success([
            'accessToken' => $user->createToken('access', ['*'], now()->addMinutes(60))->plainTextToken,
            'refreshToken' => $user->createToken('refresh', ['refresh'], now()->addDays(30))->plainTextToken,
            'expiresIn' => 3600,
        ]);
    }

    public function logout(\Illuminate\Http\Request $request)
    {
        // يُبطل فقط access token الحالي المستخدم في هذا الطلب — لا يمسّ refresh token
        // ولا جلسات/أجهزة أخرى لنفس المستخدم (طبقاً لـ §5.4 في الوثيقة)
        $request->user()->currentAccessToken()->delete();

        return $this->success(null);
    }
}
```

**`RegisterRequest`** يطبّق قواعد التحقق حرفياً من الوثيقة:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:150'],
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*\d).+$/'],
    ];
}
```

---

## 5. مثال Controller كامل (Tasks) — يوضّح النمط المتكرر لباقي الموارد

```php
<?php
// app/Http/Controllers/Api/V1/TaskController.php
namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Task\{StoreTaskRequest, UpdateTaskRequest};
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\ProgressService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController
{
    use ApiResponse;

    public function __construct(private readonly ProgressService $progressService) {}

    public function index(Request $request)
    {
        // تبويب "الكل" في الشاشة الرئيسية = هذا الاستعلام بدون type (لا فلتر يُطبَّق أصلاً).
        // تبويبا "دراسة"/"رياضة" = نفس الاستعلام مع type=study أو type=sport.
        // لا يوجد جدول منفصل لكل نوع — نفس سجل tasks يظهر في الاثنين معاً فور إنشائه،
        // ويختفي من تبويب نوعه تلقائياً إذا عُدِّل type لاحقاً (راجع api_documentation.md §6.7).
        $tasks = Task::query()
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->has('isCompleted'), fn ($q) => $q->where('is_completed', $request->boolean('isCompleted')))
            ->when($request->categoryId, fn ($q) => $q->where('category_id', $request->categoryId))
            ->when($request->dueDateFrom, fn ($q) => $q->whereDate('due_date', '>=', $request->dueDateFrom))
            ->when($request->dueDateTo, fn ($q) => $q->whereDate('due_date', '<=', $request->dueDateTo))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->input('pageSize', 20), 100));

        return $this->paginated(TaskResource::collection($tasks));
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task); // يرمي 403 FORBIDDEN عبر Policy إن لم يكن المالك
        return $this->success(new TaskResource($task));
    }

    public function store(StoreTaskRequest $request)
    {
        $task = Task::create([
            'id' => (string) Str::uuid(),
            ...$request->validated(),
        ]);

        return $this->success(new TaskResource($task), 201);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);
        $task->update($request->validated());
        return $this->success(new TaskResource($task));
    }

    public function complete(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $request->validate(['isCompleted' => 'required|boolean']);

        \DB::transaction(function () use ($task, $request) {
            $task->is_completed = $request->boolean('isCompleted');
            $task->completed_at = $task->is_completed ? now() : null;
            $task->save();

            // القاعدة الصارمة: أي تغيير في isCompleted يمر عبر ProgressService حصراً
            if ($task->type === 'study') {
                $this->progressService->recordStudyTaskCompletion($task);
            }
        });

        return $this->success([
            'id' => $task->id,
            'isCompleted' => $task->is_completed,
            'completedAt' => $task->completed_at?->toIso8601String(),
        ]);
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete(); // Soft Delete
        return response()->json(null, 204);
    }
}
```

**Policy مصاحبة** (`TaskPolicy`):

```php
public function view(User $user, Task $task): bool
{
    return $task->user_id === $user->id;
}
public function update(User $user, Task $task): bool { return $this->view($user, $task); }
public function delete(User $user, Task $task): bool { return $this->view($user, $task); }
```

باقي الـ Controllers (`StudyPlanController`, `SportSessionController`, `CategoryController`, `ReminderController`) تتبع **نفس النمط بالضبط**: Request Validation → Policy Authorization → Eloquent Query مُقيّد بـ Global Scope → Resource موحّد. مثال قاعدة `DUPLICATE_CATEGORY`:

```php
// CategoryController::store()
if (Category::where('name', $request->name)->exists()) {   // الـ Scope يقيّدها بالمستخدم تلقائياً
    throw new ApiException('DUPLICATE_CATEGORY', 'يوجد تصنيف بنفس الاسم مسبقاً', 409);
}
```

---

## 6. آلية التقدّم (Progress) — لتغذية الخط البياني بدقة وأداء

**المبدأ**: لا يُقرأ أي Endpoint تحت `/progress/*` مباشرة من `tasks`/`sport_sessions`. القراءة والكتابة تمران حصراً عبر `ProgressService`، والـ Snapshot اليومي يُحدَّث لحظة اكتمال المهمة/الجلسة فقط (Event-Driven)، وليس بحساب مُجمَّع (Aggregation) عند كل طلب — هذا يجعل استجابة المخطط البياني فورية حتى مع آلاف السجلات.

```php
<?php
// app/Services/ProgressService.php
namespace App\Services;

use App\Models\{Task, SportSession, StudyProgressSnapshot, SportProgressSnapshot};
use Carbon\Carbon;

class ProgressService
{
    public function recordStudyTaskCompletion(Task $task): void
    {
        $date = $task->due_date ?? now()->toDateString();

        StudyProgressSnapshot::updateOrCreate(
            ['user_id' => $task->user_id, 'date' => $date],
            [] // القيم تُحسب تحت
        );

        // upsert آمن ومحمي من Race Condition عبر Query ذرّي بدل قراءة-تعديل-كتابة
        StudyProgressSnapshot::where('user_id', $task->user_id)->where('date', $date)
            ->update([
                'tasks_completed' => Task::where('user_id', $task->user_id)
                    ->where('type', 'study')->whereDate('due_date', $date)
                    ->where('is_completed', true)->count(),
                'tasks_planned' => Task::where('user_id', $task->user_id)
                    ->where('type', 'study')->whereDate('due_date', $date)->count(),
            ]);
    }

    public function recordSportSessionCompletion(SportSession $session): void
    {
        SportProgressSnapshot::updateOrCreate(
            ['user_id' => $session->user_id, 'date' => $session->date],
            []
        );

        SportProgressSnapshot::where('user_id', $session->user_id)->where('date', $session->date)
            ->update([
                'sessions_completed' => SportSession::where('user_id', $session->user_id)
                    ->whereDate('date', $session->date)->where('is_completed', true)->count(),
                'sessions_planned' => SportSession::where('user_id', $session->user_id)
                    ->whereDate('date', $session->date)->count(),
                'sport_minutes' => SportSession::where('user_id', $session->user_id)
                    ->whereDate('date', $session->date)->where('is_completed', true)
                    ->sum('actual_duration_minutes'),
            ]);
    }

    public function weeklyStudy(int $userId, int $weekOffset = 0): array
    {
        $start = Carbon::now()->startOfWeek()->addWeeks($weekOffset);
        $end = (clone $start)->endOfWeek();

        return $this->rangeStudy($userId, $start->toDateString(), $end->toDateString());
    }

    public function rangeStudy(int $userId, string $from, string $to): array
    {
        // Cache لمدة قصيرة يخفّف الضغط على الاستعلامات المتكررة لنفس النطاق
        return cache()->remember("progress:study:$userId:$from:$to", 60, function () use ($userId, $from, $to) {
            $points = StudyProgressSnapshot::where('user_id', $userId)
                ->whereBetween('date', [$from, $to])
                ->orderBy('date')->get();

            return [
                'range' => ['from' => $from, 'to' => $to],
                'points' => $points->map(fn ($p) => [
                    'date' => $p->date->toDateString(),
                    'tasksCompleted' => $p->tasks_completed,
                    'tasksPlanned' => $p->tasks_planned,
                    'studyMinutes' => $p->study_minutes,
                ]),
                'summary' => [
                    'completionRate' => $points->sum('tasks_planned') > 0
                        ? round($points->sum('tasks_completed') / $points->sum('tasks_planned') * 100, 1) : 0,
                    'totalStudyMinutes' => $points->sum('study_minutes'),
                ],
            ];
        });
    }
    // rangeSport() بنفس المنطق تماماً على SportProgressSnapshot
}
```

> **لماذا `updateOrCreate` ثم `update` منفصل؟** لتفادي Race Condition عند اكتمال مهمتين لنفس اليوم في نفس اللحظة (طلبان متزامنان) — الحساب يعتمد على `COUNT`/`SUM` حيّة من الجدول الأساسي مباشرة داخل نفس الاستعلام، وليس على قيمة قديمة محفوظة في الذاكرة، فلا يحدث Overwrite لتحديث الطرف الآخر.

---

## 7. الإشعارات (Reminders) — التنفيذ الفعلي وقت الاستحقاق

### 7.1 القرار التقني

- **Laravel Task Scheduler** يشغّل أمراً كل دقيقة (`* * * * *` عبر Cron حقيقي على الخادم).
- الأمر يبحث عن كل `Reminder` حيث `is_enabled=true AND is_dispatched=false AND scheduled_for <= now()`.
- الإرسال الفعلي (Push إلى الجهاز) يتم عبر **Job** في **Queue** منفصلة (`notifications`)، وليس داخل الأمر مباشرة، حتى لا يتأخر تشغيل الدورة التالية إذا كان عدد الإشعارات كبيراً.
- القناة: **FCM (Firebase Cloud Messaging)** لإرسال Push فعلي للجهاز (متوافق مع `flutter_local_notifications` + `firebase_messaging` في الواجهة)، بالإضافة لقناة `database` لعرض سجلّ الإشعارات داخل التطبيق.

```php
<?php
// app/Console/Commands/DispatchDueReminders.php
namespace App\Console\Commands;

use App\Jobs\SendReminderPushJob;
use App\Models\Reminder;
use Illuminate\Console\Command;

class DispatchDueReminders extends Command
{
    protected $signature = 'reminders:dispatch-due';

    public function handle(): void
    {
        Reminder::where('is_enabled', true)
            ->where('is_dispatched', false)
            ->where('scheduled_for', '<=', now())
            ->chunkById(200, function ($reminders) {
                foreach ($reminders as $reminder) {
                    // تأشير فوري كمُرسَل لمنع إرساله مرتين إن تداخلت دورتان (Idempotency)
                    $reminder->update(['is_dispatched' => true, 'dispatched_at' => now()]);
                    SendReminderPushJob::dispatch($reminder)->onQueue('notifications');
                }
            });
    }
}
```

```php
<?php
// app/Jobs/SendReminderPushJob.php
namespace App\Jobs;

use App\Models\Reminder;
use App\Models\Device;
use App\Notifications\TaskReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class SendReminderPushJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly Reminder $reminder) {}

    public function handle(): void
    {
        $user = $this->reminder->user;
        $devices = Device::where('user_id', $user->id)->whereNotNull('fcm_token')->get();

        $user->notify(new TaskReminderNotification($this->reminder, $devices));

        // إشعار متكرر (daily/weekly): يُعاد جدولته تلقائياً بدل إنشاء سجل جديد
        if ($this->reminder->repeat !== 'none') {
            $next = $this->reminder->repeat === 'daily'
                ? $this->reminder->scheduled_for->addDay()
                : $this->reminder->scheduled_for->addWeek();

            $this->reminder->update(['scheduled_for' => $next, 'is_dispatched' => false]);
        }
    }
}
```

```php
<?php
// app/Notifications/TaskReminderNotification.php
namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification
{
    public function __construct(
        public readonly \App\Models\Reminder $reminder,
        public readonly \Illuminate\Support\Collection $devices,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'fcm']; // 'fcm' قناة مخصّصة عبر حزمة kreait/laravel-firebase أو مشابه
    }

    public function toDatabase($notifiable): array
    {
        return ['reminderId' => $this->reminder->id, 'message' => $this->reminder->message, 'taskId' => $this->reminder->task_id];
    }

    public function toFcm($notifiable): \Kreait\Firebase\Messaging\CloudMessage
    {
        $tokens = $this->devices->pluck('fcm_token');
        return \Kreait\Firebase\Messaging\CloudMessage::new()
            ->withNotification(['title' => 'تذكير', 'body' => $this->reminder->message])
            ->withData(['taskId' => (string) $this->reminder->task_id]);
        // الإرسال الفعلي لكل التوكنات عبر Messaging::sendMulticast() داخل Channel مخصّص
    }
}
```

**جدولة الأمر** (`routes/console.php` في Laravel 11):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:dispatch-due')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
```

> **لماذا ليس Job مباشر عند إنشاء التذكير؟** لأن `scheduled_for` قد يكون بعد أيام — لا يُعقل إبقاء Job نائماً في الـ Queue لهذه المدة. الـ Scheduler هو الحل الصحيح لأي حدث "في المستقبل غير المحدد زمنياً بدقة قريبة".

---

## 7.5 تسجيل الجهاز (`DeviceController`) — مطابقة §12.6 في الوثيقة

```php
<?php
// app/Http/Controllers/Api/V1/DeviceController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Device\StoreDeviceRequest;
use App\Models\Device;
use App\Traits\ApiResponse;

class DeviceController
{
    use ApiResponse;

    public function store(StoreDeviceRequest $request)
    {
        // Upsert حسب (user_id, deviceId) كما ينص §12.6 — لا يُنشأ سجل مكرر عند إعادة تسجيل نفس الجهاز
        $device = Device::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_id' => $request->deviceId],
            ['fcm_token' => $request->fcmToken, 'platform' => $request->platform]
        );

        return $this->success([
            'deviceId' => $device->device_id,
            'fcmToken' => $device->fcm_token,
            'platform' => $device->platform,
            'lastSyncedAt' => $device->last_synced_at?->toIso8601String(),
        ]);
    }
}
```

```php
<?php
// app/Http/Requests/Device/StoreDeviceRequest.php
namespace App\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'deviceId' => ['required', 'string', 'max:100'],
            'fcmToken' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'in:android,ios'],
        ];
    }
}
```

---

## 8. المزامنة (Sync) — Push / Pull / Conflicts

```php
<?php
// app/Services/SyncService.php (مقتطف push)
namespace App\Services;

use App\Models\{Task, StudyPlan, SportSession, Category, Reminder, SyncConflict};
use Illuminate\Support\Str;

class SyncService
{
    private array $entityModels = [
        'task' => Task::class,
        'study_plan' => StudyPlan::class,
        'sport_session' => SportSession::class,
        'category' => Category::class,
        'reminder' => Reminder::class,
    ];

    public function push(int $userId, string $deviceId, array $changes): array
    {
        $accepted = [];
        $conflicts = [];

        foreach ($changes as $change) {
            $modelClass = $this->entityModels[$change['entity']] ?? null;
            if (!$modelClass) {
                throw new \App\Exceptions\ApiException('INVALID_ENTITY_TYPE', 'entity type غير مدعوم', 400);
            }

            $existing = $modelClass::withTrashed()->find($change['id']);
            $incomingUpdatedAt = \Carbon\Carbon::parse($change['updatedAt']);

            // Last-Write-Wins: إن كانت نسخة الخادم أحدث، يُرفض تغيير العميل ويُسجَّل تعارض
            if ($existing && $existing->updated_at->gt($incomingUpdatedAt)) {
                $conflict = SyncConflict::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                    'entity' => $change['entity'],
                    'entity_id' => $change['id'],
                    'client_data' => $change['data'],
                    'server_data' => $existing->toArray(),
                    'detected_at' => now(),
                ]);

                $conflicts[] = [
                    'id' => $change['id'],
                    'entity' => $change['entity'],
                    'reason' => 'SERVER_VERSION_NEWER',
                    'serverData' => $existing->toArray(),
                ];
                continue;
            }

            match ($change['operation']) {
                'create', 'update' => $modelClass::updateOrCreate(
                    ['id' => $change['id']],
                    [...$change['data'], 'user_id' => $userId, 'updated_at' => $incomingUpdatedAt]
                ),
                'delete' => $existing?->delete(), // Soft delete — لا حذف صامت نهائي
            };

            $accepted[] = $change['id'];
        }

        \App\Models\Device::updateOrCreate(
            ['user_id' => $userId, 'device_id' => $deviceId],
            ['last_synced_at' => now()]
        );

        return ['accepted' => $accepted, 'conflicts' => $conflicts, 'syncedAt' => now()->toIso8601String()];
    }

    public function pull(int $userId, ?string $since, ?string $sinceId, int $pageSize = 200): array
    {
        // Tie-breaker: عندما تتساوى قيم updated_at لعدة سجلات (شائع مع كتابة دفعية أو
        // دقة ثوانٍ)، الاعتماد على updated_at وحده يفقد أو يكرر سجلات بين صفحات الـ pull.
        // لذا نطابق §12.2 حرفياً: (updated_at > since) OR (updated_at = since AND id > sinceId).
        $query = fn (string $model) => $model::withTrashed()
            ->where('user_id', $userId)
            ->when($since, function ($q) use ($since, $sinceId) {
                $q->where(function ($q2) use ($since, $sinceId) {
                    $q2->where('updated_at', '>', $since);
                    if ($sinceId) {
                        $q2->orWhere(fn ($q3) => $q3->where('updated_at', $since)->where('id', '>', $sinceId));
                    }
                });
            })
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($pageSize + 1);

        $changes = [];
        foreach ($this->entityModels as $entity => $model) {
            foreach ($query($model)->get() as $record) {
                $changes[] = [
                    'entity' => $entity,
                    'operation' => $record->trashed() ? 'delete' : 'update',
                    'id' => $record->id,
                    'data' => $record->toArray(),
                    'updatedAt' => $record->updated_at->toIso8601String(),
                ];
            }
        }

        $hasMore = count($changes) > $pageSize;
        $page = array_slice($changes, 0, $pageSize);
        $last = end($page);

        return [
            'changes' => $page,
            'syncedAt' => $last ? $last['updatedAt'] : ($since ?? now()->toIso8601String()),
            'syncedAtId' => $last ? $last['id'] : $sinceId,
            'hasMore' => $hasMore,
        ];
    }
}
```

`SyncController::resolveConflict()` يطبّق القيم الثلاث (`keep_client` / `keep_server` / `merge`) بتحديث السجل الفعلي ثم `status = resolved` على `sync_conflicts` — بنفس بنية الاستجابة الموثقة في القسم 12.4.2.

---

## 9. المسارات (Routes) + الحماية

```php
<?php
// routes/api.php
use App\Http\Controllers\Api\V1\{AuthController, TaskController, StudyPlanController,
    SportSessionController, CategoryController, ReminderController, ProgressController, SyncController, DeviceController};

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    Route::middleware(['auth:sanctum', 'throttle:api-user'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('devices', [DeviceController::class, 'store']);

        Route::apiResource('tasks', TaskController::class);
        Route::patch('tasks/{task}/complete', [TaskController::class, 'complete']);

        Route::apiResource('study-plans', StudyPlanController::class)->except(['show']);
        Route::get('study-plans/{studyPlan}', [StudyPlanController::class, 'show']);
        Route::get('study-plans/{studyPlan}/progress', [StudyPlanController::class, 'progress']);

        Route::apiResource('sport-sessions', SportSessionController::class);
        Route::patch('sport-sessions/{sportSession}/complete', [SportSessionController::class, 'complete']);

        Route::apiResource('categories', CategoryController::class)->except(['show']);

        Route::get('tasks/{task}/reminders', [ReminderController::class, 'forTask']);
        Route::post('reminders', [ReminderController::class, 'store']);
        Route::patch('reminders/{reminder}', [ReminderController::class, 'update']);
        Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy']);

        Route::get('progress/study/{period}', [ProgressController::class, 'study'])
            ->whereIn('period', ['weekly', 'monthly', 'range']);
        Route::get('progress/sport/{period}', [ProgressController::class, 'sport'])
            ->whereIn('period', ['weekly', 'monthly', 'range']);

        Route::post('sync/push', [SyncController::class, 'push']);
        Route::get('sync/pull', [SyncController::class, 'pull']);
        Route::get('sync/status', [SyncController::class, 'status']);
        Route::get('sync/conflicts', [SyncController::class, 'conflicts']);
        Route::post('sync/conflicts/{conflict}/resolve', [SyncController::class, 'resolve']);
        Route::post('sync/conflicts/resolve-all', [SyncController::class, 'resolveAll']);
    });
});
```

`RouteServiceProvider` / `bootstrap/app.php`:

```php
RateLimiter::for('api-user', function (Request $request) {
    return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
});
```

`bootstrap/app.php` (حد حجم Body 1MB + إجبار JSON):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \App\Http\Middleware\ForceJsonResponse::class,
        \App\Http\Middleware\SetLocaleFromHeader::class, // من Accept-Language
    ]);
})
```

```ini
; php.ini أو .htaccess / nginx: تحديد الحد الأقصى فعلياً
post_max_size = 1M
upload_max_filesize = 1M
```

---

## 10. الاختبارات (Feature Tests)

```php
<?php
// tests/Feature/TaskCompletionTest.php
use App\Models\{User, Task, StudyProgressSnapshot};

test('إكمال مهمة دراسية يحدّث study_progress_snapshots تلقائياً', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['type' => 'study', 'due_date' => today(), 'is_completed' => false]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/tasks/{$task->id}/complete", ['isCompleted' => true])
        ->assertOk()
        ->assertJsonPath('data.isCompleted', true);

    expect(StudyProgressSnapshot::where('user_id', $user->id)->where('date', today())->first()->tasks_completed)
        ->toBe(1);
});

test('لا يمكن لمستخدم الوصول لمهمة مستخدم آخر', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    $this->actingAs($intruder, 'sanctum')
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

test('حذف تصنيف مستخدم في مهام يفصل category_id تلقائياً بدل رفض الطلب', function () {
    $user = User::factory()->create();
    $category = \App\Models\Category::factory()->for($user)->create();
    $task = Task::factory()->for($user)->create(['category_id' => $category->id]);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();

    expect($task->fresh()->category_id)->toBeNull();
});

test('مهمة جديدة تظهر فوراً في تبويب "الكل" وفي تبويب نوعها الخاص', function () {
    $user = User::factory()->create();

    $created = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tasks', ['title' => 'جري صباحي', 'type' => 'sport'])
        ->assertCreated()
        ->json('data.id');

    // تبويب "الكل" — بدون type
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks')
        ->assertOk()->assertJsonFragment(['id' => $created]);

    // تبويب "رياضة" — type=sport
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=sport')
        ->assertOk()->assertJsonFragment(['id' => $created]);

    // لا تظهر في تبويب "دراسة"
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=study')
        ->assertOk()->assertJsonMissing(['id' => $created]);
});

test('تعديل نوع المهمة ينقلها من تبويب إلى آخر فوراً', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['type' => 'sport']);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/tasks/{$task->id}", ['type' => 'other'])
        ->assertOk();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=sport')
        ->assertOk()->assertJsonMissing(['id' => $task->id]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=other')
        ->assertOk()->assertJsonFragment(['id' => $task->id]);
});

test('عرض تفاصيل مهمة يعيد نفس بنية عنصر القائمة', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create();

    $this->actingAs($user, 'sanctum')->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('data.id', $task->id);
});

test('حذف مهمة يزيلها من كل التبويبات ومن التفاصيل (404)', function () {
    $user = User::factory()->create();
    $task = Task::factory()->for($user)->create(['type' => 'study']);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks')
        ->assertOk()->assertJsonMissing(['id' => $task->id]);

    $this->actingAs($user, 'sanctum')->getJson("/api/v1/tasks/{$task->id}")
        ->assertNotFound()->assertJsonPath('error.code', 'TASK_NOT_FOUND');
});
```

---

## 11. خطوات التشغيل (Ready-to-Run)

```bash
# 1) تنزيل الاعتماديات
composer create-project laravel/laravel backend "^11.0"
cd backend
composer require laravel/sanctum kreait/laravel-firebase predis/predis

# 2) الإعداد
cp .env.example .env
php artisan key:generate

# .env — القيم الأساسية
# DB_CONNECTION=mysql
# DB_DATABASE=daily_tasks
# QUEUE_CONNECTION=redis
# CACHE_STORE=redis
# SANCTUM_STATEFUL_DOMAINS=
# FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json

# 3) قاعدة البيانات
php artisan migrate --seed

# 4) تشغيل الخدمات (3 عمليات منفصلة في الإنتاج، أو عبر Supervisor)
php artisan serve                 # أو php-fpm + nginx في الإنتاج
php artisan queue:work redis --queue=notifications,default --tries=3
php artisan schedule:work         # في التطوير — Cron حقيقي في الإنتاج (* * * * * php artisan schedule:run)
```

### إعداد Supervisor للإنتاج (`/etc/supervisor/conf.d/laravel-worker.conf`)

```ini
[program:laravel-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/backend/artisan queue:work redis --queue=notifications,default --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2
```

### Cron حقيقي للـ Scheduler (`crontab -e`)

```
* * * * * cd /var/www/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 12. مطابقة نهائية مع الوثائق المرفقة

| المرجع | كيف تحقق البنية أعلاه المطابقة |
|---|---|
| `api_documentation.md` §2–4 | `ApiResponse` Trait + `Handler::render()` ينتجان نفس Envelope وأكواد الأخطاء حرفياً |
| `api_documentation.md` §12.5 | UUID كمصدر حقيقة محلي، Soft Delete، Last-Write-Wins بـ `updated_at`، لا حذف صامت |
| `code_standards.md` §4 | أكواد `Failure`/`ApiException` مطابقة لجدول الأخطاء — أي كود جديد يُضاف للوثيقة أولاً |
| `ai_workflow.md` §4.2 | كل تغيير في `is_completed` يمرّ حصراً عبر `ProgressService` (لا تحديث مباشر في Controller) |
| `ai_workflow.md` §9 | لا حساب Progress من `tasks` مباشرة عند القراءة — فقط من الـ Snapshots |
| `api_documentation.md` §5.4 | `POST /auth/logout` مُسجَّل الآن كـ Route محمي بـ `auth:sanctum` ويُبطل access token الحالي فقط |
| `api_documentation.md` §12.2 | `SyncService::pull()` يقبل `sinceId` كـ Tie-breaker ويعيد `syncedAtId`، يطابق حرفياً استراتيجية منع فقدان/تكرار السجلات |
| `api_documentation.md` §12.6 | `DeviceController::store()` مُضاف كـ Upsert على `(user_id, deviceId)`، مربوط بمسار `POST /devices` |

**قبل أي إضافة مستقبلية لحقل أو Endpoint جديد**: حدّث `api_documentation.md` أولاً، ثم طابق التغيير هنا في الجداول/الـ Controllers — نفس القاعدة الصارمة المعتمدة في المشروع.
