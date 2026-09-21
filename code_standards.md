# Code Standards — معايير الكود (Clean Architecture)

---

## 1. المبدأ العام

المشروع يعتمد **Clean Architecture** بثلاث طبقات مستقلة، كل طبقة لا تعرف تفاصيل الطبقة التي فوقها، والاعتماد يتجه دائماً من الخارج إلى الداخل:

```
Presentation  →  Domain  ←  Data
```

- **Domain**: منطق العمل الصِرف (Entities, UseCases, Repository Interfaces) — لا يعرف Flutter ولا sqflite ولا أي تفصيل تقني.
- **Data**: التنفيذ الفعلي (Models, DataSources, Repository Implementations) — يعرف Domain فقط.
- **Presentation**: الواجهة (Screens, Widgets, Riverpod Providers) — تعرف Domain فقط، ولا تتحدث مع Data مباشرة أبداً.

**القاعدة الذهبية**: أي `import` من `data/` داخل `presentation/` هو خطأ معماري ويُرفض في المراجعة (Code Review).

---

## 2. بنية المجلدات الكاملة

```
lib/
  core/
    constants/
      enums.dart
      app_constants.dart
    errors/
      failures.dart          # Failure classes (Domain-safe)
      exceptions.dart        # Exceptions (Data layer only)
    theme/
      app_colors.dart
      app_typography.dart
    utils/
      date_utils.dart
      validators.dart
    di/
      injection.dart         # Dependency Injection setup

  domain/
    entities/
      task.dart
      study_plan.dart
      sport_session.dart
      category.dart
      reminder.dart
      progress_snapshot.dart
    repositories/             # Interfaces فقط (abstract classes)
      task_repository.dart
      study_plan_repository.dart
      progress_repository.dart
    usecases/
      tasks/
        get_tasks.dart
        create_task.dart
        complete_task.dart
        delete_task.dart
      study_plans/
        get_study_plan_progress.dart
      progress/
        get_weekly_progress.dart

  data/
    models/                   # DTOs — تمتد من Entities وتضيف fromJson/toJson/fromMap
      task_model.dart
      study_plan_model.dart
    datasources/
      local/
        task_local_datasource.dart
        app_database.dart      # sqflite setup
      remote/
        task_remote_datasource.dart
    repositories/              # التنفيذ الفعلي لواجهات Domain
      task_repository_impl.dart

  presentation/
    screens/
      tasks/
        tasks_screen.dart
        widgets/
          task_card.dart
      study_plans/
      sport/
      progress/
    providers/                 # Riverpod
      task_providers.dart
      progress_providers.dart
    widgets/                   # مشتركة بين شاشات متعددة
      app_button.dart
      empty_state.dart

  main.dart

test/
  domain/usecases/
  data/repositories/
  presentation/providers/
```

---

## 3. قواعد كل طبقة بالتفصيل

### 3.1 Domain Layer

**Entity** — كائن بيانات صِرف بدون أي منطق تحويل:
```dart
class Task {
  final String id;
  final String title;
  final String? description;
  final TaskType type;
  final DateTime? dueDate;
  final Priority priority;
  final bool isCompleted;
  final String? categoryId;
  final String? parentPlanId;

  const Task({
    required this.id,
    required this.title,
    this.description,
    required this.type,
    this.dueDate,
    required this.priority,
    required this.isCompleted,
    this.categoryId,
    this.parentPlanId,
  });
}
```

**Repository Interface** — يُعرَّف في Domain، يُنفَّذ في Data:
```dart
abstract class TaskRepository {
  Future<Either<Failure, List<Task>>> getTasks({TaskFilter? filter});
  Future<Either<Failure, Task>> getTaskById(String id);
  Future<Either<Failure, Task>> createTask(Task task);
  Future<Either<Failure, Task>> completeTask(String id, bool isCompleted);
  Future<Either<Failure, void>> deleteTask(String id);
}
```

**UseCase** — عملية واحدة فقط لكل كلاس (Single Responsibility):
```dart
class CompleteTask {
  final TaskRepository repository;
  final ProgressRepository progressRepository;

  CompleteTask(this.repository, this.progressRepository);

  Future<Either<Failure, Task>> call(String taskId, bool isCompleted) async {
    final result = await repository.completeTask(taskId, isCompleted);
    return result.fold(
      (failure) => Left(failure),
      (task) async {
        await progressRepository.recordTaskCompletion(task);
        return Right(task);
      },
    );
  }
}
```
> لاحظ: منطق تحديث `study_progress_snapshots` (أو `sport_progress_snapshots` لجلسات الرياضة) مكانه هنا في الـ UseCase وليس في الـ Widget — يطابق القاعدة الموثقة في `ai_workflow.md`.

**قاعدة تسمية UseCases**: فعل + مفعول، بدون كلمة "UseCase" في الاسم (`CompleteTask` وليس `CompleteTaskUseCase`).

---

### 3.2 Data Layer

**Model** — يمتد من Entity ويضيف التحويل:
```dart
class TaskModel extends Task {
  const TaskModel({
    required super.id,
    required super.title,
    super.description,
    required super.type,
    super.dueDate,
    required super.priority,
    required super.isCompleted,
    super.categoryId,
    super.parentPlanId,
  });

  factory TaskModel.fromMap(Map<String, dynamic> map) {
    return TaskModel(
      id: map['id'] as String,
      title: map['title'] as String,
      description: map['description'] as String?,
      type: TaskType.values.byName(map['type'] as String),
      dueDate: map['due_date'] != null ? DateTime.parse(map['due_date']) : null,
      priority: Priority.values.byName(map['priority'] as String),
      isCompleted: (map['is_completed'] as int) == 1,
      categoryId: map['category_id'] as String?,
      parentPlanId: map['parent_plan_id'] as String?,
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'title': title,
      'description': description,
      'type': type.name,
      'due_date': dueDate?.toIso8601String(),
      'priority': priority.name,
      'is_completed': isCompleted ? 1 : 0,
      'category_id': categoryId,
      'parent_plan_id': parentPlanId,
    };
  }
}
```

**Repository Implementation** — يحوّل الأخطاء التقنية إلى `Failure` موحّدة:
```dart
class TaskRepositoryImpl implements TaskRepository {
  final TaskLocalDataSource localDataSource;

  TaskRepositoryImpl(this.localDataSource);

  @override
  Future<Either<Failure, Task>> completeTask(String id, bool isCompleted) async {
    try {
      final model = await localDataSource.updateCompletion(id, isCompleted);
      return Right(model);
    } on TaskNotFoundException {
      return Left(NotFoundFailure('TASK_NOT_FOUND', 'المهمة غير موجودة'));
    } on DatabaseException catch (e) {
      return Left(DatabaseFailure(e.toString()));
    }
  }
  // باقي الدوال بنفس النمط...
}
```

**قاعدة صارمة**: `Exception` لا يجب أن تتسرب أبداً خارج طبقة Data. كل استثناء يُحوَّل إلى `Failure` قبل الوصول إلى Domain أو Presentation.

---

### 3.3 Presentation Layer

**Provider (Riverpod)**:
```dart
final completeTaskProvider = FutureProvider.family<void, (String, bool)>(
  (ref, params) async {
    final useCase = ref.read(completeTaskUseCaseProvider);
    final result = await useCase(params.$1, params.$2);
    result.fold(
      (failure) => throw AppException(failure.code, failure.message),
      (_) => ref.invalidate(tasksListProvider),
    );
  },
);
```

**Widget** — لا يحتوي أي منطق عمل، فقط عرض وتفويض:
```dart
class TaskCard extends ConsumerWidget {
  final Task task;
  const TaskCard({super.key, required this.task});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      child: ListTile(
        title: Text(task.title),
        trailing: Checkbox(
          value: task.isCompleted,
          onChanged: (value) {
            ref.read(completeTaskProvider((task.id, value ?? false)));
          },
        ),
      ),
    );
  }
}
```

**قاعدة صارمة**: لا يوجد `sqflite`, `http`, أو أي استدعاء I/O مباشر داخل `presentation/` — كل شيء يمر عبر Provider → UseCase → Repository.

---

## 4. معالجة الأخطاء الموحدة (Failure Pattern)

```dart
// core/errors/failures.dart
abstract class Failure {
  final String code;
  final String message;
  const Failure(this.code, this.message);
}

class ValidationFailure extends Failure {
  final List<Map<String, String>>? details;
  ValidationFailure(String message, {this.details}) : super('VALIDATION_ERROR', message);
}

class NotFoundFailure extends Failure {
  NotFoundFailure(String code, String message) : super(code, message);
}

class DatabaseFailure extends Failure {
  DatabaseFailure(String message) : super('DATABASE_ERROR', message);
}

class NetworkFailure extends Failure {
  NetworkFailure(String message) : super('NETWORK_ERROR', message);
}
```

**قاعدة**: أكواد `Failure` يجب أن تطابق تماماً أكواد الأخطاء الموثقة في `api_documentation.md` (القسم 4) — لا يُستحدث كود جديد دون تحديث الوثيقة أولاً.

---

## 5. قواعد التسمية (Naming Conventions)

| العنصر | القاعدة | مثال |
|---|---|---|
| الملفات | `snake_case.dart` | `task_repository_impl.dart` |
| الكلاسات | `PascalCase` | `TaskRepositoryImpl` |
| المتغيرات والدوال | `camelCase` | `getWeeklyProgress()` |
| الثوابت | `camelCase` مع `const` | `const maxTitleLength = 150` |
| Enums | `PascalCase` للاسم، `camelCase` للقيم | `enum Priority { low, medium, high }` |
| Providers | لاحقة `Provider` دائماً | `tasksListProvider`, `completeTaskProvider` |
| UseCases | فعل + مفعول بدون لاحقة | `CompleteTask`, `GetWeeklyProgress` |
| اختبارات | نفس اسم الملف + `_test` | `complete_task_test.dart` |

---

## 6. قواعد Null Safety و Immutability

- كل Entity و Model **immutable** (كل الحقول `final`).
- استخدام `?` فقط للحقول الاختيارية فعلياً حسب `project_overview.md` (مثال: `description`, `dueDate`) — لا `nullable` عشوائي "احتياطاً".
- ممنوع استخدام `!` (null assertion) إلا بعد تحقق صريح من القيمة (`if (x != null)`), ويُفضَّل `??` أو pattern matching.

---

## 7. Linting و Formatting

**`analysis_options.yaml`** الأساسي:
```yaml
include: package:flutter_lints/flutter.yaml

linter:
  rules:
    - prefer_const_constructors
    - prefer_final_locals
    - avoid_print
    - always_declare_return_types
    - unawaited_futures
    - avoid_dynamic_calls
    - sort_child_properties_last
    - require_trailing_commas
```

- تنسيق الكود دائماً عبر `dart format .` قبل أي commit.
- لا `print()` في كود الإنتاج — استخدم `logger` مخصص (`core/utils/app_logger.dart`).
- عرض عريض السطر (line length) الأقصى: 100 حرف.

---

## 8. Dependency Injection

يُستخدم `Riverpod` نفسه كأداة DI (بدل `get_it` منفصلة) لتقليل عدد المكتبات:

```dart
// core/di/injection.dart
final databaseProvider = Provider<AppDatabase>((ref) => AppDatabase());

final taskLocalDataSourceProvider = Provider<TaskLocalDataSource>(
  (ref) => TaskLocalDataSourceImpl(ref.watch(databaseProvider)),
);

final taskRepositoryProvider = Provider<TaskRepository>(
  (ref) => TaskRepositoryImpl(ref.watch(taskLocalDataSourceProvider)),
);

final completeTaskUseCaseProvider = Provider<CompleteTask>(
  (ref) => CompleteTask(
    ref.watch(taskRepositoryProvider),
    ref.watch(progressRepositoryProvider),
  ),
);
```

**قاعدة**: أي كلاس جديد في Data أو Domain يحتاج تفعيلاً في `injection.dart` — لا يُستدعى مباشرة عبر `TaskRepositoryImpl()` من داخل الـ Widget.

---

## 9. الاختبارات (Testing Standards)

| النوع | النطاق | أداة |
|---|---|---|
| Unit Tests | UseCases, Repositories (mocked datasources) | `mocktail` |
| Widget Tests | كل Widget مشترك رئيسي (`TaskCard`, `EmptyState`) | `flutter_test` |
| Integration Tests | تدفقات كاملة (إنشاء مهمة → إكمالها → ظهورها في progress) | `integration_test` |

**الحد الأدنى للتغطية**: كل UseCase يحتاج اختبارين على الأقل (نجاح + فشل واحد على الأقل من `Failure`).

**مثال اختبار UseCase**:
```dart
void main() {
  late CompleteTask useCase;
  late MockTaskRepository mockTaskRepo;
  late MockProgressRepository mockProgressRepo;

  setUp(() {
    mockTaskRepo = MockTaskRepository();
    mockProgressRepo = MockProgressRepository();
    useCase = CompleteTask(mockTaskRepo, mockProgressRepo);
  });

  test('يجب أن يُرجع Task عند نجاح الإكمال ويُحدّث progress', () async {
    when(() => mockTaskRepo.completeTask('tsk_1', true))
        .thenAnswer((_) async => Right(tTask));
    when(() => mockProgressRepo.recordTaskCompletion(tTask))
        .thenAnswer((_) async => Right(null));

    final result = await useCase('tsk_1', true);

    expect(result, Right(tTask));
    verify(() => mockProgressRepo.recordTaskCompletion(tTask)).called(1);
  });

  test('يجب أن يُرجع Failure عند عدم وجود المهمة', () async {
    when(() => mockTaskRepo.completeTask('tsk_x', true))
        .thenAnswer((_) async => Left(NotFoundFailure('TASK_NOT_FOUND', 'غير موجودة')));

    final result = await useCase('tsk_x', true);

    expect(result, isA<Left>());
  });
}
```

---

## 10. مراجعة الكود (Code Review Checklist)

قبل قبول أي Pull Request:
- [ ] لا يوجد `import` من `data/` داخل `presentation/`.
- [ ] كل استثناء تقني تم تحويله إلى `Failure` موحّد.
- [ ] أكواد الأخطاء المستخدمة موثقة في `api_documentation.md`.
- [ ] أي تعديل في `isCompleted` يمر عبر `ProgressService`/`recordTaskCompletion`.
- [ ] الاختبارات مضافة للـ UseCase الجديد (نجاح + فشل).
- [ ] `dart format` و `dart analyze` بدون تحذيرات.
- [ ] لا `print()` متبقٍ في الكود.
- [ ] أسماء الملفات والكلاسات تطابق القسم 5 أعلاه.
