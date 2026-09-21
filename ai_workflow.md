# AI Workflow — دليل عمل المساعد الذكي على المشروع

هذا الملف موجّه لأي مساعد ذكاء اصطناعي (Claude Code أو غيره) يعمل على تطوير هذا المشروع. الهدف: ضمان اتساق القرارات المعمارية، وتقليل الحاجة لإعادة شرح السياق في كل جلسة عمل.

---

## 1. السياق المرجعي (اقرأ هذا أولاً)

قبل تنفيذ أي مهمة، راجع دائماً:
- `project_overview.md` — الفكرة العامة، نموذج البيانات، آلية `study_progress_snapshots`/`sport_progress_snapshots` المجمّعة (منفصلتان)، وآلية المزامنة Offline-First.
- `api_documentation.md` — جميع الـ endpoints، رموز الأخطاء، وصيغ الطلبات/الاستجابات.

**قاعدة ثابتة**: أي تعديل على نموذج البيانات أو الـ API يجب أن يُحدَّث في هذين الملفين في نفس المهمة، وليس لاحقاً.

---

## 2. نظرة عامة على البنية التقنية

| الطبقة | التقنية |
|---|---|
| اللغة/الإطار | Dart / Flutter |
| إدارة الحالة | Riverpod |
| التخزين المحلي | sqflite (أو Isar إذا تقرر التحويل لاحقاً) |
| المخططات البيانية | fl_chart |
| الإشعارات | flutter_local_notifications |
| طبقة الشبكة (إن وُجد backend) | http / dio + تطابق كامل مع `api_documentation.md` |

---

## 3. بنية المجلدات المعتمدة

```
lib/
  core/
    constants/
    errors/          # يطابق أكواد الأخطاء في api_documentation.md
    utils/
  data/
    models/          # Task, StudyPlan, SportSession, Category, ReminderNotification, ProgressSnapshot
    datasources/      # local (sqflite) + remote (إن وُجد)
    repositories/
  domain/
    entities/
    usecases/
  presentation/
    screens/
      tasks/
      study_plans/
      sport/
      progress/
      settings/
    widgets/
    providers/        # Riverpod providers
  main.dart
```

**قاعدة تسمية الملفات**: `snake_case.dart`، وكل Model له ملف مطابق في `data/models/` بنفس الاسم المستخدم في `project_overview.md` (مثال: `study_plan.dart` وليس `studyplan.dart`).

---

## 4. قواعد صارمة عند إضافة ميزة جديدة

1. **لا تُنشئ endpoint أو حقل جديد دون تحديث `api_documentation.md` و`project_overview.md` أولاً.**
2. **كل تغيير في حالة `isCompleted`** (لمهمة أو جلسة رياضة) **يجب** أن يمر عبر `ProgressService` لتحديث الجدول المناسب — `study_progress_snapshots` لمهام الدراسة أو `sport_progress_snapshots` لجلسات الرياضة (الجدولان منفصلان تماماً) — لا تُحدّث الحالة مباشرة دون المرور بهذه الخدمة، وإلا سيصبح المخطط البياني غير دقيق.
3. **كل خطأ يُعاد للواجهة** يجب أن يطابق أحد الأكواد الموجودة في جدول "رموز الأخطاء المخصصة" بـ `api_documentation.md`. عدم استخدام كود جديد دون توثيقه أولاً.
4. **لا تستخدم `SharedPreferences`** لتخزين بيانات علائقية (مهام، خطط، جلسات) — فقط لإعدادات بسيطة (مثل تفضيل اللغة أو الثيم).
5. **أي شاشة جديدة** يجب أن تُبنى كـ `ConsumerWidget` أو `ConsumerStatefulWidget` (Riverpod)، وليس `StatefulWidget` عادي إلا لحالات معزولة لا تحتاج حالة مشتركة.

---

## 5. سير العمل المقترح لأي مهمة تطوير (Task Workflow)

```
1. قراءة project_overview.md + api_documentation.md المرتبطين بالمهمة
2. تحديد: هل المهمة تغيّر نموذج البيانات؟ إن نعم → حدّث project_overview.md أولاً
3. هل المهمة تضيف/تعدّل endpoint؟ إن نعم → حدّث api_documentation.md أولاً
4. تنفيذ الكود (Model → Repository → UseCase → Provider → UI)
5. كتابة/تحديث اختبارات الوحدة (Unit Tests) للـ Repository و UseCase
6. التأكد أن معالجة الأخطاء تطابق الجدول الموحد (القسم 4 أعلاه)
7. مراجعة: هل تم تحديث الجدول الصحيح (study_progress_snapshots أو sport_progress_snapshots) إن كانت المهمة تتعلق بإكمال مهمة/جلسة؟
```

---

## 6. معايير الكود (Code Conventions)

- **التعليقات**: بالعربية أو الإنجليزية مقبولة، لكن أسماء المتغيرات والدوال بالإنجليزية دائماً.
- **معالجة الأخطاء**: استخدم `Result<T>` أو `Either<Failure, T>` (مثلاً عبر حزمة `dartz` أو نمط مخصص) بدل رمي Exceptions مباشرة في طبقة الـ UI.
- **الثوابت**: أي enum جديد (`TaskType`, `Priority`, إلخ) يُعرَّف مرة واحدة في `core/constants/enums.dart` ولا يُكرَّر.
- **التواريخ**: تُخزَّن دائماً بصيغة UTC (ISO 8601) وتُحوَّل للتوقيت المحلي فقط عند العرض.
- **الاختبارات**: كل `UseCase` جديد يحتاج اختبار وحدة واحداً على الأقل يغطي الحالة الناجحة وحالة فشل واحدة.

---

## 7. قواعد Git / Commits

| النوع | الصيغة | مثال |
|---|---|---|
| ميزة جديدة | `feat: <وصف>` | `feat: add sport session completion endpoint` |
| إصلاح خطأ | `fix: <وصف>` | `fix: correct progress snapshot delta calculation` |
| توثيق | `docs: <وصف>` | `docs: update api_documentation for reminders` |
| إعادة هيكلة | `refactor: <وصف>` | `refactor: extract ProgressService from TaskRepository` |
| اختبارات | `test: <وصف>` | `test: add unit tests for StudyPlan progress` |

**قاعدة**: أي commit يغيّر نموذج البيانات أو الـ API يجب أن يتضمن تحديث الملفات التوثيقية ضمن نفس الـ commit.

---

## 8. أسئلة يجب طرحها قبل تنفيذ مهمة غامضة

إذا كانت المهمة المطلوبة من المستخدم غير واضحة تقنياً، يجب على المساعد أن يسأل تحديداً عن:
- هل التغيير يؤثر على نموذج البيانات الحالي أم يضيف كياناً جديداً؟
- هل يحتاج endpoint جديد أم يُغطّى بموجود؟
- هل يؤثر على حساب `study_progress_snapshots` أو `sport_progress_snapshots`؟
- هل يحتاج التغيير أن يُدرَج ضمن آلية المزامنة (Offline-First Sync) كحقل `syncStatus`/`updatedAt` إضافي؟

لا يجب افتراض إجابات هذه الأسئلة عند وجود غموض حقيقي في التصميم المعماري (بعكس التفاصيل الصغيرة التي يمكن اختيار الأنسب لها مباشرة).

---

## 9. ما يجب على المساعد الذكي تجنّبه

- ❌ إنشاء جداول أو حقول جديدة في قاعدة البيانات دون تسجيلها في `project_overview.md`.
- ❌ استخدام مكتبات إدارة حالة مختلفة (Bloc/Provider/GetX) بالتوازي مع Riverpod دون قرار صريح من المستخدم بتغيير الاتجاه.
- ❌ حساب التقدم مباشرة من جدول `tasks` أو `sport_sessions` في الواجهة — يجب دائماً القراءة من `study_progress_snapshots` أو `sport_progress_snapshots` حسب القسم.
- ❌ خلط بيانات الدراسة والرياضة في نفس اللقطة/الجدول — الفصل مقصود ونهائي.
- ❌ تجاهل أكواد الأخطاء الموحدة واستبدالها برسائل حرة غير موثقة.
