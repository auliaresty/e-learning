<?php
include 'db_connection.php'; // Meng-include file koneksi database Anda

session_start(); // Mulai sesi untuk manajemen login

// Asumsi lecturer_id 2 (Dr. Budi Santosa) untuk demo.
// Di aplikasi nyata, ini dari sesi login setelah otentikasi.
// Misalnya: $_SESSION['user_id'] = 2; $_SESSION['role'] = 'lecturer';
// Cek jika sesi belum ada, set untuk demo
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // Ganti dengan ID dosen yang sesuai dari database Anda
    $_SESSION['role'] = 'lecturer';
}

// Periksa apakah pengguna sudah login dan memiliki peran 'lecturer'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    // Anda bisa redirect ke halaman login atau menampilkan pesan error
    header("Location: login.php"); // Ganti login.php dengan halaman login Anda
    exit();
}
$current_lecturer_id = $_SESSION['user_id']; // Dosen yang sedang login

// Inisialisasi variabel untuk tampilan
$current_course_id_selected = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$current_assignment_id_selected = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
$view_mode = isset($_GET['view_all']) && $_GET['view_all'] === 'true' ? 'all_assignments' : 'single_assignment';

$courses_for_dropdown = []; // Untuk dropdown Pilih Kelas
$assignments_for_dropdown = []; // Untuk dropdown Pilih Tugas
$students_grades_data = []; // Data mahasiswa dengan nilai atau semua tugas
$statistics_data = []; // Data statistik untuk single_assignment view

$header_title_text = "Input Nilai";
$dosen_name_for_header = "Dosen"; // Nama default untuk header

// --- Fungsi Helper (PHP) ---
function getGradeLetterPHP($grade_value) {
    if ($grade_value === null || $grade_value === '') return '-';
    $value = (float)$grade_value;
    if ($value >= 85) return 'A';
    if ($value >= 75) return 'B';
    if ($value >= 65) return 'C';
    if ($value >= 55) return 'D';
    return 'E';
}

function getSubmissionStatusTextPHP($submitted_at, $due_date) {
    if (!$submitted_at) return 'Belum';
    if (strtotime($submitted_at) > strtotime($due_date)) return 'Terlambat';
    return 'Mengumpul';
}

function getStatusBadgeClassPHP($submitted_at, $due_date) {
    if (!$submitted_at) return 'status-not-submitted';
    if (strtotime($submitted_at) > strtotime($due_date)) return 'status-pending'; // Atau status-late jika ada
    return 'status-graded';
}

// --- Handle Save Grades (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_course_id = (int)($_POST['current_course_id'] ?? null);
    $posted_assignment_id = (int)($_POST['current_assignment_id'] ?? null);
    $posted_view_mode = $_POST['view_mode'] ?? 'single_assignment';

    // Pastikan ini hanya berjalan jika ada course_id dan assignment_id yang valid
    if ($posted_course_id && $posted_assignment_id && isset($_POST['student_id']) && is_array($_POST['student_id'])) {
        foreach ($_POST['student_id'] as $index => $student_id) {
            $student_id = (int)$student_id;
            $grade_value_str = $_POST['grade_value'][$index] ?? '';
            $notes = $_POST['notes'][$index] ?? '';

            // Konversi nilai string ke float, set null jika kosong atau tidak valid
            $grade_value = null;
            if (is_numeric($grade_value_str) && $grade_value_str >= 0 && $grade_value_str <= 100) {
                $grade_value = (float)$grade_value_str;
            }

            $grade_letter = getGradeLetterPHP($grade_value);
            $grade_points = 0.00; // Untuk demo, asumsi 0.00, bisa dihitung berdasarkan SKS dan grade letter

            // Cek apakah nilai untuk student_id, course_id, dan item_id (assignment_id) sudah ada
            $sql_check_grade = "SELECT grade_id FROM grades WHERE student_id = ? AND course_id = ? AND item_id = ? AND grade_type = 'Assignment'";
            $stmt_check_grade = $conn->prepare($sql_check_grade);
            if ($stmt_check_grade) {
                $stmt_check_grade->bind_param("iii", $student_id, $posted_course_id, $posted_assignment_id);
                $stmt_check_grade->execute();
                $result_check_grade = $stmt_check_grade->get_result();

                if ($result_check_grade->num_rows > 0) {
                    // Update nilai yang sudah ada
                    $grade_id = $result_check_grade->fetch_assoc()['grade_id'];
                    $sql_update_grade = "UPDATE grades SET grade_value = ?, grade_letter = ?, grade_points = ?, feedback = ?, graded_at = NOW() WHERE grade_id = ?";
                    $stmt_update_grade = $conn->prepare($sql_update_grade);
                    if ($stmt_update_grade) {
                        $stmt_update_grade->bind_param("dsssi", $grade_value, $grade_letter, $grade_points, $notes, $grade_id);
                        $stmt_update_grade->execute();
                        $stmt_update_grade->close();
                    }
                } else {
                    // Insert nilai baru
                    $sql_insert_grade = "INSERT INTO grades (student_id, course_id, item_id, grade_value, grade_letter, grade_points, grade_type, feedback) VALUES (?, ?, ?, ?, ?, ?, 'Assignment', ?)";
                    $stmt_insert_grade = $conn->prepare($sql_insert_grade);
                    if ($stmt_insert_grade) {
                        $stmt_insert_grade->bind_param("iiidsss", $student_id, $posted_course_id, $posted_assignment_id, $grade_value, $grade_letter, $grade_points, $notes);
                        $stmt_insert_grade->execute();
                        $stmt_insert_grade->close();
                    }
                }
                $stmt_check_grade->close();
            }
        }
        echo "<script>alert('Nilai berhasil disimpan!');</script>";
    } else {
        echo "<script>alert('Gagal menyimpan nilai: Data tidak lengkap atau tidak valid.');</script>";
    }
    // Redirect kembali ke halaman dengan filter yang sama
    header("Location: input-nilai-dosen.php?course_id={$posted_course_id}&assignment_id={$posted_assignment_id}&view_all={$posted_view_mode}");
    exit;
}

// --- Fetch data for dropdowns (Classes and Assignments) ---
$courses_for_dropdown = [];
$sql_courses = "SELECT course_id, course_name FROM courses WHERE lecturer_id = ? ORDER BY course_name ASC";
$stmt_courses = $conn->prepare($sql_courses);
if ($stmt_courses) {
    $stmt_courses->bind_param("i", $current_lecturer_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($row = $result_courses->fetch_assoc()) {
        $courses_for_dropdown[] = $row;
    }
    $stmt_courses->close();
}

// Assignments dropdown (hanya jika course_id sudah dipilih)
if ($current_course_id_selected) {
    $sql_assignments = "SELECT assignment_id, title FROM assignments WHERE course_id = ? ORDER BY title ASC";
    $stmt_assignments = $conn->prepare($sql_assignments);
    if ($stmt_assignments) {
        $stmt_assignments->bind_param("i", $current_course_id_selected);
        $stmt_assignments->execute();
        $result_assignments = $stmt_assignments->get_result();
        while ($row = $result_assignments->fetch_assoc()) {
            $assignments_for_dropdown[] = $row;
        }
        $stmt_assignments->close();
    }
}

// --- Fetch Student Data and Grades ---
$students_grades_data = [];
$total_graded_students = 0;
$total_grade_sum = 0;
$grade_distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];

if ($current_course_id_selected) {
    $sql_students_in_course = "
        SELECT u.user_id, u.full_name, u.nim
        FROM users u
        JOIN course_enrollments ce ON u.user_id = ce.student_id
        WHERE ce.course_id = ? AND u.role = 'student'
        ORDER BY u.full_name ASC;
    ";
    $stmt_students = $conn->prepare($sql_students_in_course);
    if ($stmt_students) {
        $stmt_students->bind_param("i", $current_course_id_selected);
        $stmt_students->execute();
        $students_list_in_course = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_students->close();
        
        $selected_course_name = '';
        foreach($courses_for_dropdown as $course_opt) {
            if ($course_opt['course_id'] == $current_course_id_selected) {
                $selected_course_name = $course_opt['course_name'];
                break;
            }
        }

        if ($view_mode === 'single_assignment' && $current_assignment_id_selected) {
            // Header title untuk single assignment
            $assignment_title_for_header = '';
            $selected_assignment_due_date = '';
            foreach($assignments_for_dropdown as $assign_opt) {
                if ($assign_opt['assignment_id'] == $current_assignment_id_selected) {
                    $assignment_title_for_header = $assign_opt['title'];
                    // Perlu query due_date dari assignments
                    $sql_due_date = "SELECT due_date FROM assignments WHERE assignment_id = ?";
                    $stmt_due_date = $conn->prepare($sql_due_date);
                    if($stmt_due_date){
                        $stmt_due_date->bind_param("i", $current_assignment_id_selected);
                        $stmt_due_date->execute();
                        $result_due_date = $stmt_due_date->get_result();
                        if($row_due_date = $result_due_date->fetch_assoc()){
                            $selected_assignment_due_date = $row_due_date['due_date'];
                        }
                        $stmt_due_date->close();
                    }
                    break;
                }
            }
            $header_title_text = "Input Nilai - " . htmlspecialchars($assignment_title_for_header);

            // Fetch nilai dan status submission untuk satu tugas
            foreach ($students_list_in_course as $student) {
                $student_id = $student['user_id'];
                $grade_value = null;
                $grade_letter = '-';
                $submission_status_text = 'Belum';
                $submission_status_class = 'status-not-submitted';
                $notes = '';
                $submitted_at = null; // Waktu submit dari tabel submissions

                // Prioritaskan nilai dari tabel 'grades' jika sudah ada, digabungkan dengan submission info
                $sql_get_student_data = "
                    SELECT
                        g.grade_value,
                        g.feedback,
                        s.submitted_at
                    FROM
                        course_enrollments ce
                    JOIN users u ON ce.student_id = u.user_id
                    LEFT JOIN grades g ON u.user_id = g.student_id AND ce.course_id = g.course_id AND g.item_id = ? AND g.grade_type = 'Assignment'
                    LEFT JOIN submissions s ON u.user_id = s.student_id AND s.assignment_id = ?
                    WHERE u.user_id = ? AND ce.course_id = ?
                ";
                $stmt_get_student_data = $conn->prepare($sql_get_student_data);
                if ($stmt_get_student_data) {
                    $stmt_get_student_data->bind_param("iiii", $current_assignment_id_selected, $current_assignment_id_selected, $student_id, $current_course_id_selected);
                    $stmt_get_student_data->execute();
                    $result_student_data = $stmt_get_student_data->get_result();
                    if ($row_data = $result_student_data->fetch_assoc()) {
                        $grade_value = $row_data['grade_value'];
                        $notes = $row_data['feedback'];
                        $submitted_at = $row_data['submitted_at'];
                    }
                    $stmt_get_student_data->close();
                }
                
                $grade_letter = getGradeLetterPHP($grade_value);
                $submission_status_text = getSubmissionStatusTextPHP($submitted_at, $selected_assignment_due_date);
                $submission_status_class = getStatusBadgeClassPHP($submitted_at, $selected_assignment_due_date);

                $students_grades_data[] = [
                    'user_id' => $student_id,
                    'full_name' => $student['full_name'],
                    'nim' => $student['nim'],
                    'grade_value' => $grade_value,
                    'grade_letter' => $grade_letter,
                    'submission_status_text' => $submission_status_text,
                    'submission_status_class' => $submission_status_class,
                    'notes' => $notes
                ];

                if ($grade_value !== null) {
                    $total_graded_students++;
                    $total_grade_sum += $grade_value;
                    $grade_distribution[getGradeLetterPHP($grade_value)]++;
                }
            }
        } elseif ($view_mode === 'all_assignments') {
            // Header title untuk all assignments
            $header_title_text = "Semua Nilai - Kelas " . htmlspecialchars($selected_course_name);

            // Fetch semua tugas untuk course ini (untuk header kolom)
            $all_assignments_in_course = [];
            $sql_all_assignments = "SELECT assignment_id, title FROM assignments WHERE course_id = ? ORDER BY due_date ASC";
            $stmt_all_assignments = $conn->prepare($sql_all_assignments);
            if ($stmt_all_assignments) {
                $stmt_all_assignments->bind_param("i", $current_course_id_selected);
                $stmt_all_assignments->execute();
                $result_all_assignments = $stmt_all_assignments->get_result();
                while($row_assign = $result_all_assignments->fetch_assoc()) {
                    $all_assignments_in_course[] = $row_assign;
                }
                $stmt_all_assignments->close();
            }
            // Update assignments_for_dropdown agar sesuai dengan tugas di kelas ini
            $assignments_for_dropdown = $all_assignments_in_course;


            foreach ($students_list_in_course as $student) {
                $student_id = $student['user_id'];
                $student_all_assignments_grades = [];
                $student_total_score = 0;
                $student_graded_assignments_count = 0;

                foreach($all_assignments_in_course as $assignment_col) {
                    $assignment_id_col = $assignment_col['assignment_id'];
                    $grade_val = null;
                    $grade_letter_col = '-';

                    $sql_get_grade_for_assignment = "SELECT grade_value, grade_letter FROM grades WHERE student_id = ? AND course_id = ? AND item_id = ? AND grade_type = 'Assignment'";
                    $stmt_get_grade = $conn->prepare($sql_get_grade_for_assignment);
                    if ($stmt_get_grade) {
                        $stmt_get_grade->bind_param("iii", $student_id, $current_course_id_selected, $assignment_id_col);
                        $stmt_get_grade->execute();
                        $result_grade = $stmt_get_grade->get_result();
                        if ($row_grade = $result_grade->fetch_assoc()) {
                            $grade_val = $row_grade['grade_value'];
                            $grade_letter_col = $row_grade['grade_letter'];
                        }
                        $stmt_get_grade->close();
                    }

                    $student_all_assignments_grades[] = [
                        'assignment_id' => $assignment_id_col,
                        'title' => $assignment_col['title'],
                        'grade_value' => $grade_val,
                        'grade_letter' => $grade_letter_col
                    ];
                    if ($grade_val !== null) {
                        $student_total_score += $grade_val;
                        $student_graded_assignments_count++;
                    }
                }

                $students_grades_data[] = [
                    'user_id' => $student_id,
                    'full_name' => $student['full_name'],
                    'nim' => $student['nim'],
                    'assignments_grades' => $student_all_assignments_grades,
                    'average_score' => $student_graded_assignments_count > 0 ? round($student_total_score / $student_graded_assignments_count, 1) : null
                ];
            }
        }
    }
}

// Statistik untuk mode single_assignment (hanya ditampilkan jika ada tugas yang dipilih)
$statistics_data = [
    'total_graded_students' => $total_graded_students,
    'total_students_in_class' => count($students_list_in_course ?? []), // Jika students_list_in_course kosong, pakai array kosong
    'average_grade' => $total_graded_students > 0 ? round($total_grade_sum / $total_graded_students, 1) : 0,
    'grade_distribution' => $grade_distribution
];


// Ambil nama dosen untuk header
$sql_lecturer_header = "SELECT full_name, gelar FROM users WHERE user_id = ? AND role = 'lecturer'";
$stmt_lecturer_header = $conn->prepare($sql_lecturer_header);
if ($stmt_lecturer_header) {
    $stmt_lecturer_header->bind_param("i", $current_lecturer_id);
    $stmt_lecturer_header->execute();
    $result_lecturer_header = $stmt_lecturer_header->get_result();
    if ($row_lecturer = $result_lecturer_header->fetch_assoc()) {
        $dosen_name_for_header = htmlspecialchars($row_lecturer['full_name']);
        if (!empty($row_lecturer['gelar'])) {
             $dosen_name_for_header .= ", " . htmlspecialchars($row_lecturer['gelar']);
        }
    }
    $stmt_lecturer_header->close();
}

$conn->close(); // Tutup koneksi database
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Nilai - <?php echo $header_title_text; // Dinamis berdasarkan pilihan ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Anda yang sudah ada */
        :root {
            --color-primary: #B6D0EF;
            --color-secondary: #63A3F1;
            --color-light: #FAFFEE;
            --color-accent: #4F8A9E;
            --color-white: #FFFFFF;
        }

        body {
            background: linear-gradient(135deg, var(--color-light) 0%, var(--color-primary) 100%);
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .navbar {
            background: linear-gradient(90deg, var(--color-accent) 0%, var(--color-secondary) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: var(--color-white) !important;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .container-fluid {
            padding: 20px;
        }

        .class-selector {
            background: var(--color-white);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .assignment-selector {
            background: var(--color-white);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .grade-table-container {
            background: var(--color-white);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: linear-gradient(45deg, var(--color-secondary), var(--color-primary));
            color: var(--color-white);
            border: none;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }

        .table tbody td {
            vertical-align: middle;
            text-align: center;
        }

        .table tbody tr:nth-child(even) {
            background-color: var(--color-light);
        }

        .grade-input {
            width: 80px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px;
            font-weight: bold;
        }

        .grade-input:focus {
            border-color: var(--color-secondary);
            box-shadow: 0 0 0 0.2rem rgba(99, 163, 241, 0.25);
        }

        .grade-input.excellent {
            background-color: #d4edda;
            border-color: #28a745;
        }

        .grade-input.good {
            background-color: #d1ecf1;
            border-color: #17a2b8;
        }

        .grade-input.average {
            background-color: #fff3cd;
            border-color: #ffc107;
        }

        .grade-input.poor {
            background-color: #f8d7da;
            border-color: #dc3545;
        }

        .btn-custom {
            background: linear-gradient(45deg, var(--color-secondary), var(--color-accent));
            color: var(--color-white);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: var(--color-white);
        }

        .btn-upload {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }

        .student-name {
            text-align: left !important;
            font-weight: bold;
            color: var(--color-accent);
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-graded {
            background: #28a745;
            color: white;
        }

        .status-pending {
            background: #ffc107;
            color: black;
        }

        .status-not-submitted {
            background: #dc3545;
            color: white;
        }

        .statistics-card {
            background: var(--color-light);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid var(--color-accent);
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .alert-custom {
            background: linear-gradient(45deg, var(--color-light), var(--color-primary));
            border: 1px solid var(--color-secondary);
            color: var(--color-accent);
            border-radius: 10px;
        }

        .form-select, .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 10px 15px;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--color-secondary);
            box-shadow: 0 0 0 0.2rem rgba(99, 163, 241, 0.25);
        }

        .section-title {
            color: var(--color-accent);
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .grade-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .summary-item {
            background: var(--color-white);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .summary-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--color-accent);
        }

        .summary-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="fas fa-edit me-2"></i>
                <?php echo $header_title_text; // Dinamis berdasarkan pilihan ?>
            </span>
            <div class="d-flex align-items-center text-white">
                <i class="fas fa-user-tie me-2"></i>
                Selamat datang, <?php echo $dosen_name_for_header; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="class-selector">
            <h4 class="section-title">
                <i class="fas fa-users me-2"></i>
                Pilih Kelas
            </h4>
            <div class="row">
                <div class="col-md-6">
                    <form id="classSelectForm" method="GET" action="input-nilai-dosen.php">
                        <select class="form-select" id="classSelect" name="course_id" onchange="this.form.submit()">
                            <option value="">Pilih Kelas...</option>
                            <?php foreach ($courses_for_dropdown as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['course_id']); ?>" <?php echo ($current_course_id_selected == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-custom mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Pilih kelas untuk mulai input nilai mahasiswa
                    </div>
                </div>
            </div>
        </div>

        <div class="assignment-selector" id="assignmentSelector" style="display: <?php echo $current_course_id_selected ? 'block' : 'none'; ?>;">
            <h4 class="section-title">
                <i class="fas fa-tasks me-2"></i>
                Pilih Tugas
            </h4>
            <div class="row">
                <div class="col-md-8">
                    <form id="assignmentSelectForm" method="GET" action="input-nilai-dosen.php">
                        <input type="hidden" name="course_id" id="hiddenCourseId" value="<?php echo htmlspecialchars($current_course_id_selected ?? ''); ?>">
                        <select class="form-select" id="assignmentSelect" name="assignment_id" onchange="this.form.submit()">
                            <option value="">Pilih Tugas...</option>
                            <?php foreach ($assignments_for_dropdown as $assignment): ?>
                                <option value="<?php echo htmlspecialchars($assignment['assignment_id']); ?>" <?php echo ($current_assignment_id_selected == $assignment['assignment_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-4">
                    <a href="input-nilai-dosen.php?course_id=<?php echo htmlspecialchars($current_course_id_selected ?? ''); ?>&view_all=true" class="btn btn-custom w-100" id="viewAllGradesLink">
                        <i class="fas fa-table me-2"></i>
                        Lihat Semua Nilai
                    </a>
                </div>
            </div>
        </div>

        <div class="grade-table-container" id="gradeContainer" style="display: <?php echo ($current_course_id_selected && ($current_assignment_id_selected || $view_mode === 'all_assignments')) ? 'block' : 'none'; ?>;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="section-title mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    <span id="currentTitle"><?php echo htmlspecialchars($header_title_text); ?></span>
                </h4>
                <div>
                    <?php if ($view_mode === 'single_assignment'): // Tombol simpan hanya untuk single assignment ?>
                        <button class="btn btn-upload me-2" type="submit" form="gradeInputForm">
                            <i class="fas fa-save me-2"></i>
                            Simpan Nilai
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-custom" onclick="exportGrades()">
                        <i class="fas fa-download me-2"></i>
                        Export Excel
                    </button>
                </div>
            </div>

            <div id="statisticsSection">
                <?php if ($view_mode === 'single_assignment' && $current_assignment_id_selected): ?>
                    <div class="statistics-card">
                        <h5 style="color: var(--color-accent); margin-bottom: 15px;">
                            <i class="fas fa-chart-bar me-2"></i>Statistik Penilaian
                        </h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-row">
                                    <span>Sudah Dinilai:</span>
                                    <strong><?php echo $statistics_data['total_graded_students']; ?>/<?php echo $statistics_data['total_students_in_class']; ?> (<?php echo $statistics_data['total_students_in_class'] > 0 ? round(($statistics_data['total_graded_students'] / $statistics_data['total_students_in_class']) * 100, 1) : 0; ?>%)</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-row">
                                    <span>Rata-rata:</span>
                                    <strong><?php echo $statistics_data['average_grade']; ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-row">
                                    <span>Distribusi:</span>
                                    <span>A:<?php echo $statistics_data['grade_distribution']['A']; ?> B:<?php echo $statistics_data['grade_distribution']['B']; ?> C:<?php echo $statistics_data['grade_distribution']['C']; ?> D:<?php echo $statistics_data['grade_distribution']['D']; ?> E:<?php echo $statistics_data['grade_distribution']['E']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <form id="gradeInputForm" method="POST" action="input-nilai-dosen.php">
                    <input type="hidden" name="current_course_id" id="formCurrentCourseId" value="<?php echo htmlspecialchars($current_course_id_selected ?? ''); ?>">
                    <input type="hidden" name="current_assignment_id" id="formCurrentAssignmentId" value="<?php echo htmlspecialchars($current_assignment_id_selected ?? ''); ?>">
                    <input type="hidden" name="view_mode" id="formViewMode" value="<?php echo htmlspecialchars($view_mode); ?>">
                    <table class="table" id="gradeTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 25%;">Nama Mahasiswa</th>
                                <?php if ($view_mode === 'single_assignment'): ?>
                                    <th style="width: 15%;">Status</th>
                                    <th style="width: 15%;">Nilai</th>
                                    <th style="width: 15%;">Grade</th>
                                    <th style="width: 25%;">Keterangan</th>
                                <?php elseif ($view_mode === 'all_assignments' && !empty($assignments_for_dropdown)): ?>
                                    <?php foreach ($assignments_for_dropdown as $assignment_header): ?>
                                        <th><?php echo htmlspecialchars($assignment_header['title']); ?></th>
                                    <?php endforeach; ?>
                                    <th>Rata-rata</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="gradeTableBody">
                            <?php if (empty($students_grades_data)): ?>
                                <tr><td colspan="<?php echo ($view_mode === 'single_assignment') ? 6 : (count($assignments_for_dropdown) + 3); ?>" class="text-muted text-center py-3">Pilih kelas dan tugas untuk menampilkan nilai, atau belum ada mahasiswa di kelas ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($students_grades_data as $index => $student): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td class="student-name">
                                            <input type="hidden" name="student_id[]" value="<?php echo htmlspecialchars($student['user_id']); ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                        </td>
                                        <?php if ($view_mode === 'single_assignment'): ?>
                                            <td><span class="status-badge <?php echo htmlspecialchars($student['submission_status_class']); ?>"><?php echo htmlspecialchars($student['submission_status_text']); ?></span></td>
                                            <td>
                                                <input type="number"
                                                       class="form-control grade-input"
                                                       name="grade_value[]"
                                                       min="0" max="100"
                                                       value="<?php echo htmlspecialchars($student['grade_value'] ?? ''); ?>"
                                                       oninput="updateGradeColor(this)">
                                            </td>
                                            <td class="grade-letter-display"><?php echo htmlspecialchars($student['grade_letter']); ?></td>
                                            <td>
                                                <input type="text"
                                                       class="form-control"
                                                       name="notes[]"
                                                       placeholder="Keterangan..."
                                                       value="<?php echo htmlspecialchars($student['notes'] ?? ''); ?>">
                                            </td>
                                        <?php elseif ($view_mode === 'all_assignments'): ?>
                                            <?php foreach ($assignments_for_dropdown as $assignment_col): ?>
                                                <?php
                                                    $found_grade = null;
                                                    foreach($student['assignments_grades'] as $std_assign_grade) {
                                                        if ($std_assign_grade['assignment_id'] == $assignment_col['assignment_id']) {
                                                            $found_grade = $std_assign_grade;
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                <td><?php echo htmlspecialchars($found_grade['grade_value'] ?? '-'); ?></td>
                                            <?php endforeach; ?>
                                            <td><strong><?php echo htmlspecialchars($student['average_score'] ?? '-'); ?></strong></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>

            <div class="grade-summary" id="gradeSummary" style="display: <?php echo ($view_mode === 'single_assignment' && $current_assignment_id_selected) ? 'grid' : 'none'; ?>;">
                <?php if ($view_mode === 'single_assignment' && $current_assignment_id_selected): ?>
                    <div class="summary-item">
                        <div class="summary-number"><?php echo $statistics_data['total_graded_students']; ?></div>
                        <div class="summary-label">Sudah Dinilai</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-number"><?php echo $statistics_data['total_students_in_class'] - $statistics_data['total_graded_students']; ?></div>
                        <div class="summary-label">Belum Dinilai</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-number"><?php echo $statistics_data['average_grade']; ?></div>
                        <div class="summary-label">Rata-rata</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-number"><?php echo $statistics_data['grade_distribution']['A']; ?></div>
                        <div class="summary-label">Grade A</div>
                    </div>
                     <div class="summary-item">
                        <div class="summary-number"><?php echo $statistics_data['grade_distribution']['B']; ?></div>
                        <div class="summary-label">Grade B</div>
                    </div>
                     <div class="summary-item">
                        <div class="summary-number"><?php echo $statistics_data['grade_distribution']['C']; ?></div>
                        <div class="summary-label">Grade C</div>
                    </div>
                     <div class="summary-item">
                        <div class="summary-number"><?php echo $statistics_data['grade_distribution']['D']; ?></div>
                        <div class="summary-label">Grade D</div>
                    </div>
                     <div class="summary-item">
                        <div class="summary-number"><?php echo $statistics_data['grade_distribution']['E']; ?></div>
                        <div class="summary-label">Grade E</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // JS ini hanya untuk menangani interaksi UI di sisi klien, data utama diisi oleh PHP
        
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi warna input nilai saat halaman dimuat
            initGradeInputColors();

            // Memperbarui link "Lihat Semua Nilai" berdasarkan kelas yang dipilih
            const classSelect = document.getElementById('classSelect');
            const viewAllGradesLink = document.getElementById('viewAllGradesLink');
            if (classSelect.value) {
                viewAllGradesLink.href = `input-nilai-dosen.php?course_id=${classSelect.value}&view_all=true`;
                viewAllGradesLink.classList.remove('disabled');
            } else {
                viewAllGradesLink.href = '#';
                viewAllGradesLink.classList.add('disabled');
            }
        });

        // Helper untuk mendapatkan grade letter dari nilai (untuk feedback langsung di UI)
        function getGradeLetterFromValue(gradeValue) {
            const value = parseFloat(gradeValue);
            if (isNaN(value)) return '-';
            if (value >= 85) return 'A';
            if (value >= 75) return 'B';
            if (value >= 65) return 'C';
            if (value >= 55) return 'D';
            return 'E';
        }

        // Menerapkan warna pada input nilai dan memperbarui tampilan grade letter
        function updateGradeColor(inputElement) {
            const value = parseFloat(inputElement.value);
            inputElement.classList.remove('excellent', 'good', 'average', 'poor');
            
            const gradeLetterDisplay = inputElement.closest('tr').querySelector('.grade-letter-display');

            if (isNaN(value) || value < 0 || value > 100) {
                gradeLetterDisplay.textContent = '-';
                return;
            }

            if (value >= 85) {
                inputElement.classList.add('excellent');
            } else if (value >= 75) {
                inputElement.classList.add('good');
            } else if (value >= 65) {
                inputElement.classList.add('average');
            } else { // 0-64
                inputElement.classList.add('poor');
            }
            gradeLetterDisplay.textContent = getGradeLetterFromValue(value);
        }
        
        // Inisialisasi warna untuk semua input nilai yang ada di halaman saat dimuat
        function initGradeInputColors() {
            document.querySelectorAll('.grade-input').forEach(input => {
                updateGradeColor(input); // Panggil sekali saat load untuk nilai awal
                input.addEventListener('input', () => updateGradeColor(input)); // Tambahkan listener untuk perubahan
            });
        }

        // Fungsi untuk meng-handle export Excel (simulasi)
        function exportGrades() {
            alert('Simulasi: File Excel berhasil didownload! (Fitur export nyata memerlukan implementasi sisi server)');
            // Untuk implementasi nyata, Anda akan mengarahkan ke script PHP yang membuat dan mengirim file Excel.
            // Contoh: window.location.href = `export_excel.php?course_id=<?php // echo htmlspecialchars($current_course_id_selected ?? ''); ?>&assignment_id=<?php // echo htmlspecialchars($current_assignment_id_selected ?? ''); ?>&view_mode=<?php // echo htmlspecialchars($view_mode); ?>`;
        }
    </script>
</body>
</html>