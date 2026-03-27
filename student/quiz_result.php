<?php
session_start();
require_once "../config/database.php";
require_once "../includes/functions.php";

if (!is_logged_in() || !has_role("student")) {
    header("Location: ../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$student_id = $_SESSION["user_id"];

$result_id = isset($_GET["result_id"]) ? intval($_GET["result_id"]) : 0;
$result = null;

if ($result_id > 0) {
    $result_stmt = $conn->prepare("
        SELECT qr.*, q.title as quiz_title, q.total_marks, q.passing_marks, s.name as subject_name
        FROM quiz_results qr
        INNER JOIN quizzes q ON qr.quiz_id = q.id
        INNER JOIN subjects s ON q.subject_id = s.id
        WHERE qr.id = ? AND qr.user_id = ?
    ");
    $result_stmt->execute([$result_id, $student_id]);
    $result = $result_stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتيجة الاختبار - منصة همّة التوجيهي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <?php if ($result): ?>
            <div class="text-center">
                <h1>نتيجة الاختبار</h1>
                <h3><?php echo htmlspecialchars($result["quiz_title"]); ?></h3>
                <p class="text-muted"><?php echo htmlspecialchars($result["subject_name"]); ?></p>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h2>النتيجة: <?php echo $result["score"]; ?> من <?php echo $result["total_marks"]; ?></h2>
                        <p class="<?php echo $result["is_passed"] ? "text-success" : "text-danger"; ?>">
                            <?php echo $result["is_passed"] ? "نجحت في الاختبار! 🎉" : "لم تنجح في الاختبار 😔"; ?>
                        </p>
                        <p>النسبة المئوية: <?php echo round(($result["score"] / $result["total_marks"]) * 100, 2); ?>%</p>
                    </div>
                </div>
                
                <a href="../home/index.php" class="btn btn-primary mt-3">العودة للرئيسية</a>
            </div>
        <?php else: ?>
            <div class="text-center">
                <h2>لم يتم العثور على النتيجة</h2>
                <a href="../home/index.php" class="btn btn-primary">العودة للرئيسية</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>