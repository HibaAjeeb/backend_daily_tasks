# وثيقة API — تطبيق المهام اليومية

## 1. نظرة عامة

| | |
|---|---|
| **Base URL (إنتاج)** | `https://api.dailytasksapp.com/v1` |
| **Base URL (محلي)** | `http://localhost:8000/api/v1` |
| **بروتوكول** | HTTPS فقط |
| **صيغة البيانات** | JSON فقط (`Content-Type: application/json`) |
| **المصادقة** | Bearer Token (JWT) |
| **الترميز** | UTF-8 |

> **ملاحظة IDs**: جميع معرّفات السجلات (مستخدم، مهمة، خطة، تصنيف، جلسة، إشعار، تعارض) هي **UUID** وليست نصوصًا مثل `2b3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d6e`. استخدم المعرّف الكامل الذي يعيده الخادم كما هو.
>
> **ملاحظة Postman**: عند الاختبار عبر Postman اختر `Body → raw → JSON` وليس `Text`، وإلا فلن يقرأ الخادم الحقول وستحصل على `422`. عند إرسال JSON غير صالح تُعاد استجابة `400 INVALID_JSON`. يوجد ملف مجموعة جاهز في `postman/` مع تعليمات في `postman/README.md`.

### الهيدرز المطلوبة في كل طلب (باستثناء تسجيل الدخول والتسجيل)
```
Authorization: Bearer <access_token>
Content-Type: application/json
Accept-Language: ar | en   (اختياري — لتحديد لغة رسائل الخطأ)
```

---

## 2. تنسيق الاستجابة الموحد (Response Envelope)

### استجابة ناجحة
```json
{
  "success": true,
  "data": { },
  "meta": {
    "timestamp": "2026-09-10T14:23:00Z"
  }
}
```

### استجابة تحتوي قائمة (Pagination)
```json
{
  "success": true,
  "data": [ ],
  "meta": {
    "page": 1,
    "pageSize": 20,
    "totalItems": 57,
    "totalPages": 3
  }
}
```

### استجابة خطأ
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "بيانات الطلب غير صحيحة",
    "details": [
      { "field": "title", "issue": "title is required" }
    ]
  },
  "meta": {
    "timestamp": "2026-09-10T14:23:00Z",
    "requestId": "req_8f3ac2"
  }
}
```

---

## 3. رموز الحالة العامة (HTTP Status Codes)

| Status | المعنى | متى يُستخدم |
|---|---|---|
| 200 OK | نجاح | GET / PUT / PATCH ناجح |
| 201 Created | تم الإنشاء | POST ناجح |
| 204 No Content | تم الحذف | DELETE ناجح (بدون body) |
| 400 Bad Request | خطأ في صياغة الطلب | JSON غير صالح أو حقول ناقصة |
| 401 Unauthorized | التوكن مفقود/منتهي | عدم إرسال Authorization أو انتهاء صلاحيته |
| 403 Forbidden | لا صلاحية | محاولة الوصول لمورد يخص مستخدم آخر |
| 404 Not Found | المورد غير موجود | id غير موجود في قاعدة البيانات |
| 409 Conflict | تعارض بيانات | مثال: تصنيف بنفس الاسم موجود مسبقاً |
| 422 Unprocessable Entity | فشل التحقق من صحة البيانات | مثال: تاريخ نهاية قبل تاريخ البداية |
| 429 Too Many Requests | تجاوز الحد المسموح | Rate limiting |
| 500 Internal Server Error | خطأ في الخادم | خطأ غير متوقع |

---

## 4. رموز الأخطاء المخصصة (Error Codes)

| Code | Status | الوصف |
|---|---|---|
| `VALIDATION_ERROR` | 400/422 | فشل التحقق من الحقول المُرسَلة |
| `INVALID_JSON` | 400 | جسم الطلب ليس JSON صالحًا (تحقق من `Body → raw → JSON` في Postman) |
| `UNAUTHORIZED` | 401 | التوكن غير موجود أو غير صالح |
| `TOKEN_EXPIRED` | 401 | انتهت صلاحية access token (يُعاد أيضاً عند استخدام access token منتهٍ في أي مسار محمي) |
| `FORBIDDEN` | 403 | المستخدم لا يملك صلاحية الوصول لهذا المورد |
| `TASK_NOT_FOUND` | 404 | المهمة غير موجودة |
| `STUDY_PLAN_NOT_FOUND` | 404 | الخطة الدراسية غير موجودة |
| `SPORT_SESSION_NOT_FOUND` | 404 | جلسة الرياضة غير موجودة |
| `CATEGORY_NOT_FOUND` | 404 | التصنيف غير موجود |
| `REMINDER_NOT_FOUND` | 404 | الإشعار غير موجود |
| `DUPLICATE_CATEGORY` | 409 | يوجد تصنيف بنفس الاسم مسبقاً |
| `INVALID_DATE_RANGE` | 422 | تاريخ النهاية أقبل من تاريخ البداية |
| `INVALID_RECURRENCE_RULE` | 422 | قاعدة التكرار غير صحيحة |
| `INVALID_ENTITY_TYPE` | 400 | نوع الكيان في طلب المزامنة غير مدعوم |
| `SYNC_CONFLICT` | 409 | تعارض بين نسخة العميل ونسخة الخادم (يُعاد ضمن تفاصيل الاستجابة، ليس كخطأ حاجب) |
| `CONFLICT_NOT_FOUND` | 404 | التعارض المطلوب حله غير موجود |
| `CONFLICT_ALREADY_RESOLVED` | 409 | تم حل هذا التعارض مسبقاً |
| `RATE_LIMIT_EXCEEDED` | 429 | عدد الطلبات تجاوز الحد المسموح خلال فترة زمنية |
| `INTERNAL_ERROR` | 500 | خطأ غير متوقع في الخادم |

---

## 5. المصادقة (Authentication)

### 5.1 تسجيل حساب جديد
`POST /auth/register`

**Request Body**
```json
{
  "name": "أحمد",
  "email": "ahmad@example.com",
  "password": "StrongPass123"
}
```

**قواعد التحقق**
- `name`: إلزامي، نص حتى 150 حرفًا.
- `email`: صيغة بريد صحيحة، وغير مستخدم مسبقاً.
- `password`: 8 أحرف على الأقل، ويحتوي على حرف كبير واحد ورقم واحد على الأقل (مثال صالح: `StrongPass123`).

**استجابة النجاح (201)**
```json
{
  "success": true,
  "data": {
    "userId": "9f1c2d3e-4a5b-4c6d-8e9f-0a1b2c3d4e5f",
    "name": "أحمد",
    "email": "ahmad@example.com",
    "accessToken": "eyJhbGciOi...",
    "refreshToken": "dGhpcyBpcyBh..."
  }
}
```

**أخطاء محتملة**
| Code | Status | مثال الرسالة |
|---|---|---|
| `VALIDATION_ERROR` | 422 | "كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل" |
| `EMAIL_ALREADY_EXISTS` | 409 | "البريد الإلكتروني مستخدم مسبقاً" |

### 5.2 تسجيل الدخول
`POST /auth/login`

**Request Body**
```json
{ "email": "ahmad@example.com", "password": "StrongPass123" }
```

**استجابة النجاح (200)**
```json
{
  "success": true,
  "data": {
    "accessToken": "eyJhbGciOi...",
    "refreshToken": "dGhpcyBpcyBh...",
    "expiresIn": 3600
  }
}
```

**أخطاء محتملة**
| Code | Status | الرسالة |
|---|---|---|
| `INVALID_CREDENTIALS` | 401 | "البريد الإلكتروني أو كلمة المرور غير صحيحة" |

### 5.3 تجديد التوكن
`POST /auth/refresh`

**Request Body**
```json
{ "refreshToken": "dGhpcyBpcyBh..." }
```

**استجابة النجاح (200)**: نفس صيغة تسجيل الدخول.
**خطأ**: `TOKEN_EXPIRED` (401) إذا انتهت صلاحية refresh token → يتطلب تسجيل دخول جديد.

### 5.4 تسجيل الخروج
`POST /auth/logout`

يتطلب ترويسة `Authorization: Bearer {accessToken}` ولا يحتوي على body.
يُلغي access token الحالي ويعيد استجابة نجاح عامة.

---

## 6. Tasks — المهام

### 6.1 جلب قائمة المهام
`GET /tasks`

**Query Parameters**
| المعامل | النوع | إلزامي | الوصف |
|---|---|---|---|
| `type` | string | لا | `study` \| `sport` \| `other` |
| `isCompleted` | boolean | لا | فلترة حسب الحالة |
| `categoryId` | string | لا | فلترة حسب التصنيف |
| `dueDateFrom` | date | لا | بداية نطاق التاريخ |
| `dueDateTo` | date | لا | نهاية نطاق التاريخ |
| `page` | int | لا | افتراضي 1 |
| `pageSize` | int | لا | افتراضي 20، أقصى 100 |

**استجابة النجاح (200)**
```json
{
  "success": true,
  "data": [
    {
      "id": "1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d",
      "title": "مراجعة الفصل الثالث",
      "description": null,
      "type": "study",
      "dueDate": "2026-09-12",
      "scheduledTime": "16:00",
      "durationMinutes": 45,
      "priority": "high",
      "isCompleted": false,
      "isRecurring": false,
      "categoryId": "2b3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d6e",
      "parentPlanId": "3c4d5e6f-7a8b-4c9d-0e1f-2a3b4c5d6e7f",
      "createdAt": "2026-09-01T10:00:00Z"
    }
  ],
  "meta": { "page": 1, "pageSize": 20, "totalItems": 12, "totalPages": 1 }
}
```

### 6.2 جلب مهمة واحدة
`GET /tasks/{id}`

**استجابة النجاح (200)**: نفس بنية عنصر واحد من القائمة أعلاه.
**خطأ (404)**: `TASK_NOT_FOUND` — "المهمة المطلوبة غير موجودة"

### 6.3 إنشاء مهمة جديدة
`POST /tasks`

**Request Body**
```json
{
  "title": "حل تمارين الرياضيات",
  "description": "الفصل الرابع",
  "type": "study",
  "dueDate": "2026-09-13",
  "scheduledTime": "18:00",
  "durationMinutes": 45,
  "priority": "medium",
  "isRecurring": false,
  "categoryId": "2b3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d6e",
  "parentPlanId": "3c4d5e6f-7a8b-4c9d-0e1f-2a3b4c5d6e7f"
}
```

**قواعد التحقق**
- `title`: إلزامي، 1–150 حرف.
- `type`: إلزامي، من القيم المحددة فقط.
- `priority`: اختياري، من `low|medium|high` (الافتراضي `medium`).
- `durationMinutes`: اختياري، عدد صحيح أكبر من صفر — يُستخدم لحساب `studyMinutes` في لقطات تقدم الدراسة.
- `categoryId` / `parentPlanId`: اختياريان، وإن وُجدا يجب أن يكونا مرتبطين بسجل موجود فعلاً وإلا يُعاد `404` بالكود المناسب.

**استجابة النجاح (201)**: كائن المهمة كاملاً بعد الإنشاء.

**أخطاء محتملة**
| Code | Status | الرسالة |
|---|---|---|
| `VALIDATION_ERROR` | 400 | "title is required" |
| `CATEGORY_NOT_FOUND` | 404 | "التصنيف المحدد غير موجود" |
| `STUDY_PLAN_NOT_FOUND` | 404 | "الخطة الدراسية المحددة غير موجودة" |

### 6.4 تعديل مهمة
`PUT /tasks/{id}`
نفس بنية الإنشاء، لكن جميع الحقول اختيارية (partial update مسموح فعلياً كـ PATCH semantics).
**خطأ (404)**: `TASK_NOT_FOUND`

### 6.5 تحديد مهمة كمكتملة / غير مكتملة
`PATCH /tasks/{id}/complete`

**Request Body**
```json
{ "isCompleted": true }
```

**استجابة النجاح (200)**
```json
{
  "success": true,
  "data": {
    "id": "1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d",
    "isCompleted": true,
    "completedAt": "2026-09-10T14:30:00Z"
  }
}
```
> **ملاحظة تنفيذية**: هذا الـ endpoint هو الوحيد الذي يُطلق تحديث `study_progress_snapshots` (عندما `type = study`) — راجع وثيقة نموذج البيانات، آلية المخطط المجمّع.

**خطأ (404)**: `TASK_NOT_FOUND`

### 6.6 حذف مهمة
`DELETE /tasks/{id}`

**استجابة النجاح**: `204 No Content`
**خطأ (404)**: `TASK_NOT_FOUND`

---

## 7. Study Plans — الخطط الدراسية

### 7.1 جلب كل الخطط
`GET /study-plans`
**Query**: `page`, `pageSize`

### 7.2 جلب خطة واحدة مع مهامها
`GET /study-plans/{id}`
```json
{
  "success": true,
  "data": {
    "id": "3c4d5e6f-7a8b-4c9d-0e1f-2a3b4c5d6e7f",
    "subjectName": "الرياضيات",
    "goal": "إنهاء المنهج قبل الامتحان",
    "startDate": "2026-09-01",
    "endDate": "2026-10-15",
    "totalTasks": 20,
    "completedTasks": 8,
    "progressPercentage": 40.0,
    "tasks": [ ]
  }
}
```
**خطأ (404)**: `STUDY_PLAN_NOT_FOUND`

### 7.3 إنشاء خطة دراسية
`POST /study-plans`
```json
{
  "subjectName": "الرياضيات",
  "goal": "إنهاء المنهج قبل الامتحان",
  "startDate": "2026-09-01",
  "endDate": "2026-10-15"
}
```
**قواعد التحقق**
- `subjectName`: إلزامي.
- `endDate` إن وُجد يجب أن يكون بعد `startDate`، وإلا: `INVALID_DATE_RANGE` (422).

**استجابة النجاح**: `201` بكائن الخطة.

### 7.4 تعديل / حذف خطة
`PUT /study-plans/{id}` | `DELETE /study-plans/{id}`
- الحذف يتطلب تنبيه المستخدم مسبقاً في الواجهة أنه سيحذف المهام المرتبطة أو يفصلها (`parentPlanId = null`) — يُحدَّد عبر `?cascade=true|false` كـ query param، الافتراضي `false`.

### 7.5 تقدم خطة محددة (لتغذية المخطط البياني)
`GET /study-plans/{id}/progress?range=week|month`
```json
{
  "success": true,
  "data": {
    "planId": "3c4d5e6f-7a8b-4c9d-0e1f-2a3b4c5d6e7f",
    "range": "week",
    "points": [
      { "date": "2026-09-04", "tasksCompleted": 2, "tasksPlanned": 3 },
      { "date": "2026-09-05", "tasksCompleted": 1, "tasksPlanned": 1 }
    ]
  }
}
```
> هذا الـ endpoint خاص بخطة واحدة، لذلك تُحتسب نقاطه فورياً من مهام الخطة نفسها (`parentPlanId`) مجمّعة حسب `dueDate` — بخلاف `/progress/*` الذي يقرأ حصراً من اللقطات المجمّعة. `range=week` يعني الأسبوع الحالي، و`range=month` يعني الشهر الحالي (الافتراضي `week`).

---

## 8. Sport Sessions — جلسات الرياضة

### 8.1 جلب الجلسات
`GET /sport-sessions?dateFrom=&dateTo=&isCompleted=`

### 8.2 إنشاء جلسة رياضية
`POST /sport-sessions`
```json
{
  "exerciseName": "جري",
  "date": "2026-09-11",
  "scheduledTime": "07:00",
  "durationMinutes": 30
}
```
**قواعد التحقق**: `durationMinutes` > 0، `date` لا يمكن أن يكون في الماضي عند الإنشاء (اختياري حسب سياسة التطبيق).

### 8.3 تحديد جلسة كمكتملة
`PATCH /sport-sessions/{id}/complete`
```json
{ "isCompleted": true, "actualDurationMinutes": 28 }
```
> يُحدّث `sport_minutes` في `sport_progress_snapshots` لنفس اليوم (جدول مستقل عن الدراسة).

**خطأ (404)**: `SPORT_SESSION_NOT_FOUND`

### 8.4 تعديل / حذف
`PUT /sport-sessions/{id}` | `DELETE /sport-sessions/{id}`

---

## 9. Categories — التصنيفات

### 9.1 جلب التصنيفات
`GET /categories`

### 9.2 إنشاء تصنيف
`POST /categories`
```json
{ "name": "دراسة", "colorValue": "#4CAF50", "icon": "book" }
```
**خطأ**: `DUPLICATE_CATEGORY` (409) إذا كان الاسم موجوداً مسبقاً لنفس المستخدم.

### 9.3 تعديل / حذف
`PUT /categories/{id}` | `DELETE /categories/{id}`
- التعديل جزئي (PATCH semantics): يمكن إرسال `name` أو `colorValue` أو `icon` فقط، وأي حقل لا يُرسَل يبقى كما هو.
- عند حذف تصنيف مستخدم في مهام: الحقل `categoryId` في تلك المهام يُصبح `null` تلقائياً (لا يُحذف الطلب، سلوك متوقع يُذكر في الرد).

---

## 10. Reminders — الإشعارات

### 10.1 جلب إشعارات مهمة معينة
`GET /tasks/{taskId}/reminders`

### 10.2 إنشاء إشعار
`POST /reminders`
```json
{
  "taskId": "1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d",
  "scheduledFor": "2026-09-20T15:45:00Z",
  "message": "لا تنسَ: مراجعة الفصل الثالث",
  "repeat": "none"
}
```
**قواعد التحقق**: `scheduledFor` يجب أن يكون في المستقبل، وإلا `VALIDATION_ERROR` (400) — "scheduledFor must be a future date".
**خطأ**: `TASK_NOT_FOUND` (404) إذا لم توجد المهمة المرتبطة.

### 10.3 تفعيل/تعطيل إشعار
`PATCH /reminders/{id}`
```json
{ "isEnabled": false }
```

### 10.4 حذف إشعار
`DELETE /reminders/{id}`

---

## 11. Progress — التقدم (المخططات البيانية)

تم الحسم: **لقطتان منفصلتان** — كل endpoint هنا يقرأ إما من `study_progress_snapshots` أو من `sport_progress_snapshots` (جدولان مستقلان تماماً، بدون إعادة حساب من `tasks`/`sport_sessions`).

> `studyMinutes` تُجمع من حقل `durationMinutes` للمهام الدراسية المكتملة في ذلك اليوم. المهام بدون `durationMinutes` لا تُضيف دقائق (قيمتها صفر).

### 11.1 تقدم الدراسة — أسبوعي
`GET /progress/study/weekly?weekOffset=0`
- `weekOffset=0` الأسبوع الحالي، `-1` الأسبوع الماضي، وهكذا.

```json
{
  "success": true,
  "data": {
    "range": { "from": "2026-09-07", "to": "2026-09-13" },
    "points": [
      { "date": "2026-09-07", "tasksCompleted": 3, "tasksPlanned": 4, "studyMinutes": 90 }
    ],
    "summary": { "completionRate": 75.0, "totalStudyMinutes": 320 }
  }
}
```

### 11.2 تقدم الدراسة — شهري / نطاق مخصص
`GET /progress/study/monthly?month=2026-09`
`GET /progress/study/range?from=2026-09-01&to=2026-09-10`
**خطأ**: `INVALID_DATE_RANGE` (422) إذا `to` قبل `from`.

### 11.3 تقدم الرياضة — أسبوعي
`GET /progress/sport/weekly?weekOffset=0`
```json
{
  "success": true,
  "data": {
    "range": { "from": "2026-09-07", "to": "2026-09-13" },
    "points": [
      { "date": "2026-09-07", "sessionsCompleted": 1, "sessionsPlanned": 1, "sportMinutes": 30 }
    ],
    "summary": { "completionRate": 100.0, "totalSportMinutes": 150 }
  }
}
```

### 11.4 تقدم الرياضة — شهري / نطاق مخصص
`GET /progress/sport/monthly?month=2026-09`
`GET /progress/sport/range?from=2026-09-01&to=2026-09-10`
**خطأ**: `INVALID_DATE_RANGE` (422) إذا `to` قبل `from`.

---

## 12. Sync — المزامنة (Offline-First)

التطبيق يعمل محلياً أولاً (Local-First): كل عملية تُنفَّذ فوراً على `sqflite` بغض النظر عن الاتصال، وتُزامَن لاحقاً عبر endpoints التالية. الحقول `syncStatus` و`updatedAt` موجودة على كل سجل قابل للمزامنة (Task, StudyPlan, SportSession, Category, Reminder).

### 12.1 دفع التغييرات المحلية
`POST /sync/push`

**Request Body**
```json
{
  "deviceId": "dev_a1b2c3",
  "changes": [
    {
      "entity": "task",
      "operation": "update",
      "id": "1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d",
      "data": { "isCompleted": true, "title": "مراجعة الفصل الثالث" },
      "updatedAt": "2026-09-10T14:20:00Z"
    },
    {
      "entity": "sport_session",
      "operation": "create",
      "id": "4d5e6f7a-8b9c-4d0e-1f2a-3b4c5d6e7f8a",
      "data": { "exerciseName": "جري", "date": "2026-09-10", "durationMinutes": 30 },
      "updatedAt": "2026-09-10T14:22:00Z"
    }
  ]
}
```

**قواعد التحقق**
- `entity`: من `task | study_plan | sport_session | category | reminder`.
- `operation`: من `create | update | delete`.
- `updatedAt`: إلزامي لكل تغيير — أساس حل التعارض.

**استجابة النجاح (200)**
```json
{
  "success": true,
  "data": {
    "accepted": ["1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d", "4d5e6f7a-8b9c-4d0e-1f2a-3b4c5d6e7f8a"],
    "conflicts": [
      {
        "id": "5e6f7a8b-9c0d-4e1f-2a3b-4c5d6e7f8a9b",
        "entity": "task",
        "reason": "SERVER_VERSION_NEWER",
        "serverData": { "title": "حل تمارين الرياضيات", "updatedAt": "2026-09-10T14:25:00Z" }
      }
    ],
    "syncedAt": "2026-09-10T14:30:00Z"
  }
}
```

**استراتيجية حل التعارض**: Last-Write-Wins بمقارنة `updatedAt`. إذا كانت نسخة الخادم أحدث، يُرفض تغيير العميل ويُعاد ضمن `conflicts` مع بيانات الخادم الحالية ليقرر التطبيق (تحديث محلي تلقائي أو عرضه للمستخدم).

**أخطاء محتملة**
| Code | Status | الرسالة |
|---|---|---|
| `VALIDATION_ERROR` | 400 | "updatedAt is required for each change" |
| `INVALID_ENTITY_TYPE` | 400 | "entity type غير مدعوم" |

### 12.2 سحب التغييرات من الخادم
`GET /sync/pull?since=2026-09-09T00:00:00Z&sinceId=5e6f7a8b-9c0d-4e1f-2a3b-4c5d6e7f8a9b&deviceId=dev_a1b2c3&pageSize=200`

- `since`: آخر `syncedAt` محفوظ محلياً (من آخر استدعاء push أو pull ناجح). عند أول مزامنة يُرسَل بدون `since` لسحب كل البيانات.
- `sinceId`: آخر `syncedAtId` محفوظ محلياً — يُستخدم مع `since` كمرجع ثانوي (Tie-breaker) عندما تتساوى قيم `updatedAt` لعدة سجلات، لضمان عدم فقدان أي تغيير بين الصفحات.
- `pageSize`: اختياري (افتراضي 200، أقصى 500).

**استجابة النجاح (200)**
```json
{
  "success": true,
  "data": {
    "changes": [
      {
        "entity": "task",
        "operation": "update",
        "id": "5e6f7a8b-9c0d-4e1f-2a3b-4c5d6e7f8a9b",
        "data": { "title": "حل تمارين الرياضيات", "isCompleted": false },
        "updatedAt": "2026-09-10T14:25:00Z"
      }
    ],
    "syncedAt": "2026-09-10T14:31:00Z",
    "syncedAtId": "5e6f7a8b-9c0d-4e1f-2a3b-4c5d6e7f8a9b",
    "hasMore": false
  }
}
```
- `hasMore: true` يعني وجود تغييرات إضافية تتجاوز حد الصفحة (Pagination داخلي). استخدم قيمتي `syncedAt` و`syncedAtId` المُرجعتين في كل استجابة كـ `since` و`sinceId` للاستدعاء التالي (حتى عند `hasMore = true`) — وهذا يمنع تكرار السجلات أو فقدانها بين الصفحات.
- عند عدم وجود تغييرات جديدة تُعاد قيمة `syncedAt` كما أُرسلت في `since` (أو وقت الخادم عند أول مزامنة).

### 12.3 جلب حالة المزامنة الحالية
`GET /sync/status?deviceId=dev_a1b2c3`
```json
{
  "success": true,
  "data": {
    "lastSyncedAt": "2026-09-10T14:31:00Z",
    "pendingConflicts": 1
  }
}
```

### 12.4 حل التعارضات يدوياً

عندما يتعذر حل تعارض تلقائياً عبر Last-Write-Wins (أو عندما يريد المستخدم مراجعة القرار قبل تطبيقه)، يُحفظ التعارض في جدول `sync_conflicts` ويُعرض للمستخدم ليختار الحل يدوياً.

#### 12.4.1 جلب التعارضات المعلّقة
`GET /sync/conflicts?deviceId=dev_a1b2c3&status=pending`

**Query Parameters**
| المعامل | النوع | إلزامي | الوصف |
|---|---|---|---|
| `deviceId` | string | نعم | معرّف الجهاز |
| `status` | string | لا | `pending` \| `resolved` (افتراضي: `pending`) |
| `entity` | string | لا | فلترة حسب نوع الكيان |

**استجابة النجاح (200)**
```json
{
  "success": true,
  "data": [
    {
      "id": "6f7a8b9c-0d1e-4f2a-3b4c-5d6e7f8a9b0c",
      "entity": "task",
      "entityId": "5e6f7a8b-9c0d-4e1f-2a3b-4c5d6e7f8a9b",
      "clientData": {
        "title": "حل تمارين الرياضيات - محدّث",
        "isCompleted": true,
        "updatedAt": "2026-09-10T14:20:00Z"
      },
      "serverData": {
        "title": "حل تمارين الرياضيات",
        "isCompleted": false,
        "updatedAt": "2026-09-10T14:25:00Z"
      },
      "detectedAt": "2026-09-10T14:30:00Z",
      "status": "pending"
    }
  ],
  "meta": { "page": 1, "pageSize": 20, "totalItems": 1, "totalPages": 1 }
}
```

**خطأ**: `VALIDATION_ERROR` (400) إذا `deviceId` مفقود.

#### 12.4.2 حل تعارض محدد يدوياً
`POST /sync/conflicts/{id}/resolve`

**Request Body**
```json
{ "resolution": "keep_client" }
```

**قيم `resolution` الممكنة**
| القيمة | الأثر |
|---|---|
| `keep_client` | نسخة العميل تُعتمد وتُكتب على الخادم |
| `keep_server` | نسخة الخادم تُعتمد وتُرسَل للعميل ليُحدّث نسخته المحلية |
| `merge` | يُرسِل العميل بيانات مدموجة يدوياً ضمن الحقل `mergedData` (اختياري) |

**Request Body عند `merge`**
```json
{
  "resolution": "merge",
  "mergedData": { "title": "حل تمارين الرياضيات - محدّث", "isCompleted": true }
}
```

**قواعد التحقق**
- `resolution`: إلزامي، من القيم الثلاث فقط.
- عند `resolution = merge`: حقل `mergedData` إلزامي.

**استجابة النجاح (200)**
```json
{
  "success": true,
  "data": {
    "id": "6f7a8b9c-0d1e-4f2a-3b4c-5d6e7f8a9b0c",
    "status": "resolved",
    "resolution": "keep_client",
    "finalData": { "title": "حل تمارين الرياضيات - محدّث", "isCompleted": true },
    "resolvedAt": "2026-09-10T14:40:00Z"
  }
}
```

**أخطاء محتملة**
| Code | Status | الرسالة |
|---|---|---|
| `CONFLICT_NOT_FOUND` | 404 | "التعارض المطلوب غير موجود" |
| `CONFLICT_ALREADY_RESOLVED` | 409 | "تم حل هذا التعارض مسبقاً" |
| `VALIDATION_ERROR` | 400 | "mergedData is required when resolution is merge" |

#### 12.4.3 حل جماعي (اختياري — لتسريع تجربة المستخدم)
`POST /sync/conflicts/resolve-all`
```json
{ "resolution": "keep_client", "deviceId": "dev_a1b2c3" }
```
يطبّق نفس القرار (`keep_client` أو `keep_server`) على كل التعارضات `pending` للجهاز المحدد دفعة واحدة. لا يدعم `merge` (يتطلب مراجعة فردية).

**استجابة النجاح (200)**
```json
{ "success": true, "data": { "resolvedCount": 3 } }
```

### 12.5 سياسات المزامنة
| القاعدة | التفصيل |
|---|---|
| مصدر الحقيقة المحلي | `sqflite` هو المصدر الأساسي؛ الخادم نسخة احتياطية/مزامنة فقط |
| حل التعارض | Last-Write-Wins حسب `updatedAt`، بدون حذف صامت — التعارضات تُحفظ وتُعرض |
| تكرار المزامنة | تلقائي عند توفر الاتصال (Background Worker) + زر "مزامنة الآن" يدوي |
| الحذف | Soft Delete محلياً (`deletedAt`) قبل إرساله كـ `operation: delete`، لتفادي فقدان البيانات عند فشل الشبكة |

### 12.6 تسجيل الجهاز / تحديث توكن الإشعارات
`POST /devices`

يربط الجهاز بالمستخدم ويحدّث توكن FCM اللازم لإرسال إشعارات التذكير. العملية Upsert حسب (`deviceId`) لنفس المستخدم.

**Request Body**
```json
{
  "deviceId": "dev_a1b2c3",
  "fcmToken": "eXampleToken...",
  "platform": "android"
}
```

**قواعد التحقق**
- `deviceId`: إلزامي، 1–100 حرف.
- `fcmToken`: اختياري، حتى 255 حرفاً.
- `platform`: اختياري، من `android | ios`.

**استجابة النجاح (200)**
```json
{
  "success": true,
  "data": {
    "deviceId": "dev_a1b2c3",
    "fcmToken": "eXampleToken...",
    "platform": "android",
    "lastSyncedAt": "2026-09-10T14:31:00Z"
  }
}
```

> توكن FCM يُحدَّث في نفس السجل عند تغيّره (مثلاً بعد إعادة تثبيت التطبيق)، ويُستخدم لاحقاً في إرسال التذكيرات عبر `/tasks/{taskId}/reminders`.

---

## 13. القيود العامة والسياسات (Policies)

| السياسة | القيمة |
|---|---|
| Rate Limit | 100 طلب/دقيقة لكل مستخدم |
| أقصى حجم Request Body | 1 MB |
| صلاحية Access Token | 60 دقيقة |
| صلاحية Refresh Token | 30 يوماً |
| Pagination الافتراضي | `page=1, pageSize=20` (أقصى 100) |
| Idempotency | يُنصح بإرسال هيدر `Idempotency-Key` مع طلبات POST الحساسة (مثل إنشاء جلسة دفع مستقبلية إن وُجدت) |

## 14. مثال شامل على معالجة الخطأ في الطرف الأمامي (Flutter)

```dart
Future<T> handleResponse<T>(
  http.Response response,
  T Function(Map<String, dynamic>) fromJson,
) async {
  final body = jsonDecode(response.body) as Map<String, dynamic>;

  if (body['success'] == true) {
    return fromJson(body['data']);
  }

  final error = body['error'] as Map<String, dynamic>;
  final code = error['code'] as String;
  final message = error['message'] as String;

  switch (code) {
    case 'TOKEN_EXPIRED':
      throw SessionExpiredException(message);
    case 'VALIDATION_ERROR':
      throw ValidationException(message, error['details']);
    case 'TASK_NOT_FOUND':
    case 'STUDY_PLAN_NOT_FOUND':
    case 'SPORT_SESSION_NOT_FOUND':
    case 'CATEGORY_NOT_FOUND':
    case 'REMINDER_NOT_FOUND':
      throw NotFoundException(message);
    case 'RATE_LIMIT_EXCEEDED':
      throw RateLimitException(message);
    case 'CONFLICT_NOT_FOUND':
    case 'CONFLICT_ALREADY_RESOLVED':
      throw SyncConflictException(message);
    default:
      throw UnknownApiException(message);
  }
}
```

---

## 15. ملخص جدول Endpoints الكامل

| Method | Path | الوصف |
|---|---|---|
| POST | /auth/register | تسجيل حساب جديد |
| POST | /auth/login | تسجيل الدخول |
| POST | /auth/refresh | تجديد التوكن |
| GET | /tasks | قائمة المهام |
| GET | /tasks/{id} | مهمة واحدة |
| POST | /tasks | إنشاء مهمة |
| PUT | /tasks/{id} | تعديل مهمة |
| PATCH | /tasks/{id}/complete | إكمال/إلغاء إكمال مهمة |
| DELETE | /tasks/{id} | حذف مهمة |
| GET | /study-plans | قائمة الخطط |
| GET | /study-plans/{id} | خطة واحدة مع مهامها |
| POST | /study-plans | إنشاء خطة |
| PUT | /study-plans/{id} | تعديل خطة |
| DELETE | /study-plans/{id} | حذف خطة |
| GET | /study-plans/{id}/progress | تقدم خطة محددة |
| GET | /sport-sessions | قائمة الجلسات |
| POST | /sport-sessions | إنشاء جلسة |
| PATCH | /sport-sessions/{id}/complete | إكمال جلسة |
| PUT | /sport-sessions/{id} | تعديل جلسة |
| DELETE | /sport-sessions/{id} | حذف جلسة |
| GET | /categories | قائمة التصنيفات |
| POST | /categories | إنشاء تصنيف |
| PUT | /categories/{id} | تعديل تصنيف |
| DELETE | /categories/{id} | حذف تصنيف |
| GET | /tasks/{taskId}/reminders | إشعارات مهمة |
| POST | /reminders | إنشاء إشعار |
| PATCH | /reminders/{id} | تفعيل/تعطيل إشعار |
| DELETE | /reminders/{id} | حذف إشعار |
| GET | /progress/study/weekly | تقدم الدراسة الأسبوعي |
| GET | /progress/study/monthly | تقدم الدراسة الشهري |
| GET | /progress/study/range | تقدم الدراسة لنطاق مخصص |
| GET | /progress/sport/weekly | تقدم الرياضة الأسبوعي |
| GET | /progress/sport/monthly | تقدم الرياضة الشهري |
| GET | /progress/sport/range | تقدم الرياضة لنطاق مخصص |
| POST | /sync/push | دفع التغييرات المحلية للخادم |
| GET | /sync/pull | سحب تغييرات الخادم |
| GET | /sync/status | حالة المزامنة الحالية |
| GET | /sync/conflicts | جلب التعارضات المعلّقة |
| POST | /sync/conflicts/{id}/resolve | حل تعارض محدد يدوياً |
| POST | /sync/conflicts/resolve-all | حل جماعي لكل التعارضات المعلّقة |
| POST | /devices | تسجيل الجهاز وتحديث توكن الإشعارات |
