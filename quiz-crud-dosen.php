<?php
ob_start(); // Start output buffering
session_start(); // Start session

// ERROR REPORTING (Untuk development, hapus atau ubah ke Off di produksi)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone untuk menghindari warning
date_default_timezone_set('Asia/Jakarta'); 

include 'db_connection.php'; // Include database connection file

// Redirect if not logged in or not a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.html");
    exit();
}

$current_lecturer_id = $_SESSION['user_id']; 

// Initialize data arrays for display
$quizzes_data = []; 
$questions_with_options = []; 
$student_rankings = []; 

// Initialize variables for the Add/Edit Question form
$editing_question_id = null;
$edit_quiz_id = '';
$edit_question_text = '';
$edit_question_formula = '';
$edit_question_explanation = '';
$edit_options = ['', '', '', '']; 
$edit_correct_answer_index = null; 

// --- Helper Functions ---

// PHP function to get question options
function getQuizQuestionOptions($conn, $question_id) {
    $options = [];
    $stmt = $conn->prepare("SELECT option_id, option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY option_id ASC");
    if ($stmt) {
        $stmt->bind_param("i", $question_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $options[] = $row;
            }
        } else {
            error_log("FUNC getQuizQuestionOptions: Failed to execute statement: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("FUNC getQuizQuestionOptions: Failed to prepare statement: " . $conn->error);
    }
    return $options;
}

// PHP function to format date for display in rankings
function formatDateForDisplayPHP($dateString) {
    if (empty($dateString) || $dateString === '0000-00-00 00:00:00') {
        return '-';
    }
    return date('d F Y H:i', strtotime($dateString));
}

// Function to handle sample data generation (extracted for clarity)
function handleGenerateSampleRankings($conn, $lecturer_id) {
    $stmt_attempt = null; 
    $stmt_answer = null; 
    $message = '';

    $conn->begin_transaction();
    try {
        // Fetch quizzes managed by the current lecturer
        $quizzes_in_db = [];
        $sql_quizzes_in_db = "SELECT quiz_id, total_questions FROM quizzes WHERE course_id IN (SELECT course_id FROM courses WHERE lecturer_id = ?)";
        $stmt_quizzes_in_db = $conn->prepare($sql_quizzes_in_db);
        if (!$stmt_quizzes_in_db) throw new Exception("Prepare quizzes in db failed: " . $conn->error);
        $stmt_quizzes_in_db->bind_param("i", $lecturer_id);
        if (!$stmt_quizzes_in_db->execute()) throw new Exception("Execute quizzes in db failed: " . $stmt_quizzes_in_db->error);
        $result_quizzes_in_db = $stmt_quizzes_in_db->get_result();
        while($row = $result_quizzes_in_db->fetch_assoc()) {
            $quizzes_in_db[$row['quiz_id']] = $row['total_questions'];
        }
        $stmt_quizzes_in_db->close();

        // Fetch students enrolled in courses managed by the current lecturer
        $students_in_db = [];
        $sql_students_in_db = "SELECT DISTINCT u.user_id, u.full_name, u.nim 
                               FROM users u
                               JOIN course_enrollments ce ON u.user_id = ce.student_id
                               JOIN courses c ON ce.course_id = c.course_id
                               WHERE u.role = 'student' AND c.lecturer_id = ?";
        $stmt_students_in_db = $conn->prepare($sql_students_in_db);
        if (!$stmt_students_in_db) throw new Exception("Prepare students in db failed: " . $conn->error);
        $stmt_students_in_db->bind_param("i", $lecturer_id);
        if (!$stmt_students_in_db->execute()) throw new Exception("Execute students in db failed: " . $stmt_students_in_db->error);
        $result_students_in_db = $stmt_students_in_db->get_result();
        while($row = $result_students_in_db->fetch_assoc()) {
            $students_in_db[] = $row;
        }
        $stmt_students_in_db->close();

        if (empty($quizzes_in_db) || empty($students_in_db)) {
            $message = "Tidak ada kuis yang diampu oleh Anda atau tidak ada mahasiswa yang terdaftar di kelas Anda untuk membuat data sample peringkat.";
            $conn->rollback(); 
            return $message;
        } 
        
        // Delete existing relevant data (answers first, then attempts)
        $conn->query("DELETE qa FROM quiz_answers qa 
                      JOIN quiz_attempts qatt ON qa.attempt_id = qatt.attempt_id 
                      JOIN quizzes qz ON qatt.quiz_id = qz.quiz_id 
                      JOIN courses c ON qz.course_id = c.course_id 
                      WHERE c.lecturer_id = " . $lecturer_id);

        $conn->query("DELETE qatt FROM quiz_attempts qatt 
                      JOIN quizzes qz ON qatt.quiz_id = qz.quiz_id 
                      JOIN courses c ON qz.course_id = c.course_id 
                      WHERE c.lecturer_id = " . $lecturer_id);

        // Prepare statements for inserting new data
        $stmt_attempt = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, student_id, end_time, score, is_completed) VALUES (?, ?, ?, ?, 1)");
        if (!$stmt_attempt) throw new Exception("Prepare insert attempt failed: " . $conn->error);

        $stmt_answer = $conn->prepare("INSERT INTO quiz_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?)");
        if (!$stmt_answer) throw new Exception("Prepare insert answer failed: " . $conn->error);

        // Generate sample data for each student for each quiz
        foreach ($students_in_db as $student) {
            foreach ($quizzes_in_db as $quiz_id => $total_questions_quiz_from_quiz_table) {
                // Get actual questions and options for the current quiz
                $all_question_details_in_quiz = []; 
                $sql_question_options_for_quiz = "SELECT qq.question_id, qo.option_id, qo.is_correct
                                                 FROM quiz_questions qq
                                                 JOIN question_options qo ON qq.question_id = qo.question_id
                                                 WHERE qq.quiz_id = ?";
                $stmt_q_options_full = $conn->prepare($sql_question_options_for_quiz);
                if (!$stmt_q_options_full) throw new Exception("Prepare question options full failed: " . $conn->error);
                $stmt_q_options_full->bind_param("i", $quiz_id);
                if (!$stmt_q_options_full->execute()) throw new Exception("Execute question options full failed: " . $stmt_q_options_full->error);
                $result_q_options_full = $stmt_q_options_full->get_result();
                while($row_opt_full = $result_q_options_full->fetch_assoc()) {
                    $all_question_details_in_quiz[$row_opt_full['question_id']][] = $row_opt_full;
                }
                $stmt_q_options_full->close();
                
                $actual_total_questions_in_quiz = count($all_question_details_in_quiz);

                if ($actual_total_questions_in_quiz === 0) {
                    continue; 
                }

                $score = rand(40, 100);
                $correct_answers_count_for_sim = round($score / 100 * $actual_total_questions_in_quiz);
                $end_time = date('Y-m-d H:i:s', strtotime('+' . rand(1, 5) . ' days')); 

                $stmt_attempt->bind_param("iisi", $quiz_id, $student['user_id'], $end_time, $score);
                if (!$stmt_attempt->execute()) throw new Exception("Failed to insert attempt: " . $stmt_attempt->error);
                $attempt_id = $conn->insert_id;

                $correct_questions_answered = 0;
                $questions_in_quiz_ids_shuffled = array_keys($all_question_details_in_quiz);
                shuffle($questions_in_quiz_ids_shuffled); 

                foreach ($questions_in_quiz_ids_shuffled as $question_id) {
                    $options_for_current_question = $all_question_details_in_quiz[$question_id];
                    $correct_option = null;
                    $wrong_options = [];

                    foreach ($options_for_current_question as $opt) {
                        if ($opt['is_correct']) {
                            $correct_option = $opt;
                        } else {
                            $wrong_options[] = $opt;
                        }
                    }

                    $selected_option_id = null;
                    $is_correct_answer_simulated_flag = 0;

                    if ($correct_questions_answered < $correct_answers_count_for_sim && $correct_option) {
                        $selected_option_id = $correct_option['option_id'];
                        $is_correct_answer_simulated_flag = 1;
                        $correct_questions_answered++;
                    } else {
                        if (!empty($wrong_options)) {
                            $random_wrong_option = $wrong_options[array_rand($wrong_options)];
                            $selected_option_id = $random_wrong_option['option_id'];
                            $is_correct_answer_simulated_flag = 0;
                        } elseif ($correct_option) {
                            $selected_option_id = $correct_option['option_id'];
                            $is_correct_answer_simulated_flag = 0;
                        } else {
                            continue; 
                        }
                    }
                    
                    if ($selected_option_id !== null) { 
                        $stmt_answer->bind_param("iiii", $attempt_id, $question_id, $selected_option_id, $is_correct_answer_simulated_flag);
                        if (!$stmt_answer->execute()) throw new Exception("Failed to insert answer: " . $stmt_answer->error);
                    }
                }
            }
        }
        $conn->commit();
        $message = "Data sample peringkat berhasil digenerate!";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Gagal mengenerate data sample peringkat: " . $e->getMessage() . " (Line: " . $e->getLine() . ")";
    } finally {
        if (isset($stmt_attempt) && $stmt_attempt !== null) $stmt_attempt->close(); 
        if (isset($stmt_answer) && $stmt_answer !== null) $stmt_answer->close();   
    }
    return $message;
}

// --- Request Handling ---

// Handle Form Submission (Add/Update Question)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_question'; 
    $quiz_id = (int)($_POST['quiz_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $question_formula = trim($_POST['question_formula'] ?? '');
    $question_explanation = trim($_POST['question_explanation'] ?? '');
    $option_texts = $_POST['option_text'] ?? [];
    $correct_answer_index = (int)($_POST['correctAnswer'] ?? -1); 
    $question_id_to_edit = (int)($_POST['editingId'] ?? 0); 

    // Validate inputs
    if (empty($quiz_id) || empty($question_text) || empty($question_explanation) || count($option_texts) !== 4 || $correct_answer_index === -1) {
        echo "<script>alert('Mohon lengkapi semua field yang wajib diisi dan pilih jawaban yang benar!'); window.location.href='quiz-crud-dosen.php?tab=" . ($action === 'update_question' ? 'questions' : 'add-question') . "';</script>";
        exit;
    }
    $filtered_options = array_filter($option_texts, 'strlen');
    if (count($filtered_options) !== 4) {
        echo "<script>alert('Semua 4 pilihan jawaban harus diisi!'); window.location.href='quiz-crud-dosen.php?tab=" . ($action === 'update_question' ? 'questions' : 'add-question') . "';</script>";
        exit;
    }

    $conn->begin_transaction();
    try {
        if ($action === 'update_question' && $question_id_to_edit > 0) {
            // Check ownership for update
            $check_ownership_sql = "SELECT qq.question_id FROM quiz_questions qq JOIN quizzes q ON qq.quiz_id = q.quiz_id WHERE qq.question_id = ? AND q.course_id IN (SELECT course_id FROM courses WHERE lecturer_id = ?)";
            $stmt_check = $conn->prepare($check_ownership_sql);
            if (!$stmt_check) throw new Exception("Failed to prepare ownership check for update: " . $conn->error);
            $stmt_check->bind_param("ii", $question_id_to_edit, $current_lecturer_id);
            if (!$stmt_check->execute()) throw new Exception("Failed to execute ownership check for update: " . $stmt_check->error);
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows === 0) {
                throw new Exception("Anda tidak memiliki izin untuk mengedit soal ini.");
            }
            $stmt_check->close();

            // Update question
            $stmt_q = $conn->prepare("UPDATE quiz_questions SET quiz_id = ?, question_text = ?, question_formula = ?, question_type = ?, explanation = ? WHERE question_id = ?");
            if (!$stmt_q) throw new Exception("Failed to prepare UPDATE question: " . $conn->error);
            $question_type = 'multiple_choice'; 
            $formula_to_save = empty($question_formula) ? NULL : $question_formula;
            $stmt_q->bind_param("issssi", $quiz_id, $question_text, $formula_to_save, $question_type, $question_explanation, $question_id_to_edit);
            if (!$stmt_q->execute()) throw new Exception("Failed to execute UPDATE question: " . $stmt_q->error);
            $stmt_q->close();

            // Delete old options
            $stmt_del_opt = $conn->prepare("DELETE FROM question_options WHERE question_id = ?");
            if (!$stmt_del_opt) throw new Exception("Failed to prepare DELETE options: " . $conn->error);
            $stmt_del_opt->bind_param("i", $question_id_to_edit);
            if (!$stmt_del_opt->execute()) throw new Exception("Failed to execute DELETE options: " . $stmt_del_opt->error);
            $stmt_del_opt->close();

            $inserted_question_id = $question_id_to_edit;

        } else { // Add new question
            // Check quiz ownership for new question
            $check_quiz_ownership_sql = "SELECT quiz_id FROM quizzes WHERE quiz_id = ? AND course_id IN (SELECT course_id FROM courses WHERE lecturer_id = ?)";
            $stmt_check_quiz = $conn->prepare($check_quiz_ownership_sql);
            if (!$stmt_check_quiz) throw new Exception("Failed to prepare quiz ownership check: " . $conn->error);
            $stmt_check_quiz->bind_param("ii", $quiz_id, $current_lecturer_id);
            if (!$stmt_check_quiz->execute()) throw new Exception("Failed to execute quiz ownership check: " . $stmt_check_quiz->error);
            $result_check_quiz = $stmt_check_quiz->get_result();
            if ($result_check_quiz->num_rows === 0) {
                throw new Exception("Anda tidak memiliki izin untuk menambahkan soal ke quiz ini.");
            }
            $stmt_check_quiz->close();

            // Insert new question
            $stmt_q = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, question_formula, question_type, explanation) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_q) throw new Exception("Failed to prepare INSERT question: " . $conn->error);
            $question_type = 'multiple_choice'; 
            $formula_to_save = empty($question_formula) ? NULL : $question_formula;
            $stmt_q->bind_param("issss", $quiz_id, $question_text, $formula_to_save, $question_type, $question_explanation);
            if (!$stmt_q->execute()) throw new Exception("Failed to execute INSERT question: " . $stmt_q->error);
            $inserted_question_id = $conn->insert_id;
            $stmt_q->close();
        }

        // Insert new/updated options
        foreach ($option_texts as $idx => $option_text) {
            $is_correct = ($idx == $correct_answer_index) ? 1 : 0;
            $stmt_opt = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
            if (!$stmt_opt) throw new Exception("Failed to prepare INSERT option: " . $conn->error);
            $stmt_opt->bind_param("isi", $inserted_question_id, $option_text, $is_correct);
            if (!$stmt_opt->execute()) throw new Exception("Failed to execute INSERT option: " . $stmt_opt->error);
            $stmt_opt->close();
        }

        $conn->commit();
        echo "<script>alert('Soal berhasil " . ($action === 'update_question' ? 'diperbarui' : 'ditambahkan') . "!'); window.location.href='quiz-crud-dosen.php?tab=questions';</script>";

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in POST action: " . $e->getMessage() . " on line " . $e->getLine()); 
        echo "<script>alert('Terjadi kesalahan saat menyimpan soal: " . $e->getMessage() . " (Line: " . $e->getLine() . ")'); window.location.href='quiz-crud-dosen.php?tab=" . ($action === 'update_question' ? 'questions' : 'add-question') . "';</script>";
    }
    exit;
}

// Handle Delete Question
if (isset($_GET['action']) && $_GET['action'] === 'delete_question' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    $conn->begin_transaction();
    try {
        // Check ownership before deleting
        $check_ownership_sql = "SELECT qq.question_id FROM quiz_questions qq JOIN quizzes q ON qq.quiz_id = q.quiz_id WHERE qq.question_id = ? AND q.course_id IN (SELECT course_id FROM courses WHERE lecturer_id = ?)";
        $stmt_check = $conn->prepare($check_ownership_sql);
        if (!$stmt_check) throw new Exception("Failed to prepare ownership check for delete: " . $conn->error);
        $stmt_check->bind_param("ii", $delete_id, $current_lecturer_id);
        if (!$stmt_check->execute()) throw new Exception("Failed to execute ownership check for delete: " . $stmt_check->error);
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows === 0) {
            throw new Exception("Anda tidak memiliki izin untuk menghapus soal ini.");
        }
        $stmt_check->close();

        // Delete student answers related to this question first
        $stmt_del_ans = $conn->prepare("DELETE FROM quiz_answers WHERE question_id = ?");
        if (!$stmt_del_ans) throw new Exception("Failed to prepare delete quiz answers: " . $conn->error);
        $stmt_del_ans->bind_param("i", $delete_id);
        if (!$stmt_del_ans->execute()) throw new Exception("Failed to execute delete quiz answers: " . $stmt_del_ans->error);
        $stmt_del_ans->close();

        // Delete options
        $stmt_del_opt = $conn->prepare("DELETE FROM question_options WHERE question_id = ?");
        if (!$stmt_del_opt) throw new Exception("Failed to prepare delete options: " . $conn->error);
        $stmt_del_opt->bind_param("i", $delete_id);
        if (!$stmt_del_opt->execute()) throw new Exception("Failed to execute delete options: " . $stmt_del_opt->error);
        $stmt_del_opt->close();

        // Delete question
        $stmt_del_q = $conn->prepare("DELETE FROM quiz_questions WHERE question_id = ?");
        if (!$stmt_del_q) throw new Exception("Failed to prepare delete question: " . $conn->error);
        $stmt_del_q->bind_param("i", $delete_id);
        if (!$stmt_del_q->execute()) throw new Exception("Failed to execute delete question: " . $stmt_del_q->error);
        
        if ($stmt_del_q->affected_rows > 0) {
            echo "<script>alert('Soal berhasil dihapus!');</script>";
        } else {
            echo "<script>alert('Soal tidak ditemukan atau sudah dihapus.');</script>";
        }
        $stmt_del_q->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in DELETE action: " . $e->getMessage() . " on line " . $e->getLine()); 
        echo "<script>alert('Gagal menghapus soal: " . $e->getMessage() . " (Line: " . $e->getLine() . ")');</script>";
    }
    header("Location: quiz-crud-dosen.php?tab=questions");
    exit;
}

// Handle Generate Sample Rankings
if (isset($_GET['action']) && $_GET['action'] === 'generate_rankings') {
    $alert_message = handleGenerateSampleRankings($conn, $current_lecturer_id);
    echo "<script>alert('$alert_message');</script>";
    header("Location: quiz-crud-dosen.php?tab=rankings");
    exit;
}


// --- Fetch Data for Display ---

// Fetch all Quizzes for dropdowns
$sql_quizzes = "SELECT quiz_id, title FROM quizzes WHERE course_id IN (SELECT course_id FROM courses WHERE lecturer_id = ?) ORDER BY title ASC";
$stmt_quizzes = $conn->prepare($sql_quizzes);
if ($stmt_quizzes) { 
    $stmt_quizzes->bind_param("i", $current_lecturer_id);
    if ($stmt_quizzes->execute()) {
        $result_quizzes = $stmt_quizzes->get_result();
        while ($row = $result_quizzes->fetch_assoc()) {
            $quizzes_data[] = $row;
        }
    } else {
        error_log("Failed to execute quizzes statement for display: " . $stmt_quizzes->error);
    }
    $stmt_quizzes->close();
} else {
    error_log("Failed to prepare quizzes statement for display: " . $conn->error);
}

// Fetch all Questions with Options for display
$sql_questions = "
    SELECT
        qq.question_id,
        qq.quiz_id,
        qq.question_text,
        qq.question_formula,
        qq.question_type,
        qq.explanation,
        q.title AS quiz_title
    FROM
        quiz_questions qq
    JOIN
        quizzes q ON qq.quiz_id = q.quiz_id
    WHERE
        q.course_id IN (SELECT course_id FROM courses WHERE lecturer_id = ?)
    ORDER BY
        qq.quiz_id, qq.question_id ASC
";
$stmt_questions = $conn->prepare($sql_questions);
if ($stmt_questions) { 
    $stmt_questions->bind_param("i", $current_lecturer_id);
    if ($stmt_questions->execute()) {
        $result_questions = $stmt_questions->get_result();
        while ($row = $result_questions->fetch_assoc()) {
            $options = getQuizQuestionOptions($conn, $row['question_id']);
            $row['options'] = $options; 
            $questions_with_options[] = $row;
        }
    } else {
        error_log("Failed to execute questions statement for display: " . $stmt_questions->error);
    }
    $stmt_questions->close();
} else {
    error_log("Failed to prepare questions statement for display: " . $conn->error);
}

// Fetch Student Rankings for display
$sql_rankings = "
    SELECT
        qa.student_id,
        u.full_name,
        u.nim,
        AVG(qa.score) AS score, 
        SUM(CASE WHEN qz_ans.is_correct = 1 THEN 1 ELSE 0 END) AS correct_answers,
        COUNT(DISTINCT qz_q.question_id) AS total_questions_attempted,
        MAX(qa.end_time) as end_time 
    FROM
        quiz_attempts qa
    JOIN
        users u ON qa.student_id = u.user_id
    JOIN
        quizzes qz ON qa.quiz_id = qz.quiz_id
    JOIN
        courses c ON qz.course_id = c.course_id
    LEFT JOIN
        quiz_answers qz_ans ON qa.attempt_id = qz_ans.attempt_id
    LEFT JOIN
        quiz_questions qz_q ON qz_ans.question_id = qz_q.question_id
    WHERE
        u.role = 'student' AND c.lecturer_id = ?
    GROUP BY
        qa.student_id, u.full_name, u.nim
    ORDER BY
        score DESC;
";
$stmt_rankings = $conn->prepare($sql_rankings);
if ($stmt_rankings) { 
    $stmt_rankings->bind_param("i", $current_lecturer_id);
    if ($stmt_rankings->execute()) {
        $result_rankings = $stmt_rankings->get_result();
        while ($row = $result_rankings->fetch_assoc()) {
            $student_rankings[] = $row;
        }
    } else {
        error_log("Failed to execute rankings statement for display: " . $stmt_rankings->error);
    }
    $stmt_rankings->close();
} else {
    error_log("Failed to prepare rankings statement for display: " . $conn->error);
}

// Handle edit mode if requested via GET parameter (e.g., from an edit button)
if (isset($_GET['action']) && $_GET['action'] === 'edit_question' && isset($_GET['id'])) {
    $editing_question_id = (int)$_GET['id'];
    $question_to_edit = null;

    foreach ($questions_with_options as $q) {
        if ($q['question_id'] === $editing_question_id) {
            $question_to_edit = $q;
            break;
        }
    }

    if ($question_to_edit) {
        $edit_quiz_id = $question_to_edit['quiz_id'];
        $edit_question_text = $question_to_edit['question_text'];
        $edit_question_formula = $question_to_edit['question_formula'];
        $edit_question_explanation = $question_to_edit['explanation']; 
        
        foreach ($question_to_edit['options'] as $idx => $option) {
            $edit_options[$idx] = $option['option_text'];
            if ($option['is_correct']) {
                $edit_correct_answer_index = $idx;
            }
        }
    } else {
        echo "<script>alert('Soal tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.'); window.location.href='quiz-crud-dosen.php?tab=questions';</script>";
        exit;
    }
}

$conn->close(); // Close database connection
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen Soal - Dosen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            margin: 20px;
            min-height: calc(100vh - 40px);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        
        .nav-pills .nav-link {
            border-radius: 25px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            border: none;
            border-radius: 25px;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            border-radius: 25px;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
            border: none;
            border-radius: 25px;
        }
        
        .form-control, .form-select {
            border-radius: 15px;
            border: 2px solid #e3e6f0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .question-card {
            background: linear-gradient(135deg, #f8f9ff 0%, #e6f3ff 100%);
            border-left: 5px solid #667eea;
        }
        
        .option-input {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            transition: all 0.3s ease;
        }
        
        .option-input:focus {
            border-color: #667eea;
            border-style: solid;
        }
        
        .student-rank {
            background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
            color: #2d3436;
            border-radius: 15px;
            padding: 1rem;
            margin: 0.5rem 0;
        }
        
        .rank-badge {
            background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .formula-display {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            white-space: pre-line;
        }
        
        .tab-content {
            padding: 2rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin: 1rem 0;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header">
            <h1><i class="fas fa-calculator"></i> Sistem Manajemen Soal</h1>
            <h4>Dosen - <?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
            <p class="mb-0">Kelola Soal dan Pantau Nilai Mahasiswa</p>
        </div>
        
        <div class="container-fluid">
            <ul class="nav nav-pills justify-content-center mt-4" id="mainTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'questions') ? 'active' : ''; ?>" id="questions-tab" data-bs-toggle="pill" data-bs-target="#questions" type="button" role="tab">
                        <i class="fas fa-question-circle"></i> Kelola Soal
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'add-question') ? 'active' : ''; ?>" id="add-question-tab" data-bs-toggle="pill" data-bs-target="#add-question" type="button" role="tab">
                        <i class="fas fa-plus-circle"></i> Tambah Soal
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'rankings') ? 'active' : ''; ?>" id="rankings-tab" data-bs-toggle="pill" data-bs-target="#rankings" type="button" role="tab">
                        <i class="fas fa-trophy"></i> Peringkat Mahasiswa
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'statistics') ? 'active' : ''; ?>" id="statistics-tab" data-bs-toggle="pill" data-bs-target="#statistics" type="button" role="tab">
                        <i class="fas fa-chart-bar"></i> Statistik
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="mainTabContent">
                <div class="tab-pane fade <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'questions') ? 'show active' : ''; ?> animate-fade-in" id="questions" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3><i class="fas fa-clipboard-list"></i> Daftar Soal</h3>
                        <div>
                            <span class="badge bg-info fs-6">Total Soal: <span id="totalQuestions"><?php echo count($questions_with_options); ?></span></span>
                        </div>
                    </div>
                    
                    <div class="row" id="questionsList">
                        <?php if (empty($questions_with_options)): ?>
                            <div class="col-12">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-question-circle fa-3x mb-3"></i>
                                    <p>Belum ada soal yang tersedia untuk mata kuliah yang Anda ampu.</p>
                                    <a href="quiz-crud-dosen.php?tab=add-question" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Tambah Soal Pertama
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($questions_with_options as $question): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card question-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h6 class="text-primary">Soal #<?php echo htmlspecialchars($question['question_id']); ?> (Quiz: <?php echo htmlspecialchars($question['quiz_title'] ?? 'Tidak Diketahui'); ?>)</h6>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editQuestionModal" data-question-id="<?php echo $question['question_id']; ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a></li>
                                                        <li><a class="dropdown-item text-danger" href="quiz-crud-dosen.php?action=delete_question&id=<?php echo $question['question_id']; ?>&tab=questions" onclick="return confirm('Apakah Anda yakin ingin menghapus soal ini?');">
                                                            <i class="fas fa-trash"></i> Hapus
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <p class="card-text"><strong><?php echo htmlspecialchars($question['question_text']); ?></strong></p>
                                            
                                            <?php if (!empty($question['question_formula'])): ?>
                                                <div class="formula-display mb-3">
                                                    <?php echo nl2br(htmlspecialchars($question['question_formula'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="options mb-3">
                                                <?php foreach ($question['options'] as $idx => $option): ?>
                                                    <div class="form-check <?php echo $option['is_correct'] ? 'text-success fw-bold' : ''; ?>">
                                                        <span class="badge <?php echo $option['is_correct'] ? 'bg-success' : 'bg-light text-dark'; ?> me-2">
                                                            <?php echo chr(65 + $idx); ?>
                                                        </span>
                                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                                        <?php if ($option['is_correct']): ?><i class="fas fa-check text-success ms-2"></i><?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <div class="explanation">
                                                <small class="text-muted">
                                                    <strong>Penjelasan:</strong> <?php echo htmlspecialchars($question['explanation']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'add-question') ? 'show active' : ''; ?> animate-fade-in" id="add-question" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title"><i class="fas fa-plus-circle"></i> <span id="formTitle"><?php echo $editing_question_id ? 'Edit Soal' : 'Tambah Soal Baru'; ?></span></h3>
                            <form id="questionForm" method="POST" action="quiz-crud-dosen.php">
                                <input type="hidden" name="action" value="<?php echo $editing_question_id ? 'update_question' : 'add_question'; ?>">
                                <input type="hidden" id="editingId" name="editingId" value="<?php echo htmlspecialchars($editing_question_id ?? ''); ?>">
                                
                                <div class="mb-3">
                                    <label for="quizSelect" class="form-label">Pilih Quiz</label>
                                    <select class="form-select" id="quizSelect" name="quiz_id" required>
                                        <option value="">Pilih Quiz...</option>
                                        <?php foreach ($quizzes_data as $quiz): ?>
                                            <option value="<?php echo htmlspecialchars($quiz['quiz_id']); ?>" <?php echo ($edit_quiz_id == $quiz['quiz_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($quiz['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="questionText" class="form-label">Pertanyaan</label>
                                    <textarea class="form-control" id="questionText" name="question_text" rows="3" required placeholder="Masukkan pertanyaan..."><?php echo htmlspecialchars($edit_question_text); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="questionFormula" class="form-label">Formula/Rumus (Opsional)</label>
                                    <textarea class="form-control" id="questionFormula" name="question_formula" rows="3" placeholder="Masukkan formula atau rumus jika ada..."><?php echo htmlspecialchars($edit_question_formula); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Pilihan Jawaban</label>
                                    <div id="optionsContainer">
                                        <?php for ($i = 0; $i < 4; $i++): ?>
                                            <div class="input-group mb-2">
                                                <span class="input-group-text"><?php echo chr(65 + $i); ?></span>
                                                <input type="text" class="form-control option-input" name="option_text[]" placeholder="Pilihan <?php echo chr(65 + $i); ?>" value="<?php echo htmlspecialchars($edit_options[$i] ?? ''); ?>" required>
                                                <div class="input-group-text">
                                                    <input class="form-check-input mt-0" type="radio" name="correctAnswer" value="<?php echo $i; ?>" <?php echo ($edit_correct_answer_index === $i) ? 'checked' : ''; ?> required>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="form-text text-muted">Pilih jawaban yang benar dengan menandai radio button</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="questionExplanation" class="form-label">Penjelasan</label>
                                    <textarea class="form-control" id="questionExplanation" name="question_explanation" rows="3" required placeholder="Masukkan penjelasan untuk jawaban yang benar..."><?php echo htmlspecialchars($edit_question_explanation); ?></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <span id="submitButtonText"><?php echo $editing_question_id ? 'Simpan Perubahan' : 'Simpan Soal'; ?></span>
                                    </button>
                                    <a href="quiz-crud-dosen.php?tab=add-question" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Batal
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'rankings') ? 'show active' : ''; ?> animate-fade-in" id="rankings" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3><i class="fas fa-trophy"></i> Peringkat Mahasiswa</h3>
                        <a href="quiz-crud-dosen.php?action=generate_rankings&tab=rankings" class="btn btn-success" onclick="return confirm('Ini akan menghapus dan membuat ulang data peringkat yang terkait dengan mata kuliah Anda. Lanjutkan?');">
                            <i class="fas fa-users"></i> Generate Data Sample
                        </a>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div id="rankingsList">
                                        <?php if (empty($student_rankings)): ?>
                                            <div class="text-center text-muted">
                                                <i class="fas fa-users fa-3x mb-3"></i>
                                                <p>Belum ada data peringkat mahasiswa untuk mata kuliah yang Anda ampu.</p>
                                                <small>Klik "Generate Data Sample" untuk melihat contoh data</small>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead class="table-dark">
                                                        <tr>
                                                            <th>Peringkat</th>
                                                            <th>NIM</th>
                                                            <th>Nama Mahasiswa</th>
                                                            <th>Nilai</th>
                                                            <th>Benar/Total</th>
                                                            <th>Tanggal Selesai</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($student_rankings as $index => $student): ?>
                                                            <?php
                                                                $rank = $index + 1;
                                                                // Ensure total_questions_attempted is not 0 to prevent division by zero
                                                                $percentage = ($student['total_questions_attempted'] > 0) ? round(($student['correct_answers'] / $student['total_questions_attempted']) * 100) : 0;
                                                                $statusClass = 'bg-success';
                                                                $statusText = 'Lulus';
                                                                
                                                                // Define passing score here if needed, or get from quiz table
                                                                // For simplicity, using a fixed 70 for demo
                                                                $passing_score = 70; 
                                                                if ($student['score'] < $passing_score) {
                                                                    $statusClass = 'bg-danger';
                                                                    $statusText = 'Tidak Lulus';
                                                                } else if ($student['score'] < ($passing_score + 10)) { // e.g., 70-79
                                                                    $statusClass = 'bg-warning';
                                                                    $statusText = 'Cukup';
                                                                }
                                                                
                                                                $rankIcon = '';
                                                                if ($rank === 1) $rankIcon = '<i class="fas fa-trophy text-warning"></i> ';
                                                                else if ($rank === 2) $rankIcon = '<i class="fas fa-medal text-secondary"></i> ';
                                                                else if ($rank === 3) $rankIcon = '<i class="fas fa-award text-info"></i> ';
                                                            ?>
                                                            <tr>
                                                                <td class="fw-bold"><?php echo $rankIcon . $rank; ?></td>
                                                                <td><?php echo htmlspecialchars($student['nim'] ?? '-'); ?></td>
                                                                <td><?php echo htmlspecialchars($student['full_name'] ?? '-'); ?></td>
                                                                <td><span class="badge bg-primary fs-6"><?php echo number_format($student['score'] ?? 0, 2); ?></span></td>
                                                                <td><?php echo htmlspecialchars($student['correct_answers'] ?? 0) . '/' . htmlspecialchars($student['total_questions_attempted'] ?? 0) . ' (' . $percentage . '%)'; ?></td>
                                                                <td><?php echo formatDateForDisplayPHP($student['end_time']); ?></td>
                                                                <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'statistics') ? 'show active' : ''; ?> animate-fade-in" id="statistics" role="tabpanel">
                    <h3><i class="fas fa-chart-bar"></i> Statistik Soal dan Mahasiswa</h3>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h4 id="statsQuestions"><?php echo count($questions_with_options); ?></h4>
                                <p>Total Soal Anda</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h4 id="statsStudents"><?php echo count($student_rankings); ?></h4>
                                <p>Total Mahasiswa Diampu</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h4 id="statsAvgScore"><?php echo count($student_rankings) > 0 ? number_format(array_sum(array_column($student_rankings, 'score')) / count($student_rankings), 2) : '0'; ?></h4>
                                <p>Nilai Rata-rata</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <h4 id="statsHighestScore"><?php echo count($student_rankings) > 0 ? number_format(max(array_column($student_rankings, 'score')), 2) : '0'; ?></h4>
                                <p>Nilai Tertinggi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editQuestionModalLabel">Edit Soal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editQuestionForm" method="POST" action="quiz-crud-dosen.php">
                        <input type="hidden" name="action" value="update_question">
                        <input type="hidden" id="editQuestionId" name="editingId" value="">
                        
                        <div class="mb-3">
                            <label for="editQuizSelect" class="form-label">Pilih Quiz</label>
                            <select class="form-select" id="editQuizSelect" name="quiz_id" required>
                                <option value="">Pilih Quiz...</option>
                                </select>
                        </div>

                        <div class="mb-3">
                            <label for="editQuestionText" class="form-label">Pertanyaan</label>
                            <textarea class="form-control" id="editQuestionText" name="question_text" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editQuestionFormula" class="form-label">Formula/Rumus (Opsional)</label>
                            <textarea class="form-control" id="editQuestionFormula" name="question_formula" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Pilihan Jawaban</label>
                            <div id="editOptionsContainer">
                                <?php for ($i = 0; $i < 4; $i++): ?>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text"><?php echo chr(65 + $i); ?></span>
                                        <input type="text" class="form-control" id="editOption<?php echo $i; ?>" name="option_text[]" required>
                                        <div class="input-group-text">
                                            <input class="form-check-input mt-0" type="radio" name="correctAnswer" value="<?php echo $i; ?>" required>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editQuestionExplanation" class="form-label">Penjelasan</label>
                            <textarea class="form-control" id="editQuestionExplanation" name="question_explanation" rows="3" required></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables populated by PHP
        const ALL_QUIZZES = <?php echo json_encode($quizzes_data); ?>;
        const ALL_QUESTIONS_DATA = <?php echo json_encode($questions_with_options); ?>;
        const ALL_RANKINGS_DATA = <?php echo json_encode($student_rankings); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            renderQuizDropdowns();
            renderQuestionsList(); // This updates the total count, not the actual list
            updateStatisticsDisplay();

            const urlParams = new URLSearchParams(window.location.search);
            const activeTabId = urlParams.get('tab') || 'questions'; 
            const activeTabButton = document.getElementById(`${activeTabId}-tab`);
            if (activeTabButton) {
                const bsTab = new bootstrap.Tab(activeTabButton);
                bsTab.show();
            } else {
                 const defaultTabButton = document.getElementById('questions-tab');
                 const bsTab = new bootstrap.Tab(defaultTabButton);
                 bsTab.show();
            }

            const editModalElement = document.getElementById('editQuestionModal');
            editModalElement.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const questionId = parseInt(button.getAttribute('data-question-id')); 
                populateEditModal(questionId);
            });
        });

        function renderQuizDropdowns() {
            const quizSelect = document.getElementById('quizSelect');

            const populateDropdown = (dropdownElement, selectedValue = '') => {
                dropdownElement.innerHTML = '<option value="">Pilih Quiz...</option>';
                ALL_QUIZZES.forEach(quiz => {
                    const option = document.createElement('option');
                    option.value = quiz.quiz_id;
                    option.textContent = quiz.title;
                    if (String(quiz.quiz_id) === String(selectedValue)) {
                        option.selected = true;
                    }
                    dropdownElement.appendChild(option);
                });
            };

            const phpEditingId = document.getElementById('editingId').value;
            if (phpEditingId) {
                const questionToEdit = ALL_QUESTIONS_DATA.find(q => String(q.question_id) === String(phpEditingId));
                if (questionToEdit) {
                    populateDropdown(quizSelect, questionToEdit.quiz_id);
                } else {
                    populateDropdown(quizSelect); 
                }
            } else {
                populateDropdown(quizSelect); 
            }
        }

        function renderQuestionsList() {
            const totalQuestionsSpan = document.getElementById('totalQuestions');
            if (totalQuestionsSpan) {
                totalQuestionsSpan.textContent = ALL_QUESTIONS_DATA.length;
            }
            // The actual list rendering is done by PHP on page load.
            // If you need dynamic updates without page reload, you would render the cards here.
        }

        function populateEditModal(questionId) {
            const question = ALL_QUESTIONS_DATA.find(q => q.question_id === questionId);
            if (!question) {
                console.error('Soal tidak ditemukan untuk diedit:', questionId);
                return;
            }

            document.getElementById('editQuestionId').value = question.question_id;
            document.getElementById('editQuestionText').value = question.question_text;
            document.getElementById('editQuestionFormula').value = question.question_formula || '';
            document.getElementById('editQuestionExplanation').value = question.explanation;

            const editQuizSelect = document.getElementById('editQuizSelect');
            editQuizSelect.innerHTML = '<option value="">Pilih Quiz...</option>';
            ALL_QUIZZES.forEach(quiz => {
                const option = document.createElement('option');
                option.value = quiz.quiz_id;
                option.textContent = quiz.title;
                if (quiz.quiz_id === question.quiz_id) {
                    option.selected = true;
                }
                editQuizSelect.appendChild(option);
            });

            const optionsContainer = document.getElementById('editOptionsContainer');
            question.options.forEach((option, idx) => {
                const optionInput = optionsContainer.querySelector(`input[id="editOption${idx}"]`); 
                if (optionInput) {
                    optionInput.value = option.option_text;
                }
                const correctRadio = optionsContainer.querySelector(`input[name="correctAnswer"][value="${idx}"]`);
                if (correctRadio) {
                    correctRadio.checked = option.is_correct;
                }
            });
        }
        
        function updateStatisticsDisplay() {
            document.getElementById('statsQuestions').textContent = ALL_QUESTIONS_DATA.length;
            
            document.getElementById('statsStudents').textContent = ALL_RANKINGS_DATA.length;
            
            if (ALL_RANKINGS_DATA.length > 0) {
                const avgScore = ALL_RANKINGS_DATA.reduce((sum, student) => sum + parseFloat(student.score), 0) / ALL_RANKINGS_DATA.length;
                const highestScore = Math.max(...ALL_RANKINGS_DATA.map(student => parseFloat(student.score)));
                
                document.getElementById('statsAvgScore').textContent = avgScore.toFixed(2);
                document.getElementById('statsHighestScore').textContent = highestScore.toFixed(2);
            } else {
                document.getElementById('statsAvgScore').textContent = '0.00';
                document.getElementById('statsHighestScore').textContent = '0.00';
            }
        }

        function nl2br(str) {
            if (typeof str !== 'string' && str !== null) return ''; 
            if (str === null) return '';
            return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
        }
        
        function htmlspecialchars(str) {
            if (typeof str !== 'string' && str !== null) return ''; 
            if (str === null) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return str.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>