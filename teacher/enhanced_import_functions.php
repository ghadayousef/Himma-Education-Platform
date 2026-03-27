<?php
/**
 * دوال الاستيراد  للأسئلة
 *  Question Import Functions
 */

function parseQuestionsFromText($text, $conn) {
    $questions = [];
    $lines = explode("\n", $text);
    $current_question = null;
    $question_counter = 0;
    
    // تنظيف النص وإزالة الأسطر الفارغة
    $lines = array_filter(array_map("trim", $lines), function($line) {
        return !empty($line);
    });
    
    foreach ($lines as $line_num => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // التحقق من بداية سؤال جديد
        if (preg_match("/^(\d+)[\.\-\)\s]/", $line, $matches)) {
            // حفظ السؤال السابق إذا كان موجوداً
            if ($current_question && !empty($current_question["question_text"])) {
                $questions[] = $current_question;
            }
            
            // بدء سؤال جديد
            $question_counter++;
            $question_text = preg_replace("/^(\d+)[\.\-\)\s]+/", "", $line);
            
            $current_question = [
                "question_text" => $question_text,
                "question_type" => "multiple_choice",
                "marks" => 5,
                "options" => [],
                "correct_answer" => "",
                "explanation" => ""
            ];
            
        } elseif ($current_question && preg_match("/^([أابجدهـوزحطيكلمنسعفصقرشتثخذضظغ]|[A-D]|[1-4])[\.\-\)\s]/u", $line)) {
            // خيار من خيارات السؤال
            $option_text = preg_replace("/^([أابجدهـوزحطيكلمنسعفصقرشتثخذضظغ]|[A-D]|[1-4])[\.\-\)\s]+/u", "", $line);
            $current_question["options"][] = $option_text;
            
        } elseif ($current_question && preg_match("/(الإجابة|الاجابة|الجواب|الحل).*?:?\s*([أابجدهـوزحطيكلمنسعفصقرشتثخذضظغ]|[A-D]|[1-4]|صح|خطأ|صحيح|خاطئ)/ui", $line, $matches)) {
            // الإجابة الصحيحة
            $answer = trim($matches[2]);
            $current_question["correct_answer"] = $answer;
            
            // تحديد نوع السؤال بناءً على الإجابة
            if (in_array(strtolower($answer), ["صح", "خطأ", "صحيح", "خاطئ", "true", "false"])) {
                $current_question["question_type"] = "true_false";
                // إضافة خيارات صح/خطأ إذا لم تكن موجودة
                if (empty($current_question["options"])) {
                    $current_question["options"] = ["صح", "خطأ"];
                }
            }
            
        } elseif ($current_question && preg_match("/(شرح|تفسير|توضيح)/ui", $line)) {
            // شرح الإجابة
            $explanation = preg_replace("/(شرح|تفسير|توضيح).*?:?\s*/ui", "", $line);
            $current_question["explanation"] = $explanation;
            
        } elseif ($current_question && !empty($line) && !preg_match("/^\d+[\.\-\)]/", $line)) {
            // إضافة للنص الحالي للسؤال
            $current_question["question_text"] .= " " . $line;
        }
    }
    
    // إضافة السؤال الأخير
    if ($current_question && !empty($current_question["question_text"])) {
        $questions[] = $current_question;
    }
    
    return $questions;
}

function importQuestionsToDatabase($questions, $quiz_id, $conn) {
    $imported_count = 0;
    $errors = [];
    
    foreach ($questions as $index => $question_data) {
        try {
            // تنظيف بيانات السؤال
            $question_text = trim($question_data["question_text"]);
            $question_type = $question_data["question_type"];
            $marks = intval($question_data["marks"]);
            
            if (empty($question_text)) {
                $errors[] = "السؤال رقم " . ($index + 1) . ": نص السؤال فارغ";
                continue;
            }
            
            // إدراج السؤال
            $insert_question = $conn->prepare("
                INSERT INTO quiz_questions (quiz_id, question_text, question_type, marks, order_number, correct_answer, explanation) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $correct_answer_text = "";
            if ($question_type === "true_false") {
                $correct_answer_text = $question_data["correct_answer"];
            }
            
            $insert_question->execute([
                $quiz_id,
                $question_text,
                $question_type,
                $marks,
                $index + 1,
                $correct_answer_text,
                $question_data["explanation"] ?? ""
            ]);
            
            $question_id = $conn->lastInsertId();
            
            // إدراج الخيارات
            if (!empty($question_data["options"])) {
                foreach ($question_data["options"] as $opt_index => $option_text) {
                    $option_text = trim($option_text);
                    if (empty($option_text)) continue;
                    
                    // تحديد الإجابة الصحيحة
                    $is_correct = 0;
                    $correct_answer = $question_data["correct_answer"];
                    
                    // مقارنة مرنة للإجابة الصحيحة
                    if ($question_type === "true_false") {
                        $is_correct = (
                            (in_array(strtolower($correct_answer), ["صح", "صحيح", "true"]) && in_array(strtolower($option_text), ["صح", "صحيح"])) ||
                            (in_array(strtolower($correct_answer), ["خطأ", "خاطئ", "false"]) && in_array(strtolower($option_text), ["خطأ", "خاطئ"]))
                        ) ? 1 : 0;
                    } else {
                        // للأسئلة الاختيارية
                        $option_letters = ["أ", "ب", "ج", "د", "هـ", "و", "ز", "ح"];
                        $english_letters = ["A", "B", "C", "D", "E", "F", "G", "H"];
                        
                        $is_correct = (
                            $correct_answer === $option_letters[$opt_index] ||
                            $correct_answer === $english_letters[$opt_index] ||
                            $correct_answer === ($opt_index + 1) ||
                            strtolower($correct_answer) === strtolower($option_text)
                        ) ? 1 : 0;
                    }
                    
                    $insert_option = $conn->prepare("
                        INSERT INTO quiz_options (question_id, option_text, is_correct, order_number) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $insert_option->execute([$question_id, $option_text, $is_correct, $opt_index + 1]);
                }
            }
            
            $imported_count++;
            
        } catch (Exception $e) {
            $errors[] = "خطأ في السؤال رقم " . ($index + 1) . ": " . $e->getMessage();
        }
    }
    
    return [
        "imported_count" => $imported_count,
        "errors" => $errors
    ];
}

function validateQuestionFile($file) {
    $errors = [];
    
    // فحص حجم الملف
    if ($file["size"] > 10 * 1024 * 1024) { // 10MB
        $errors[] = "حجم الملف كبير جداً. الحد الأقصى 10MB";
    }
    
    // فحص نوع الملف
    $allowed_types = ["text/plain", "application/octet-stream"];
    if (!in_array($file["type"], $allowed_types)) {
        $errors[] = "نوع الملف غير مدعوم. يرجى رفع ملف نصي (.txt)";
    }
    
    // فحص امتداد الملف
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($file_extension, ["txt", "text"])) {
        $errors[] = "امتداد الملف غير صحيح. يرجى استخدام .txt";
    }
    
    return $errors;
}
?>