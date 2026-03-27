<?php
/**
 * بنك الأسئلة - منصة همّة التوجيهي
 * Question Bank - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول كمعلم
if (!is_logged_in() || !has_role('teacher')) {
    redirect('../auth/login.php');
}

$db = new Database();
$conn = $db->connect();
$teacher_id = $_SESSION['user_id'];

// معالجة رفع ملف الأسئلة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_questions'])) {
    $subject_id = intval($_POST['subject_id']);
    $category = $_POST['category'] ?? 'general';
    
    if (isset($_FILES['questions_file']) && $_FILES['questions_file']['error'] === 0) {
        $file = $_FILES['questions_file'];
        $file_content = file_get_contents($file['tmp_name']);
        
        // معالجة محتوى الملف
        $questions_added = processQuestionsFile($conn, $file_content, $subject_id, $category, $teacher_id);
        
        if ($questions_added > 0) {
            $success_message = "تم إضافة $questions_added سؤال بنجاح إلى بنك الأسئلة!";
        } else {
            $error_message = "لم يتم إضافة أي أسئلة. تحقق من تنسيق الملف.";
        }
    }
}

// معالجة إنشاء اختبار من بنك الأسئلة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz_from_bank'])) {
    $quiz_title = $_POST['quiz_title'];
    $subject_id = intval($_POST['subject_id']);
    $questions_count = intval($_POST['questions_count']);
    $category = $_POST['category'] ?? '';
    $duration = intval($_POST['duration']);
    
    $quiz_id = createQuizFromBank($conn, $quiz_title, $subject_id, $questions_count, $category, $duration, $teacher_id);
    
    if ($quiz_id) {
        $success_message = "تم إنشاء الاختبار بنجاح! <a href='quiz_view.php?id=$quiz_id'>عرض الاختبار</a>";
    } else {
        $error_message = "فشل في إنشاء الاختبار. تحقق من وجود أسئلة كافية في بنك الأسئلة.";
    }
}

// جلب مواد المعلم
$subjects_stmt = $conn->prepare("SELECT * FROM subjects WHERE teacher_id = ? AND is_active = 1");
$subjects_stmt->execute([$teacher_id]);
$subjects = $subjects_stmt->fetchAll();

// جلب إحصائيات بنك الأسئلة
$stats_stmt = $conn->prepare("
    SELECT 
        qb.category,
        COUNT(*) as questions_count,
        s.name as subject_name
    FROM question_bank qb
    JOIN subjects s ON qb.subject_id = s.id
    WHERE s.teacher_id = ?
    GROUP BY qb.subject_id, qb.category
");
$stats_stmt->execute([$teacher_id]);
$bank_stats = $stats_stmt->fetchAll();

// دالة معالجة ملف الأسئلة
function processQuestionsFile($conn, $content, $subject_id, $category, $teacher_id) {
    $questions_added = 0;
    $lines = explode("\n", $content);
    $current_question = null;
    $options = [];
    $correct_answer = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // إذا كان السطر يبدأ بـ "س:" أو "Q:" فهو سؤال جديد
        if (preg_match('/^(س:|Q:)\s*(.+)/', $line, $matches)) {
            // حفظ السؤال السابق إذا وُجد
            if ($current_question && !empty($options)) {
                if (saveQuestionToBank($conn, $current_question, $options, $correct_answer, $subject_id, $category)) {
                    $questions_added++;
                }
            }
            
            // بدء سؤال جديد
            $current_question = $matches[2];
            $options = [];
            $correct_answer = '';
        }
        // إذا كان السطر يبدأ بـ أ) ب) ج) د) أو A) B) C) D) فهو خيار
        elseif (preg_match('/^([أ-د]|[A-D])\)\s*(.+)/', $line, $matches)) {
            $option_letter = $matches[1];
            $option_text = $matches[2];
            
            // إذا كان الخيار يحتوي على علامة * فهو الإجابة الصحيحة
            if (strpos($option_text, '*') !== false) {
                $option_text = str_replace('*', '', $option_text);
                $correct_answer = $option_letter;
            }
            
            $options[] = [
                'letter' => $option_letter,
                'text' => trim($option_text)
            ];
        }
    }
    
    // حفظ السؤال الأخير
    if ($current_question && !empty($options)) {
        if (saveQuestionToBank($conn, $current_question, $options, $correct_answer, $subject_id, $category)) {
            $questions_added++;
        }
    }
    
    return $questions_added;
}

// دالة حفظ السؤال في بنك الأسئلة
function saveQuestionToBank($conn, $question_text, $options, $correct_answer, $subject_id, $category) {
    try {
        // إدراج السؤال في بنك الأسئلة
        $insert_question = $conn->prepare("
            INSERT INTO question_bank (subject_id, question_text, question_type, category, options, correct_answer, created_at)
            VALUES (?, ?, 'multiple_choice', ?, ?, ?, NOW())
        ");
        
        $options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
        $insert_question->execute([$subject_id, $question_text, $category, $options_json, $correct_answer]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// دالة إنشاء اختبار من بنك الأسئلة
function createQuizFromBank($conn, $title, $subject_id, $questions_count, $category, $duration, $teacher_id) {
    try {
        // إنشاء الاختبار
        $create_quiz = $conn->prepare("
            INSERT INTO quizzes (subject_id, title, description, duration, total_marks, passing_marks, is_active, created_at)
            VALUES (?, ?, 'اختبار تم إنشاؤه من بنك الأسئلة', ?, ?, ?, 1, NOW())
        ");
        
        $total_marks = $questions_count; // درجة واحدة لكل سؤال
        $passing_marks = ceil($total_marks * 0.6); // 60% للنجاح
        
        $create_quiz->execute([$subject_id, $title, $duration, $total_marks, $passing_marks]);
        $quiz_id = $conn->lastInsertId();
        
        // اختيار أسئلة عشوائية من بنك الأسئلة
        $where_clause = "WHERE qb.subject_id = ?";
        $params = [$subject_id];
        
        if (!empty($category)) {
            $where_clause .= " AND qb.category = ?";
            $params[] = $category;
        }
        
        $select_questions = $conn->prepare("
            SELECT * FROM question_bank qb 
            $where_clause 
            ORDER BY RAND() 
            LIMIT ?
        ");
        
        $params[] = $questions_count;
        $select_questions->execute($params);
        $selected_questions = $select_questions->fetchAll();
        
        // إضافة الأسئلة المختارة إلى الاختبار
        $order_number = 1;
        foreach ($selected_questions as $question) {
            // إدراج السؤال
            $insert_question = $conn->prepare("
                INSERT INTO quiz_questions (quiz_id, question_text, question_type, marks, order_number)
                VALUES (?, ?, ?, 1, ?)
            ");
            $insert_question->execute([$quiz_id, $question['question_text'], $question['question_type'], $order_number]);
            $question_id = $conn->lastInsertId();
            
            // إدراج الخيارات
            $options = json_decode($question['options'], true);
            foreach ($options as $index => $option) {
                $is_correct = ($option['letter'] === $question['correct_answer']) ? 1 : 0;
                
                $insert_option = $conn->prepare("
                    INSERT INTO quiz_options (question_id, option_text, is_correct, order_number)
                    VALUES (?, ?, ?, ?)
                ");
                $insert_option->execute([$question_id, $option['text'], $is_correct, $index + 1]);
            }
            
            $order_number++;
        }
        
        return $quiz_id;
    } catch (Exception $e) {
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بنك الأسئلة - منصة همّة التوجيهي</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: white !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .main-content {
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .upload-area {
            border: 2px dashed var(--primary-color);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: rgba(102, 126, 234, 0.05);
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: var(--secondary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-right: 4px solid var(--primary-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .format-example {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            border-right: 4px solid var(--success-color);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../home/index.php">
                <i class="fas fa-graduation-cap"></i> همّة التوجيهي
            </a>
            
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="quiz_management.php">
                    <i class="fas fa-list"></i> إدارة الاختبارات
                </a>
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="text-center">
                        <i class="fas fa-database text-primary"></i>
                        بنك الأسئلة
                    </h1>
                    <p class="text-center text-muted">أضف أسئلة بسهولة وأنشئ اختبارات تلقائياً</p>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <?php if (!empty($bank_stats)): ?>
                <div class="stats-grid">
                    <?php foreach ($bank_stats as $stat): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stat['questions_count']; ?></div>
                            <div class="stat-label"><?php echo htmlspecialchars($stat['subject_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($stat['category']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Upload Questions -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-upload"></i>
                                رفع أسئلة جديدة
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">اختر المادة</label>
                                    <select name="subject_id" class="form-select" required>
                                        <option value="">-- اختر المادة --</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>">
                                                <?php echo htmlspecialchars($subject['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">التصنيف</label>
                                    <select name="category" class="form-select">
                                        <option value="general">عام</option>
                                        <option value="chapter1">الفصل الأول</option>
                                        <option value="chapter2">الفصل الثاني</option>
                                        <option value="midterm">امتحان نصفي</option>
                                        <option value="final">امتحان نهائي</option>
                                    </select>
                                </div>

                                <div class="upload-area">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                    <h5>اختر ملف الأسئلة</h5>
                                    <p class="text-muted">يدعم ملفات .txt و .docx</p>
                                    <input type="file" name="questions_file" class="form-control" accept=".txt,.docx" required>
                                </div>

                                <button type="submit" name="upload_questions" class="btn btn-primary w-100 mt-3">
                                    <i class="fas fa-upload"></i> رفع الأسئلة
                                </button>
                            </form>

                            <!-- Format Example -->
                            <div class="mt-4">
                                <h6><i class="fas fa-info-circle text-info"></i> تنسيق الملف المطلوب:</h6>
                                <div class="format-example">
س: ما هي عاصمة الأردن؟<br>
أ) عمان *<br>
ب) إربد<br>
ج) الزرقاء<br>
د) العقبة<br><br>

س: كم عدد محافظات الأردن؟<br>
أ) 10<br>
ب) 11<br>
ج) 12 *<br>
د) 13
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-star text-warning"></i>
                                    ضع علامة * بجانب الإجابة الصحيحة
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create Quiz from Bank -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-magic"></i>
                                إنشاء اختبار من بنك الأسئلة
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">عنوان الاختبار</label>
                                    <input type="text" name="quiz_title" class="form-control" placeholder="مثال: اختبار الفصل الأول" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">اختر المادة</label>
                                    <select name="subject_id" class="form-select" required>
                                        <option value="">-- اختر المادة --</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>">
                                                <?php echo htmlspecialchars($subject['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">التصنيف (اختياري)</label>
                                    <select name="category" class="form-select">
                                        <option value="">جميع التصنيفات</option>
                                        <option value="general">عام</option>
                                        <option value="chapter1">الفصل الأول</option>
                                        <option value="chapter2">الفصل الثاني</option>
                                        <option value="midterm">امتحان نصفي</option>
                                        <option value="final">امتحان نهائي</option>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">عدد الأسئلة</label>
                                            <input type="number" name="questions_count" class="form-control" min="1" max="50" value="10" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">المدة (دقيقة)</label>
                                            <input type="number" name="duration" class="form-control" min="5" max="180" value="30" required>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" name="create_quiz_from_bank" class="btn btn-success w-100">
                                    <i class="fas fa-magic"></i> إنشاء اختبار تلقائياً
                                </button>
                            </form>

                            <div class="mt-4 p-3 bg-light rounded">
                                <h6><i class="fas fa-lightbulb text-warning"></i> كيف يعمل؟</h6>
                                <ul class="small mb-0">
                                    <li>يتم اختيار الأسئلة عشوائياً من بنك الأسئلة</li>
                                    <li>كل سؤال يحمل درجة واحدة</li>
                                    <li>درجة النجاح 60% من إجمالي الدرجات</li>
                                    <li>يمكن تعديل الاختبار بعد الإنشاء</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5><i class="fas fa-tools text-primary"></i> إجراءات سريعة</h5>
                            <div class="btn-group" role="group">
                                <a href="quiz_management.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list"></i> عرض جميع الاختبارات
                                </a>
                                <a href="question_bank_manage.php" class="btn btn-outline-success">
                                    <i class="fas fa-edit"></i> إدارة بنك الأسئلة
                                </a>
                                <a href="quiz_statistics.php" class="btn btn-outline-info">
                                    <i class="fas fa-chart-bar"></i> إحصائيات الاختبارات
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>