# نظام إدارة هرمي - منصة همة التوجيهي

## التصميم والواجهة

### نمط التصميم
- **المرجع الأساسي**: لوحات تحكم إدارية حديثة مثل Vercel Dashboard، GitHub Admin
- **النمط**: Clean Modern Dashboard + Dark Mode Support + Arabic RTL

### الألوان الأساسية
- Primary: #2563eb (أزرق إداري)
- Secondary: #64748b (رمادي متوسط)
- Success: #16a34a (أخضر للموافقة)
- Warning: #d97706 (برتقالي للانتظار)
- Danger: #dc2626 (أحمر للرفض)
- Background: #f8fafc (خلفية فاتحة)
- Card: #ffffff (أبيض للكروت)

### الخطوط
- العناوين: Inter font-weight 600-700
- النصوص: Inter font-weight 400-500
- الأرقام والإحصائيات: JetBrains Mono font-weight 500

### مكونات التصميم الرئيسية
- **الكروت**: خلفية بيضاء، حدود رفيعة، ظلال خفيفة
- **الأزرار**: ألوان متدرجة، hover effects ناعمة
- **الجداول**: تصميم نظيف مع فصل واضح بين الصفوف
- **النماذج**: تصميم متسق مع validation واضحة

## الصور المطلوبة
1. **hero-admin-dashboard.jpg** - صورة لوحة تحكم حديثة مع إحصائيات (Style: photorealistic, modern dashboard)
2. **region-management-icon.png** - أيقونة إدارة المناطق (Style: minimalist, blue theme)
3. **teacher-approval-process.jpg** - عملية موافقة المعلمين (Style: vector-style, workflow diagram)
4. **hierarchical-structure.png** - هيكل إداري هرمي (Style: minimalist, organizational chart)
5. **statistics-charts.jpg** - رسوم بيانية وإحصائيات (Style: modern, data visualization)
6. **user-management-interface.jpg** - واجهة إدارة المستخدمين (Style: clean, modern UI)

---

## مهام التطوير

### 1. إعداد قاعدة البيانات (Database Setup)
- [ ] إنشاء جداول النظام الهرمي الجديدة
- [ ] تحديث الجداول الموجودة بالحقول المطلوبة
- [ ] إدراج بيانات المناطق الأساسية (غزة، الشمال، الوسطى، الجنوب)
- [ ] إنشاء مدراء المناطق والوكلاء الافتراضيين

### 2. المكونات الأساسية (Core Components)
- [ ] **AdminDashboard.tsx** - لوحة تحكم المدير العام
- [ ] **RegionManagerDashboard.tsx** - لوحة تحكم مدير المنطقة
- [ ] **DeputyDashboard.tsx** - لوحة تحكم الوكيل
- [ ] **HierarchicalNavigation.tsx** - نظام التنقل الهرمي
- [ ] **StatisticsCards.tsx** - كروت الإحصائيات

### 3. إدارة الطلبات (Application Management)
- [ ] **ApplicationsList.tsx** - قائمة طلبات المعلمين
- [ ] **ApplicationDetails.tsx** - تفاصيل طلب معلم
- [ ] **ApplicationApproval.tsx** - نظام الموافقة والرفض
- [ ] **ApplicationFilters.tsx** - فلترة وبحث الطلبات

### 4. إدارة المستخدمين والمناطق (User & Region Management)
- [ ] **UserManagement.tsx** - إدارة المستخدمين
- [ ] **RegionManagement.tsx** - إدارة المناطق
- [ ] **DeputyManagement.tsx** - إدارة الوكلاء
- [ ] **PermissionsManager.tsx** - إدارة الصلاحيات

### 5. النماذج والواجهات (Forms & Interfaces)
- [ ] **EnhancedRegistration.tsx** - نموذج التسجيل المحدث
- [ ] **UserStatusManager.tsx** - إدارة حالات المستخدمين
- [ ] **RegionAssignment.tsx** - تعيين المناطق
- [ ] **TeacherApplicationForm.tsx** - نموذج طلب انضمام المعلمين

### 6. المكونات المساعدة (Helper Components)
- [ ] **StatusBadge.tsx** - شارات الحالة
- [ ] **ActionButtons.tsx** - أزرار الإجراءات
- [ ] **SearchAndFilter.tsx** - البحث والفلترة
- [ ] **DataTable.tsx** - جداول البيانات
- [ ] **NotificationSystem.tsx** - نظام الإشعارات

### 7. خدمات API (API Services)
- [ ] **adminService.ts** - خدمات المدير العام
- [ ] **regionService.ts** - خدمات إدارة المناطق
- [ ] **applicationService.ts** - خدمات طلبات المعلمين
- [ ] **userService.ts** - خدمات إدارة المستخدمين
- [ ] **permissionService.ts** - خدمات الصلاحيات

### 8. الصفحات الرئيسية (Main Pages)
- [ ] **Index.tsx** - الصفحة الرئيسية مع التوجيه حسب الدور
- [ ] **SuperAdminPage.tsx** - صفحة المدير العام
- [ ] **RegionManagerPage.tsx** - صفحة مدير المنطقة
- [ ] **DeputyPage.tsx** - صفحة الوكيل
- [ ] **TeacherApplicationPage.tsx** - صفحة طلب انضمام المعلم

### 9. نظام المصادقة والتوجيه (Auth & Routing)
- [ ] **AuthProvider.tsx** - موفر المصادقة
- [ ] **ProtectedRoute.tsx** - المسارات المحمية
- [ ] **RoleBasedAccess.tsx** - الوصول حسب الدور
- [ ] **LoginForm.tsx** - نموذج تسجيل الدخول المحدث

### 10. التقارير والإحصائيات (Reports & Analytics)
- [ ] **ReportsPage.tsx** - صفحة التقارير
- [ ] **AnalyticsDashboard.tsx** - لوحة الإحصائيات
- [ ] **RegionStatistics.tsx** - إحصائيات المناطق
- [ ] **TeacherApplicationStats.tsx** - إحصائيات طلبات المعلمين

## الهيكل الهرمي للنظام

### المدير العام (Super Admin)
- إدارة جميع المناطق والوكلاء
- مراجعة وموافقة جميع طلبات المعلمين
- إنشاء وتعديل مدراء المناطق
- الوصول إلى جميع التقارير والإحصائيات

### مدير المنطقة (Region Manager)
- إدارة الوكلاء في منطقته
- مراجعة طلبات المعلمين في المنطقة
- إدارة الصلاحيات والمواد المتاحة
- تقارير خاصة بالمنطقة

### الوكيل (Deputy)
- مراجعة طلبات انضمام المعلمين
- قبول أو رفض الطلبات مع إضافة ملاحظات
- متابعة حالة الطلبات المعالجة
- إحصائيات الطلبات الشخصية

### المعلم (Teacher)
- تقديم طلب انضمام للمنطقة المناسبة
- متابعة حالة الطلب
- تحديث البيانات والمستندات
- الوصول للمواد المتاحة بعد الموافقة

## قاعدة البيانات المطلوبة

### الجداول الجديدة:
1. **app_d2335_regions** - المناطق (غزة، الشمال، الوسطى، الجنوب)
2. **app_d2335_region_managers** - مدراء المناطق
3. **app_d2335_deputies** - الوكلاء
4. **app_d2335_teacher_applications** - طلبات انضمام المعلمين
5. **app_d2335_region_permissions** - صلاحيات المناطق
6. **app_d2335_directorate_subjects** - المديريات والمساقات المتاحة

### التحديثات على الجداول الموجودة:
- إضافة حقول region_id, deputy_id, application_status للمستخدمين
- ربط الفروع بمدراء المناطق
- إضافة معلومات المديرية والمساق للمواد

## معايير النجاح
- [ ] نظام هرمي واضح ومنظم
- [ ] واجهات مستخدم بديهية وسهلة الاستخدام باللغة العربية
- [ ] نظام صلاحيات محكم وآمن
- [ ] سرعة في معالجة الطلبات
- [ ] تقارير دقيقة وشاملة
- [ ] تجربة مستخدم متسقة عبر جميع المستويات
- [ ] دعم كامل للغة العربية والاتجاه من اليمين لليسار (RTL)
