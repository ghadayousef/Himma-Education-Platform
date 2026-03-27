<?php
/**
 * صفحة الاختبار - منصة همّة التوجيهي
 * Quiz Page - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->connect();

$quiz_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// معالجة إرسال الاختبار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $answers = $_POST['answers'] ?? [];
    $start_time = $_POST['start_time'];
    $end_time = time();
    $time_taken = $end_time - $start_time;
    
    try {
        // جلب الأسئلة والإجابات الصحيحة
        $stmt = $conn->prepare("
            SELECT id, correct_answer, marks 
            FROM quiz_questions 
            WHERE quiz_id = ?
        ");
        $stmt->execute([$quiz_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $total_score = 0;
        $correct_answers = 0;
        $total_questions = count($questions);
        
        // حساب النتيجة
        foreach ($answers as $question_id => $user_answer) {
            if (isset($questions[$question_id])) {
                $question_data = $conn->prepare("SELECT correct_answer, marks FROM quiz_questions WHERE id = ?");
                $question_data->execute([$question_id]);
                $question_info = $question_data->fetch();
                
                if (strtolower(trim($user_answer)) === strtolower(trim($question_info['correct_answer']))) {
                    $total_score += $question_info['marks'];
                    $correct_answers++;
                }
            }
        }
        
        // حفظ النتيجة
        $stmt = $conn->prepare("
            INSERT INTO quiz_results (quiz_id, user_id, score, total_questions, correct_answers, time_taken, answers, completed_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $quiz_id, $user_id, $total_score, $total_questions, 
            $correct_answers, $time_taken, json_encode($answers)
        ]);
        
        // إعادة توجيه لصفحة النتائج
        header("Location: quiz_result.php?id={$quiz_id}&result_id=" . $conn->lastInsertId());
        exit;
        
    } catch (Exception $e) {
        $error = 'حدث خطأ في حفظ النتيجة: ' . $e->getMessage();
    }
}

// جلب معلومات الاختبار
try {
    $stmt = $conn->prepare("
        SELECT q.*, s.name as subject_name, s.category,
               e.id as enrollment_id, e.status as enrollment_status
        FROM quizzes q
        LEFT JOIN subjects s ON q.subject_id = s.id
        LEFT JOIN enrollments e ON s.id = e.subject_id AND e.user_id = ?
        WHERE q.id = ? AND q.is_active = 1
    ");
    $stmt->execute([$user_id, $quiz_id]);
    $quiz = $stmt->fetch();
    
    if (!$quiz) {
        header('Location: subjects.php');
        exit;
    }
    
    // التحقق من التسجيل في المادة
    if (!$quiz['enrollment_id'] || $quiz['enrollment_status'] !== 'active') {
        header('Location: subjects.php?error=not_enrolled');
        exit;
    }
    
    // التحقق من عدم أداء الاختبار مسبقاً
    $stmt = $conn->prepare("SELECT id FROM quiz_results WHERE quiz_id = ? AND user_id = ?");
    $stmt->execute([$quiz_id, $user_id]);
    if ($stmt->fetch()) {
        header("Location: quiz_result.php?id={$quiz_id}");
        exit;
    }
    
} catch (Exception $e) {
    header('Location: subjects.php?error=database');
    exit;
}

// جلب أسئلة الاختبار
$stmt = $conn->prepare("
    SELECT id, question_text, question_type, options, marks 
    FROM quiz_questions 
    WHERE quiz_id = ? 
    ORDER BY id
");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

if (empty($questions)) {
    header('Location: subjects.php?error=no_questions');
    exit;
}

$page_title = $quiz['title'];
include '../includes/student_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Timer Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h6 class="text-white">الوقت المتبقي</h6>
                    <div id="timer" class="timer-display">
                        <?php echo sprintf('%02d:%02d', $quiz['duration'], 0); ?>
                    </div>
                </div>
                
                <hr class="text-white">
                
                <div class="mb-3">
                    <h6 class="text-white"><?php echo htmlspecialchars($quiz['subject_name']); ?></h6>
                    <span class="badge bg-<?php echo $quiz['category'] === 'scientific' ? 'info' : ($quiz['category'] === 'literary' ? 'warning' : 'success'); ?>">
                        <?php echo $quiz['category'] === 'scientific' ? 'علمي' : ($quiz['category'] === 'literary' ? 'أدبي' : 'لغات'); ?>
                    </span>
                </div>
                
                <div class="quiz-info text-white">
                    <p><i class="fas fa-question-circle"></i> عدد الأسئلة: <?php echo count($questions); ?></p>
                    <p><i class="fas fa-star"></i> الدرجة الكلية: <?php echo $quiz['total_marks']; ?></p>
                    <p><i class="fas fa-check-circle"></i> درجة النجاح: <?php echo $quiz['pass_marks']; ?></p>
                </div>
                
                <hr class="text-white">
                
                <div class="questions-nav">
                    <h6 class="text-white mb-3">الأسئلة</h6>
                    <div class="row">
                        <?php for ($i = 1; $i <= count($questions); $i++): ?>
                        <div class="col-3 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-light question-nav-btn" 
                                    onclick="goToQuestion(<?php echo $i; ?>)">
                                <?php echo $i; ?>
                            </button>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                    <p class="text-muted"><?php echo htmlspecialchars($quiz['description']); ?></p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-success" onclick="submitQuiz()">
                        <i class="fas fa-paper-plane"></i> إرسال الاختبار
                    </button>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Quiz Form -->
            <form id="quizForm" method="POST">
                <input type="hidden" name="submit_quiz" value="1">
                <input type="hidden" name="start_time" value="<?php echo time(); ?>">
                
                <?php foreach ($questions as $index => $question): ?>
                <div class="card mb-4 question-card" id="question_<?php echo $index + 1; ?>" 
                     style="<?php echo $index > 0 ? 'display: none;' : ''; ?>">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">السؤال <?php echo $index + 1; ?> من <?php echo count($questions); ?></h5>
                            <span class="badge bg-primary"><?php echo $question['marks']; ?> درجة</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <h6 class="question-text mb-4"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></h6>
                        
                        <?php if ($question['question_type'] === 'multiple_choice'): ?>
                            <?php 
                            $options = json_decode($question['options'], true);
                            $option_letters = ['أ', 'ب', 'ج', 'د', 'هـ', 'و'];
                            ?>
                            <?php foreach ($options as $key => $option): ?>
                                <?php if (!empty($option)): ?>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" 
                                           name="answers[<?php echo $question['id']; ?>]" 
                                           value="<?php echo htmlspecialchars($option); ?>"
                                           id="q<?php echo $question['id']; ?>_<?php echo $key; ?>">
                                    <label class="form-check-label" for="q<?php echo $question['id']; ?>_<?php echo $key; ?>">
                                        <?php echo $option_letters[$key] ?? ($key + 1); ?>. <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                        <?php elseif ($question['question_type'] === 'true_false'): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" 
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       value="صح" id="q<?php echo $question['id']; ?>_true">
                                <label class="form-check-label" for="q<?php echo $question['id']; ?>_true">
                                    أ. صح
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" 
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       value="خطأ" id="q<?php echo $question['id']; ?>_false">
                                <label class="form-check-label" for="q<?php echo $question['id']; ?>_false">
                                    ب. خطأ
                                </label>
                            </div>
                            
                        <?php elseif ($question['question_type'] === 'short_answer'): ?>
                            <div class="mb-3">
                                <textarea class="form-control" 
                                          name="answers[<?php echo $question['id']; ?>]" 
                                          rows="3" 
                                          placeholder="اكتب إجابتك هنا..."></textarea>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" 
                                    onclick="previousQuestion()" 
                                    <?php echo $index === 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-arrow-right"></i> السؤال السابق
                            </button>
                            
                            <?php if ($index < count($questions) - 1): ?>
                                <button type="button" class="btn btn-primary" onclick="nextQuestion()">
                                    السؤال التالي <i class="fas fa-arrow-left"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success" onclick="submitQuiz()">
                                    <i class="fas fa-paper-plane"></i> إرسال الاختبار
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </main>
    </div>
</div>

<style>
.sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.timer-display {
    font-size: 2rem;
    font-weight: bold;
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    backdrop-filter: blur(10px);
}

.timer-warning {
    color: #ff6b6b !important;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.question-text {
    font-size: 1.1rem;
    line-height: 1.6;
}

.form-check-label {
    font-size: 1rem;
    cursor: pointer;
}

.form-check-input:checked + .form-check-label {
    font-weight: bold;
    color: #007bff;
}

.question-nav-btn {
    width: 100%;
    border-radius: 50%;
    aspect-ratio: 1;
}

.question-nav-btn.answered {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

.quiz-info p {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}
</style>

<script>
let currentQuestion = 1;
let totalQuestions = <?php echo count($questions); ?>;
let quizDuration = <?php echo $quiz['duration']; ?>; // بالدقائق
let timeRemaining = quizDuration * 60; // بالثواني

// مؤقت الاختبار
function startTimer() {
    const timerElement = document.getElementById('timer');
    
    const timerInterval = setInterval(function() {
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        
        timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // تحذير عند بقاء 5 دقائق
        if (timeRemaining <= 300) {
            timerElement.classList.add('timer-warning');
        }
        
        // انتهاء الوقت
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            alert('انتهى وقت الاختبار! سيتم إرسال إجاباتك تلقائياً.');
            submitQuiz();
        }
        
        timeRemaining--;
    }, 1000);
}

function goToQuestion(questionNum) {
    // إخفاء السؤال الحالي
    document.getElementById(`question_${currentQuestion}`).style.display = 'none';
    
    // إظهار السؤال المطلوب
    document.getElementById(`question_${questionNum}`).style.display = 'block';
    
    currentQuestion = questionNum;
    updateQuestionNavigation();
}

function nextQuestion() {
    if (currentQuestion < totalQuestions) {
        goToQuestion(currentQuestion + 1);
    }
}

function previousQuestion() {
    if (currentQuestion > 1) {
        goToQuestion(currentQuestion - 1);
    }
}

function updateQuestionNavigation() {
    // تحديث حالة أزرار التنقل
    const navButtons = document.querySelectorAll('.question-nav-btn');
    navButtons.forEach((btn, index) => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-light');
        
        if (index + 1 === currentQuestion) {
            btn.classList.remove('btn-outline-light');
            btn.classList.add('btn-primary');
        }
        
        // تحديد الأسئلة المجاب عليها
        const questionId = index + 1;
        const questionInputs = document.querySelectorAll(`#question_${questionId} input[type="radio"]:checked, #question_${questionId} textarea`);
        if (questionInputs.length > 0) {
            let hasAnswer = false;
            questionInputs.forEach(input => {
                if (input.type === 'radio' && input.checked) {
                    hasAnswer = true;
                } else if (input.type === 'textarea' && input.value.trim() !== '') {
                    hasAnswer = true;
                }
            });
            
            if (hasAnswer) {
                btn.classList.add('answered');
            }
        }
    });
}

function submitQuiz() {
    if (confirm('هل أنت متأكد من إرسال الاختبار؟ لن تتمكن من تعديل إجاباتك بعد الإرسال.')) {
        document.getElementById('quizForm').submit();
    }
}

// منع إعادة تحميل الصفحة أو الخروج منها
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'هل أنت متأكد من مغادرة الاختبار؟ ستفقد جميع إجاباتك.';
});

// بدء المؤقت عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    startTimer();
    updateQuestionNavigation();
    
    // تحديث حالة الأسئلة عند تغيير الإجابات
    document.addEventListener('change', updateQuestionNavigation);
    document.addEventListener('input', updateQuestionNavigation);
});

// اختصارات لوحة المفاتيح
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft' && currentQuestion < totalQuestions) {
        nextQuestion();
    } else if (e.key === 'ArrowRight' && currentQuestion > 1) {
        previousQuestion();
    } else if (e.ctrlKey && e.key === 'Enter') {
        submitQuiz();
    }
});
</script>

<?php include '../includes/student_footer.php'; ?>