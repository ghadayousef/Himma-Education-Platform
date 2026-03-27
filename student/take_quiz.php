<?php
/**
 * صفحة أداء الاختبار للطالب - منصة همّة التوجيهي
 * Student Quiz Taking Page - Himma Tawjihi Platform
 */

session_start();
require_once "../config/database.php";
require_once "../includes/functions.php";

// التحقق من تسجيل الدخول كطالب
if (!is_logged_in() || !has_role("student")) {
    header("Location: ../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$student_id = $_SESSION["user_id"];

// تهيئة المتغيرات
$quiz = null;
$questions = [];
$error_message = "";

// جلب الاختبار المحدد
$quiz_id = isset($_GET["quiz_id"]) ? intval($_GET["quiz_id"]) : 0;

if ($quiz_id > 0) {
    try {
        $quiz_stmt = $conn->prepare("
            SELECT q.*, s.name as subject_name 
            FROM quizzes q
            INNER JOIN subjects s ON q.subject_id = s.id
            WHERE q.id = ? AND q.is_active = 1
        ");
        $quiz_stmt->execute([$quiz_id]);
        $quiz = $quiz_stmt->fetch();
        
        if ($quiz) {
            // جلب أسئلة الاختبار
            $questions_stmt = $conn->prepare("
                SELECT * FROM quiz_questions 
                WHERE quiz_id = ? 
                ORDER BY COALESCE(order_number, id) ASC
            ");
            $questions_stmt->execute([$quiz_id]);
            $questions = $questions_stmt->fetchAll();
            
            // تعيين قيم افتراضية
            if (!isset($quiz["duration"]) || $quiz["duration"] == 0) {
                $quiz["duration"] = 60;
            }
            if (!isset($quiz["passing_marks"]) || $quiz["passing_marks"] == 0) {
                $quiz["passing_marks"] = ceil($quiz["total_marks"] * 0.6);
            }
        } else {
            $error_message = "الاختبار غير متاح أو غير موجود.";
        }
    } catch (Exception $e) {
        $error_message = "خطأ في جلب بيانات الاختبار: " . $e->getMessage();
    }
} else {
    $error_message = "لم يتم تحديد اختبار صحيح.";
}

// معالجة إرسال الاختبار
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_quiz"]) && $quiz) {
    try {
        $answers = $_POST["answers"] ?? [];
        
        // إنشاء سجل نتيجة
        $insert_result = $conn->prepare("
            INSERT INTO quiz_results (user_id, quiz_id, total_marks, started_at, completed_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $insert_result->execute([$student_id, $quiz_id, $quiz["total_marks"]]);
        $result_id = $conn->lastInsertId();
        
        $total_score = 0;
        
        // تقييم الإجابات
        foreach ($questions as $question) {
            $question_id = $question["id"];
            $student_answer = $answers[$question_id] ?? "";
            $marks_earned = 0;
            $is_correct = 0;
            
            if ($question["question_type"] === "multiple_choice" || $question["question_type"] === "true_false") {
                // للأسئلة الاختيارية
                $correct_option_stmt = $conn->prepare("
                    SELECT id FROM quiz_options 
                    WHERE question_id = ? AND is_correct = 1 
                    LIMIT 1
                ");
                $correct_option_stmt->execute([$question_id]);
                $correct_option = $correct_option_stmt->fetch();
                
                if ($correct_option && $student_answer == $correct_option["id"]) {
                    $is_correct = 1;
                    $marks_earned = $question["marks"];
                    $total_score += $marks_earned;
                }
                
                // حفظ الإجابة
                $insert_answer = $conn->prepare("
                    INSERT INTO quiz_answers (result_id, question_id, selected_option_id, is_correct, marks_earned)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_answer->execute([$result_id, $question_id, $student_answer ?: null, $is_correct, $marks_earned]);
                
            } elseif ($question["question_type"] === "short_answer" || $question["question_type"] === "essay") {
                // للأسئلة النصية
                $model_answer = $question["correct_answer"] ?? "";
                
                if (!empty($student_answer) && !empty($model_answer)) {
                    // مقارنة بسيطة
                    $similarity = similar_text(strtolower(trim($student_answer)), strtolower(trim($model_answer)), $percent);
                    
                    if ($percent >= 80) {
                        $marks_earned = $question["marks"];
                        $is_correct = 1;
                    } elseif ($percent >= 60) {
                        $marks_earned = $question["marks"] * 0.7;
                    } elseif ($percent >= 40) {
                        $marks_earned = $question["marks"] * 0.5;
                    }
                    
                    $total_score += $marks_earned;
                }
                
                // حفظ الإجابة
                $insert_answer = $conn->prepare("
                    INSERT INTO quiz_answers (result_id, question_id, answer_text, is_correct, marks_earned)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_answer->execute([$result_id, $question_id, $student_answer, $is_correct, $marks_earned]);
            }
        }
        
        // تحديث النتيجة النهائية
        $is_passed = ($total_score >= $quiz["passing_marks"]) ? 1 : 0;
        $update_result = $conn->prepare("
            UPDATE quiz_results SET score = ?, is_passed = ? WHERE id = ?
        ");
        $update_result->execute([$total_score, $is_passed, $result_id]);
        
        // إعادة توجيه إلى صفحة النتائج
        header("Location: quiz_result.php?result_id=" . $result_id);
        exit;
        
    } catch (Exception $e) {
        $error_message = "خطأ في حفظ النتائج: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أداء الاختبار - منصة همّة التوجيهي</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #4facfe;
            --warning-color: #43e97b;
        }

        body {
            font-family: "Cairo", sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .quiz-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .question-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            border-right: 4px solid var(--primary-color);
        }

        .question-number {
            background: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .timer {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--warning-color);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            font-weight: bold;
            z-index: 1000;
        }

        .answer-option {
            padding: 1rem;
            margin: 0.5rem 0;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .answer-option:hover {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
        }

        .error-container {
            text-align: center;
            padding: 3rem;
        }
    </style>
</head>
<body>
    <?php if ($quiz && !empty($questions) && empty($error_message)): ?>
        <!-- Timer -->
        <div class="timer" id="timer">
            <i class="fas fa-clock"></i>
            <span id="timeLeft"><?php echo $quiz["duration"]; ?>:00</span>
        </div>

        <div class="container mt-4">
            <!-- Quiz Header -->
            <div class="quiz-header text-center">
                <h1><i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars($quiz["title"]); ?></h1>
                <p class="mb-2"><?php echo htmlspecialchars($quiz["subject_name"]); ?></p>
                <p class="mb-0">
                    <i class="fas fa-clock"></i> <?php echo $quiz["duration"]; ?> دقيقة |
                    <i class="fas fa-star"></i> <?php echo $quiz["total_marks"]; ?> درجة |
                    <i class="fas fa-check-circle"></i> درجة النجاح: <?php echo $quiz["passing_marks"]; ?>
                </p>
            </div>

            <!-- Quiz Form -->
            <form method="POST" id="quizForm">
                <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                
                <?php foreach ($questions as $index => $question): ?>
                    <div class="card question-card">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <div class="question-number me-3">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2"><?php echo htmlspecialchars($question["question_text"]); ?></h5>
                                    <span class="badge bg-primary"><?php echo $question["marks"]; ?> درجة</span>
                                </div>
                            </div>

                            <?php if ($question["question_type"] === "multiple_choice" || $question["question_type"] === "true_false"): ?>
                                <?php
                                $options_stmt = $conn->prepare("SELECT * FROM quiz_options WHERE question_id = ? ORDER BY COALESCE(order_number, id)");
                                $options_stmt->execute([$question["id"]]);
                                $options = $options_stmt->fetchAll();
                                ?>
                                
                                <div class="answers-container">
                                    <?php foreach ($options as $option): ?>
                                        <label class="answer-option d-block">
                                            <input type="radio" name="answers[<?php echo $question["id"]; ?>]" 
                                                   value="<?php echo $option["id"]; ?>" class="form-check-input me-2">
                                            <?php echo htmlspecialchars($option["option_text"]); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                            <?php elseif ($question["question_type"] === "short_answer"): ?>
                                <div class="mb-3">
                                    <textarea name="answers[<?php echo $question["id"]; ?>]" 
                                              class="form-control" rows="3" 
                                              placeholder="اكتب إجابتك هنا..."></textarea>
                                </div>

                            <?php elseif ($question["question_type"] === "essay"): ?>
                                <div class="mb-3">
                                    <textarea name="answers[<?php echo $question["id"]; ?>]" 
                                              class="form-control" rows="6" 
                                              placeholder="اكتب إجابتك المفصلة هنا..."></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Submit Button -->
                <div class="text-center mb-4">
                    <button type="submit" name="submit_quiz" class="btn btn-success btn-lg">
                        <i class="fas fa-paper-plane"></i> إرسال الاختبار
                    </button>
                </div>
            </form>
        </div>

        <script>
            // Timer functionality
            let timeLeft = <?php echo $quiz["duration"] * 60; ?>;
            const timerElement = document.getElementById("timeLeft");
            
            function updateTimer() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.textContent = minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
                
                if (timeLeft <= 0) {
                    alert("انتهى الوقت! سيتم إرسال الاختبار تلقائياً.");
                    document.getElementById("quizForm").submit();
                    return;
                }
                
                timeLeft--;
            }
            
            setInterval(updateTimer, 1000);
            
            // Confirm before leaving
            window.addEventListener("beforeunload", function(e) {
                e.preventDefault();
                e.returnValue = "هل أنت متأكد من مغادرة الاختبار؟";
            });
            
            document.getElementById("quizForm").addEventListener("submit", function() {
                window.removeEventListener("beforeunload", function() {});
            });
        </script>

    <?php else: ?>
        <!-- Error Message -->
        <div class="container mt-5">
            <div class="error-container">
                <i class="fas fa-exclamation-triangle fa-5x text-warning mb-4"></i>
                <h2>عذراً!</h2>
                <p class="lead"><?php echo $error_message ?: "لا يمكن الوصول إلى الاختبار المطلوب."; ?></p>
                <a href="../home/index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> العودة للرئيسية
                </a>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>