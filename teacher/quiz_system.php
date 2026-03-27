<?php
/**
 * نظام الاختبارات  - منصة همّة التوجيهي
 * Simple Quiz System - Himma Educational Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول كمعلم
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../home/index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();
$teacher_id = $_SESSION['user_id'];

// تحديث جداول قاعدة البيانات إذا لزم الأمر
try {
    // التأكد من وجود جدول quiz_questions مع الحقول المطلوبة
    $conn->exec("
        CREATE TABLE IF NOT EXISTS quiz_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT NOT NULL,
            question_text TEXT NOT NULL,
            question_type ENUM('multiple_choice', 'true_false', 'short_answer') DEFAULT 'multiple_choice',
            options JSON NULL,
            correct_answer TEXT,
            marks INT DEFAULT 5,
            order_num INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // التأكد من وجود جدول quiz_options
    $conn->exec("
        CREATE TABLE IF NOT EXISTS quiz_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            option_text TEXT NOT NULL,
            is_correct BOOLEAN DEFAULT FALSE,
            order_number INT DEFAULT 1,
            FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // تجاهل الأخطاء إذا كانت الجداول موجودة
}

$action = $_GET['action'] ?? 'list';
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$message = '';
$error = '';

// معالجة إنشاء اختبار جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $title = trim($_POST['title']);
    $subject_id = intval($_POST['subject_id']);
    $description = trim($_POST['description']);
    $duration = intval($_POST['duration']);
    $total_marks = intval($_POST['total_marks']);
    $pass_marks = intval($_POST['pass_marks']);
    
    if (!empty($title) && $subject_id > 0) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO quizzes (subject_id, title, description, duration, total_marks, pass_marks, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$subject_id, $title, $description, $duration, $total_marks, $pass_marks]);
            $new_quiz_id = $conn->lastInsertId();
            $message = "تم إنشاء الاختبار بنجاح!";
            $action = 'edit';
            $quiz_id = $new_quiz_id;
        } catch (Exception $e) {
            $error = "خطأ في إنشاء الاختبار: " . $e->getMessage();
        }
    } else {
        $error = "يرجى ملء جميع الحقول المطلوبة!";
    }
}

// معالجة إضافة سؤال بسيط
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_simple_question'])) {
    $quiz_id = intval($_POST['quiz_id']);
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];
    $marks = intval($_POST['marks']);
    
    if (!empty($question_text) && $quiz_id > 0) {
        try {
            // حساب الترقيم التالي
            $order_stmt = $conn->prepare("SELECT COALESCE(MAX(order_num), 0) + 1 as next_order FROM quiz_questions WHERE quiz_id = ?");
            $order_stmt->execute([$quiz_id]);
            $next_order = $order_stmt->fetch()['next_order'];
            
            $options = [];
            $correct_answer = '';
            
            // معالجة الخيارات حسب نوع السؤال
            if ($question_type === 'multiple_choice') {
                $option_texts = array_filter($_POST['options'], function($opt) { return !empty(trim($opt)); });
                $correct_option = intval($_POST['correct_option']) - 1;
                
                foreach ($option_texts as $index => $option_text) {
                    $options[] = trim($option_text);
                }
                
                if (isset($options[$correct_option])) {
                    $correct_answer = $options[$correct_option];
                }
            } elseif ($question_type === 'true_false') {
                $options = ['صح', 'خطأ'];
                $correct_answer = $_POST['correct_tf'] === 'true' ? 'صح' : 'خطأ';
            } else {
                $correct_answer = trim($_POST['short_answer']);
            }
            
            // إدراج السؤال
            $insert_question = $conn->prepare("
                INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer, marks, order_num) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_question->execute([
                $quiz_id, 
                $question_text, 
                $question_type, 
                json_encode($options), 
                $correct_answer, 
                $marks, 
                $next_order
            ]);
            
            $message = "تم إضافة السؤال بنجاح!";
        } catch (Exception $e) {
            $error = "خطأ في إضافة السؤال: " . $e->getMessage();
        }
    } else {
        $error = "يرجى ملء جميع حقول السؤال!";
    }
}

// معالجة استيراد الأسئلة المبسط
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_questions_simple'])) {
    $quiz_id = intval($_POST['quiz_id']);
    $questions_text = trim($_POST['questions_text']);
    
    if (!empty($questions_text) && $quiz_id > 0) {
        $imported_count = 0;
        $lines = explode("\n", $questions_text);
        $current_question = '';
        $current_options = [];
        $current_answer = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // بداية سؤال جديد (رقم + نقطة أو شرطة)
            if (preg_match('/^(\d+)[.\-\)]?\s*(.+)/', $line, $matches)) {
                // حفظ السؤال السابق
                if (!empty($current_question)) {
                    if (saveSimpleQuestion($conn, $quiz_id, $current_question, $current_options, $current_answer)) {
                        $imported_count++;
                    }
                }
                
                // بداية سؤال جديد
                $current_question = $matches[2];
                $current_options = [];
                $current_answer = '';
            }
            // خيارات (أ، ب، ج، د أو a، b، c، d)
            elseif (preg_match('/^([أابجدهـوزحطيa-d])[.\-\)]\s*(.+)/i', $line, $matches)) {
                $current_options[] = $matches[2];
            }
            // الإجابة الصحيحة
            elseif (preg_match('/(الإجابة|الاجابة|إجابة|جواب|صحيح|correct|answer).*?:?\s*(.+)/i', $line, $matches)) {
                $current_answer = trim($matches[2]);
            }
            // إضافة للسؤال الحالي
            elseif (!empty($current_question) && !preg_match('/^[أابجدهـوزحطيa-d][.\-\)]/i', $line)) {
                $current_question .= ' ' . $line;
            }
        }
        
        // حفظ السؤال الأخير
        if (!empty($current_question)) {
            if (saveSimpleQuestion($conn, $quiz_id, $current_question, $current_options, $current_answer)) {
                $imported_count++;
            }
        }
        
        if ($imported_count > 0) {
            $message = "تم استيراد $imported_count سؤال بنجاح!";
        } else {
            $error = "لم يتم العثور على أسئلة صالحة للاستيراد!";
        }
    }
}

// دالة حفظ السؤال البسيط
function saveSimpleQuestion($conn, $quiz_id, $question_text, $options, $answer) {
    try {
        // تحديد نوع السؤال
        $question_type = 'short_answer';
        if (!empty($options)) {
            if (count($options) == 2 && (in_array('صح', $options) || in_array('خطأ', $options))) {
                $question_type = 'true_false';
                $options = ['صح', 'خطأ'];
            } else {
                $question_type = 'multiple_choice';
            }
        }
        
        // تحديد الإجابة الصحيحة
        $correct_answer = $answer;
        if ($question_type === 'true_false') {
            $correct_answer = (stripos($answer, 'صح') !== false || stripos($answer, 'true') !== false) ? 'صح' : 'خطأ';
        }
        
        // حساب الترقيم
        $order_stmt = $conn->prepare("SELECT COALESCE(MAX(order_num), 0) + 1 as next_order FROM quiz_questions WHERE quiz_id = ?");
        $order_stmt->execute([$quiz_id]);
        $next_order = $order_stmt->fetch()['next_order'];
        
        // إدراج السؤال
        $insert_question = $conn->prepare("
            INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer, marks, order_num) 
            VALUES (?, ?, ?, ?, ?, 5, ?)
        ");
        $insert_question->execute([
            $quiz_id, 
            $question_text, 
            $question_type, 
            json_encode($options), 
            $correct_answer, 
            $next_order
        ]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// حذف سؤال
if (isset($_GET['delete_question'])) {
    $question_id = intval($_GET['delete_question']);
    try {
        $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $message = "تم حذف السؤال بنجاح!";
    } catch (Exception $e) {
        $error = "خطأ في حذف السؤال!";
    }
}

// جلب البيانات
$quiz = null;
$questions = [];
if ($quiz_id > 0) {
    $quiz_stmt = $conn->prepare("
        SELECT q.*, s.name as subject_name FROM quizzes q
        INNER JOIN subjects s ON q.subject_id = s.id
        WHERE q.id = ?
    ");
    $quiz_stmt->execute([$quiz_id]);
    $quiz = $quiz_stmt->fetch();
    
    if ($quiz) {
        $questions_stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_num ASC");
        $questions_stmt->execute([$quiz_id]);
        $questions = $questions_stmt->fetchAll();
    }
}

// جلب اختبارات المعلم
$quizzes_stmt = $conn->prepare("
    SELECT q.*, s.name as subject_name,
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as questions_count
    FROM quizzes q
    INNER JOIN subjects s ON q.subject_id = s.id
    WHERE s.teacher_id = ?
    ORDER BY q.created_at DESC
");
$quizzes_stmt->execute([$teacher_id]);
$quizzes = $quizzes_stmt->fetchAll();

// جلب المواد
$subjects_stmt = $conn->prepare("SELECT * FROM subjects WHERE teacher_id = ? AND is_active = 1");
$subjects_stmt->execute([$teacher_id]);
$subjects = $subjects_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام الاختبارات  - منصة همّة التوجيهي</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }
        
        .quiz-item {
            border-right: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .quiz-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .question-item {
            border-right: 4px solid #28a745;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
        }
        
        .simple-form {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #667eea;
            font-weight: 600;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 10px;
        }
        
        .import-box {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
        }
        
        .import-box:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../home/index.php">
                <i class="fas fa-graduation-cap"></i> همّة التوجيهي
            </a>
            <div class="navbar-nav me-auto">
                <a class="nav-link text-white" href="../home/index.php">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
                <a class="nav-link text-white" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="text-center mb-4">
            <h1><i class="fas fa-clipboard-list text-primary"></i> نظام الاختبارات </h1>
            <p class="text-muted">إدارة سهلة وبسيطة للاختبارات والأسئلة</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo ($action === 'list') ? 'active' : ''; ?>" href="?action=list">
                    <i class="fas fa-list"></i> قائمة الاختبارات
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($action === 'create') ? 'active' : ''; ?>" href="?action=create">
                    <i class="fas fa-plus"></i> إنشاء اختبار
                </a>
            </li>
            <?php if ($quiz_id > 0): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($action === 'edit') ? 'active' : ''; ?>" href="?action=edit&quiz_id=<?php echo $quiz_id; ?>">
                        <i class="fas fa-edit"></i> إدارة الأسئلة
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($action === 'import') ? 'active' : ''; ?>" href="?action=import&quiz_id=<?php echo $quiz_id; ?>">
                        <i class="fas fa-upload"></i> استيراد أسئلة
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Content -->
        <?php if ($action === 'list'): ?>
            <!-- قائمة الاختبارات -->
            <div class="row">
                <?php if (empty($quizzes)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                            <h4>لا توجد اختبارات بعد</h4>
                            <p class="text-muted">أنشئ اختبارك الأول الآن!</p>
                            <a href="?action=create" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus"></i> إنشاء اختبار جديد
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($quizzes as $q): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card quiz-item h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($q['title']); ?></h5>
                                    <p class="text-muted small"><?php echo htmlspecialchars($q['subject_name']); ?></p>
                                    <div class="mb-3">
                                        <span class="badge bg-primary me-2"><?php echo $q['questions_count']; ?> سؤال</span>
                                        <span class="badge bg-success me-2"><?php echo $q['total_marks']; ?> درجة</span>
                                        <span class="badge bg-warning"><?php echo $q['duration']; ?> دقيقة</span>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <a href="?action=edit&quiz_id=<?php echo $q['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> إدارة الأسئلة
                                        </a>
                                        <a href="?action=import&quiz_id=<?php echo $q['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-upload"></i> استيراد أسئلة
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'create'): ?>
            <!-- إنشاء اختبار جديد -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="simple-form">
                        <h4 class="mb-4"><i class="fas fa-plus text-primary"></i> إنشاء اختبار جديد</h4>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">عنوان الاختبار *</label>
                                    <input type="text" name="title" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">المادة *</label>
                                    <select name="subject_id" class="form-select" required>
                                        <option value="">اختر المادة...</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>">
                                                <?php echo htmlspecialchars($subject['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">وصف الاختبار</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">المدة (بالدقائق) *</label>
                                    <input type="number" name="duration" class="form-control" value="60" min="1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">إجمالي الدرجات *</label>
                                    <input type="number" name="total_marks" class="form-control" value="100" min="1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">درجة النجاح *</label>
                                    <input type="number" name="pass_marks" class="form-control" value="60" min="1" required>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" name="create_quiz" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus"></i> إنشاء الاختبار
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'edit' && $quiz): ?>
            <!-- إدارة الأسئلة -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-question-circle"></i> 
                                أسئلة اختبار: <?php echo htmlspecialchars($quiz['title']); ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($questions); ?> سؤال</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($questions)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">لا توجد أسئلة بعد. أضف السؤال الأول!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($questions as $index => $question): ?>
                                    <div class="card question-item">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6>السؤال <?php echo $index + 1; ?></h6>
                                                <div>
                                                    <span class="badge bg-primary"><?php echo $question['marks']; ?> درجة</span>
                                                    <a href="?action=edit&quiz_id=<?php echo $quiz_id; ?>&delete_question=<?php echo $question['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger ms-2"
                                                       onclick="return confirm('هل أنت متأكد من حذف هذا السؤال؟')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            
                                            <p class="mb-3"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                            
                                            <?php if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false'): ?>
                                                <?php $options = json_decode($question['options'], true) ?? []; ?>
                                                <div class="options-list">
                                                    <?php foreach ($options as $opt_index => $option): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" disabled 
                                                                   <?php echo ($option === $question['correct_answer']) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label <?php echo ($option === $question['correct_answer']) ? 'text-success fw-bold' : ''; ?>">
                                                                <?php echo htmlspecialchars($option); ?>
                                                                <?php if ($option === $question['correct_answer']): ?>
                                                                    <i class="fas fa-check text-success"></i>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <strong>الإجابة الصحيحة:</strong> <?php echo htmlspecialchars($question['correct_answer']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- إضافة سؤال جديد -->
                    <div class="simple-form">
                        <h5 class="mb-3"><i class="fas fa-plus text-success"></i> إضافة سؤال جديد</h5>
                        
                        <form method="POST" id="questionForm">
                            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">نوع السؤال</label>
                                <select name="question_type" class="form-select" onchange="toggleQuestionType()">
                                    <option value="multiple_choice">اختيار من متعدد</option>
                                    <option value="true_false">صح/خطأ</option>
                                    <option value="short_answer">إجابة قصيرة</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">نص السؤال *</label>
                                <textarea name="question_text" class="form-control" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">الدرجة *</label>
                                <input type="number" name="marks" class="form-control" value="5" min="1" required>
                            </div>
                            
                            <!-- خيارات الاختيار من متعدد -->
                            <div id="multipleChoiceOptions">
                                <label class="form-label">الخيارات</label>
                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text">أ)</span>
                                        <input type="text" name="options[]" class="form-control" placeholder="الخيار الأول">
                                        <div class="input-group-text">
                                            <input type="radio" name="correct_option" value="1" checked>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text">ب)</span>
                                        <input type="text" name="options[]" class="form-control" placeholder="الخيار الثاني">
                                        <div class="input-group-text">
                                            <input type="radio" name="correct_option" value="2">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text">ج)</span>
                                        <input type="text" name="options[]" class="form-control" placeholder="الخيار الثالث">
                                        <div class="input-group-text">
                                            <input type="radio" name="correct_option" value="3">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">د)</span>
                                        <input type="text" name="options[]" class="form-control" placeholder="الخيار الرابع">
                                        <div class="input-group-text">
                                            <input type="radio" name="correct_option" value="4">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- خيارات صح/خطأ -->
                            <div id="trueFalseOptions" style="display: none;">
                                <label class="form-label">الإجابة الصحيحة</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="correct_tf" value="true" checked>
                                    <label class="form-check-label">صح</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="correct_tf" value="false">
                                    <label class="form-check-label">خطأ</label>
                                </div>
                            </div>
                            
                            <!-- الإجابة القصيرة -->
                            <div id="shortAnswerOptions" style="display: none;">
                                <label class="form-label">الإجابة الصحيحة</label>
                                <textarea name="short_answer" class="form-control mb-3" rows="2" placeholder="اكتب الإجابة الصحيحة هنا..."></textarea>
                            </div>
                            
                            <button type="submit" name="add_simple_question" class="btn btn-success w-100">
                                <i class="fas fa-plus"></i> إضافة السؤال
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'import' && $quiz): ?>
            <!-- استيراد الأسئلة -->
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-upload"></i> 
                                استيراد أسئلة لاختبار: <?php echo htmlspecialchars($quiz['title']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                                
                                <div class="import-box mb-4">
                                    <i class="fas fa-file-text fa-3x text-muted mb-3"></i>
                                    <h5>الصق الأسئلة هنا</h5>
                                    <p class="text-muted">انسخ والصق الأسئلة بالتنسيق البسيط</p>
                                    
                                    <textarea name="questions_text" class="form-control mt-3" rows="12" 
                                              placeholder="مثال بسيط:

1. ما هو ناتج 2 + 2؟
أ) 3
ب) 4
ج) 5
د) 6
الإجابة: ب

2. الأرض كروية الشكل
الإجابة: صح

3. ما هي عاصمة الأردن؟
الإجابة: عمان"></textarea>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" name="import_questions_simple" class="btn btn-success btn-lg">
                                        <i class="fas fa-upload"></i> استيراد الأسئلة
                                    </button>
                                </div>
                            </form>
                            
                            <div class="mt-4 p-3 bg-light rounded">
                                <h6><i class="fas fa-info-circle text-primary"></i> تعليمات بسيطة:</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success"></i> ابدأ كل سؤال برقم ونقطة (1. 2. 3.)</li>
                                    <li><i class="fas fa-check text-success"></i> اكتب الخيارات بأحرف (أ) ب) ج) د)</li>
                                    <li><i class="fas fa-check text-success"></i> حدد الإجابة بكتابة "الإجابة:" متبوعة بالجواب</li>
                                    <li><i class="fas fa-check text-success"></i> للأسئلة النصية اكتب الإجابة مباشرة</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- صفحة غير موجودة -->
            <div class="text-center py-5">
                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                <h4>الصفحة غير موجودة</h4>
                <p class="text-muted">يرجى اختيار عملية من القائمة أعلاه</p>
                <a href="?action=list" class="btn btn-primary">العودة لقائمة الاختبارات</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleQuestionType() {
            const questionType = document.querySelector('select[name="question_type"]').value;
            
            // إخفاء جميع الخيارات
            document.getElementById('multipleChoiceOptions').style.display = 'none';
            document.getElementById('trueFalseOptions').style.display = 'none';
            document.getElementById('shortAnswerOptions').style.display = 'none';
            
            // إظهار الخيارات المناسبة
            if (questionType === 'multiple_choice') {
                document.getElementById('multipleChoiceOptions').style.display = 'block';
            } else if (questionType === 'true_false') {
                document.getElementById('trueFalseOptions').style.display = 'block';
            } else if (questionType === 'short_answer') {
                document.getElementById('shortAnswerOptions').style.display = 'block';
            }
        }
    </script>
</body>
</html>