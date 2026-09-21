# DayWise API — Endpoints Reference (Flutter)

Compact, implementation-accurate reference for wiring the Flutter client.
Full contract and error examples: `api_documentation.md`.

- All record IDs are **UUIDs** — store and send them as-is.
- All bodies are **JSON** (`Content-Type: application/json`, UTF-8).
- Soft deletes: `DELETE` returns `204`; the record disappears from every list/show endpoint.

---

## Base URL

| Environment | URL |
|---|---|
| Production | `https://api.dailytasksapp.com/v1` |
| Local — Android emulator | `http://10.0.2.2:8000/api/v1` |
| Local — iOS simulator | `http://127.0.0.1:8000/api/v1` |
| Local — real device | `http://<PC-LAN-IP>:8000/api/v1` |

Override at build time: `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1`

## Headers

```
Authorization: Bearer <accessToken>     # every endpoint except register/login/refresh
Content-Type: application/json
Accept-Language: ar | en                # optional, controls error message language
```

## Response envelope

Success:
```json
{ "success": true, "data": { }, "meta": { "timestamp": "..." } }
```

Paginated list (`meta` replaces `timestamp`):
```json
{ "success": true, "data": [ ], "meta": { "page": 1, "pageSize": 20, "totalItems": 57, "totalPages": 3 } }
```

Error:
```json
{
  "success": false,
  "error": { "code": "VALIDATION_ERROR", "message": "...", "details": [ { "field": "title", "issue": "..." } ] },
  "meta": { "timestamp": "...", "requestId": "req_8f3ac2" }
}
```

## Limits

| Rule | Value |
|---|---|
| Rate limit | 100 requests/minute per user (429 `RATE_LIMIT_EXCEEDED`) |
| Max request body | 1 MB |
| Access token | 60 minutes |
| Refresh token | 30 days |
| Pagination | `page=1`, `pageSize=20` (max 100 for lists, max 500 for sync pull) |

---

## 1. Auth

### POST /auth/register — no token
```json
{ "name": "Ahmad", "email": "ahmad@example.com", "password": "StrongPass123" }
```
Rules: `name` ≤ 150, valid email, password ≥ 8 with one uppercase letter and one digit.
`201`:
```json
{ "success": true, "data": {
  "userId": "9f1c2d3e-...", "name": "Ahmad", "email": "ahmad@example.com",
  "accessToken": "3|nQ7Xk...", "refreshToken": "4|dR2mYp..."
} }
```
Errors: `VALIDATION_ERROR` (422), `EMAIL_ALREADY_EXISTS` (409).

### POST /auth/login — no token
```json
{ "email": "ahmad@example.com", "password": "StrongPass123" }
```
`200`:
```json
{ "success": true, "data": { "accessToken": "5|kT9pX...", "refreshToken": "6|wA4jC...", "expiresIn": 3600 } }
```
Errors: `INVALID_CREDENTIALS` (401), `VALIDATION_ERROR` (422).

### POST /auth/refresh — no token
```json
{ "refreshToken": "6|wA4jC..." }
```
`200`: same shape as login. Errors: `TOKEN_EXPIRED` (401) → require a new login.

### POST /auth/logout — token
No body. Invalidates only the current access token. `200` with a generic success payload.

---

## 2. Tasks

### GET /tasks — token
Query params:

| Param | Type | Notes |
|---|---|---|
| `type` | string | `study` \| `sport` \| `other` — omit for the Home "All" tab |
| `isCompleted` | bool | filter by completion |
| `categoryId` | string | UUID |
| `dueDateFrom` / `dueDateTo` | date | `YYYY-MM-DD` |
| `page` / `pageSize` | int | default 1 / 20, max 100 |

`200` paginated. Task item:
```json
{
  "id": "1a2b3c4d-...", "title": "Read chapter 3", "description": null,
  "type": "study", "dueDate": "2026-09-12", "scheduledTime": "16:00",
  "durationMinutes": 45, "priority": "high", "isCompleted": false,
  "completedAt": null, "isRecurring": false, "recurrenceRule": null,
  "categoryId": "2b3c4d5e-...", "parentPlanId": "3c4d5e6f-...",
  "createdAt": "2026-09-01T10:00:00Z", "updatedAt": "2026-09-01T10:00:00Z"
}
```

> Home tabs: "All" = `GET /tasks` (no `type`), "Study"/"Sport" = same call with `type=study` / `type=sport`.
> There is no separate table — create/update/type-change/delete is reflected instantly in every tab.

### GET /tasks/{id} — token
`200` single task (same shape as the list item). Error: `TASK_NOT_FOUND` (404).

### POST /tasks — token
```json
{
  "title": "Solve math exercises", "description": "Chapter 4", "type": "study",
  "dueDate": "2026-09-13", "scheduledTime": "18:00", "durationMinutes": 45,
  "priority": "medium", "isRecurring": false,
  "recurrenceRule": null, "categoryId": "2b3c4d5e-...", "parentPlanId": "3c4d5e6f-..."
}
```
Required: `title` (1–150), `type`. `durationMinutes` feeds `studyMinutes` snapshots.
`201` with the full task. Errors: `VALIDATION_ERROR` (422), `CATEGORY_NOT_FOUND` (404),
`STUDY_PLAN_NOT_FOUND` (404), `INVALID_RECURRENCE_RULE` (422).

### PUT /tasks/{id} — token
Same body as create; every field optional (partial update). `200` with the full task.
Error: `TASK_NOT_FOUND` (404).

### PATCH /tasks/{id}/complete — token
```json
{ "isCompleted": true }
```
`200`:
```json
{ "success": true, "data": { "id": "1a2b3c4d-...", "isCompleted": true, "completedAt": "2026-09-10T14:30:00Z" } }
```
Only this endpoint updates `study_progress_snapshots` when `type = study`.

### DELETE /tasks/{id} — token
`204 No Content`. Error: `TASK_NOT_FOUND` (404).

---

## 3. Categories

### GET /categories — token
`200` with a plain array (not paginated):
```json
{ "id": "2b3c4d5e-...", "name": "Study", "colorValue": "#4CAF50", "icon": "book",
  "createdAt": "...", "updatedAt": "..." }
```

### POST /categories — token
```json
{ "name": "Study", "colorValue": "#4CAF50", "icon": "book" }
```
`201` with the category. Error: `DUPLICATE_CATEGORY` (409) — unique per user.

### PUT /categories/{id} — token
Partial update (`name` / `colorValue` / `icon`). Errors: `DUPLICATE_CATEGORY` (409), `CATEGORY_NOT_FOUND` (404).

### DELETE /categories/{id} — token
`204`. Tasks that referenced it get `categoryId = null` automatically.

---

## 4. Study Plans

### GET /study-plans — token
`200` paginated. Plan item:
```json
{
  "id": "3c4d5e6f-...", "subjectName": "Math", "goal": "Finish before the exam",
  "startDate": "2026-09-01", "endDate": "2026-10-15",
  "totalTasks": 20, "completedTasks": 8, "progressPercentage": 40.0,
  "tasks": [], "createdAt": "...", "updatedAt": "..."
}
```

### GET /study-plans/{id} — token
`200` with the plan above and its `tasks` array populated. Error: `STUDY_PLAN_NOT_FOUND` (404).

### POST /study-plans — token
```json
{ "subjectName": "Math", "goal": "Finish before the exam", "startDate": "2026-09-01", "endDate": "2026-10-15" }
```
`201`. Error: `INVALID_DATE_RANGE` (422) when `endDate < startDate`.

### PUT /study-plans/{id} — token
Partial update. Errors: `INVALID_DATE_RANGE` (422), `STUDY_PLAN_NOT_FOUND` (404).

### DELETE /study-plans/{id}?cascade=true|false — token
`204`. Default `false` → linked tasks are detached (`parentPlanId = null`);
`true` → linked tasks are soft-deleted too.

### GET /study-plans/{id}/progress?range=week|month — token
Computed live from the plan's tasks grouped by `dueDate` (not from snapshots):
```json
{ "success": true, "data": {
  "planId": "3c4d5e6f-...", "range": "week",
  "points": [ { "date": "2026-09-04", "tasksCompleted": 2, "tasksPlanned": 3 } ]
} }
```

---

## 5. Sport Sessions

### GET /sport-sessions?dateFrom=&dateTo=&isCompleted= — token
`200` with a plain array (not paginated), newest first:
```json
{
  "id": "4d5e6f7a-...", "exerciseName": "Running", "date": "2026-09-11",
  "scheduledTime": "07:00", "durationMinutes": 30, "actualDurationMinutes": 28,
  "isCompleted": true, "completedAt": "2026-09-11T07:30:00Z",
  "createdAt": "...", "updatedAt": "..."
}
```

### POST /sport-sessions — token
```json
{ "exerciseName": "Running", "date": "2026-09-11", "scheduledTime": "07:00", "durationMinutes": 30 }
```
`201` with the full session. `durationMinutes` > 0.

### PATCH /sport-sessions/{id}/complete — token
```json
{ "isCompleted": true, "actualDurationMinutes": 28 }
```
`200` with the full session. Updates `sport_progress_snapshots` for that day.
Error: `SPORT_SESSION_NOT_FOUND` (404).

### PUT /sport-sessions/{id} — token
Partial update. Error: `SPORT_SESSION_NOT_FOUND` (404).

### DELETE /sport-sessions/{id} — token
`204`. Error: `SPORT_SESSION_NOT_FOUND` (404).

---

## 6. Reminders

### GET /tasks/{taskId}/reminders — token
`200` with a plain array:
```json
{
  "id": "6f7a8b9c-...", "taskId": "1a2b3c4d-...",
  "scheduledFor": "2026-09-20T15:45:00Z", "message": "Don't forget chapter 3",
  "repeat": "none", "isEnabled": true, "createdAt": "...", "updatedAt": "..."
}
```

### POST /reminders — token
```json
{ "taskId": "1a2b3c4d-...", "scheduledFor": "2026-09-20T15:45:00Z",
  "message": "Don't forget chapter 3", "repeat": "none" }
```
`scheduledFor` must be in the future; `repeat` ∈ `none|daily|weekly`.
`201` with the reminder. Errors: `VALIDATION_ERROR` (422), `TASK_NOT_FOUND` (404).

### PATCH /reminders/{id} — token
Partial update, e.g. `{ "isEnabled": false }`. Error: `REMINDER_NOT_FOUND` (404).

### DELETE /reminders/{id} — token
`204`. Error: `REMINDER_NOT_FOUND` (404).

---

## 7. Progress (snapshots)

Reads exclusively from `study_progress_snapshots` / `sport_progress_snapshots` — never recomputed from tasks.

### GET /progress/study/weekly?weekOffset=0 — token
`weekOffset`: `0` current week, `-1` previous week, …
```json
{ "success": true, "data": {
  "range": { "from": "2026-09-07", "to": "2026-09-13" },
  "points": [ { "date": "2026-09-07", "tasksCompleted": 3, "tasksPlanned": 4, "studyMinutes": 90 } ],
  "summary": { "completionRate": 75.0, "totalStudyMinutes": 320 }
} }
```

### GET /progress/study/monthly?month=2026-09 — token
Same shape as weekly.

### GET /progress/study/range?from=2026-09-01&to=2026-09-10 — token
Same shape. Error: `INVALID_DATE_RANGE` (422) when `to < from`.

### GET /progress/sport/weekly?weekOffset=0 — token
```json
{ "success": true, "data": {
  "range": { "from": "2026-09-07", "to": "2026-09-13" },
  "points": [ { "date": "2026-09-07", "sessionsCompleted": 1, "sessionsPlanned": 1, "sportMinutes": 30 } ],
  "summary": { "completionRate": 100.0, "totalSportMinutes": 150 }
} }
```

### GET /progress/sport/monthly?month=2026-09 — token
### GET /progress/sport/range?from=2026-09-01&to=2026-09-10 — token
Same shape as sport weekly.

---

## 8. Sync (offline-first)

Entities: `task | study_plan | sport_session | category | reminder`. Operations: `create | update | delete`.

### POST /sync/push — token
```json
{
  "deviceId": "dev_a1b2c3",
  "changes": [
    { "entity": "task", "operation": "update", "id": "1a2b3c4d-...",
      "data": { "isCompleted": true, "title": "Read chapter 3" },
      "updatedAt": "2026-09-10T14:20:00Z" }
  ]
}
```
`updatedAt` is required per change (Last-Write-Wins).
`200`:
```json
{ "success": true, "data": {
  "accepted": [ "1a2b3c4d-..." ],
  "conflicts": [ { "id": "1a2b3c4d-...", "entity": "task", "reason": "SERVER_VERSION_NEWER",
                   "serverData": { "title": "...", "updatedAt": "..." } } ],
  "syncedAt": "2026-09-10T14:30:00Z"
} }
```
Errors: `VALIDATION_ERROR` (400), `INVALID_ENTITY_TYPE` (400).

### GET /sync/pull?since=&sinceId=&deviceId=&pageSize=200 — token
- `since`: last stored `syncedAt` (omit on first sync to pull everything).
- `sinceId`: last stored `syncedAtId` — tie-breaker when several records share the same `updatedAt`.
- `pageSize`: default 200, max 500.

`200`:
```json
{ "success": true, "data": {
  "changes": [ { "entity": "task", "operation": "update", "id": "5e6f7a8b-...",
                 "data": { "title": "...", "isCompleted": false },
                 "updatedAt": "2026-09-10T14:25:00Z" } ],
  "syncedAt": "2026-09-10T14:31:00Z",
  "syncedAtId": "5e6f7a8b-...",
  "hasMore": false
} }
```
Always store `syncedAt` + `syncedAtId` from each response and reuse them as `since` + `sinceId` (even when `hasMore = true`).

### GET /sync/status?deviceId=dev_a1b2c3 — token
```json
{ "success": true, "data": { "lastSyncedAt": "2026-09-10T14:31:00Z", "pendingConflicts": 1 } }
```

### GET /sync/conflicts?deviceId=dev_a1b2c3&status=pending&entity=task — token
`deviceId` required; `status` default `pending`; `entity` optional. `200` paginated:
```json
{
  "id": "6f7a8b9c-...", "entity": "task", "entityId": "5e6f7a8b-...",
  "clientData": { "title": "...", "updatedAt": "..." },
  "serverData": { "title": "...", "updatedAt": "..." },
  "detectedAt": "...", "status": "pending"
}
```

### POST /sync/conflicts/{id}/resolve — token
```json
{ "resolution": "keep_client" }
```
`resolution` ∈ `keep_client | keep_server | merge`; `merge` requires `mergedData`:
```json
{ "resolution": "merge", "mergedData": { "title": "Updated title", "isCompleted": true } }
```
`200`:
```json
{ "success": true, "data": {
  "id": "6f7a8b9c-...", "status": "resolved", "resolution": "keep_client",
  "finalData": { "title": "...", "isCompleted": true }, "resolvedAt": "..."
} }
```
Errors: `CONFLICT_NOT_FOUND` (404), `CONFLICT_ALREADY_RESOLVED` (409), `VALIDATION_ERROR` (400).

### POST /sync/conflicts/resolve-all — token
```json
{ "resolution": "keep_client", "deviceId": "dev_a1b2c3" }
```
Only `keep_client` / `keep_server` (no `merge`). `200`: `{ "data": { "resolvedCount": 3 } }`.

---

## 9. Devices (FCM)

### POST /devices — token
Upsert by (`userId`, `deviceId`) — safe to call on every login/start.
```json
{ "deviceId": "dev_a1b2c3", "fcmToken": "eXampleToken...", "platform": "android" }
```
Rules: `deviceId` 1–100 required; `fcmToken` ≤ 255 optional; `platform` ∈ `android|ios` optional.
`200`:
```json
{ "success": true, "data": {
  "deviceId": "dev_a1b2c3", "fcmToken": "eXampleToken...",
  "platform": "android", "lastSyncedAt": "2026-09-10T14:31:00Z"
} }
```

---

## Error codes

| Code | Status | Meaning |
|---|---|---|
| `VALIDATION_ERROR` | 400/422 | Invalid/missing fields (`error.details` lists `field` + `issue`) |
| `INVALID_JSON` | 400 | Malformed JSON body |
| `UNAUTHORIZED` | 401 | Missing/invalid token |
| `TOKEN_EXPIRED` | 401 | Access/refresh token expired → login again |
| `INVALID_CREDENTIALS` | 401 | Wrong email or password |
| `EMAIL_ALREADY_EXISTS` | 409 | Email already registered |
| `FORBIDDEN` | 403 | Resource belongs to another user |
| `TASK_NOT_FOUND` | 404 | Task not found |
| `STUDY_PLAN_NOT_FOUND` | 404 | Study plan not found |
| `SPORT_SESSION_NOT_FOUND` | 404 | Sport session not found |
| `CATEGORY_NOT_FOUND` | 404 | Category not found |
| `REMINDER_NOT_FOUND` | 404 | Reminder not found |
| `DUPLICATE_CATEGORY` | 409 | Category name already used by this user |
| `INVALID_DATE_RANGE` | 422 | `to`/`endDate` before `from`/`startDate` |
| `INVALID_RECURRENCE_RULE` | 422 | Unsupported recurrence rule |
| `INVALID_ENTITY_TYPE` | 400 | Unsupported sync entity |
| `CONFLICT_NOT_FOUND` | 404 | Sync conflict not found |
| `CONFLICT_ALREADY_RESOLVED` | 409 | Conflict already resolved |
| `RATE_LIMIT_EXCEEDED` | 429 | More than 100 requests/minute |
| `INTERNAL_ERROR` | 500 | Unexpected server error |

---

## Flutter integration (ready-to-use)

Drop-in Dart code mirroring `lib/` in the Flutter app — only dependency: `dio: ^5.0.0`.

| Snippet | Drop-in file |
|---|---|
| `ApiConstants` | `lib/core/constants/api_constants.dart` |
| `ApiEnvelope` + `ApiClient` | `lib/core/network/api_client.dart` |
| `PaginationMeta` + `Paginated<T>` | `lib/core/network/paginated.dart` |
| `AppException` + `ApiException` | `lib/core/errors/exceptions.dart` |
| `FieldIssue` | `lib/core/errors/failures.dart` |
| `ApiErrorMapper` | `lib/core/network/api_error_mapper.dart` |
| `TokenProvider` + `SessionEvents` + `AuthInterceptor` | `lib/core/network/` |
| `TaskDto` example | `lib/data/models/task_model.dart` |

### Endpoint → `ApiConstants` mapping

| Endpoint | `ApiConstants` member |
|---|---|
| `POST /auth/register` | `registerPath` |
| `POST /auth/login` | `loginPath` |
| `POST /auth/refresh` | `refreshPath` |
| `POST /auth/logout` | `logoutPath` |
| `GET/POST /tasks` | `tasksPath` |
| `GET/PUT/DELETE /tasks/{id}` | `taskPath(id)` |
| `PATCH /tasks/{id}/complete` | `taskCompletePath(id)` |
| `GET /tasks/{taskId}/reminders` | `taskRemindersPath(taskId)` |
| `GET/POST /study-plans` | `studyPlansPath` |
| `GET/PUT/DELETE /study-plans/{id}` | `studyPlanPath(id)` |
| `GET /study-plans/{id}/progress` | `studyPlanProgressPath(id)` |
| `GET/POST /sport-sessions` | `sportSessionsPath` |
| `GET/PUT/DELETE /sport-sessions/{id}` | `sportSessionPath(id)` |
| `PATCH /sport-sessions/{id}/complete` | `sportSessionCompletePath(id)` |
| `GET/POST /categories` | `categoriesPath` |
| `PUT/DELETE /categories/{id}` | `categoryPath(id)` |
| `POST /reminders` | `remindersPath` |
| `PATCH/DELETE /reminders/{id}` | `reminderPath(id)` |
| `GET /progress/study/*` | `studyWeeklyProgressPath`, `studyMonthlyProgressPath`, `studyRangeProgressPath` |
| `GET /progress/sport/*` | `sportWeeklyProgressPath`, `sportMonthlyProgressPath`, `sportRangeProgressPath` |
| `POST /sync/push` | `syncPushPath` |
| `GET /sync/pull` | `syncPullPath` |
| `GET /sync/status` | `syncStatusPath` |
| `GET /sync/conflicts` | `syncConflictsPath` |
| `POST /sync/conflicts/{id}/resolve` | `syncConflictResolvePath(id)` |
| `POST /sync/conflicts/resolve-all` | `syncConflictsResolveAllPath` |
| `POST /devices` | `devicesPath` |

---

### 1. `ApiConstants` — `lib/core/constants/api_constants.dart`

```dart
abstract final class ApiConstants {
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://api.dailytasksapp.com/v1',
  );

  static const connectTimeout = Duration(seconds: 15);
  static const receiveTimeout = Duration(seconds: 30);
  static const sendTimeout = Duration(seconds: 30);

  static const authorizationHeader = 'Authorization';
  static const bearerPrefix = 'Bearer ';
  static const acceptLanguageHeader = 'Accept-Language';
  static const idempotencyKeyHeader = 'Idempotency-Key';

  static const maxPullPageSize = 500;
  static const defaultPullPageSize = 200;

  // Auth
  static const registerPath = '/auth/register';
  static const loginPath = '/auth/login';
  static const refreshPath = '/auth/refresh';
  static const logoutPath = '/auth/logout';

  // Tasks
  static const tasksPath = '/tasks';
  static String taskPath(String id) => '$tasksPath/$id';
  static String taskCompletePath(String id) => '${taskPath(id)}/complete';
  static String taskRemindersPath(String taskId) =>
      '${taskPath(taskId)}/reminders';

  // Study plans
  static const studyPlansPath = '/study-plans';
  static String studyPlanPath(String id) => '$studyPlansPath/$id';
  static String studyPlanProgressPath(String id) =>
      '${studyPlanPath(id)}/progress';

  // Sport sessions
  static const sportSessionsPath = '/sport-sessions';
  static String sportSessionPath(String id) => '$sportSessionsPath/$id';
  static String sportSessionCompletePath(String id) =>
      '${sportSessionPath(id)}/complete';

  // Categories
  static const categoriesPath = '/categories';
  static String categoryPath(String id) => '$categoriesPath/$id';

  // Reminders
  static const remindersPath = '/reminders';
  static String reminderPath(String id) => '$remindersPath/$id';

  // Progress
  static const studyWeeklyProgressPath = '/progress/study/weekly';
  static const studyMonthlyProgressPath = '/progress/study/monthly';
  static const studyRangeProgressPath = '/progress/study/range';
  static const sportWeeklyProgressPath = '/progress/sport/weekly';
  static const sportMonthlyProgressPath = '/progress/sport/monthly';
  static const sportRangeProgressPath = '/progress/sport/range';

  // Sync
  static const syncPushPath = '/sync/push';
  static const syncPullPath = '/sync/pull';
  static const syncStatusPath = '/sync/status';
  static const syncConflictsPath = '/sync/conflicts';
  static String syncConflictResolvePath(String id) =>
      '$syncConflictsPath/$id/resolve';
  static const syncConflictsResolveAllPath = '/sync/conflicts/resolve-all';

  // Devices
  static const devicesPath = '/devices';
}
```

### 2. Envelope + pagination — `lib/core/network/paginated.dart`

```dart
class ApiEnvelope<T> {
  const ApiEnvelope({required this.data, this.meta});

  final T data;
  final PaginationMeta? meta;
}

class PaginationMeta {
  const PaginationMeta({
    required this.page,
    required this.pageSize,
    required this.totalItems,
    required this.totalPages,
  });

  final int page;
  final int pageSize;
  final int totalItems;
  final int totalPages;

  static const empty = PaginationMeta(
    page: 1,
    pageSize: 0,
    totalItems: 0,
    totalPages: 0,
  );

  bool get hasMore => page < totalPages;

  factory PaginationMeta.fromJson(Map<String, dynamic> json) {
    return PaginationMeta(
      page: (json['page'] as num?)?.toInt() ?? 1,
      pageSize: (json['pageSize'] as num?)?.toInt() ?? 0,
      totalItems: (json['totalItems'] as num?)?.toInt() ?? 0,
      totalPages: (json['totalPages'] as num?)?.toInt() ?? 0,
    );
  }

  static PaginationMeta? fromJsonOrNull(Object? json) {
    if (json is! Map<String, dynamic>) {
      return null;
    }
    return PaginationMeta.fromJson(json);
  }

  Map<String, dynamic> toJson() => {
    'page': page,
    'pageSize': pageSize,
    'totalItems': totalItems,
    'totalPages': totalPages,
  };
}

class Paginated<T> {
  const Paginated({required this.items, this.meta = PaginationMeta.empty});

  final List<T> items;
  final PaginationMeta meta;

  factory Paginated.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) itemFromJson,
  ) {
    final rawItems = json['data'];
    final items = rawItems is List
        ? rawItems
              .whereType<Map<String, dynamic>>()
              .map(itemFromJson)
              .toList(growable: false)
        : <T>[];
    return Paginated(
      items: items,
      meta: PaginationMeta.fromJsonOrNull(json['meta']) ?? PaginationMeta.empty,
    );
  }
}
```

### 3. `ApiClient` — `lib/core/network/api_client.dart`

```dart
import 'package:dio/dio.dart';

import '../errors/exceptions.dart';
import 'api_error_mapper.dart';
import 'paginated.dart';

/// Thin wrapper around [Dio] that:
/// - unwraps the `{ success, data, meta }` envelope,
/// - throws [ApiException] (never [DioException]) for the data layer,
/// - tolerates bodies that do not follow the envelope (proxies, gateways).
class ApiClient {
  ApiClient(this._dio);

  final Dio _dio;

  Future<ApiEnvelope<T>> get<T>(
    String path, {
    Map<String, dynamic>? query,
    required T Function(Object? json) decoder,
    CancelToken? cancelToken,
  }) {
    return _send(
      () => _dio.get<Map<String, dynamic>>(
        path,
        queryParameters: _cleanQuery(query),
        cancelToken: cancelToken,
      ),
      decoder,
    );
  }

  Future<ApiEnvelope<T>> post<T>(
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    required T Function(Object? json) decoder,
    String? idempotencyKey,
    CancelToken? cancelToken,
  }) {
    return _send(
      () => _dio.post<Map<String, dynamic>>(
        path,
        data: body,
        queryParameters: _cleanQuery(query),
        cancelToken: cancelToken,
        options: _optionsWithIdempotencyKey(idempotencyKey),
      ),
      decoder,
    );
  }

  Future<ApiEnvelope<T>> put<T>(
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    required T Function(Object? json) decoder,
    CancelToken? cancelToken,
  }) {
    return _send(
      () => _dio.put<Map<String, dynamic>>(
        path,
        data: body,
        queryParameters: _cleanQuery(query),
        cancelToken: cancelToken,
      ),
      decoder,
    );
  }

  Future<ApiEnvelope<T>> patch<T>(
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    required T Function(Object? json) decoder,
    CancelToken? cancelToken,
  }) {
    return _send(
      () => _dio.patch<Map<String, dynamic>>(
        path,
        data: body,
        queryParameters: _cleanQuery(query),
        cancelToken: cancelToken,
      ),
      decoder,
    );
  }

  /// Used for endpoints that answer `204 No Content` (e.g. DELETE).
  Future<void> delete(
    String path, {
    Map<String, dynamic>? query,
    CancelToken? cancelToken,
  }) async {
    try {
      await _dio.delete<Object?>(
        path,
        queryParameters: _cleanQuery(query),
        cancelToken: cancelToken,
      );
    } on DioException catch (exception) {
      throw ApiErrorMapper.fromDioException(exception);
    }
  }

  Future<ApiEnvelope<T>> _send<T>(
    Future<Response<Map<String, dynamic>>> Function() send,
    T Function(Object? json) decoder,
  ) async {
    try {
      final response = await send();
      final body = response.data;
      if (body == null) {
        throw ApiException(
          code: 'INTERNAL_ERROR',
          message: 'استجابة فارغة من الخادم',
          statusCode: response.statusCode,
        );
      }
      if (body['success'] != true) {
        throw ApiErrorMapper.fromResponseBody(
          body,
          statusCode: response.statusCode,
        );
      }
      return ApiEnvelope<T>(
        data: decoder(body['data']),
        meta: PaginationMeta.fromJsonOrNull(body['meta']),
      );
    } on DioException catch (exception) {
      throw ApiErrorMapper.fromDioException(exception);
    }
  }

  Options? _optionsWithIdempotencyKey(String? idempotencyKey) {
    if (idempotencyKey == null) {
      return null;
    }
    return Options(headers: {'Idempotency-Key': idempotencyKey});
  }

  Map<String, dynamic>? _cleanQuery(Map<String, dynamic>? query) {
    if (query == null) {
      return null;
    }
    final cleaned = <String, dynamic>{};
    for (final entry in query.entries) {
      if (entry.value != null) {
        cleaned[entry.key] = entry.value;
      }
    }
    return cleaned.isEmpty ? null : cleaned;
  }
}
```

### 4. Errors — `exceptions.dart`, `failures.dart`, `api_error_mapper.dart`

```dart
class AppException implements Exception {
  const AppException(this.code, this.message);

  final String code;
  final String message;

  @override
  String toString() => '$code: $message';
}

class ApiException extends AppException {
  const ApiException({
    required String code,
    required String message,
    this.statusCode,
    this.details,
    this.requestId,
  }) : super(code, message);

  final int? statusCode;
  final List<FieldIssue>? details;
  final String? requestId;
}

class FieldIssue {
  const FieldIssue({required this.field, required this.issue});

  final String field;
  final String issue;

  factory FieldIssue.fromJson(Map<String, dynamic> json) {
    return FieldIssue(
      field: json['field']?.toString() ?? '',
      issue: json['issue']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() => {'field': field, 'issue': issue};
}
```

```dart
import 'package:dio/dio.dart';

import '../errors/exceptions.dart';
import '../errors/failures.dart';

abstract final class ApiErrorMapper {
  static ApiException fromDioException(DioException exception) {
    switch (exception.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.transformTimeout:
      case DioExceptionType.connectionError:
        return const ApiException(
          code: 'NETWORK_ERROR',
          message: 'تعذر الاتصال بالخادم، تحقق من اتصالك بالإنترنت',
        );
      case DioExceptionType.badCertificate:
        return const ApiException(
          code: 'NETWORK_ERROR',
          message: 'شهادة الخادم غير موثوقة',
        );
      case DioExceptionType.cancel:
        return const ApiException(
          code: 'NETWORK_ERROR',
          message: 'تم إلغاء الطلب',
        );
      case DioExceptionType.badResponse:
      case DioExceptionType.unknown:
        final response = exception.response;
        final data = response?.data;
        if (data is Map<String, dynamic>) {
          return fromResponseBody(data, statusCode: response?.statusCode);
        }
        return ApiException(
          code: codeForStatus(response?.statusCode),
          message: messageForStatus(response?.statusCode),
          statusCode: response?.statusCode,
        );
    }
  }

  /// Parses `{ success: false, error: { code, message, details }, meta }`.
  static ApiException fromResponseBody(
    Map<String, dynamic> body, {
    int? statusCode,
  }) {
    final rawError = body['error'];
    final rawMeta = body['meta'];
    final requestId = rawMeta is Map<String, dynamic>
        ? rawMeta['requestId']?.toString()
        : null;

    if (rawError is Map<String, dynamic>) {
      return ApiException(
        code: rawError['code']?.toString() ?? codeForStatus(statusCode),
        message:
            rawError['message']?.toString() ?? messageForStatus(statusCode),
        statusCode: statusCode,
        details: parseFieldIssues(rawError['details']),
        requestId: requestId,
      );
    }

    return ApiException(
      code: codeForStatus(statusCode),
      message: messageForStatus(statusCode),
      statusCode: statusCode,
      requestId: requestId,
    );
  }

  static List<FieldIssue>? parseFieldIssues(Object? details) {
    if (details is! List) {
      return null;
    }
    final issues = details
        .whereType<Map<String, dynamic>>()
        .map(FieldIssue.fromJson)
        .toList(growable: false);
    return issues.isEmpty ? null : issues;
  }

  static String codeForStatus(int? status) {
    return switch (status) {
      400 || 422 => 'VALIDATION_ERROR',
      401 => 'UNAUTHORIZED',
      403 => 'FORBIDDEN',
      404 => 'NOT_FOUND',
      409 => 'CONFLICT',
      429 => 'RATE_LIMIT_EXCEEDED',
      500 => 'INTERNAL_ERROR',
      _ => 'INTERNAL_ERROR',
    };
  }

  static String messageForStatus(int? status) {
    return switch (status) {
      400 || 422 => 'بيانات الطلب غير صحيحة',
      401 => 'يجب تسجيل الدخول للمتابعة',
      403 => 'لا تملك صلاحية الوصول لهذا المورد',
      404 => 'المورد المطلوب غير موجود',
      409 => 'تعارض في البيانات',
      429 => 'تم تجاوز الحد المسموح من الطلبات',
      _ => 'حدث خطأ غير متوقع في الخادم',
    };
  }
}
```

> `ApiErrorMapper.toFailure` / `fromApiException` (in the Flutter project) additionally map every code above to a domain `Failure` (`ValidationFailure`, `TokenExpiredFailure`, `RateLimitFailure`, …).

### 5. Dio bootstrap + auth interceptor — `lib/core/network/`

```dart
abstract class TokenProvider {
  Future<String?> getAccessToken();
  Future<String?> getRefreshToken();
  Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
  });
  Future<void> clear();
}
```

```dart
import 'dart:async';

enum SessionEvent { tokensRefreshed, sessionExpired }

class SessionEvents {
  final StreamController<SessionEvent> _controller =
      StreamController<SessionEvent>.broadcast();

  Stream<SessionEvent> get stream => _controller.stream;

  void add(SessionEvent event) => _controller.add(event);

  Future<void> dispose() => _controller.close();
}
```

```dart
import 'package:dio/dio.dart';

import '../constants/api_constants.dart';
import 'session_events.dart';
import 'token_provider.dart';

/// Attaches the bearer token + `Accept-Language` to every request and
/// transparently refreshes the access token once when the server answers
/// `401 TOKEN_EXPIRED`.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({
    required TokenProvider tokenProvider,
    required Dio refreshClient,
    required SessionEvents sessionEvents,
    String Function()? languageCode,
  }) : _tokenProvider = tokenProvider,
       _refreshClient = refreshClient,
       _sessionEvents = sessionEvents,
       _languageCode = languageCode ?? (() => 'ar');

  static const _retriedKey = 'auth_retried';

  final TokenProvider _tokenProvider;
  final Dio _refreshClient;
  final SessionEvents _sessionEvents;
  final String Function() _languageCode;

  Future<String?>? _refreshInFlight;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _tokenProvider.getAccessToken();
    if (token != null && token.isNotEmpty) {
      options.headers[ApiConstants.authorizationHeader] =
          '${ApiConstants.bearerPrefix}$token';
    }
    options.headers[ApiConstants.acceptLanguageHeader] = _languageCode();
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final response = err.response;
    final isUnauthorized = response?.statusCode == 401;
    final alreadyRetried = err.requestOptions.extra[_retriedKey] == true;
    final isAuthEndpoint = err.requestOptions.path.startsWith('/auth/');
    final code = _errorCode(response);

    final shouldRefresh =
        isUnauthorized &&
        !alreadyRetried &&
        !isAuthEndpoint &&
        code != 'UNAUTHORIZED';

    if (!shouldRefresh) {
      handler.next(err);
      return;
    }

    final newAccessToken = await _refreshAccessToken();
    if (newAccessToken == null) {
      _sessionEvents.add(SessionEvent.sessionExpired);
      await _tokenProvider.clear();
      handler.next(err);
      return;
    }

    final options = err.requestOptions;
    options.extra[_retriedKey] = true;
    options.headers[ApiConstants.authorizationHeader] =
        '${ApiConstants.bearerPrefix}$newAccessToken';

    try {
      final retried = await _refreshClient.fetch<dynamic>(options);
      handler.resolve(retried);
    } on DioException catch (retryError) {
      handler.next(retryError);
    }
  }

  /// Single-flight refresh: concurrent 401s wait for the same refresh call.
  Future<String?> _refreshAccessToken() {
    return _refreshInFlight ??= _performRefresh().whenComplete(() {
      _refreshInFlight = null;
    });
  }

  Future<String?> _performRefresh() async {
    final refreshToken = await _tokenProvider.getRefreshToken();
    if (refreshToken == null || refreshToken.isEmpty) {
      return null;
    }

    try {
      final response = await _refreshClient.post<Map<String, dynamic>>(
        ApiConstants.refreshPath,
        data: {'refreshToken': refreshToken},
      );
      final body = response.data;
      if (body == null || body['success'] != true) {
        return null;
      }
      final data = body['data'];
      if (data is! Map<String, dynamic>) {
        return null;
      }
      final accessToken = data['accessToken']?.toString();
      if (accessToken == null || accessToken.isEmpty) {
        return null;
      }
      final newRefreshToken = data['refreshToken']?.toString() ?? refreshToken;
      await _tokenProvider.saveTokens(
        accessToken: accessToken,
        refreshToken: newRefreshToken,
      );
      _sessionEvents.add(SessionEvent.tokensRefreshed);
      return accessToken;
    } on DioException {
      return null;
    }
  }

  String? _errorCode(Response<dynamic>? response) {
    final data = response?.data;
    if (data is! Map<String, dynamic>) {
      return null;
    }
    final error = data['error'];
    if (error is! Map<String, dynamic>) {
      return null;
    }
    return error['code']?.toString();
  }
}
```

```dart
Dio buildDio({
  String baseUrl = ApiConstants.baseUrl,
  required TokenProvider tokenProvider,
  required SessionEvents sessionEvents,
}) {
  final options = BaseOptions(
    baseUrl: baseUrl,
    connectTimeout: ApiConstants.connectTimeout,
    receiveTimeout: ApiConstants.receiveTimeout,
    sendTimeout: ApiConstants.sendTimeout,
    headers: const {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  );
  final client = Dio(options);
  client.interceptors.add(
    AuthInterceptor(
      tokenProvider: tokenProvider,
      refreshClient: Dio(options),
      sessionEvents: sessionEvents,
    ),
  );
  return client;
}

// Usage
// final dio = buildDio(tokenProvider: tokenStorage, sessionEvents: sessionEvents);
// final api = ApiClient(dio);
```

### 6. Model example (Task) — `lib/data/models/task_model.dart`

```dart
enum TaskType { study, sport, other }
enum TaskPriority { low, medium, high }

class TaskDto {
  const TaskDto({
    required this.id,
    required this.title,
    required this.type,
    required this.priority,
    required this.isCompleted,
    required this.isRecurring,
    required this.createdAt,
    this.description,
    this.dueDate,
    this.scheduledTime,
    this.durationMinutes,
    this.completedAt,
    this.recurrenceRule,
    this.categoryId,
    this.parentPlanId,
  });

  final String id;
  final String title;
  final String? description;
  final TaskType type;
  final DateTime? dueDate; // "YYYY-MM-DD"
  final String? scheduledTime; // "HH:mm"
  final int? durationMinutes;
  final TaskPriority priority;
  final bool isCompleted;
  final DateTime? completedAt;
  final bool isRecurring;
  final Map<String, dynamic>? recurrenceRule;
  final String? categoryId;
  final String? parentPlanId;
  final DateTime createdAt;

  factory TaskDto.fromJson(Map<String, dynamic> json) {
    return TaskDto(
      id: json['id'] as String? ?? '',
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      type: TaskType.values.firstWhere(
        (item) => item.name == json['type'],
        orElse: () => TaskType.other,
      ),
      dueDate: DateTime.tryParse(json['dueDate'] as String? ?? ''),
      scheduledTime: json['scheduledTime'] as String?,
      durationMinutes: (json['durationMinutes'] as num?)?.toInt(),
      priority: TaskPriority.values.firstWhere(
        (item) => item.name == json['priority'],
        orElse: () => TaskPriority.medium,
      ),
      isCompleted: json['isCompleted'] as bool? ?? false,
      completedAt: DateTime.tryParse(json['completedAt'] as String? ?? ''),
      isRecurring: json['isRecurring'] as bool? ?? false,
      recurrenceRule: json['recurrenceRule'] as Map<String, dynamic>?,
      categoryId: json['categoryId'] as String?,
      parentPlanId: json['parentPlanId'] as String?,
      createdAt:
          DateTime.tryParse(json['createdAt'] as String? ?? '') ??
          DateTime.now().toUtc(),
    );
  }

  /// Body for `POST /tasks` and `PUT /tasks/{id}` — nulls omitted so the
  /// same payload works for create and partial update.
  Map<String, dynamic> toJson() => {
    'title': title,
    if (description != null) 'description': description,
    'type': type.name,
    if (dueDate != null) 'dueDate': _dateOnly(dueDate!),
    if (scheduledTime != null) 'scheduledTime': scheduledTime,
    if (durationMinutes != null) 'durationMinutes': durationMinutes,
    'priority': priority.name,
    'isCompleted': isCompleted,
    'isRecurring': isRecurring,
    if (recurrenceRule != null) 'recurrenceRule': recurrenceRule,
    if (categoryId != null) 'categoryId': categoryId,
    if (parentPlanId != null) 'parentPlanId': parentPlanId,
  };

  static String _dateOnly(DateTime date) {
    final year = date.year.toString().padLeft(4, '0');
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '$year-$month-$day';
  }
}
```

### 7. Endpoint usage cheat-sheet

```dart
final api = ApiClient(buildDio(
  tokenProvider: tokenStorage,
  sessionEvents: sessionEvents,
));

Map<String, dynamic> asMap(Object? json) =>
    (json as Map<String, dynamic>?) ?? const <String, dynamic>{};
List<dynamic> asList(Object? json) => json is List ? json : const <dynamic>[];

// ---- Auth (no token) ----
final register = await api.post<Map<String, dynamic>>(
  ApiConstants.registerPath,
  body: {'name': 'Ahmad', 'email': email, 'password': password},
  decoder: asMap,
);
await tokenStorage.saveTokens(
  accessToken: register.data['accessToken'] as String,
  refreshToken: register.data['refreshToken'] as String,
);

final login = await api.post<Map<String, dynamic>>(
  ApiConstants.loginPath,
  body: {'email': email, 'password': password},
  decoder: asMap,
);

await api.post<Map<String, dynamic>>(
  ApiConstants.logoutPath,
  decoder: asMap,
);

// ---- Tasks ----
final page = await api.get<List<dynamic>>(
  ApiConstants.tasksPath,
  query: {
    if (type != null) 'type': type, // study | sport | other — omit for "All"
    if (isCompleted != null) 'isCompleted': isCompleted,
    if (categoryId != null) 'categoryId': categoryId,
    if (dueDateFrom != null) 'dueDateFrom': dueDateFrom, // YYYY-MM-DD
    if (dueDateTo != null) 'dueDateTo': dueDateTo,
    'page': 1,
    'pageSize': 20,
  },
  decoder: asList,
);
final tasks = page.data
    .whereType<Map<String, dynamic>>()
    .map(TaskDto.fromJson)
    .toList(growable: false);
final meta = page.meta ?? PaginationMeta.empty;

final task = await api.get<Map<String, dynamic>>(
  ApiConstants.taskPath(taskId),
  decoder: asMap,
);

final created = await api.post<Map<String, dynamic>>(
  ApiConstants.tasksPath,
  body: taskDto.toJson(),
  decoder: asMap,
);

await api.put<Map<String, dynamic>>(
  ApiConstants.taskPath(taskId),
  body: taskDto.toJson(),
  decoder: asMap,
);

await api.patch<void>(
  ApiConstants.taskCompletePath(taskId),
  body: {'isCompleted': true},
  decoder: (_) {},
);

await api.delete(ApiConstants.taskPath(taskId)); // 204

// ---- Categories (plain array) ----
final categories = await api.get<List<dynamic>>(
  ApiConstants.categoriesPath,
  decoder: asList,
);

await api.post<Map<String, dynamic>>(
  ApiConstants.categoriesPath,
  body: {'name': 'Study', 'colorValue': '#4CAF50', 'icon': 'book'},
  decoder: asMap,
);

// ---- Study plans ----
final plans = await api.get<List<dynamic>>(
  ApiConstants.studyPlansPath,
  query: {'page': 1, 'pageSize': 20},
  decoder: asList,
);

final plan = await api.get<Map<String, dynamic>>(
  ApiConstants.studyPlanPath(planId),
  decoder: asMap,
);

final planProgress = await api.get<Map<String, dynamic>>(
  ApiConstants.studyPlanProgressPath(planId),
  query: {'range': 'week'}, // week | month
  decoder: asMap,
);

// ---- Sport sessions (plain array, newest first) ----
final sessions = await api.get<List<dynamic>>(
  ApiConstants.sportSessionsPath,
  query: {
    if (dateFrom != null) 'dateFrom': dateFrom,
    if (dateTo != null) 'dateTo': dateTo,
    if (isCompleted != null) 'isCompleted': isCompleted,
  },
  decoder: asList,
);

await api.patch<Map<String, dynamic>>(
  ApiConstants.sportSessionCompletePath(sessionId),
  body: {'isCompleted': true, 'actualDurationMinutes': 28},
  decoder: asMap,
);

// ---- Reminders ----
final reminders = await api.get<List<dynamic>>(
  ApiConstants.taskRemindersPath(taskId),
  decoder: asList,
);

final reminder = await api.post<Map<String, dynamic>>(
  ApiConstants.remindersPath,
  body: {
    'taskId': taskId,
    'scheduledFor': scheduledForUtc, // ISO-8601, must be in the future
    'message': 'Don\'t forget chapter 3',
    'repeat': 'none', // none | daily | weekly
  },
  decoder: asMap,
);

await api.patch<Map<String, dynamic>>(
  ApiConstants.reminderPath(reminderId),
  body: {'isEnabled': false},
  decoder: asMap,
);

// ---- Progress ----
final studyWeekly = await api.get<Map<String, dynamic>>(
  ApiConstants.studyWeeklyProgressPath,
  query: {'weekOffset': 0},
  decoder: asMap,
);

final studyMonthly = await api.get<Map<String, dynamic>>(
  ApiConstants.studyMonthlyProgressPath,
  query: {'month': '2026-09'},
  decoder: asMap,
);

final sportWeekly = await api.get<Map<String, dynamic>>(
  ApiConstants.sportWeeklyProgressPath,
  query: {'weekOffset': 0},
  decoder: asMap,
);

// ---- Sync ----
final pushResult = await api.post<Map<String, dynamic>>(
  ApiConstants.syncPushPath,
  body: {
    'deviceId': deviceId,
    'changes': [
      {
        'entity': 'task', // task | study_plan | sport_session | category | reminder
        'operation': 'update', // create | update | delete
        'id': taskId,
        'data': {'isCompleted': true},
        'updatedAt': DateTime.now().toUtc().toIso8601String(),
      },
    ],
  },
  decoder: asMap,
);

final pullResult = await api.get<Map<String, dynamic>>(
  ApiConstants.syncPullPath,
  query: {
    if (lastSyncedAt != null) 'since': lastSyncedAt,
    if (lastSyncedId != null) 'sinceId': lastSyncedId,
    'deviceId': deviceId,
    'pageSize': ApiConstants.defaultPullPageSize, // max 500
  },
  decoder: asMap,
);

final syncStatus = await api.get<Map<String, dynamic>>(
  ApiConstants.syncStatusPath,
  query: {'deviceId': deviceId},
  decoder: asMap,
);

final conflicts = await api.get<List<dynamic>>(
  ApiConstants.syncConflictsPath,
  query: {'deviceId': deviceId, 'status': 'pending', 'entity': 'task'},
  decoder: asList,
);

final resolved = await api.post<Map<String, dynamic>>(
  ApiConstants.syncConflictResolvePath(conflictId),
  body: {'resolution': 'keep_client'}, // keep_client | keep_server | merge
  decoder: asMap,
);

await api.post<Map<String, dynamic>>(
  ApiConstants.syncConflictsResolveAllPath,
  body: {'resolution': 'keep_server', 'deviceId': deviceId},
  decoder: asMap,
);

// ---- Devices (FCM upsert) ----
await api.post<Map<String, dynamic>>(
  ApiConstants.devicesPath,
  body: {
    'deviceId': deviceId,
    'fcmToken': fcmToken,
    'platform': 'android', // android | ios
  },
  decoder: asMap,
);
```
