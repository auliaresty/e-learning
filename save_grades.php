<?php
// save_grades.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json'); // Respon dalam format JSON

include 'db_connection.php';

// Ambil data POST yang dikirim dari JavaScript
$data = json_decode(file_get_contents('php://input'), true);

$response = ['success' => false, 'message' => 'Terjadi kesalahan.', 'error_detail' => null];

if (isset($data['course_id']) && isset($data['grades'])) {
    $course_id = (int)$data['course_id'];
    $grades_to_save = $data['grades'];

    $conn->begin_transaction();
    $all_success = true;

    // Fungsi helper (sama seperti di laporan-dosen.php)
    function getGradeLetterForSave($final_score) {
        if ($final_score >= 85) return 'A';
        if ($final_score >= 70) return 'B';
        if ($final_score >= 55) return 'C';
        if ($final_score >= 40) return 'D';
        return 'E';
    }

    function getGradePointsForSave($grade_letter) {
        switch ($grade_letter) {
            case 'A': return 4.00;
            case 'B': return 3.00;
            case 'C': return 2.00;
            case 'D': return 1.00;
            case 'E': return 0.00;
            default: return 0.00;
        }
    }

    // Mapping tipe grade dari JS ke ENUM di DB (pastikan ini sesuai!)
    $grade_type_map = [
        'tugas' => 'Assignment',
        'uts' => 'UTS', // Perbaiki ini menjadi 'UTS' (kapital)
        'uas' => 'UAS', // Perbaiki ini menjadi 'UAS' (kapital)
        'partisipasi' => 'Partisipasi' // Sesuaikan jika ini bukan nilai valid di ENUM Anda
    ];

    foreach ($grades_to_save as $student_grade) {
        $student_id = (int)$student_grade['user_id'];

        foreach (['tugas', 'uts', 'uas', 'partisipasi'] as $type_key) {
            $value = (float)$student_grade[$type_key];
            $db_grade_type = $grade_type_map[$type_key];

            $grade_letter = getGradeLetterForSave($value);
            $grade_points = getGradePointsForSave($grade_letter);

            // Cek apakah entri grade sudah ada
            $check_sql = "SELECT grade_id FROM grades WHERE student_id = ? AND course_id = ? AND grade_type = ?";
            $stmt_check = $conn->prepare($check_sql);
            if (!$stmt_check) {
                $all_success = false;
                $response['error_detail'] = "Failed to prepare check statement: " . $conn->error;
                break 2; // Break both loops
            }
            $stmt_check->bind_param("iis", $student_id, $course_id, $db_grade_type);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $stmt_check->close();

            if ($result_check->num_rows > 0) {
                // Update existing grade
                $update_sql = "UPDATE grades SET grade_value = ?, grade_letter = ?, grade_points = ?, graded_at = NOW() WHERE student_id = ? AND course_id = ? AND grade_type = ?";
                $stmt_update = $conn->prepare($update_sql);
                if (!$stmt_update) {
                    $all_success = false;
                    $response['error_detail'] = "Failed to prepare update statement for " . $db_grade_type . ": " . $conn->error;
                    break 2;
                }
                $stmt_update->bind_param("dssiis", $value, $grade_letter, $grade_points, $student_id, $course_id, $db_grade_type);
                if (!$stmt_update->execute()) {
                    $all_success = false;
                    $response['error_detail'] = "Failed to execute update for " . $db_grade_type . ": " . $stmt_update->error;
                    $stmt_update->close();
                    break 2;
                }
                $stmt_update->close();
            } else {
                // Insert new grade. Masukkan NULL untuk item_id dan grade_id agar auto-increment bekerja
                $insert_sql = "INSERT INTO grades (student_id, course_id, item_id, grade_value, grade_letter, grade_points, grade_type, graded_at) VALUES (?, ?, NULL, ?, ?, ?, ?, NOW())";
                $stmt_insert = $conn->prepare($insert_sql);
                if (!$stmt_insert) {
                    $all_success = false;
                    $response['error_detail'] = "Failed to prepare insert statement for " . $db_grade_type . ": " . $conn->error;
                    break 2;
                }
                // 'iiddss' -> integer, integer, decimal, decimal, string, string
                $stmt_insert->bind_param("iiddss", $student_id, $course_id, $value, $grade_letter, $grade_points, $db_grade_type);
                if (!$stmt_insert->execute()) {
                    $all_success = false;
                    $response['error_detail'] = "Failed to execute insert for " . $db_grade_type . ": " . $stmt_insert->error;
                    $stmt_insert->close();
                    break 2;
                }
                $stmt_insert->close();
            }
        } // End of foreach type_key

        if (!$all_success) break; // Exit student loop if any component failed

        // Handle Final Course Grade (Optional: if you want to store it explicitly)
        $final_score = ($student_grade['tugas'] * 0.20) + ($student_grade['uts'] * 0.30) + ($student_grade['uas'] * 0.40) + ($student_grade['partisipasi'] * 0.10);
        $final_grade_letter = getGradeLetterForSave($final_score);
        $final_grade_points = getGradePointsForSave($final_grade_letter);

        $check_final_sql = "SELECT grade_id FROM grades WHERE student_id = ? AND course_id = ? AND grade_type = 'Final Course'";
        $stmt_check_final = $conn->prepare($check_final_sql);
        if (!$stmt_check_final) {
             $all_success = false;
             $response['error_detail'] = "Failed to prepare final check statement: " . $conn->error;
             break;
        }
        $stmt_check_final->bind_param("ii", $student_id, $course_id);
        $stmt_check_final->execute();
        $result_check_final = $stmt_check_final->get_result();
        $stmt_check_final->close();

        if ($result_check_final->num_rows > 0) {
            $update_final_sql = "UPDATE grades SET grade_value = ?, grade_letter = ?, grade_points = ?, graded_at = NOW() WHERE student_id = ? AND course_id = ? AND grade_type = 'Final Course'";
            $stmt_update_final = $conn->prepare($update_final_sql);
            if (!$stmt_update_final) {
                $all_success = false;
                $response['error_detail'] = "Failed to prepare final update statement: " . $conn->error;
                break;
            }
            $stmt_update_final->bind_param("dssii", $final_score, $final_grade_letter, $final_grade_points, $student_id, $course_id);
            if (!$stmt_update_final->execute()) {
                $all_success = false;
                $response['error_detail'] = "Failed to execute final update: " . $stmt_update_final->error;
                $stmt_update_final->close();
                break;
            }
            $stmt_update_final->close();
        } else {
            // Insert new final course grade. Masukkan NULL untuk item_id dan grade_id.
            $insert_final_sql = "INSERT INTO grades (student_id, course_id, item_id, grade_value, grade_letter, grade_points, grade_type, graded_at) VALUES (?, ?, NULL, ?, ?, ?, 'Final Course', NOW())";
            $stmt_insert_final = $conn->prepare($insert_final_sql);
            if (!$stmt_insert_final) {
                $all_success = false;
                $response['error_detail'] = "Failed to prepare final insert statement: " . $conn->error;
                break;
            }
            $stmt_insert_final->bind_param("iidds", $student_id, $course_id, $final_score, $final_grade_letter, $final_grade_points);
            if (!$stmt_insert_final->execute()) {
                $all_success = false;
                $response['error_detail'] = "Failed to execute final insert: " . $stmt_insert_final->error;
                $stmt_insert_final->close();
                break;
            }
            $stmt_insert_final->close();
        }

    } // End of foreach student_grade

    if ($all_success) {
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Nilai berhasil disimpan.';
    } else {
        $conn->rollback();
        $response['message'] = 'Gagal menyimpan nilai.';
    }

} else {
    $response['message'] = 'Data tidak lengkap.';
}

echo json_encode($response);
$conn->close();
?>