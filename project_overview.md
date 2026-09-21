# نظرة عامة على المشروع (Project Overview)

## اسم المشروع
تطبيق مهام يومية بلغة Dart/Flutter — يجمع بين إدارة المهام، خطط الدراسة، الرياضة، والتقدم البصري.

## فكرة المشروع
تطبيق يساعد المستخدم على:
- كتابة خطة دراسية مع مخطط بياني يُظهر تقدمه.
- تحديد قسم خاص لأوقات الرياضة.
- تحديد وقت لأي مهمة أخرى.
- إرسال إشعارات للتذكير بالمهام.

---

## التقييم الأولي للفكرة

**نقاط القوة**
- الجمع بين "خطة دراسية + مخطط تقدم" غير شائع في أغلب تطبيقات المهام البسيطة.
- تخصيص قسم مستقل للرياضة يشجع على الاستمرارية بدل دفنها ضمن قائمة مهام عامة.
- الإشعارات عنصر أساسي لا غنى عنه في أي تطبيق مهام.

**أسئلة يجب حسمها مبكراً**
1. نوع المخطط البياني: أسبوعي/شهري؟ نسبة إنجاز لكل مادة؟ أم خط زمني للالتزام (streak)؟
2. هل "الخطة الدراسية" تحتاج جدولة متكررة (مثال: مراجعة كل يوم اثنين) بعكس المهام العادية التي تكون مرة واحدة؟
3. هل الرياضة تحتاج فقط "وقت مخصص" أم أيضاً تتبع نوع التمرين والمدة الفعلية؟

**اقتراحات تطوير مستقبلية**
- نظام Streak (سلسلة أيام الالتزام المتتالية) كمحفز نفسي.
- أولويات للمهام (Priority levels: عالية/متوسطة/منخفضة).
- تصنيفات/Tags (دراسة، رياضة، عمل، شخصي...) لفلترة العرض.
- تقرير أسبوعي/شهري تلقائي بنسبة الإنجاز الكلية.
- وضع "عدم إزعاج" ذكي للإشعارات أثناء وقت الدراسة المحدد مسبقاً.
- مزامنة سحابية اختيارية (Firebase) لدعم أكثر من جهاز مستقبلاً.

**التقنيات المقترحة**
- إدارة الحالة: `Riverpod` أو `Bloc`.
- تخزين محلي: `sqflite` أو `Isar` (أفضل من `SharedPreferences` بسبب البيانات العلائقية).
- المخططات البيانية: `fl_chart`.
- الإشعارات: `flutter_local_notifications`.

---

## نموذج البيانات (Data Model)

### 1. Task — المهمة العامة (الكيان الأب)
```dart
class Task {
  String id;
  String title;
  String? description;
  TaskType type; // study, sport, other
  DateTime? dueDate;
  TimeOfDay? scheduledTime;
  int? durationMinutes; // المدة التقديرية للمهمة الدراسية بالدقائق (تُستخدم في studyMinutes)
  Priority priority; // low, medium, high
  bool isCompleted;
  DateTime? completedAt;
  bool isRecurring;
  RecurrenceRule? recurrence; // يومي/أسبوعي/أيام محددة
  String? categoryId;
  String? parentPlanId; // إن كانت جزءاً من خطة دراسية
  DateTime createdAt;
}

enum TaskType { study, sport, other }
enum Priority { low, medium, high }
```

### 2. StudyPlan — الخطة الدراسية
```dart
class StudyPlan {
  String id;
  String subjectName;
  String? goal;
  DateTime startDate;
  DateTime? endDate;
  List<String> taskIds;
  int totalTasks;
  int completedTasks;
  double get progressPercentage =>
      totalTasks == 0 ? 0 : (completedTasks / totalTasks) * 100;
}
```

### 3. SportSession — جلسة الرياضة
```dart
class SportSession {
  String id;
  String? exerciseName;
  DateTime date;
  TimeOfDay scheduledTime;
  int durationMinutes;
  bool isCompleted;
  int? actualDurationMinutes;
}
```

### 4. Category — التصنيف
```dart
class Category {
  String id;
  String name;
  int colorValue;
  IconData? icon;
}
```

### 5. ReminderNotification — الإشعار
```dart
class ReminderNotification {
  String id;
  String taskId;
  DateTime scheduledFor;
  String message;
  NotificationRepeat repeat; // none, daily, weekly
  bool isEnabled;
}

enum NotificationRepeat { none, daily, weekly }
```

### 6. StudyProgressSnapshot و SportProgressSnapshot — لقطتا التقدم المنفصلتان
تم الحسم: **لقطتان منفصلتان** (وليس لقطة واحدة مشتركة)، بحيث يُعرض مخططان بيانيان مستقلان لكل قسم في الواجهة.

```dart
class StudyProgressSnapshot {
  String id;
  DateTime date;
  int tasksCompleted;
  int tasksPlanned;
  int studyMinutes;
  double get completionRate =>
      tasksPlanned == 0 ? 0 : (tasksCompleted / tasksPlanned) * 100;
}

class SportProgressSnapshot {
  String id;
  DateTime date;
  int sessionsCompleted;
  int sessionsPlanned;
  int sportMinutes;
  double get completionRate =>
      sessionsPlanned == 0 ? 0 : (sessionsCompleted / sessionsPlanned) * 100;
}
```
تم اختيار **الطريقة المجمّعة (aggregated)**: بدل حساب التقدم من جدول `tasks`/`sport_sessions` في كل مرة يُفتح فيها المخطط، تُحفظ لقطة يومية جاهزة لكل قسم على حدة، وتُحدَّث فقط عند حدوث تغيير فعلي (إكمال مهمة دراسية أو جلسة رياضة).

**سبب الفصل**: كل قسم له وحدة قياس ومنطق مختلف (مهام مقابل جلسات، وقد تختلف معدلات الإنجاز المستهدفة)، والفصل يسمح بعرض مخططين مستقلين، وفلترة/تصفية كل قسم دون التأثير على الآخر.

### العلاقات بين الكيانات
```
StudyPlan (1) ──< (N) Task
Category  (1) ──< (N) Task
Task      (1) ──< (N) ReminderNotification
SportSession مستقلة، ويمكن ربطها اختيارياً بـ Task عام
```

### جداول قاعدة البيانات (sqflite)

| الجدول | الحقول الأساسية |
|---|---|
| tasks | id, title, type, due_date, priority, is_completed, category_id, parent_plan_id, duration_minutes |
| study_plans | id, subject_name, start_date, end_date, total_tasks, completed_tasks |
| sport_sessions | id, exercise_name, date, duration_minutes, is_completed |
| categories | id, name, color |
| reminders | id, task_id, scheduled_for, repeat_type |
| study_progress_snapshots | id, date, tasks_completed, tasks_planned, study_minutes |
| sport_progress_snapshots | id, date, sessions_completed, sessions_planned, sport_minutes |

ملاحظة: عند استخدام `Isar` بدل `sqflite`، تصبح العلاقات (مثل `taskIds` في StudyPlan) أسهل عبر `IsarLinks` بدل كتابة JOIN queries يدوياً.

---

## آلية المخطط المجمّع (Aggregated Progress) — منفصل بين الدراسة والرياضة

### الفكرة
تحديث صف واحد في `study_progress_snapshots` **أو** `sport_progress_snapshots` (حسب نوع الحدث) **عند حدوث تغيير فعلي**، بدلاً من إعادة الحساب من `tasks`/`sport_sessions` في كل قراءة. كل قسم له خدمة تحديث وجدول مستقل تماماً عن الآخر.

### خدمة التحديث
```dart
class ProgressService {
  final Database db;
  ProgressService(this.db);

  Future<void> onTaskCompleted(Task task) async {
    if (task.type != TaskType.study) return; // فقط مهام الدراسة تُحدّث هذا الجدول
    final today = _dateOnly(DateTime.now());
    await _upsertStudySnapshot(
      date: today,
      deltaTasksCompleted: task.isCompleted ? 1 : -1,
      deltaStudyMinutes: task.isCompleted ? _estimateMinutes(task) : -_estimateMinutes(task),
    );
  }

  Future<void> onSportSessionCompleted(SportSession session) async {
    final today = _dateOnly(session.date);
    await _upsertSportSnapshot(
      date: today,
      deltaSessionsCompleted: session.isCompleted ? 1 : -1,
      deltaSportMinutes: session.isCompleted ? (session.actualDurationMinutes ?? 0) : 0,
    );
  }

  Future<void> _upsertStudySnapshot({
    required DateTime date,
    int deltaTasksCompleted = 0,
    int deltaStudyMinutes = 0,
  }) async {
    final existing = await db.query('study_progress_snapshots',
        where: 'date = ?', whereArgs: [date.toIso8601String()]);

    if (existing.isEmpty) {
      await db.insert('study_progress_snapshots', {
        'date': date.toIso8601String(),
        'tasks_completed': deltaTasksCompleted.clamp(0, 999),
        'study_minutes': deltaStudyMinutes.clamp(0, 999999),
        'tasks_planned': await _countPlannedStudyTasks(date),
      });
    } else {
      final row = existing.first;
      await db.update('study_progress_snapshots', {
        'tasks_completed': (row['tasks_completed'] as int) + deltaTasksCompleted,
        'study_minutes': (row['study_minutes'] as int) + deltaStudyMinutes,
      }, where: 'id = ?', whereArgs: [row['id']]);
    }
  }

  Future<void> _upsertSportSnapshot({
    required DateTime date,
    int deltaSessionsCompleted = 0,
    int deltaSportMinutes = 0,
  }) async {
    final existing = await db.query('sport_progress_snapshots',
        where: 'date = ?', whereArgs: [date.toIso8601String()]);

    if (existing.isEmpty) {
      await db.insert('sport_progress_snapshots', {
        'date': date.toIso8601String(),
        'sessions_completed': deltaSessionsCompleted.clamp(0, 999),
        'sport_minutes': deltaSportMinutes.clamp(0, 999999),
        'sessions_planned': await _countPlannedSportSessions(date),
      });
    } else {
      final row = existing.first;
      await db.update('sport_progress_snapshots', {
        'sessions_completed': (row['sessions_completed'] as int) + deltaSessionsCompleted,
        'sport_minutes': (row['sport_minutes'] as int) + deltaSportMinutes,
      }, where: 'id = ?', whereArgs: [row['id']]);
    }
  }

  DateTime _dateOnly(DateTime dt) => DateTime(dt.year, dt.month, dt.day);
}
```

> **ملاحظة (الباك اند)**: في الخادم تُجمع `study_minutes` من `durationMinutes` للمهام الدراسية المكتملة فقط (لا يوجد تقدير تلقائي) — راجع `api_documentation.md` §11.

### حساب المخطط لليوم (Planned Counts)
```dart
Future<int> _countPlannedStudyTasks(DateTime date) async {
  final result = await db.rawQuery('''
    SELECT COUNT(*) as count FROM tasks
    WHERE type = 'study' AND (due_date = ? OR (is_recurring = 1 AND recurrence_matches(due_date, ?)))
  ''', [date.toIso8601String(), date.toIso8601String()]);
  return result.first['count'] as int;
}

Future<int> _countPlannedSportSessions(DateTime date) async {
  final result = await db.rawQuery('''
    SELECT COUNT(*) as count FROM sport_sessions WHERE date = ?
  ''', [date.toIso8601String()]);
  return result.first['count'] as int;
}
```

### قراءة البيانات لعرضها في fl_chart (مخططان مستقلان)
```dart
Future<List<StudyProgressSnapshot>> getWeeklyStudyProgress() async {
  final sevenDaysAgo = DateTime.now().subtract(const Duration(days: 7));
  final rows = await db.query('study_progress_snapshots',
      where: 'date >= ?', whereArgs: [sevenDaysAgo.toIso8601String()],
      orderBy: 'date ASC');
  return rows.map((r) => StudyProgressSnapshot.fromMap(r)).toList();
}

Future<List<SportProgressSnapshot>> getWeeklySportProgress() async {
  final sevenDaysAgo = DateTime.now().subtract(const Duration(days: 7));
  final rows = await db.query('sport_progress_snapshots',
      where: 'date >= ?', whereArgs: [sevenDaysAgo.toIso8601String()],
      orderBy: 'date ASC');
  return rows.map((r) => SportProgressSnapshot.fromMap(r)).toList();
}
```
كل نتيجة تُمرَّر لمكوّن `fl_chart` منفصل — مخطط للدراسة ومخطط آخر للرياضة، كل بلونه الخاص (راجع `brand_guidelines.md`: أزرق للدراسة، أخضر للرياضة).

---

## Offline-First Sync (المزامنة)

التطبيق يعمل محلياً بالكامل عبر `sqflite` كمصدر حقيقة أساسي (Source of Truth محلي)، والمزامنة مع الخادم **اختيارية وغير حاصرة** — أي عملية (إنشاء/تعديل/حذف) تُنفَّذ محلياً فوراً بغض النظر عن حالة الاتصال، ثم تُزامَن لاحقاً في الخلفية.

### آلية العمل
1. كل سجل محلي (Task, StudyPlan, SportSession...) يحمل حقلين إضافيين:
   - `syncStatus`: `synced` | `pending` | `conflict`
   - `updatedAt`: طابع زمني UTC يُحدَّث عند أي تعديل محلي.
2. عند توفر الاتصال، تعمل مهمة خلفية (Background Sync Worker) تدفع كل السجلات `pending` عبر `POST /sync/push`، ثم تسحب أي تغييرات من الخادم عبر `GET /sync/pull`.
3. **استراتيجية حل التعارض**: Last-Write-Wins بالاعتماد على `updatedAt` — السجل الأحدث زمنياً يفوز، والطرف الخاسر يُحفظ مؤقتاً في جدول `sync_conflicts` لمراجعة يدوية اختيارية من المستخدم (لا حذف صامت).
4. جدول `sync_cursor` محلي يحفظ آخر `syncedAt` تم استلامه من الخادم، ليُرسَل كـ `since` في الطلب التالي بدل سحب كل البيانات في كل مرة.

راجع endpoints المزامنة الكاملة (`/sync/push`, `/sync/pull`, صيغ الطلب/الاستجابة، وحالات التعارض) في `api_documentation.md`.

> لتلقي إشعارات التذكير (Push) يسجّل التطبيق جهازه وتوكن FCM عبر `POST /devices` بعد تسجيل الدخول — راجع `api_documentation.md` §12.6.
