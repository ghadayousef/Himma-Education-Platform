/**
 * تفاعلات JavaScript  - منصة همّة التعليمية
 *  JavaScript Interactions - Himma Educational Platform
 */

class HimmaInteractions {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.initializeAnimations();
    }

    init() {
        // تهيئة المتغيرات الأساسية
        this.isTouch = 'ontouchstart' in window;
        this.animationQueue = [];
        this.scrollPosition = 0;
        this.isScrolling = false;
        
        // إعداد Intersection Observer للرسوم المتحركة
        this.setupIntersectionObserver();
        
        // إعداد مراقب تغيير الحجم
        this.setupResizeObserver();
    }

    setupEventListeners() {
        // أحداث التحميل
        document.addEventListener('DOMContentLoaded', () => {
            this.onDOMContentLoaded();
        });

        window.addEventListener('load', () => {
            this.onWindowLoad();
        });

        // أحداث التمرير
        window.addEventListener('scroll', this.throttle(() => {
            this.handleScroll();
        }, 16)); // 60fps

        // أحداث تغيير الحجم
        window.addEventListener('resize', this.debounce(() => {
            this.handleResize();
        }, 250));

        // أحداث اللمس والماوس
        this.setupInteractionEvents();

        // أحداث النماذج
        this.setupFormEvents();

        // أحداث الأزرار
        this.setupButtonEvents();
    }

    setupIntersectionObserver() {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.animateElement(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '50px'
        });

        // مراقبة العناصر القابلة للرسوم المتحركة
        document.querySelectorAll('[data-animate]').forEach(el => {
            this.observer.observe(el);
        });
    }

    setupResizeObserver() {
        if ('ResizeObserver' in window) {
            this.resizeObserver = new ResizeObserver(entries => {
                entries.forEach(entry => {
                    this.handleElementResize(entry.target, entry.contentRect);
                });
            });
        }
    }

    onDOMContentLoaded() {
        // إضافة فئات CSS للتحكم في الرسوم المتحركة
        document.body.classList.add('js-loaded');
        
        // تهيئة المكونات
        this.initializeComponents();
        
        // إعداد CSRF token للنماذج
        this.setupCSRFTokens();
        
        // تهيئة التحقق من النماذج
        this.initializeFormValidation();
    }

    onWindowLoad() {
        // إخفاء شاشة التحميل
        this.hideLoadingScreen();
        
        // تشغيل الرسوم المتحركة الأولية
        this.startInitialAnimations();
        
        // تحسين الأداء
        this.optimizePerformance();
    }

    initializeComponents() {
        // تهيئة البطاقات التفاعلية
        this.initializeCards();
        
        // تهيئة أشرطة التقدم
        this.initializeProgressBars();
        
        // تهيئة النوافذ المنبثقة
        this.initializeModals();
        
        // تهيئة القوائم المنسدلة
        this.initializeDropdowns();
        
        // تهيئة التبديل بين الألسنة
        this.initializeTabs();
    }

    initializeCards() {
        document.querySelectorAll('.card-enhanced').forEach(card => {
            // إضافة تأثير الإمالة ثلاثية الأبعاد
            card.addEventListener('mousemove', (e) => {
                if (!this.isTouch) {
                    this.applyTiltEffect(card, e);
                }
            });

            card.addEventListener('mouseleave', () => {
                this.resetTiltEffect(card);
            });

            // إضافة تأثير النقر
            card.addEventListener('click', (e) => {
                this.createRippleEffect(card, e);
            });
        });
    }

    applyTiltEffect(element, event) {
        const rect = element.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        
        const mouseX = event.clientX - centerX;
        const mouseY = event.clientY - centerY;
        
        const rotateX = (mouseY / rect.height) * -10;
        const rotateY = (mouseX / rect.width) * 10;
        
        element.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
        element.style.transition = 'transform 0.1s ease-out';
    }

    resetTiltEffect(element) {
        element.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateZ(0px)';
        element.style.transition = 'transform 0.3s ease-out';
    }

    createRippleEffect(element, event) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple-effect');

        element.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    initializeProgressBars() {
        document.querySelectorAll('.progress-enhanced').forEach(progressBar => {
            const fill = progressBar.querySelector('.progress-fill-enhanced');
            const targetWidth = fill.getAttribute('data-width') || '0%';
            
            // تأخير الرسوم المتحركة حتى تصبح مرئية
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            fill.style.width = targetWidth;
                        }, 200);
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            observer.observe(progressBar);
        });
    }

    initializeModals() {
        // إعداد النوافذ المنبثقة
        document.querySelectorAll('[data-modal-trigger]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = trigger.getAttribute('data-modal-trigger');
                this.openModal(modalId);
            });
        });

        document.querySelectorAll('[data-modal-close]').forEach(closeBtn => {
            closeBtn.addEventListener('click', () => {
                this.closeModal();
            });
        });

        // إغلاق عند النقر خارج النافذة
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal();
            }
        });

        // إغلاق بمفتاح Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.classList.add('modal-open');
            
            // تركيز على أول عنصر قابل للتفاعل
            const focusableElement = modal.querySelector('input, button, textarea, select, [tabindex]:not([tabindex="-1"])');
            if (focusableElement) {
                setTimeout(() => focusableElement.focus(), 100);
            }
        }
    }

    closeModal() {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            activeModal.classList.remove('active');
            document.body.classList.remove('modal-open');
        }
    }

    initializeDropdowns() {
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            const trigger = dropdown.querySelector('.dropdown-trigger');
            const menu = dropdown.querySelector('.dropdown-menu');

            if (trigger && menu) {
                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleDropdown(dropdown);
                });

                // إغلاق عند النقر خارج القائمة
                document.addEventListener('click', () => {
                    this.closeDropdown(dropdown);
                });
            }
        });
    }

    toggleDropdown(dropdown) {
        const isOpen = dropdown.classList.contains('active');
        
        // إغلاق جميع القوائم الأخرى
        document.querySelectorAll('.dropdown.active').forEach(d => {
            if (d !== dropdown) {
                this.closeDropdown(d);
            }
        });

        if (isOpen) {
            this.closeDropdown(dropdown);
        } else {
            this.openDropdown(dropdown);
        }
    }

    openDropdown(dropdown) {
        dropdown.classList.add('active');
        const menu = dropdown.querySelector('.dropdown-menu');
        if (menu) {
            menu.style.opacity = '0';
            menu.style.transform = 'translateY(-10px)';
            
            requestAnimationFrame(() => {
                menu.style.transition = 'all 0.2s ease';
                menu.style.opacity = '1';
                menu.style.transform = 'translateY(0)';
            });
        }
    }

    closeDropdown(dropdown) {
        dropdown.classList.remove('active');
    }

    initializeTabs() {
        document.querySelectorAll('.tabs-container').forEach(container => {
            const triggers = container.querySelectorAll('.tab-trigger');
            const contents = container.querySelectorAll('.tab-content');

            triggers.forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = trigger.getAttribute('data-tab');
                    this.switchTab(container, targetId);
                });
            });
        });
    }

    switchTab(container, targetId) {
        const triggers = container.querySelectorAll('.tab-trigger');
        const contents = container.querySelectorAll('.tab-content');

        // إزالة الحالة النشطة من جميع العناصر
        triggers.forEach(t => t.classList.remove('active'));
        contents.forEach(c => c.classList.remove('active'));

        // تفعيل العناصر المطلوبة
        const activeTrigger = container.querySelector(`[data-tab="${targetId}"]`);
        const activeContent = container.querySelector(`#${targetId}`);

        if (activeTrigger && activeContent) {
            activeTrigger.classList.add('active');
            activeContent.classList.add('active');
        }
    }

    setupFormEvents() {
        // التحقق من النماذج في الوقت الفعلي
        document.querySelectorAll('input, textarea, select').forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });

            input.addEventListener('input', () => {
                this.clearFieldError(input);
            });
        });

        // إرسال النماذج
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    validateField(field) {
        const value = field.value.trim();
        const rules = this.getValidationRules(field);
        const errors = [];

        // التحقق من الحقول المطلوبة
        if (rules.required && !value) {
            errors.push('هذا الحقل مطلوب');
        }

        if (value) {
            // التحقق من البريد الإلكتروني
            if (rules.email && !this.isValidEmail(value)) {
                errors.push('البريد الإلكتروني غير صحيح');
            }

            // التحقق من الطول الأدنى
            if (rules.minLength && value.length < rules.minLength) {
                errors.push(`يجب أن يكون الطول ${rules.minLength} أحرف على الأقل`);
            }

            // التحقق من الطول الأقصى
            if (rules.maxLength && value.length > rules.maxLength) {
                errors.push(`يجب أن يكون الطول ${rules.maxLength} أحرف كحد أقصى`);
            }

            // التحقق من النمط
            if (rules.pattern && !rules.pattern.test(value)) {
                errors.push(rules.patternMessage || 'التنسيق غير صحيح');
            }
        }

        if (errors.length > 0) {
            this.showFieldError(field, errors[0]);
            return false;
        } else {
            this.clearFieldError(field);
            return true;
        }
    }

    getValidationRules(field) {
        const rules = {};
        
        if (field.hasAttribute('required')) rules.required = true;
        if (field.type === 'email') rules.email = true;
        if (field.hasAttribute('minlength')) rules.minLength = parseInt(field.getAttribute('minlength'));
        if (field.hasAttribute('maxlength')) rules.maxLength = parseInt(field.getAttribute('maxlength'));
        if (field.hasAttribute('pattern')) {
            rules.pattern = new RegExp(field.getAttribute('pattern'));
            rules.patternMessage = field.getAttribute('data-pattern-message');
        }

        return rules;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);
        
        field.classList.add('error-state');
        
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.textContent = message;
        
        field.parentNode.appendChild(errorElement);
    }

    clearFieldError(field) {
        field.classList.remove('error-state');
        
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }

    validateForm(form) {
        const fields = form.querySelectorAll('input, textarea, select');
        let isValid = true;

        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    setupButtonEvents() {
        // تأثيرات الأزرار
        document.querySelectorAll('.btn-enhanced').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.createRippleEffect(btn, e);
            });

            // تأثير التحميل
            if (btn.hasAttribute('data-loading')) {
                btn.addEventListener('click', () => {
                    this.showButtonLoading(btn);
                });
            }
        });
    }

    showButtonLoading(button) {
        const originalText = button.textContent;
        button.classList.add('loading');
        button.disabled = true;
        
        // إعادة تعيين الحالة بعد فترة (يمكن تخصيصها)
        setTimeout(() => {
            this.hideButtonLoading(button, originalText);
        }, 2000);
    }

    hideButtonLoading(button, originalText) {
        button.classList.remove('loading');
        button.disabled = false;
        button.textContent = originalText;
    }

    setupInteractionEvents() {
        // تأثيرات اللمس للأجهزة المحمولة
        if (this.isTouch) {
            document.querySelectorAll('.hover-lift').forEach(element => {
                element.addEventListener('touchstart', () => {
                    element.classList.add('touch-active');
                });

                element.addEventListener('touchend', () => {
                    setTimeout(() => {
                        element.classList.remove('touch-active');
                    }, 150);
                });
            });
        }
    }

    handleScroll() {
        const currentScroll = window.pageYOffset;
        const scrollDiff = currentScroll - this.scrollPosition;
        
        // إخفاء/إظهار شريط التنقل
        this.handleNavbarScroll(scrollDiff);
        
        // تحديث شريط التقدم
        this.updateScrollProgress();
        
        // تأثيرات المنظر (Parallax)
        this.handleParallaxEffects();
        
        this.scrollPosition = currentScroll;
    }

    handleNavbarScroll(scrollDiff) {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            if (scrollDiff > 0 && this.scrollPosition > 100) {
                navbar.classList.add('hidden');
            } else {
                navbar.classList.remove('hidden');
            }
        }
    }

    updateScrollProgress() {
        const progressBar = document.querySelector('.scroll-progress');
        if (progressBar) {
            const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrolled = (this.scrollPosition / scrollHeight) * 100;
            progressBar.style.width = Math.min(scrolled, 100) + '%';
        }
    }

    handleParallaxEffects() {
        document.querySelectorAll('[data-parallax]').forEach(element => {
            const speed = parseFloat(element.getAttribute('data-parallax')) || 0.5;
            const yPos = -(this.scrollPosition * speed);
            element.style.transform = `translateY(${yPos}px)`;
        });
    }

    handleResize() {
        // إعادة حساب الأبعاد
        this.recalculateDimensions();
        
        // تحديث الرسوم المتحركة
        this.updateAnimations();
    }

    handleElementResize(element, rect) {
        // التعامل مع تغيير حجم العناصر
        if (element.classList.contains('chart-container')) {
            this.resizeChart(element);
        }
    }

    animateElement(element) {
        const animationType = element.getAttribute('data-animate');
        const delay = parseInt(element.getAttribute('data-delay')) || 0;
        
        setTimeout(() => {
            element.classList.add('animate-' + animationType);
        }, delay);
    }

    setupCSRFTokens() {
        // إضافة CSRF tokens للنماذج
        document.querySelectorAll('form').forEach(form => {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = this.getCSRFToken();
                form.appendChild(csrfInput);
            }
        });
    }

    getCSRFToken() {
        // الحصول على CSRF token من meta tag أو cookie
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        return metaToken ? metaToken.getAttribute('content') : '';
    }

    initializeFormValidation() {
        // إعداد التحقق المتقدم من النماذج
        document.querySelectorAll('.form-enhanced').forEach(form => {
            this.enhanceForm(form);
        });
    }

    enhanceForm(form) {
        // إضافة مؤشرات قوة كلمة المرور
        const passwordFields = form.querySelectorAll('input[type="password"]');
        passwordFields.forEach(field => {
            this.addPasswordStrengthIndicator(field);
        });

        // إضافة التحقق من تطابق كلمات المرور
        const confirmPasswordField = form.querySelector('input[name="confirm_password"]');
        if (confirmPasswordField) {
            this.addPasswordConfirmation(confirmPasswordField);
        }
    }

    addPasswordStrengthIndicator(passwordField) {
        const indicator = document.createElement('div');
        indicator.className = 'password-strength';
        indicator.innerHTML = `
            <div class="strength-bar">
                <div class="strength-fill"></div>
            </div>
            <div class="strength-text">قوة كلمة المرور</div>
        `;
        
        passwordField.parentNode.appendChild(indicator);
        
        passwordField.addEventListener('input', () => {
            this.updatePasswordStrength(passwordField, indicator);
        });
    }

    updatePasswordStrength(field, indicator) {
        const password = field.value;
        const strength = this.calculatePasswordStrength(password);
        
        const fill = indicator.querySelector('.strength-fill');
        const text = indicator.querySelector('.strength-text');
        
        fill.style.width = strength.percentage + '%';
        fill.className = 'strength-fill ' + strength.class;
        text.textContent = strength.text;
    }

    calculatePasswordStrength(password) {
        let score = 0;
        let feedback = [];
        
        if (password.length >= 8) score += 25;
        else feedback.push('8 أحرف على الأقل');
        
        if (/[a-z]/.test(password)) score += 25;
        else feedback.push('حرف صغير');
        
        if (/[A-Z]/.test(password)) score += 25;
        else feedback.push('حرف كبير');
        
        if (/[0-9]/.test(password)) score += 25;
        else feedback.push('رقم');
        
        if (/[^A-Za-z0-9]/.test(password)) score += 25;
        else feedback.push('رمز خاص');
        
        let strengthClass, strengthText;
        
        if (score < 50) {
            strengthClass = 'weak';
            strengthText = 'ضعيفة - تحتاج: ' + feedback.join(', ');
        } else if (score < 75) {
            strengthClass = 'medium';
            strengthText = 'متوسطة';
        } else if (score < 100) {
            strengthClass = 'good';
            strengthText = 'جيدة';
        } else {
            strengthClass = 'strong';
            strengthText = 'قوية جداً';
        }
        
        return {
            percentage: Math.min(score, 100),
            class: strengthClass,
            text: strengthText
        };
    }

    // دوال مساعدة
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    hideLoadingScreen() {
        const loadingScreen = document.querySelector('.loading-screen');
        if (loadingScreen) {
            loadingScreen.classList.add('fade-out');
            setTimeout(() => {
                loadingScreen.remove();
            }, 500);
        }
    }

    startInitialAnimations() {
        // تشغيل الرسوم المتحركة الأولية
        document.querySelectorAll('[data-animate-on-load]').forEach((element, index) => {
            setTimeout(() => {
                element.classList.add('animate-fade-in');
            }, index * 100);
        });
    }

    optimizePerformance() {
        // تحسين الأداء
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => {
                this.preloadImages();
                this.optimizeImages();
            });
        }
    }

    preloadImages() {
        // تحميل الصور مسبقاً
        document.querySelectorAll('img[data-src]').forEach(img => {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.getAttribute('data-src');
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            imageObserver.observe(img);
        });
    }

    optimizeImages() {
        // تحسين جودة الصور حسب حجم الشاشة
        if ('devicePixelRatio' in window && window.devicePixelRatio > 1) {
            document.querySelectorAll('img[data-src-2x]').forEach(img => {
                img.src = img.getAttribute('data-src-2x');
            });
        }
    }

    recalculateDimensions() {
        // إعادة حساب الأبعاد عند تغيير حجم النافذة
        document.querySelectorAll('[data-responsive]').forEach(element => {
            this.updateResponsiveElement(element);
        });
    }

    updateResponsiveElement(element) {
        const breakpoint = window.innerWidth;
        const responsiveRules = JSON.parse(element.getAttribute('data-responsive') || '{}');
        
        Object.keys(responsiveRules).forEach(bp => {
            if (breakpoint <= parseInt(bp)) {
                Object.assign(element.style, responsiveRules[bp]);
            }
        });
    }

    updateAnimations() {
        // تحديث الرسوم المتحركة عند تغيير الحجم
        this.animationQueue.forEach(animation => {
            if (animation.update) {
                animation.update();
            }
        });
    }

    resizeChart(chartContainer) {
        // تحديث حجم الرسوم البيانية
        const chart = chartContainer.querySelector('canvas');
        if (chart && chart.chart) {
            chart.chart.resize();
        }
    }
}

// تهيئة النظام عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    window.himmaInteractions = new HimmaInteractions();
});

// إضافة CSS للتأثيرات
const style = document.createElement('style');
style.textContent = `
    .ripple-effect {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    }

    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }

    .field-error {
        color: #fa709a;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        animation: shake 0.3s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .password-strength {
        margin-top: 0.5rem;
    }

    .strength-bar {
        width: 100%;
        height: 4px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 2px;
        overflow: hidden;
    }

    .strength-fill {
        height: 100%;
        transition: all 0.3s ease;
        border-radius: 2px;
    }

    .strength-fill.weak { background: #fa709a; }
    .strength-fill.medium { background: #ffd93d; }
    .strength-fill.good { background: #6bcf7f; }
    .strength-fill.strong { background: #4facfe; }

    .strength-text {
        font-size: 0.75rem;
        margin-top: 0.25rem;
        opacity: 0.8;
    }

    .loading-screen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        transition: opacity 0.5s ease;
    }

    .loading-screen.fade-out {
        opacity: 0;
    }

    .touch-active {
        transform: scale(0.95) !important;
    }

    .navbar.hidden {
        transform: translateY(-100%);
    }

    .scroll-progress {
        position: fixed;
        top: 0;
        left: 0;
        height: 3px;
        background: linear-gradient(90deg, #4facfe, #43e97b);
        z-index: 1000;
        transition: width 0.1s ease;
    }
`;

document.head.appendChild(style);