<?php
include 'db_connection.php'; // Meng-include file koneksi database Anda

// Inisialisasi variabel
$current_exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : 'UTS'; // Default ke 'UTS'
$progress_data_for_display = []; // Data detail progres per kelas dan tugas
$overall_summary_data = [
    'total_students' => 0,
    'submitted_count' => 0,
    'not_submitted_count' => 0,
    'completion_rate' => 0
];
$lecturer_name_for_header = "Dosen"; // Nama default untuk header

// Asumsi lecturer_id 2 (Dr. Budi Santosa) untuk demo
// PENTING: Jika Anda menggunakan $_SESSION['user_id'] dari login, ganti ini:
// $current_lecturer_id = $_SESSION['user_id']; 
$current_lecturer_id = 2; // Menggunakan ID dosen hardcode untuk demo, sesuaikan jika perlu

// --- Fungsi Helper (PHP) ---
// Fungsi ini akan digunakan di sisi PHP (server) untuk memformat tanggal
function formatDateForDisplayPHP($dateString) {
    if (empty($dateString) || $dateString === '0000-00-00 00:00:00') {
        return '-'; // Handle null or zero date
    }
    return date('d F Y H:i', strtotime($dateString)); // Contoh format Indonesia
}

// --- Fetch Data ---

// 1. Ambil nama dosen untuk header
$sql_lecturer_name = "SELECT full_name, gelar FROM users WHERE user_id = ? AND role = 'lecturer'";
$stmt_lecturer_name = $conn->prepare($sql_lecturer_name);
if ($stmt_lecturer_name) {
    $stmt_lecturer_name->bind_param("i", $current_lecturer_id);
    $stmt_lecturer_name->execute();
    $result_lecturer_name = $stmt_lecturer_name->get_result();
    if ($row_lecturer = $result_lecturer_name->fetch_assoc()) {
        $lecturer_name_for_header = htmlspecialchars($row_lecturer['full_name'] . ', ' . ($row_lecturer['gelar'] ?? ''));
    }
    $stmt_lecturer_name->close();
}

// 2. Ambil semua mata kuliah yang diampu dosen ini dan ujian terkait
$courses_by_lecturer = [];
$exams_by_course = [];

$sql_courses_and_exams = "
    SELECT
        c.course_id,
        c.course_name,
        c.course_code,
        e.exam_id,
        e.title AS exam_title,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.online_link
    FROM
        courses c
    LEFT JOIN
        exams e ON c.course_id = e.course_id
    WHERE
        c.lecturer_id = ? AND e.exam_type = ?
    ORDER BY
        c.course_name ASC, e.exam_date ASC;
";
$stmt_courses_and_exams = $conn->prepare($sql_courses_and_exams);
if ($stmt_courses_and_exams) {
    $stmt_courses_and_exams->bind_param("is", $current_lecturer_id, $current_exam_type);
    $stmt_courses_and_exams->execute();
    $result_courses_and_exams = $stmt_courses_and_exams->get_result();

    while ($row = $result_courses_and_exams->fetch_assoc()) {
        if (!isset($courses_by_lecturer[$row['course_id']])) {
            $courses_by_lecturer[$row['course_id']] = [
                'course_id' => $row['course_id'],
                'course_name' => $row['course_name'],
                'course_code' => $row['course_code'],
                'students' => [], // Akan diisi nanti
                'submitted_count' => 0, // Per course
                'not_submitted_count' => 0, // Per course
                'exam_details' => $row // Detail ujian utama untuk course ini
            ];
            $exams_by_course[$row['course_id']] = $row['exam_id']; // Simpan exam_id per course
        }
    }
    $stmt_courses_and_exams->close();
}

// 3. Ambil semua mahasiswa yang terdaftar di mata kuliah yang diampu dosen ini
// Dan status pengumpulan ujian mereka
$all_students_in_lecturer_courses = [];
if (!empty($courses_by_lecturer)) {
    $course_ids_str = implode(',', array_keys($courses_by_lecturer));
    // Perbaikan: Pastikan join exam_attempts dengan exams untuk mencocokkan exam_id
    $sql_students_and_submissions = "
        SELECT
            u.user_id,
            u.full_name,
            u.nim,
            ce.course_id,
            ea.attempt_id,
            ea.end_time AS submission_time,
            ea.is_completed,
            e.exam_id,
            e.exam_type
        FROM
            users u
        JOIN
            course_enrollments ce ON u.user_id = ce.student_id
        LEFT JOIN
            exams e ON ce.course_id = e.course_id AND e.exam_type = ?
        LEFT JOIN
            exam_attempts ea ON u.user_id = ea.student_id AND e.exam_id = ea.exam_id
        WHERE
            u.role = 'student' AND ce.course_id IN ({$course_ids_str})
        ORDER BY
            ce.course_id, u.full_name;
    ";
    
    $stmt_students_and_submissions = $conn->prepare($sql_students_and_submissions);
    if ($stmt_students_and_submissions) {
        $stmt_students_and_submissions->bind_param("s", $current_exam_type);
        $stmt_students_and_submissions->execute();
        $result_students_and_submissions = $stmt_students_and_submissions->get_result();

        while ($row = $result_students_and_submissions->fetch_assoc()) {
            $course_id = $row['course_id'];
            if (!isset($all_students_in_lecturer_courses[$course_id])) {
                $all_students_in_lecturer_courses[$course_id] = [];
            }
            $all_students_in_lecturer_courses[$course_id][$row['user_id']] = $row;
        }
        $stmt_students_and_submissions->close();
    }
}

// 4. Kumpulkan data untuk tampilan
$total_all_students_global = 0;
$total_submitted_global = 0;
$total_not_submitted_global = 0;

foreach ($courses_by_lecturer as $course_id => $course_info) {
    $students_for_this_course = $all_students_in_lecturer_courses[$course_id] ?? [];
    $submitted_count_per_course = 0;
    $not_submitted_count_per_course = 0;

    $display_students_for_course_card = []; // Siswa yang akan ditampilkan di card
    
    foreach ($students_for_this_course as $student_id => $student_details) {
        $submission_status = [
            'submitted' => ($student_details['attempt_id'] !== null && $student_details['is_completed'] == 1),
            'submission_time' => $student_details['submission_time'],
            'file_type' => 'pdf' // Simulasikan tipe file, tidak ada di DB Anda
        ];

        if ($submission_status['submitted']) {
            $submitted_count_per_course++;
            $total_submitted_global++;
        } else {
            $not_submitted_count_per_course++;
            $total_not_submitted_global++;
        }
        
        $display_students_for_course_card[] = [
            'user_id' => $student_details['user_id'],
            'full_name' => $student_details['full_name'],
            'nim' => $student_details['nim'],
            'submission_status' => $submission_status
        ];
    }
    
    $total_all_students_global += count($students_for_this_course);

    $progress_data_for_display[] = [
        'course_id' => $course_id,
        'course_name' => $course_info['course_name'],
        'students' => $display_students_for_course_card, // Siswa dengan status submit/belum
        'submitted_count' => $submitted_count_per_course,
        'not_submitted_count' => $not_submitted_count_per_course
    ];
}

// 5. Hitung Ringkasan Statistik Global
$overall_summary_data['total_students'] = $total_all_students_global;
$overall_summary_data['submitted_count'] = $total_submitted_global;
$overall_summary_data['not_submitted_count'] = $total_not_submitted_global;
$overall_completion_percentage = 0;
if ($total_all_students_global > 0) {
    $overall_completion_percentage = round(($total_submitted_global / $total_all_students_global) * 100);
}
$overall_summary_data['completion_rate'] = $overall_completion_percentage;


$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Submission - Dosen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #B6D0EF;
            --secondary-blue: #63A3F1;
            --light-green: #FAFFEE;
            --dark-teal: #4F8A9E;
            --white: #FFFFFF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .dashboard-container {
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid var(--dark-teal);
        }

        .header h1 {
            color: var(--dark-teal);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 16px;
            margin: 0;
        }

        .exam-selector {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .exam-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .exam-tab {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            background: var(--light-green);
            color: var(--dark-teal);
            text-decoration: none; /* Add this for <a> tag */
        }

        .exam-tab.active {
            background: var(--dark-teal);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(79, 138, 158, 0.3);
        }

        .exam-tab:hover:not(.active) {
            background: var(--primary-blue);
            transform: translateY(-2px);
        }

        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .class-card {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border-top: 4px solid var(--secondary-blue);
        }

        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .class-header {
            background: linear-gradient(135deg, var(--secondary-blue), var(--primary-blue));
            color: var(--white);
            padding: 20px;
            text-align: center;
        }

        .class-header h3 {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .class-header .stats {
            font-size: 14px;
            opacity: 0.9;
        }

        .student-list {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 10px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .student-item:hover {
            background: var(--light-green);
            transform: translateX(5px);
        }

        .student-item.submitted {
            background: rgba(40, 167, 69, 0.1);
            border-left-color: #28a745;
        }

        .student-item.not-submitted {
            background: rgba(220, 53, 69, 0.1);
            border-left-color: #dc3545;
        }

        .student-info {
            display: flex;
            flex-direction: column;
        }

        .student-name {
            font-weight: 600;
            color: var(--dark-teal);
            margin-bottom: 2px;
        }

        .student-id {
            font-size: 12px;
            color: #666;
        }

        .submission-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge.submitted {
            background: #28a745;
            color: white;
        }

        .status-badge.not-submitted {
            background: #dc3545;
            color: white;
        }

        .download-btn {
            background: var(--dark-teal);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .download-btn:hover {
            background: var(--secondary-blue);
            transform: scale(1.05);
        }

        .summary-stats {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: var(--light-green);
            border-top: 3px solid var(--dark-teal);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-teal);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-weight: 600;
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--dark-teal);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: var(--secondary-blue);
            transform: scale(1.1);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 50px;
            color: var(--dark-teal);
        }

        .spinner {
            border: 4px solid var(--primary-blue);
            border-top: 4px solid var(--dark-teal);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .class-grid {
                grid-template-columns: 1fr;
            }
            
            .exam-tabs {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Dashboard Monitoring Submission</h1>
            <p>Pantau progress submission UTS dan UAS mahasiswa secara real-time</p>
        </div>

        <div class="exam-selector">
            <div class="exam-tabs">
                <a href="ujian-dosen.php?exam_type=UTS" class="exam-tab <?php echo ($current_exam_type === 'UTS') ? 'active' : ''; ?>" data-exam="UTS">
                    <i class="fas fa-clipboard-check"></i> UTS (Ujian Tengah Semester)
                </a>
                <a href="ujian-dosen.php?exam_type=UAS" class="exam-tab <?php echo ($current_exam_type === 'UAS') ? 'active' : ''; ?>" data-exam="UAS">
                    <i class="fas fa-file-alt"></i> UAS (Ujian Akhir Semester)
                </a>
            </div>
        </div>

        <div class="summary-stats">
            <h4 class="mb-4" style="color: var(--dark-teal);">
                <i class="fas fa-chart-bar"></i> Ringkasan Statistik <span id="current-exam-label"><?php echo htmlspecialchars($current_exam_type); ?></span>
            </h4>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" id="total-students"><?php echo $overall_summary_data['total_students']; ?></div>
                    <div class="stat-label">Total Mahasiswa</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="submitted-count"><?php echo $overall_summary_data['submitted_count']; ?></div>
                    <div class="stat-label">Sudah Submit</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="not-submitted-count"><?php echo $overall_summary_data['not_submitted_count']; ?></div>
                    <div class="stat-label">Belum Submit</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="completion-rate"><?php echo $overall_summary_data['completion_rate']; ?>%</div>
                    <div class="stat-label">Tingkat Penyelesaian</div>
                </div>
            </div>
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Memuat data submission...</p>
        </div>

        <div class="class-grid" id="class-grid">
            <?php if (empty($progress_data_for_display)): ?>
                <div class="col-12 text-center text-muted py-5">
                    <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                    <p>Tidak ada data ujian <?php echo htmlspecialchars($current_exam_type); ?> ditemukan.</p>
                </div>
            <?php else: ?>
                <?php foreach ($progress_data_for_display as $course): ?>
                    <div class="class-card">
                        <div class="class-header">
                            <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                            <div class="stats">
                                <?php echo $course['submitted_count']; ?>/<?php echo count($course['students']); ?> mahasiswa telah submit
                            </div>
                        </div>
                        <div class="student-list">
                            <?php foreach ($course['students'] as $student): ?>
                                <?php
                                    $statusClass = $student['submission_status']['submitted'] ? 'submitted' : 'not-submitted';
                                    $statusIcon = $student['submission_status']['submitted'] ? 'fa-check-circle' : 'fa-times-circle';
                                    $statusText = $student['submission_status']['submitted'] ? 'Submitted' : 'Belum Submit';
                                    $downloadBtnHtml = $student['submission_status']['submitted'] ? '
                                        <button class="download-btn" onclick="downloadFile(\'' . htmlspecialchars($student['full_name']) . '\', \'Ujian ' . htmlspecialchars($current_exam_type) . ' ' . htmlspecialchars($course['course_name']) . '\', \'' . htmlspecialchars($student['submission_status']['file_type'] ?? 'pdf') . '\')" title="Download Ujian ' . htmlspecialchars($current_exam_type) . '">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    ' : '';
                                    // Perbaikan di sini: Gunakan formatDateForDisplayPHP() (fungsi PHP)
                                    $submissionTimeHtml = $student['submission_status']['submitted'] && $student['submission_status']['submission_time'] ?
                                        '<div class="text-muted" style="font-size: 11px; margin-top: 2px;">' . formatDateForDisplayPHP($student['submission_status']['submission_time']) . '</div>' : '';
                                ?>
                                <div class="student-item <?php echo $statusClass; ?>">
                                    <div class="student-info">
                                        <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                        <div class="student-id"><?php echo htmlspecialchars($student['nim']); ?></div>
                                        <?php echo $submissionTimeHtml; ?>
                                    </div>
                                    <div class="submission-status">
                                        <div class="status-badge <?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $statusIcon; ?>"></i>
                                            <?php echo $statusText; ?>
                                        </div>
                                        <?php echo $downloadBtnHtml; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <a href="ujian-dosen.php?exam_type=<?php echo htmlspecialchars($current_exam_type); ?>" class="refresh-btn" title="Refresh Data">
            <i class="fas fa-sync-alt"></i>
        </a>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // JS ini hanya untuk visual dan interaksi UI, data sudah di-render oleh PHP
        
        // Simulasikan fungsi download (karena PHP tidak menangani download file fisik di sini)
        function downloadFile(studentName, examTitle, fileType) {
            alert(`Simulasi Download: File ujian ${examTitle} untuk ${studentName} (${fileType}) akan diunduh.`);
        }

        // Helper for htmlspecialchars (since JS doesn't have it natively, for consistency)
        function htmlspecialchars(str) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return str.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Helper to format date for display (used in JS, PHP already formats most dates)
        // Fungsi ini tidak lagi dipanggil dari PHP, jadi tidak akan menyebabkan error.
        // Jika Anda ingin menggunakan format tanggal yang sama di JS, Anda bisa panggil formatDateForDisplayPHP dari PHP
        // atau gunakan fungsi JS ini untuk data yang akan diolah di client-side.
        /*
        function formatDateForDisplay(dateString) {
            const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            return new Date(dateString).toLocaleDateString('id-ID', options);
        }
        */
    </script>
</body>
</html>