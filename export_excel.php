<?php
// export_excel.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connection.php'; // Pastikan jalur ini benar

// Ambil parameter dari URL
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;
$class_name = isset($_GET['class_name']) ? $_GET['class_name'] : null; // Ini adalah course_code
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '2024/2025';

// Pastikan ada course_id yang dipilih
if (!$course_id) {
    die("Parameter course_id tidak ditemukan.");
}

// Data dosen dan mata kuliah yang sama seperti di laporan-dosen.php
$current_lecturer_id = 2; // Sesuaikan dengan ID dosen yang relevan

// --- Fungsi Helper PHP (sama seperti di laporan-dosen.php) ---
function getGradeLetterPHP($final_score) {
    if ($final_score >= 85) return 'A';
    if ($final_score >= 70) return 'B';
    if ($final_score >= 55) return 'C';
    if ($final_score >= 40) return 'D';
    return 'E';
}

function getGradePointsPHP($grade_letter) {
    switch ($grade_letter) {
        case 'A': return 4.00;
        case 'B': return 3.00;
        case 'C': return 2.00;
        case 'D': return 1.00;
        case 'E': return 0.00;
        default: return 0.00;
    }
}

// --- Ambil informasi Mata Kuliah dan Dosen untuk nama file Excel dan header laporan ---
$course_name = "Laporan_Nilai";
$course_code = "";
$lecturer_name_for_excel = "Dosen Pengajar";

$sql_course_info = "SELECT c.course_name, c.course_code, u.full_name, u.gelar 
                    FROM courses c JOIN users u ON c.lecturer_id = u.user_id 
                    WHERE c.course_id = ?";
$stmt_course_info = $conn->prepare($sql_course_info);
if ($stmt_course_info) {
    $stmt_course_info->bind_param("i", $course_id);
    $stmt_course_info->execute();
    $result_course_info = $stmt_course_info->get_result();
    if ($row = $result_course_info->fetch_assoc()) {
        $course_name = $row['course_name'];
        $course_code = $row['course_code'];
        $lecturer_name_for_excel = $row['full_name'] . ($row['gelar'] ? ', ' . $row['gelar'] : '');
    }
    $stmt_course_info->close();
}

// --- Ambil Data Mahasiswa dan Nilainya (Logika sama seperti di laporan-dosen.php) ---
$students_data_for_export = [];

$sql_students_in_course = "
    SELECT u.user_id, u.full_name, u.nim, u.study_program
    FROM users u
    JOIN course_enrollments ce ON u.user_id = ce.student_id
    WHERE ce.course_id = ? AND u.role = 'student'
    ORDER BY u.full_name ASC;
";
$stmt_students = $conn->prepare($sql_students_in_course);
if ($stmt_students) {
    $stmt_students->bind_param("i", $course_id);
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();

    $all_students_for_report = [];
    while ($student_row = $result_students->fetch_assoc()) {
        $all_students_for_report[] = $student_row;
    }
    $stmt_students->close();
}

foreach ($all_students_for_report as $student) {
    $student_id = $student['user_id'];
    $final_score = 0;
    $grade_letter = 'E';
    $ips = 0.00;
    $ipk = 0.00;

    $component_grades = [
        'Assignment' => ['grade_value' => null, 'percentage' => 0.20],
        'UTS' => ['grade_value' => null, 'percentage' => 0.30],
        'UAS' => ['grade_value' => null, 'percentage' => 0.40],
        'Partisipasi' => ['grade_value' => null, 'percentage' => 0.10]
    ];

    // Fetch Assignment Grade
    $sql_avg_assignment_grade = "SELECT AVG(grade_value) as avg_grade FROM grades WHERE student_id = ? AND course_id = ? AND grade_type = 'Assignment'";
    $stmt_avg_assign = $conn->prepare($sql_avg_assignment_grade);
    if ($stmt_avg_assign) {
        $stmt_avg_assign->bind_param("ii", $student_id, $course_id);
        $stmt_avg_assign->execute();
        $result_avg_assign = $stmt_avg_assign->get_result();
        if ($row = $result_avg_assign->fetch_assoc()) {
            $component_grades['Assignment']['grade_value'] = $row['avg_grade'];
        }
        $stmt_avg_assign->close();
    }

    // Fetch UTS and UAS from grades table or exam_attempts
    $sql_exam_grades_from_grades_table = "SELECT grade_value, grade_type FROM grades WHERE student_id = ? AND course_id = ? AND (grade_type = 'UTS' OR grade_type = 'UAS') ORDER BY graded_at DESC";
    $stmt_exam_grades_from_grades = $conn->prepare($sql_exam_grades_from_grades_table);
    if ($stmt_exam_grades_from_grades) {
        $stmt_exam_grades_from_grades->bind_param("ii", $student_id, $course_id);
        $stmt_exam_grades_from_grades->execute();
        $result_exam_grades_from_grades = $stmt_exam_grades_from_grades->get_result();
        while($row = $result_exam_grades_from_grades->fetch_assoc()){
            if($row['grade_type'] == 'UTS') $component_grades['UTS']['grade_value'] = $row['grade_value'];
            if($row['grade_type'] == 'UAS') $component_grades['UAS']['grade_value'] = $row['grade_value'];
        }
        $stmt_exam_grades_from_grades->close();
    }
    if ($component_grades['UTS']['grade_value'] === null) {
        $sql_uts_score = "SELECT eat.score FROM exam_attempts eat JOIN exams e ON eat.exam_id = e.exam_id WHERE eat.student_id = ? AND e.course_id = ? AND e.exam_type = 'UTS' ORDER BY eat.end_time DESC LIMIT 1";
        $stmt_uts_score = $conn->prepare($sql_uts_score);
        if ($stmt_uts_score) {
            $stmt_uts_score->bind_param("ii", $student_id, $course_id);
            $stmt_uts_score->execute();
            $result_uts_score = $stmt_uts_score->get_result();
            if ($row = $result_uts_score->fetch_assoc()) {
                $component_grades['UTS']['grade_value'] = $row['score'];
            }
            $stmt_uts_score->close();
        }
    }
    if ($component_grades['UAS']['grade_value'] === null) {
        $sql_uas_score = "SELECT eat.score FROM exam_attempts eat JOIN exams e ON eat.exam_id = e.exam_id WHERE eat.student_id = ? AND e.course_id = ? AND e.exam_type = 'UAS' ORDER BY eat.end_time DESC LIMIT 1";
        $stmt_uas_score = $conn->prepare($sql_uas_score);
        if ($stmt_uas_score) {
            $stmt_uas_score->bind_param("ii", $student_id, $course_id);
            $stmt_uas_score->execute();
            $result_uas_score = $stmt_uas_score->get_result();
            if ($row = $result_uas_score->fetch_assoc()) {
                $component_grades['UAS']['grade_value'] = $row['score'];
            }
            $stmt_uas_score->close();
        }
    }

    // Partisipasi (simulasi jika tidak ada di DB)
    if ($component_grades['Partisipasi']['grade_value'] === null) {
        $component_grades['Partisipasi']['grade_value'] = $component_grades['Assignment']['grade_value'] ?? rand(70,95);
    }
    // Simulasi jika nilai komponen masih null setelah fetching (pastikan tidak ada null di perhitungan)
    $component_grades['Assignment']['grade_value'] = $component_grades['Assignment']['grade_value'] ?? rand(60, 90);
    $component_grades['UTS']['grade_value'] = $component_grades['UTS']['grade_value'] ?? rand(50, 85);
    $component_grades['UAS']['grade_value'] = $component_grades['UAS']['grade_value'] ?? rand(55, 90);


    // Hitung nilai akhir
    $final_score =
        ($component_grades['Assignment']['grade_value'] * $component_grades['Assignment']['percentage']) +
        ($component_grades['UTS']['grade_value'] * $component_grades['UTS']['percentage']) +
        ($component_grades['UAS']['grade_value'] * $component_grades['UAS']['percentage']) +
        ($component_grades['Partisipasi']['grade_value'] * $component_grades['Partisipasi']['percentage']);

    $grade_letter = getGradeLetterPHP($final_score);
    $grade_points = getGradePointsPHP($grade_letter);

    // Simulasi IPS dan IPK
    $ips = $grade_points;
    $ipk = (rand(200, 390) / 100);

    $students_data_for_export[] = [
        'NIM' => $student['nim'],
        'Nama Mahasiswa' => $student['full_name'],
        'Tugas (20%)' => round($component_grades['Assignment']['grade_value']),
        'UTS (30%)' => round($component_grades['UTS']['grade_value']),
        'UAS (40%)' => round($component_grades['UAS']['grade_value']),
        'Partisipasi (10%)' => round($component_grades['Partisipasi']['grade_value']),
        'Nilai Akhir' => round($final_score, 1),
        'Grade' => $grade_letter,
        'IP Semester' => number_format($ips, 2, '.', ''), // Pastikan format desimal dengan titik
        'IPK' => number_format($ipk, 2, '.', '')          // Pastikan format desimal dengan titik
    ];
}

$conn->close();

// --- Generate CSV Output ---
// Nama file untuk download
$filename = "Laporan_Nilai_" . str_replace(" ", "_", $course_name) . "_" . $course_code . "_" . $semester . "_" . str_replace("/", "-", $academic_year) . ".csv";

// Set header untuk download file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// --- PENTING: Delimiter diatur ke titik koma (;) ---
$delimiter = ';';

// Tambahkan BOM untuk UTF-8, membantu Excel mengenali encoding dengan benar.
// Kadang ada sistem yang bermasalah dengan BOM, jika masih ga rapi coba hapus baris ini.
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 

// Baris informasi laporan (seperti judul)
fputcsv($output, ["Laporan Nilai Semester " . strtoupper($semester) . " " . $academic_year], $delimiter);
fputcsv($output, ["Mata Kuliah: " . $course_name . " (" . $course_code . ")"], $delimiter);
fputcsv($output, ["Dosen Pengajar: " . $lecturer_name_for_excel], $delimiter);
fputcsv($output, [""]); // Baris kosong sebagai pemisah

// Tambahkan header tabel
if (!empty($students_data_for_export)) {
    fputcsv($output, array_keys($students_data_for_export[0]), $delimiter);
} else {
    // Jika tidak ada data siswa, tetap tampilkan header kolom
    fputcsv($output, ['NIM', 'Nama Mahasiswa', 'Tugas (20%)', 'UTS (30%)', 'UAS (40%)', 'Partisipasi (10%)', 'Nilai Akhir', 'Grade', 'IP Semester', 'IPK'], $delimiter);
}

// Tambahkan data siswa
foreach ($students_data_for_export as $row) {
    fputcsv($output, $row, $delimiter);
}

fclose($output);
exit;

?>