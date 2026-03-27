        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // تأكيد الحذف
        function confirmDelete(message = 'هل أنت متأكد من الحذف؟') {
            return confirm(message);
        }

        // تبديل الشريط الجانبي في الموبايل
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // إخفاء التنبيهات تلقائياً
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('show')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);

        // تحديث الوقت
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ar-SA');
            const dateString = now.toLocaleDateString('ar-SA');
            
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = `${dateString} - ${timeString}`;
            }
        }

        // تحديث الوقت كل ثانية
        setInterval(updateTime, 1000);
        updateTime();

        // تأكيد إرسال النماذج المهمة
        document.addEventListener('DOMContentLoaded', function() {
            const dangerousForms = document.querySelectorAll('form[data-confirm]');
            dangerousForms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const message = form.getAttribute('data-confirm');
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            });
        });

        // تحسين تجربة المستخدم للجداول
        document.addEventListener('DOMContentLoaded', function() {
            const tables = document.querySelectorAll('table');
            tables.forEach(function(table) {
                // إضافة فئة للجداول المتجاوبة
                if (!table.parentElement.classList.contains('table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        });
    </script>
</body>
</html>