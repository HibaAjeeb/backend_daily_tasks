# اختبار الـ API عبر Postman

## الاستيراد
1. افتح Postman ثم `Import`.
2. استورد الملفين:
   - `Daily Tasks API.postman_collection.json`
   - `Daily Tasks Local.postman_environment.json`
3. اختر البيئة **Daily Tasks Local** من أعلى يمين Postman.

## قبل البدء
- شغّل السيرفر: `php artisan serve` (المنفذ الافتراضي `8000`).
- تأكد أن `baseUrl` في البيئة يساوي `http://127.0.0.1:8000/api/v1`.
- **مهم جدًا**: في كل طلب POST/PUT/PATCH اختر:
  `Body` → `raw` → من القائمة المنسدلة اختر `JSON` (وليس `Text`).
  إذا أرسلت النص كـ `Text` فلن يقرأ Laravel الحقول وستحصل على خطأ `422` بأن كل الحقول مطلوبة.

## الترتيب الصحيح للاختبار
1. مجلد **Auth** → `Register` (أو `Login`).
   - يتم حفظ `accessToken` و`refreshToken` تلقائيًا في متغيرات المجموعة، وتُستخدم في بقية الطلبات.
   - سياسة كلمة المرور: 8 أحرف على الأقل + حرف كبير + رقم، مثال: `StrongPass123`.
2. `Categories` → `Create Category` (يُحفظ `categoryId` تلقائيًا).
3. `Study Plans` → `Create Study Plan` (يُحفظ `studyPlanId` تلقائيًا).
4. `Tasks` → `Create Task` (يُحفظ `taskId` تلقائيًا) ثم `Complete Task`.
5. `Reminders` → `Create Reminder` (يُحفظ `reminderId` تلقائيًا).
6. `Sport Sessions` → `Create Sport Session` ثم `Complete Sport Session`.
7. `Progress` و`Sync` بأي ترتيب.

## ملاحظات
- رسائل الخطأ تُعاد بالعربية افتراضيًا. لإرجاعها بالإنجليزية أضف الهيدر: `Accept-Language: en`.
- إذا حصلت على `422` راجع `error.details` في الاستجابة: كل عنصر يوضح الحقل والمشكلة.
- إذا حصلت على `400 INVALID_JSON` فمعناه أن جسم الطلب ليس JSON صالحًا (تحقق من اختيار `raw` → `JSON`).
- إذا حصلت على `404 CATEGORY_NOT_FOUND` أو `TASK_NOT_FOUND` فالمعرّف المرسل غير موجود؛ أنشئ السجل أولًا أو استخدم معرّفًا صحيحًا.
