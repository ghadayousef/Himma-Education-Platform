<?php
/**
 * صفحة استيراد الأسئلة  - منصة همّة التوجيهي
 *  Questions Import Page - Himma Platform
 */

session_start();
require_once "../config/database.php";
require_once "../includes/functions.php";
require_once "enhanced_import_functions.php";

// التحقق من الصلاحيات
if (!is_logged_in() || !has_role("teacher")) {
    redirect("../auth/login.php");
}

$db = new Database();
$conn = $db->connect();
$teacher_id = $_SESSION["user_id"];

// جلب المواد التي يدرسها المعلم
$subjects_stmt = $conn->prepare("SELECT * FROM subjects WHERE teacher_id = ? AND is_active = 1");
$subjects_stmt->execute([$teacher_id]);
$subjects = $subjects_stmt->fetchAll();

// جلب الاختبارات
$quizzes = [];
if (!empty($subjects)) {
    $subject_ids = array_column($subjects, "id");
    $placeholders = str_repeat("?,", count($subject_ids) - 1) . "?";
    $quizzes_stmt = $conn->prepare("SELECT q.*, s.name as subject_name FROM quizzes q INNER JOIN subjects s ON q.subject_id = s.id WHERE q.subject_id IN ($placeholders) ORDER BY q.created_at DESC");
    $quizzes_stmt->execute($subject_ids);
    $quizzes = $quizzes_stmt->fetchAll();
}

$message = "";
$message_type = "";

// معالجة رفع الملف
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["import_questions"])) {
    $quiz_id = intval($_POST["quiz_id"]);
    
    if ($quiz_id <= 0) {
        $message = "يرجى اختيار اختبار صحيح";
        $message_type = "danger";
    } elseif (isset($_FILES["questions_file"]) && $_FILES["questions_file"]["error"] === UPLOAD_ERR_OK) {
        // التحقق من صحة الملف
        $file_errors = validateQuestionFile($_FILES["questions_file"]);
        
        if (!empty($file_errors)) {
            $message = implode("<br>", $file_errors);
            $message_type = "danger";
        } else {
            try {
                // قراءة محتوى الملف
                $file_content = file_get_contents($_FILES["questions_file"]["tmp_name"]);
                
                // تحويل الترميز إذا لزم الأمر
                if (!mb_check_encoding($file_content, "UTF-8")) {
                    $file_content = mb_convert_encoding($file_content, "UTF-8", "auto");
                }
                
                // تحليل الأسئلة
                $questions = parseQuestionsFromText($file_content, $conn);
                
                if (empty($questions)) {
                    $message = "لم يتم العثور على أسئلة صالحة في الملف";
                    $message_type = "warning";
                } else {
                    // استيراد الأسئلة
                    $import_result = importQuestionsToDatabase($questions, $quiz_id, $conn);
                    
                    $message = "تم استيراد " . $import_result["imported_count"] . " سؤال بنجاح";
                    $message_type = "success";
                    
                    if (!empty($import_result["errors"])) {
                        $message .= "<br><strong>أخطاء:</strong><br>" . implode("<br>", $import_result["errors"]);
                        $message_type = "warning";
                    }
                }
                
            } catch (Exception $e) {
                $message = "خطأ في معالجة الملف: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    } else {
        $message = "يرجى اختيار ملف للرفع";
        $message_type = "warning";
    }
}

// معالجة الاستيراد من النص المباشر
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["import_text"])) {
    $quiz_id = intval($_POST["quiz_id"]);
    $questions_text = $_POST["questions_text"] ?? "";
    
    if ($quiz_id <= 0) {
        $message = "يرجى اختيار اختبار صحيح";
        $message_type = "danger";
    } elseif (empty(trim($questions_text))) {
        $message = "يرجى إدخال نص الأسئلة";
        $message_type = "warning";
    } else {
        try {
            // تحليل الأسئلة من النص
            $questions = parseQuestionsFromText($questions_text, $conn);
            
            if (empty($questions)) {
                $message = "لم يتم العثور على أسئلة صالحة في النص";
                $message_type = "warning";
            } else {
                // استيراد الأسئلة
                $import_result = importQuestionsToDatabase($questions, $quiz_id, $conn);
                
                $message = "تم استيراد " . $import_result["imported_count"] . " سؤال بنجاح";
                $message_type = "success";
                
                if (!empty($import_result["errors"])) {
                    $message .= "<br><strong>أخطاء:</strong><br>" . implode("<br>", $import_result["errors"]);
                    $message_type = "warning";
                }
            }
            
        } catch (Exception $e) {
            $message = "خطأ في معالجة النص: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد الأسئلة المحسن - منصة همّة التوجيهي</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: "Cairo", sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .main-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .import-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .import-method {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .import-method:hover, .import-method.active {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .file-drop-zone {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: rgba(102, 126, 234, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-drop-zone:hover {
            background: rgba(102, 126, 234, 0.1);
        }
        
        .example-format {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
            white-space: pre-line;
        }
        
        .progress-container {
            display: none;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header -->
        <div class="main-header">
            <h1><i class="fas fa-file-import"></i> استيراد الأسئلة المحسن</h1>
            <p class="mb-0">استيراد مرن وسريع للأسئلة من ملفات نصية أو نص مباشر</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $message_type === "success" ? "check-circle" : ($message_type === "danger" ? "exclamation-triangle" : "info-circle"); ?>"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Import Methods -->
                <div class="card import-card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload"></i> طرق الاستيراد</h5>
                    </div>
                    <div class="card-body">
                        <!-- Method Selection -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="import-method active" data-method="file">
                                    <div class="text-center">
                                        <i class="fas fa-file-upload fa-2x text-primary mb-2"></i>
                                        <h6>رفع ملف نصي</h6>
                                        <small class="text-muted">رفع ملف .txt يحتوي على الأسئلة</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="import-method" data-method="text">
                                    <div class="text-center">
                                        <i class="fas fa-keyboard fa-2x text-success mb-2"></i>
                                        <h6>لصق النص مباشرة</h6>
                                        <small class="text-muted">كتابة أو لصق الأسئلة مباشرة</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quiz Selection -->
                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-clipboard-list"></i> اختيار الاختبار:</label>
                            <select class="form-select" id="quiz_id" name="quiz_id" required>
                                <option value="">اختر الاختبار...</option>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <option value="<?php echo $quiz["id"]; ?>">
                                        <?php echo htmlspecialchars($quiz["title"]); ?> 
                                        (<?php echo htmlspecialchars($quiz["subject_name"]); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- File Import Form -->
                        <form method="POST" enctype="multipart/form-data" id="fileImportForm" class="import-form">
                            <input type="hidden" name="quiz_id" id="file_quiz_id">
                            
                            <div class="file-drop-zone" onclick="document.getElementById('questions_file').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h5>اسحب الملف هنا أو انقر للاختيار</h5>
                                <p class="text-muted">ملفات نصية (.txt) حتى 10MB</p>
                                <input type="file" name="questions_file" id="questions_file" accept=".txt,.text" style="display: none;">
                            </div>
                            
                            <div class="progress-container">
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <button type="submit" name="import_questions" class="btn btn-primary btn-lg">
                                    <i class="fas fa-file-import"></i> استيراد من الملف
                                </button>
                            </div>
                        </form>

                        <!-- Text Import Form -->
                        <form method="POST" id="textImportForm" class="import-form" style="display: none;">
                            <input type="hidden" name="quiz_id" id="text_quiz_id">
                            
                            <div class="mb-3">
                                <label class="form-label">الصق الأسئلة هنا:</label>
                                <textarea name="questions_text" class="form-control" rows="15" 
                                          placeholder="مثال:
1. ما هو ناتج 2 + 2؟
أ) 3
ب) 4
ج) 5
د) 6
الإجابة الصحيحة هي: ب

2. الأرض كروية الشكل
الاجابة الصحيحة هي: صح"></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" name="import_text" class="btn btn-success btn-lg">
                                    <i class="fas fa-keyboard"></i> استيراد من النص
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Instructions -->
                <div class="card import-card">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle"></i> تعليمات الاستيراد</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>تنسيق الأسئلة:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> ابدأ كل سؤال برقم</li>
                                <li><i class="fas fa-check text-success"></i> اكتب الخيارات بحروف (أ، ب، ج، د)</li>
                                <li><i class="fas fa-check text-success"></i> حدد الإجابة الصحيحة</li>
                                <li><i class="fas fa-check text-success"></i> اترك سطر فارغ بين الأسئلة</li>
                            </ul>
                        </div>
                        
                        <div class="example-format">
1. ما هي عاصمة الأردن؟
أ) عمان
ب) إربد
ج) الزرقاء
د) العقبة
الإجابة الصحيحة هي: أ

2. الشمس نجم
الإجابة: صح
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <?php if (!empty($quizzes)): ?>
                <div class="card import-card">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-bar"></i> إحصائيات الاختبارات</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>عدد الاختبارات:</strong> <?php echo count($quizzes); ?></p>
                        <p><strong>عدد المواد:</strong> <?php echo count($subjects); ?></p>
                        
                        <div class="mt-3">
                            <a href="quiz_system.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus"></i> إنشاء اختبار جديد
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Method switching
        document.querySelectorAll(".import-method").forEach(method => {
            method.addEventListener("click", function() {
                // Remove active class from all methods
                document.querySelectorAll(".import-method").forEach(m => m.classList.remove("active"));
                document.querySelectorAll(".import-form").forEach(f => f.style.display = "none");
                
                // Add active class to selected method
                this.classList.add("active");
                
                // Show corresponding form
                const methodType = this.dataset.method;
                if (methodType === "file") {
                    document.getElementById("fileImportForm").style.display = "block";
                } else if (methodType === "text") {
                    document.getElementById("textImportForm").style.display = "block";
                }
            });
        });
        
        // Quiz selection sync
        document.getElementById("quiz_id").addEventListener("change", function() {
            document.getElementById("file_quiz_id").value = this.value;
            document.getElementById("text_quiz_id").value = this.value;
        });
        
        // File selection
        document.getElementById("questions_file").addEventListener("change", function() {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                document.querySelector(".file-drop-zone h5").textContent = "تم اختيار: " + fileName;
            }
        });
        
        // Form submission with progress
        document.getElementById("fileImportForm").addEventListener("submit", function() {
            document.querySelector(".progress-container").style.display = "block";
            let progress = 0;
            const progressBar = document.querySelector(".progress-bar");
            
            const interval = setInterval(() => {
                progress += 10;
                progressBar.style.width = progress + "%";
                if (progress >= 90) {
                    clearInterval(interval);
                }
            }, 100);
        });
    </script>
</body>
</html>