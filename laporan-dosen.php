<?php
// laporan-dosen.php
error_reporting(E_ALL); // Tampilkan semua error PHP
ini_set('display_errors', 1); // Aktifkan tampilan error

include 'db_connection.php'; // Meng-include file koneksi database Anda

// Inisialisasi variabel filter dari GET request
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$selected_semester = isset($_GET['semester']) ? $_GET['semester'] : 'genap-2023'; // Default semester "Genap 2023/2024"
$selected_class_name = isset($_GET['class_name']) ? $_GET['class_name'] : null; // Ini adalah course_code
$academic_year = '2024/2025'; // Tahun Akademik default, atau bisa diambil dinamis jika ada di database

// Data yang akan dirender ke HTML
$courses_for_filter_dropdown = [];
$class_codes_for_dropdown = []; // Untuk dropdown 'Kelas'
$students_final_grades = []; // Data nilai akhir siswa untuk tabel
$summary_stats = [
    'total_mahasiswa' => 0,
    'sudah_dinilai' => 0,
    'rata_rata_ip' => '0.00'
];
$grade_distribution_php = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];

$dosen_name_for_header = "Dosen Pengajar";

$current_lecturer_id = 2; // Sesuaikan dengan ID dosen yang relevan

// --- Fungsi Helper PHP ---
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

// --- Fetch Dosen Name for Header ---
$sql_lecturer_header = "SELECT full_name, gelar FROM users WHERE user_id = ? AND role = 'lecturer'";
$stmt_lecturer_header = $conn->prepare($sql_lecturer_header);
if ($stmt_lecturer_header) {
    $stmt_lecturer_header->bind_param("i", $current_lecturer_id);
    $stmt_lecturer_header->execute();
    $result_lecturer_header = $stmt_lecturer_header->get_result();
    if ($row_lecturer = $result_lecturer_header->fetch_assoc()) {
        $dosen_name_for_header = htmlspecialchars($row_lecturer['full_name'] . ', ' . ($row_lecturer['gelar'] ?? ''));
    }
    $stmt_lecturer_header->close();
} else {
    error_log("Error preparing lecturer query: " . $conn->error);
}

// --- Fetch Courses for Dropdown (Mata Kuliah) ---
$sql_courses = "SELECT course_id, course_name, course_code, credits FROM courses WHERE lecturer_id = ? ORDER BY course_name ASC";
$stmt_courses = $conn->prepare($sql_courses);
if ($stmt_courses) {
    $stmt_courses->bind_param("i", $current_lecturer_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($row = $result_courses->fetch_assoc()) {
        $courses_for_filter_dropdown[] = $row;
    }
    $stmt_courses->close();
} else {
    error_log("Error preparing courses query: " . $conn->error);
}

// --- Fetch Class Codes for Dropdown (Kelas) ---
if ($selected_course_id) {
    $sql_class_codes = "SELECT course_code FROM courses WHERE course_id = ? AND lecturer_id = ?";
    $stmt_class_codes = $conn->prepare($sql_class_codes);
    if ($stmt_class_codes) {
        $stmt_class_codes->bind_param("ii", $selected_course_id, $current_lecturer_id);
        $stmt_class_codes->execute();
        $result_class_codes = $stmt_class_codes->get_result();
        if ($row = $result_class_codes->fetch_assoc()) {
            $class_codes_for_dropdown[] = $row['course_code'];
        }
        $stmt_class_codes->close();
    }
} else {
    foreach ($courses_for_filter_dropdown as $course) {
        $class_codes_for_dropdown[] = $course['course_code'];
    }
}
$class_codes_for_dropdown = array_unique($class_codes_for_dropdown);
sort($class_codes_for_dropdown);

// --- Fetch Data Laporan (jika mata kuliah dipilih) ---
if ($selected_course_id) {
    $current_course_sks = 0;
    foreach ($courses_for_filter_dropdown as $course) {
        if ($course['course_id'] == $selected_course_id) {
            $current_course_sks = $course['credits'];
            break;
        }
    }

    $sql_students_in_course = "
        SELECT u.user_id, u.full_name, u.nim, u.study_program
        FROM users u
        JOIN course_enrollments ce ON u.user_id = ce.student_id
        WHERE ce.course_id = ? AND u.role = 'student'
        ORDER BY u.full_name ASC;
    ";
    $stmt_students = $conn->prepare($sql_students_in_course);
    if ($stmt_students) {
        $stmt_students->bind_param("i", $selected_course_id);
        $stmt_students->execute();
        $result_students = $stmt_students->get_result();

        $all_students_for_report = [];
        while ($student_row = $result_students->fetch_assoc()) {
            $all_students_for_report[] = $student_row;
        }
        $stmt_students->close();
    } else {
        error_log("Error preparing students in course query: " . $conn->error);
    }


    // Calculate grades for each student
    foreach ($all_students_for_report as $student) {
        $student_id = $student['user_id'];
        $final_score = 0;
        $grade_letter = 'E';
        $ips = 0.00;
        $ipk = 0.00;

        // Inisialisasi nilai komponen dengan null
        $component_grades_values = [
            'tugas' => null,
            'uts' => null,
            'uas' => null,
            'partisipasi' => null
        ];

        // Fetch values from `grades` table if exist
        $sql_existing_grades = "SELECT grade_value, grade_type FROM grades WHERE student_id = ? AND course_id = ? AND (grade_type = 'Assignment' OR grade_type = 'UTS' OR grade_type = 'UAS' OR grade_type = 'Partisipasi')";
        $stmt_existing_grades = $conn->prepare($sql_existing_grades);
        if ($stmt_existing_grades) {
            $stmt_existing_grades->bind_param("ii", $student_id, $selected_course_id);
            $stmt_existing_grades->execute();
            $result_existing_grades = $stmt_existing_grades->get_result();
            while($row = $result_existing_grades->fetch_assoc()){
                if(strtolower($row['grade_type']) == 'assignment') $component_grades_values['tugas'] = $row['grade_value'];
                if(strtolower($row['grade_type']) == 'uts') $component_grades_values['uts'] = $row['grade_value'];
                if(strtolower($row['grade_type']) == 'uas') $component_grades_values['uas'] = $row['grade_value'];
                if(strtolower($row['grade_type']) == 'partisipasi') $component_grades_values['partisipasi'] = $row['grade_value'];
            }
            $stmt_existing_grades->close();
        }

        // Fetch UTS/UAS from exam_attempts if not in `grades` table
        if ($component_grades_values['uts'] === null) {
            $sql_uts_score = "SELECT eat.score FROM exam_attempts eat JOIN exams e ON eat.exam_id = e.exam_id WHERE eat.student_id = ? AND e.course_id = ? AND e.exam_type = 'UTS' ORDER BY eat.end_time DESC LIMIT 1";
            $stmt_uts_score = $conn->prepare($sql_uts_score);
            if ($stmt_uts_score) {
                $stmt_uts_score->bind_param("ii", $student_id, $selected_course_id);
                $stmt_uts_score->execute();
                $result_uts_score = $stmt_uts_score->get_result();
                if ($row = $result_uts_score->fetch_assoc()) {
                    $component_grades_values['uts'] = $row['score'];
                }
                $stmt_uts_score->close();
            }
        }
        if ($component_grades_values['uas'] === null) {
            $sql_uas_score = "SELECT eat.score FROM exam_attempts eat JOIN exams e ON eat.exam_id = e.exam_id WHERE eat.student_id = ? AND e.course_id = ? AND e.exam_type = 'UAS' ORDER BY eat.end_time DESC LIMIT 1";
            $stmt_uas_score = $conn->prepare($sql_uas_score);
            if ($stmt_uas_score) {
                $stmt_uas_score->bind_param("ii", $student_id, $selected_course_id);
                $stmt_uas_score->execute();
                $result_uas_score = $stmt_uas_score->get_result();
                if ($row = $result_uas_score->fetch_assoc()) {
                    // PERBAIKAN TYPO: '$row['row_value']' menjadi '$row['grade_value']'
                    $component_grades_values['uas'] = $row['score']; 
                }
                $stmt_uas_score->close();
            }
        }

        // Assign default/simulated values if still null after DB fetch
        $component_grades_values['tugas'] = $component_grades_values['tugas'] ?? rand(70,90);
        $component_grades_values['uts'] = $component_grades_values['uts'] ?? rand(65,85);
        $component_grades_values['uas'] = $component_grades_values['uas'] ?? rand(70,90);
        $component_grades_values['partisipasi'] = $component_grades_values['partisipasi'] ?? rand(75,95);


        // Hitung nilai akhir berdasarkan nilai komponen yang sudah terisi (dari DB atau simulasi)
        $final_score =
            ($component_grades_values['tugas'] * 0.20) +
            ($component_grades_values['uts'] * 0.30) +
            ($component_grades_values['uas'] * 0.40) +
            ($component_grades_values['partisipasi'] * 0.10);

        $grade_letter = getGradeLetterPHP($final_score);
        $grade_points = getGradePointsPHP($grade_letter);

        $ips = $grade_points;
        $ipk = (rand(200, 390) / 100);

        $students_final_grades[] = [
            'user_id' => $student_id,
            'nim' => $student['nim'],
            'full_name' => $student['full_name'],
            'tugas' => round($component_grades_values['tugas']),
            'uts' => round($component_grades_values['uts']),
            'uas' => round($component_grades_values['uas']),
            'partisipasi' => round($component_grades_values['partisipasi']),
            'nilai_akhir' => round($final_score, 1),
            'grade_letter' => $grade_letter,
            'ips' => number_format($ips, 2),
            'ipk' => number_format($ipk, 2)
        ];

        if ($final_score > 0) {
            $summary_stats['sudah_dinilai']++;
            $grade_distribution_php[$grade_letter]++;
        }
        $summary_stats['total_mahasiswa']++;
        $summary_stats['rata_rata_ip'] += $ips;
    }

    if ($summary_stats['total_mahasiswa'] > 0) {
        $summary_stats['rata_rata_ip'] = number_format($summary_stats['rata_rata_ip'] / $summary_stats['total_mahasiswa'], 2);
    } else {
        $summary_stats['rata_rata_ip'] = number_format(0.00, 2);
    }
}

$conn->close();

$js_students_final_grades = json_encode($students_final_grades);
$js_summary_stats = json_encode($summary_stats);
$js_grade_distribution_php = json_encode($grade_distribution_php);

$js_selected_course_id = json_encode($selected_course_id);
$js_selected_semester = json_encode($selected_semester);
$js_selected_class_name = json_encode($selected_class_name);
$js_academic_year = json_encode($academic_year);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Semester - Sistem Akademik</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Definisi variabel warna */
        :root {
            --color-primary: #B6D0EF; /* Warna biru lebih cerah */
            --color-secondary: #63A3F1; /* Warna biru utama, lebih kuat */
            --color-accent: #FAFFEE; /* Biru muda sangat terang, untuk latar belakang ringan */
            --color-dark: #4F8A9E; /* Biru gelap untuk teks/elemen utama */
            --color-white: #FFFFFF;
            --color-success: #28a745;
            --color-info: #17a2b8;
            --color-warning: #ffc107;
            --color-danger: #dc3545;
        }

        /* Reset dan Box Sizing */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: #333;
        }

        .navbar {
            background: linear-gradient(90deg, var(--color-dark) 0%, var(--color-secondary) 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            color: var(--color-white) !important;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .navbar-text {
            color: var(--color-white) !important;
            font-weight: 500;
        }

        .main-container {
            padding: 2rem;
            max-width: 100%;
        }

        .header-section {
            background: var(--color-white);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-left: 5px solid var(--color-secondary);
        }

        .header-section h2 {
            color: var(--color-dark);
        }

        .header-section p {
            color: #666;
        }

        .semester-info {
            background: var(--color-accent);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid var(--color-primary);
        }

        .semester-info label {
            color: var(--color-dark);
        }

        .form-select, .form-control {
            border: 2px solid var(--color-primary);
            border-radius: 8px;
            padding: 0.5rem;
            text-align: left;
            font-weight: bold;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--color-secondary);
            box-shadow: 0 0 0 0.2rem rgba(99, 163, 241, 0.25);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(90deg, var(--color-secondary) 0%, var(--color-primary) 100%);
            color: var(--color-white);
            border: none;
            padding: 1.5rem;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--color-primary);
            color: var(--color-dark);
            border: none;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 1rem 0.5rem;
            font-size: 0.9rem;
        }

        .table tbody td {
            text-align: center;
            vertical-align: middle;
            padding: 0.8rem 0.5rem;
            border-color: var(--color-primary);
        }

        /* Styling for editable cells */
        .editable-grade {
            cursor: pointer;
            border: 1px dashed #ccc; /* Visual cue for editable */
            min-width: 50px;
        }
        .editable-grade:focus {
            outline: none;
            border: 1px solid var(--color-secondary);
            background-color: #e6f3ff;
        }
        .editable-grade.invalid {
            border: 2px solid red;
            background-color: #ffe0e0;
        }


        .table tbody tr:hover {
            background-color: var(--color-accent);
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--color-secondary) 0%, var(--color-dark) 100%);
            border: none;
            border-radius: 10px;
            padding: 0.8rem 2rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .btn-success {
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 10px;
            padding: 0.8rem 2rem;
            font-weight: bold;
        }
        .btn-info {
            background: linear-gradient(90deg, #17a2b8 0%, #17b3a8 100%);
            border: none;
            border-radius: 10px;
            padding: 0.8rem 2rem;
            font-weight: bold;
        }

        .grade-display {
            font-weight: bold;
            font-size: 1.1rem;
            padding: 0.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .grade-A { background: #d4edda; color: #155724; }
        .grade-B { background: #d1ecf1; color: #0c5460; }
        .grade-C { background: #fff3cd; color: #856404; }
        .grade-D { background: #f8d7da; color: #721c24; }
        .grade-E { background: #f5c6cb; color: #721c24; }

        .ip-display {
            background: var(--color-accent);
            border: 2px solid var(--color-primary);
            border-radius: 10px;
            padding: 1rem;
            margin: 0.5rem 0;
        }

        .ip-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--color-dark);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--color-white);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-left: 5px solid var(--color-secondary);
        }

        .summary-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--color-dark);
            margin-bottom: 0.5rem;
        }

        .summary-label {
            color: var(--color-secondary);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .table-responsive {
                font-size: 0.8rem;
            }

            .form-control {
                padding: 0.3rem;
                font-size: 0.8rem;
            }
        }

        .loading {
            display: none;
            text-align: center;
            margin: 2rem 0;
        }

        .spinner-border {
            color: var(--color-secondary);
        }

        .table-center th, .table-center td {
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                Sistem Akademik - Dosen Portal
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white">
                    <i class="fas fa-user me-2"></i>
                    <?php echo $dosen_name_for_header; ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="header-section">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <h2 class="mb-3">
                        <i class="fas fa-chart-line me-2" style="color: var(--color-secondary);"></i>
                        Laporan Nilai Semester
                    </h2>
                    <p class="mb-0 text-muted">Kelola dan input nilai mahasiswa untuk semester aktif</p>
                </div>
            </div>
        </div>

        <div class="semester-info">
            <form id="filterForm" method="GET" action="laporan-dosen.php">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Mata Kuliah:</label>
                        <select class="form-select" id="mataKuliah" name="course_id" onchange="this.form.submit()">
                            <option value="">Pilih Mata Kuliah...</option>
                            <?php
                            foreach ($courses_for_filter_dropdown as $course) {
                                $selected = ($course['course_id'] == $selected_course_id) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($course['course_id']) . '" ' . $selected . '>' . htmlspecialchars($course['course_name']) . ' (' . htmlspecialchars($course['course_code']) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Semester:</label>
                        <select class="form-select" id="semester" name="semester" onchange="this.form.submit()">
                            <option value="ganjil-2024" <?php echo ($selected_semester == 'ganjil-2024') ? 'selected' : ''; ?>>Ganjil 2024/2025</option>
                            <option value="genap-2023" <?php echo ($selected_semester == 'genap-2023') ? 'selected' : ''; ?>>Genap 2023/2024</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Kelas:</label>
                        <select class="form-select" id="kelas" name="class_name" onchange="this.form.submit()">
                            <option value="">Pilih Kelas...</option>
                            <?php
                            foreach ($class_codes_for_dropdown as $code) {
                                $selected = ($selected_class_name === $code) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($code) . '" ' . $selected . '>' . htmlspecialchars($code) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tahun Akademik:</label>
                        <input type="text" class="form-control" name="academic_year" value="<?php echo htmlspecialchars($academic_year); ?>" readonly>
                    </div>
                </div>
            </form>
        </div>

        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-number" id="totalMahasiswa"><?php echo $summary_stats['total_mahasiswa']; ?></div>
                <div class="summary-label">Total Mahasiswa</div>
            </div>
            <div class="summary-card">
                <div class="summary-number" id="sudahDinilai"><?php echo $summary_stats['sudah_dinilai']; ?></div>
                <div class="summary-label">Sudah Dinilai</div>
            </div>
            <div class="summary-card">
                <div class="summary-number" id="rataRataIP"><?php echo $summary_stats['rata_rata_ip']; ?></div>
                <div class="summary-label">Rata-rata IP</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="fas fa-table me-2"></i>
                    Daftar Nilai Mahasiswa
                </span>
                <div>
                    <button class="btn btn-primary btn-sm" onclick="calculateAllGrades()">
                        <i class="fas fa-calculator me-2"></i>Hitung Semua Nilai
                    </button>
                    <button class="btn btn-success btn-sm ms-2" onclick="saveAllGrades()">
                        <i class="fas fa-save me-2"></i>Simpan Semua
                    </button>
                    <button class="btn btn-info btn-sm ms-2" onclick="exportToExcel()">
                        <i class="fas fa-download me-2"></i>Export Excel
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="loading" id="loadingSpinner">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat data mahasiswa...</p>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-center" id="nilaiTable">
                        <thead>
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">NIM</th>
                                <th rowspan="2">Nama Mahasiswa</th>
                                <th colspan="4">Nilai Komponen</th>
                                <th rowspan="2">Nilai Akhir</th>
                                <th rowspan="2">Grade</th>
                                <th rowspan="2">IP Semester</th>
                                <th rowspan="2">IPK</th>
                            </tr>
                            <tr>
                                <th>Tugas<br>(20%)</th>
                                <th>UTS<br>(30%)</th>
                                <th>UAS<br>(40%)</th>
                                <th>Partisipasi<br>(10%)</th>
                            </tr>
                        </thead>
                        <tbody id="mahasiswaTableBody">
                            <?php if ($selected_course_id === null): ?>
                                <tr><td colspan="11" class="text-muted text-center py-3">Pilih mata kuliah untuk menampilkan laporan nilai.</td></tr>
                            <?php elseif (empty($students_final_grades)): ?>
                                <tr><td colspan="11" class="text-muted text-center py-3">Tidak ada data nilai untuk mata kuliah ini.</td></tr>
                            <?php else: ?>
                                <?php $row_number = 1; ?>
                                <?php foreach ($students_final_grades as $student): ?>
                                    <tr data-user-id="<?php echo htmlspecialchars($student['user_id']); ?>">
                                        <td><?php echo $row_number++; ?></td>
                                        <td><?php echo htmlspecialchars($student['nim']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td class="editable-grade" data-grade-type="tugas" contenteditable="true"><?php echo htmlspecialchars($student['tugas']); ?></td>
                                        <td class="editable-grade" data-grade-type="uts" contenteditable="true"><?php echo htmlspecialchars($student['uts']); ?></td>
                                        <td class="editable-grade" data-grade-type="uas" contenteditable="true"><?php echo htmlspecialchars($student['uas']); ?></td>
                                        <td class="editable-grade" data-grade-type="partisipasi" contenteditable="true"><?php echo htmlspecialchars($student['partisipasi']); ?></td>
                                        <td class="final-score"><?php echo htmlspecialchars($student['nilai_akhir']); ?></td>
                                        <td class="grade-display grade-letter grade-<?php echo htmlspecialchars($student['grade_letter']); ?>"><?php echo htmlspecialchars($student['grade_letter']); ?></td>
                                        <td class="ips"><?php echo htmlspecialchars($student['ips']); ?></td>
                                        <td class="ipk"><?php echo htmlspecialchars($student['ipk']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i>Distribusi Grade
                    </div>
                    <div class="card-body">
                        <div id="gradeDistribution">
                            <?php if ($selected_course_id === null || empty($students_final_grades)): ?>
                                <p class="text-muted text-center">Data distribusi belum tersedia.</p>
                            <?php else: ?>
                                <?php
                                $total_grades_for_distribution = array_sum($grade_distribution_php);
                                $grades_order = ['A', 'B', 'C', 'D', 'E'];
                                if ($total_grades_for_distribution > 0):
                                ?>
                                    <div class="d-flex flex-column">
                                        <?php foreach ($grades_order as $grade_letter): ?>
                                            <?php
                                            $count = $grade_distribution_php[$grade_letter] ?? 0;
                                            $percentage = ($total_grades_for_distribution > 0) ? ($count / $total_grades_for_distribution * 100) : 0;
                                            ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="me-2" style="width: 30px; font-weight: bold;"><?php echo htmlspecialchars($grade_letter); ?>:</div>
                                                <div class="progress flex-grow-1" style="height: 25px;">
                                                    <div class="progress-bar grade-<?php echo htmlspecialchars($grade_letter); ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $count; ?> (<?php echo number_format($percentage, 1); ?>%)
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">Data distribusi belum tersedia.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>Keterangan Penilaian
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <strong>Grade A:</strong> 85-100 (4.00)<br>
                                <strong>Grade B:</strong> 70-84 (3.00-3.99)<br>
                                <strong>Grade C:</strong> 55-69 (2.00-2.99)
                            </div>
                            <div class="col-6">
                                <strong>Grade D:</strong> 40-54 (1.00-1.99)<br>
                                <strong>Grade E:</strong> 0-39 (0.00)<br>
                                <strong>SKS:</strong> Sesuai mata kuliah
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data yang di-pass dari PHP
        const PHP_STUDENTS_FINAL_GRADES = <?php echo $js_students_final_grades; ?>;
        const PHP_SUMMARY_STATS = <?php echo $js_summary_stats; ?>;
        const PHP_GRADE_DISTRIBUTION_DATA = <?php echo $js_grade_distribution_php; ?>;
        const SELECTED_COURSE_ID = <?php echo $js_selected_course_id; ?>;
        const SELECTED_SEMESTER = <?php echo $js_selected_semester; ?>;
        const SELECTED_CLASS_NAME = <?php echo $js_selected_class_name; ?>;
        const ACADEMIC_YEAR = <?php echo $js_academic_year; ?>;

        // Grade calculation function (client-side, for "Hitung Semua Nilai")
        function getGradeLetterJS(score) {
            if (score >= 85) return 'A';
            if (score >= 70) return 'B';
            if (score >= 55) return 'C';
            if (score >= 40) return 'D';
            return 'E';
        }

        function getGradePointsJS(gradeLetter) {
            switch (gradeLetter) {
                case 'A': return 4.00;
                case 'B': return 3.00;
                case 'C': return 2.00;
                case 'D': return 1.00;
                case 'E': return 0.00;
                default: return 0.00;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Set dropdown values on load
            document.getElementById('mataKuliah').value = SELECTED_COURSE_ID;
            document.getElementById('semester').value = SELECTED_SEMESTER;
            document.getElementById('kelas').value = SELECTED_CLASS_NAME;
            document.querySelector('input[name="academic_year"]').value = ACADEMIC_YEAR;

            // Add event listeners for editable cells
            const editableCells = document.querySelectorAll('.editable-grade');
            editableCells.forEach(cell => {
                cell.addEventListener('input', function() {
                    const value = parseFloat(this.textContent);
                    if (isNaN(value) || value < 0 || value > 100) {
                        this.classList.add('invalid');
                    } else {
                        this.classList.remove('invalid');
                    }
                });
                // Optional: Trigger recalculation on blur if you want real-time update
                // cell.addEventListener('blur', calculateAllGrades);
            });
            
            // Initial render of summary and distribution if data is loaded by PHP
            updateSummaryCards();
            renderGradeDistributionChartJS(PHP_GRADE_DISTRIBUTION_DATA); // Use JS rendering for distribution

            hideLoadingSpinner(); // Sembunyikan spinner setelah semua data dimuat
        });

        function calculateAllGrades() {
            const tableBody = document.getElementById('mahasiswaTableBody');
            const rows = tableBody.querySelectorAll('tr[data-user-id]');
            const newGradeDistribution = {'A': 0, 'B': 0, 'C': 0, 'D': 0, 'E': 0};
            let totalIPSum = 0;
            let studentsGradedCount = 0;
            let calculationStopped = false; // Flag to stop further calculation if invalid input found

            rows.forEach(row => {
                if (calculationStopped) return; // Stop processing further rows

                const userId = row.dataset.userId;
                const tugasCell = row.querySelector('[data-grade-type="tugas"]');
                const utsCell = row.querySelector('[data-grade-type="uts"]');
                const uasCell = row.querySelector('[data-grade-type="uas"]');
                const partisipasiCell = row.querySelector('[data-grade-type="partisipasi"]');
                const finalScoreCell = row.querySelector('.final-score');
                const gradeLetterCell = row.querySelector('.grade-letter');
                const ipsCell = row.querySelector('.ips');

                const tugas = parseFloat(tugasCell.textContent);
                const uts = parseFloat(utsCell.textContent);
                const uas = parseFloat(uasCell.textContent);
                const partisipasi = parseFloat(partisipasiCell.textContent);

                // Validate inputs for current row
                if (isNaN(tugas) || tugas < 0 || tugas > 100 ||
                    isNaN(uts) || uts < 0 || uts > 100 ||
                    isNaN(uas) || uas < 0 || uas > 100 ||
                    isNaN(partisipasi) || partisipasi < 0 || partisipasi > 100) {
                    alert('Terdapat nilai yang tidak valid (bukan angka atau di luar 0-100) pada mahasiswa ' + row.querySelector('td:nth-child(3)').textContent + '. Mohon perbaiki.');
                    calculationStopped = true; // Set flag to stop
                    return; // Skip current row and stop iteration
                } else {
                     tugasCell.classList.remove('invalid');
                     utsCell.classList.remove('invalid');
                     uasCell.classList.remove('invalid');
                     partisipasiCell.classList.remove('invalid');
                }

                const finalScore = (tugas * 0.20) + (uts * 0.30) + (uas * 0.40) + (partisipasi * 0.10);
                const gradeLetter = getGradeLetterJS(finalScore);
                const gradePoints = getGradePointsJS(gradeLetter);
                const ips = gradePoints;

                // Update UI for current row
                finalScoreCell.textContent = finalScore.toFixed(1);
                gradeLetterCell.textContent = gradeLetter;
                gradeLetterCell.className = `grade-display grade-letter grade-${gradeLetter}`;
                ipsCell.textContent = ips.toFixed(2);

                // For summary stats and distribution
                if (finalScore > 0) {
                    studentsGradedCount++;
                    totalIPSum += ips;
                    newGradeDistribution[gradeLetter]++;
                }
            });

            if (calculationStopped) { // If an invalid input was found, do not update summaries/distribution
                return;
            }

            // Update summary cards
            const totalStudents = rows.length;
            const avgIP = totalStudents > 0 ? (totalIPSum / totalStudents).toFixed(2) : '0.00';
            document.getElementById('totalMahasiswa').textContent = totalStudents;
            document.getElementById('sudahDinilai').textContent = studentsGradedCount;
            document.getElementById('rataRataIP').textContent = avgIP;

            // Re-render grade distribution chart
            renderGradeDistributionChartJS(newGradeDistribution);

            alert('Nilai berhasil dihitung ulang di tampilan.');
        }

        function saveAllGrades() {
            const tableBody = document.getElementById('mahasiswaTableBody');
            const rows = tableBody.querySelectorAll('tr[data-user-id]');
            const gradesToSave = [];
            let hasInvalidInput = false;

            // First, re-calculate to ensure all values are fresh and valid
            // This also ensures 'invalid' classes are applied.
            const initialCalculationStopped = false; // Placeholder, as calculateAllGrades handles its own stopping.
            calculateAllGrades(); // Re-calculate before saving

            // Check if calculateAllGrades found any invalid inputs
            const cellsWithInvalidClass = document.querySelectorAll('.editable-grade.invalid');
            if (cellsWithInvalidClass.length > 0) {
                alert('Tidak dapat menyimpan. Terdapat nilai yang tidak valid (bukan angka atau di luar 0-100) yang ditandai merah. Mohon perbaiki.');
                return;
            }
            
            rows.forEach(row => {
                const userId = row.dataset.userId;
                const tugas = parseFloat(row.querySelector('[data-grade-type="tugas"]').textContent);
                const uts = parseFloat(row.querySelector('[data-grade-type="uts"]').textContent);
                const uas = parseFloat(row.querySelector('[data-grade-type="uas"]').textContent);
                const partisipasi = parseFloat(row.querySelector('[data-grade-type="partisipasi"]').textContent);

                gradesToSave.push({
                    user_id: userId,
                    tugas: tugas,
                    uts: uts,
                    uas: uas,
                    partisipasi: partisipasi
                });
            });

            if (SELECTED_COURSE_ID === null) {
                alert('Pilih mata kuliah terlebih dahulu untuk menyimpan nilai.');
                return;
            }

            showLoadingSpinner();

            fetch('save_grades.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    course_id: SELECTED_COURSE_ID,
                    grades: gradesToSave
                })
            })
            .then(response => {
                if (!response.ok) {
                    // Check if response is not OK (e.g., 404, 500)
                    return response.text().then(text => { throw new Error('HTTP error! Status: ' + response.status + ' Response: ' + text); });
                }
                return response.json();
            })
            .then(data => {
                hideLoadingSpinner();
                if (data.success) {
                    alert(data.message + '\nHalaman akan di-refresh untuk memuat nilai terbaru.');
                    window.location.reload();
                } else {
                    alert('Gagal menyimpan nilai: ' + data.message + (data.error_detail ? '\nDetail: ' + data.error_detail : ''));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoadingSpinner();
                alert('Terjadi kesalahan saat mengirim data ke server. Periksa konsol browser (F12) untuk detailnya.');
            });
        }

        function updateSummaryCards() {
            document.getElementById('totalMahasiswa').textContent = PHP_SUMMARY_STATS.total_mahasiswa;
            document.getElementById('sudahDinilai').textContent = PHP_SUMMARY_STATS.sudah_dinilai;
            document.getElementById('rataRataIP').textContent = PHP_SUMMARY_STATS.rata_rata_ip;
        }

        function renderGradeDistributionChartJS(distributionData) {
            const gradeDistributionDiv = document.getElementById('gradeDistribution');
            gradeDistributionDiv.innerHTML = ''; 

            const grades = ['A', 'B', 'C', 'D', 'E'];
            let hasData = false;
            for (const grade of grades) {
                if (distributionData[grade] > 0) {
                    hasData = true;
                    break;
                }
            }

            if (!hasData || Object.keys(distributionData).length === 0) {
                gradeDistributionDiv.innerHTML = '<p class="text-muted text-center">Data distribusi belum tersedia.</p>';
                return;
            }

            const totalGrades = Object.values(distributionData).reduce((sum, count) => sum + count, 0);

            if (totalGrades === 0) {
                gradeDistributionDiv.innerHTML = '<p class="text-muted text-center">Data distribusi belum tersedia.</p>';
                return;
            }

            let chartHtml = '<div class="d-flex flex-column">';
            grades.forEach(grade => {
                const count = distributionData[grade];
                const percentage = (totalGrades > 0) ? (count / totalGrades * 100).toFixed(1) : 0;
                const gradeClass = `grade-${grade}`;
                chartHtml += `
                    <div class="d-flex align-items-center mb-2">
                        <div class="me-2" style="width: 30px; font-weight: bold;">${grade}:</div>
                        <div class="progress flex-grow-1" style="height: 25px;">
                            <div class="progress-bar ${gradeClass}" role="progressbar" style="width: ${percentage}%;" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100">
                                ${count} (${percentage}%)
                            </div>
                        </div>
                    </div>
                `;
            });
            chartHtml += '</div>';
            gradeDistributionDiv.innerHTML = chartHtml;
        }
        
        if (SELECTED_COURSE_ID !== null && PHP_STUDENTS_FINAL_GRADES.length > 0) {
             renderGradeDistributionChartJS(PHP_GRADE_DISTRIBUTION_DATA);
        } else {
             document.getElementById('gradeDistribution').innerHTML = '<p class="text-muted text-center">Data distribusi belum tersedia.</p>';
        }


        function showLoadingSpinner() {
            document.getElementById('loadingSpinner').style.display = 'flex';
        }

        function hideLoadingSpinner() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }
        
        function exportToExcel() {
            const courseId = document.getElementById('mataKuliah').value;
            const semester = document.getElementById('semester').value;
            const className = document.getElementById('kelas').value;
            const academicYear = document.querySelector('input[name="academic_year"]').value;

            if (!courseId) {
                alert('Pilih mata kuliah terlebih dahulu untuk export data.');
                return;
            }

            window.location.href = `export_excel.php?course_id=${courseId}&semester=${semester}&class_name=${className}&academic_year=${academicYear}`;
        }
    </script>
</body>
</html>