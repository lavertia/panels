# رؤية Lavertia — مرجع المنتج

## لماذا يوجد Lavertia؟

**Lavertia هو PHP-driven Laravel application framework يوفّر حرية UI الكاملة ويقلّل الوقت اللازم لتطوير تطبيقات جميلة وعملية عبر Inertia.**

ليس نسخة Filament بواجهة React، وليس Admin Panel إلزاميًا، وليس CSS framework. يجب أن يستطيع المطور إنشاء Landing Page أو Authentication Page أو Portal أو Dashboard أو CRUD كامل من PHP-first APIs؛ وعند الحاجة يبني React/Vue/Svelte Components خاصة به.

**المصدر:** [إجابات المؤسس عن جميع الأسئلة المئة](VISION_100_ANSWERS.md). هذه الرؤية تُميز ما اعتمده المستخدم عن اقتراحات التنفيذ.

## المبادئ غير القابلة للمساومة

1. **Page أولًا، Panel اختياري.** الصفحة وحدة مستقلة وتُسجل في Laravel route دون Panel. Panel تجمع صفحات وتضيف ميزات مشتركة.
2. **Frontend مستقل.** React أولوية تنفيذ، مع Protocol مشترك لـ Vue/Svelte وليس ادعاء تماثل دون اختبارات.
3. **PHP-first، وليس PHP-only.** Declarative fluent components للسيناريوهات الشائعة، ومسار Frontend escape hatch للاستخدامات المعقدة.
4. **UI Kit قابلة للتبديل.** كل التنفيذ المرئي، بما فيه Shell وForms وTables وModals، يقع خلف Adapter/Registry. لا يستورد Core shadcn.
5. **Theme مستقلة عن UI Kit.** Tokens للألوان والقياسات والخطوط والدلالة البصرية؛ Preset تجمع Kit وTheme وLayout.
6. **تبدأ جميلة وجاهزة.** صفحة تعمل وواجهات متجاوبة وAccessible، لا مجرد Renderer تجريبي.
7. **Plugins جزء من المنتج.** مكونات ومساهمات قابلة للنشر، ويمكن بيع Plugins في المستقبل؛ Marketplace ليست شرطًا لإطلاق Core.
8. **Backend موثوق.** Laravel مسؤول عن Authorization وValidation وTenant Isolation والبيانات؛ UI لا تمنح صلاحيات بنفسها.
9. **Performance مبني على القياس.** Eloquent Server Pagination وLazy Components دون تحميل ملايين السجلات للمتصفح.
10. **التوافق والاختبارات.** API versioning، UI Kit capability checks، اختبارات RTL/SSR/a11y والتوافق بين Frontends.

## المفاهيم

- **Application:** نقطة تكوين Default UI Kit والـ Theme وجميع Pages، حتى خارج Panel.
- **Page:** محتوى ومسار وسلوك مستقل. يمكن إسنادها اختياريًا إلى Panel/Layouts.
- **Layout:** كيفية ترتيب محتوى الصفحة. ليست مساوية للـ Panel.
- **Panel:** بيئة تنظيمية اختيارية مع Navigation وShell وTenancy ومكونات مشتركة.
- **UI Kit:** Implementation/Adapters للمكونات المرئية (مثل shadcn أو headless).
- **Theme:** Design tokens قابلة للتطبيق على Kits متوافقة.
- **Preset:** اختيار جاهز يجمع Kit وTheme وLayout وإعدادات الافتراض.
- **Component Registry:** ربط type المرسل عبر Protocol بمكون Frontend محلي أو من Package.
- **Plugin:** حزمة قابلة للتوسعة قد تضم PHP schema وRenderer وAssets وRoutes وTests.

## ما اعتمده المستخدم صراحة في الإجابات

- **Q5:** جميع أنواع الصفحات، وليس Panel وحدها.
- **Q6:** سرعة التطوير + كثرة المكونات + إمكانية بيع Plugins للمطورين.
- **Q21:** Panel اختيارية تُنظم مجموعة صفحات.
- **Q24:** لا تقيّد Authentication بـ Fortify؛ يمكن تكامل نظام خاص أو مزود آخر مثل Supabase.
- **Q25/42/65:** Shell/UI أكبر من Sidebar/Header؛ تدعم مناطق متعددة وتركيبًا حرًا.
- **Q27:** Default UI Kit على مستوى التطبيق مع override داخل Panel، مثل Marketing Kit وAdmin Kit.
- **Q1–4, Q7–20, Q22–23, Q26, Q28–60, Q62–63, Q66–100:** قرارات اتجاهية موثقة بالكامل في المصدر، وليست كلها مزايا منفذة.

## مسائل لم تُحسم — لا تعتمد في API مستقرة

- **Q61:** أنواع Page الرسمية لـ Auth/Marketing/Panel وأسماء أصنافها.
- **Q64:** Layout API النهائية والعلاقة البرمجية بينها وبين Panel (الفرق المفاهيمي فقط مفسَّر).
- اختيار طريقة توزيع Plugins التجارية وسياسة السوق والترخيص لم يُحدَّد تفصيليًا.
- عائلة مكتبة UI الافتراضية للإصدار المستقر وأساليب mixing/fallback تحتاج اختبار توافق قبل الالتزام.

## معيار نجاح Lavertia

يجب أن يستطيع مطور Laravel البدء بـ UI جميلة، إنشاء CRUD آمن في وقت قصير، استبدال UI Kit دون إعادة كتابة Schema، وامتلاك كامل مصدر مكوناته حين يحتاج — من أول صفحة عامة حتى تطبيق أعمال متكامل.

**حالة التنفيذ:** هذه وثيقة رؤية؛ ليست قائمة Features موجودة. راجع [Feature Roadmap](FEATURE_ROADMAP.md) للحصول على الحالات والتبعيات والاختبارات.
