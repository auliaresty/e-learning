<?php
include 'db_connection.php'; // Meng-include file koneksi database Anda

// Inisialisasi variabel untuk data yang akan dirender
$progress_data_for_display = []; // Data detail progres per kelas dan tugas
$overall_summary_data = [
    'total_classes' => 0,
    'total_students' => 0,
    'total_assignments' => 0,
    'overall_completion_rate' => 0
];
$lecturer_name_for_header = "Dosen"; // Nama default untuk header

// Asumsi lecturer_id 2 (Dr. Budi Santosa) untuk demo
$current_lecturer_id = 2;

// --- Fungsi Helper ---
function formatDateForDisplay($dateString) {
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

// 2. Ambil semua mata kuliah yang diampu oleh dosen ini
$courses_by_lecturer = [];
$sql_courses_lecturer = "SELECT course_id, course_name, course_code, credits FROM courses WHERE lecturer_id = ? ORDER BY course_name ASC";
$stmt_courses_lecturer = $conn->prepare($sql_courses_lecturer);
if ($stmt_courses_lecturer) {
    $stmt_courses_lecturer->bind_param("i", $current_lecturer_id);
    $stmt_courses_lecturer->execute();
    $result_courses_lecturer = $stmt_courses_lecturer->get_result();
    while ($row = $result_courses_lecturer->fetch_assoc()) {
        $courses_by_lecturer[$row['course_id']] = $row;
    }
    $stmt_courses_lecturer->close();
}

$all_assignments_count = 0;
$total_possible_submissions = 0;
$total_actual_submissions = 0;

foreach ($courses_by_lecturer as $course_id => $course_info) {
    $course_data = [
        'course_id' => $course_id,
        'course_name' => $course_info['course_name'],
        'course_code' => $course_info['course_code'],
        'credits' => $course_info['credits'],
        'students' => [],
        'assignments' => [],
        'total_completed' => 0,
        'total_late' => 0,
        'total_not_submitted' => 0,
    ];

    // Ambil semua mahasiswa yang terdaftar di mata kuliah ini
    $students_in_course = [];
    $sql_students_in_course = "
        SELECT u.user_id, u.full_name, u.nim
        FROM users u
        JOIN course_enrollments ce ON u.user_id = ce.student_id
        WHERE ce.course_id = ? AND u.role = 'student'
        ORDER BY u.full_name ASC;
    ";
    $stmt_students_in_course = $conn->prepare($sql_students_in_course);
    if ($stmt_students_in_course) {
        $stmt_students_in_course->bind_param("i", $course_id);
        $stmt_students_in_course->execute();
        $result_students_in_course = $stmt_students_in_course->get_result();
        while ($row = $result_students_in_course->fetch_assoc()) {
            $students_in_course[$row['user_id']] = $row;
        }
        $stmt_students_in_course->close();
    }
    $course_data['students'] = array_values($students_in_course);

    // Ambil semua tugas untuk mata kuliah ini
    $assignments_in_course = [];
    $sql_assignments_in_course = "SELECT assignment_id, title, due_date FROM assignments WHERE course_id = ? ORDER BY due_date ASC";
    $stmt_assignments_in_course = $conn->prepare($sql_assignments_in_course);
    if ($stmt_assignments_in_course) {
        $stmt_assignments_in_course->bind_param("i", $course_id);
        $stmt_assignments_in_course->execute();
        $result_assignments_in_course = $stmt_assignments_in_course->get_result();
        while ($row = $result_assignments_in_course->fetch_assoc()) {
            $assignments_in_course[$row['assignment_id']] = $row;
        }
        $stmt_assignments_in_course->close();
    }
    
    // Proses status pengumpulan untuk setiap tugas
    foreach ($assignments_in_course as $assignment_id => $assignment_info) {
        $assignment_progress = [
            'assignment_id' => $assignment_id,
            'title' => $assignment_info['title'],
            'due_date' => $assignment_info['due_date'],
            'progress' => [ 'completed' => 0, 'late' => 0, 'not_submitted' => 0 ],
            'students_status' => []
        ];

        $all_assignments_count++; // Hitung total tugas
        $total_possible_submissions += count($students_in_course);

        foreach ($students_in_course as $student_id => $student_info) {
            $status = 'not-submitted';
            $submission_time = null;
            $submitted = false;

            // Cek apakah mahasiswa sudah submit tugas ini
            $sql_submission = "SELECT submitted_at FROM submissions WHERE assignment_id = ? AND student_id = ?";
            $stmt_submission = $conn->prepare($sql_submission);
            if ($stmt_submission) {
                $stmt_submission->bind_param("ii", $assignment_id, $student_id);
                $stmt_submission->execute();
                $result_submission = $stmt_submission->get_result();
                if ($row_submission = $result_submission->fetch_assoc()) {
                    $submitted = true;
                    $submission_time = $row_submission['submitted_at'];
                    $total_actual_submissions++;
                }
                $stmt_submission->close();
            }

            if ($submitted) {
                $due_datetime = strtotime($assignment_info['due_date']);
                $submitted_datetime = strtotime($submission_time);

                if ($submitted_datetime <= $due_datetime) {
                    $status = 'completed';
                    $assignment_progress['progress']['completed']++;
                    $course_data['total_completed']++;
                } else {
                    $status = 'late';
                    $assignment_progress['progress']['late']++;
                    $course_data['total_late']++;
                }
            } else {
                // Asumsi jika belum submit dan due date sudah lewat, ini tetap 'not-submitted'
                // atau bisa juga dibedakan menjadi 'due_date_passed' vs 'still_pending'
                $assignment_progress['progress']['not_submitted']++;
                $course_data['total_not_submitted']++;
            }

            $assignment_progress['students_status'][] = [
                'user_id' => $student_id,
                'full_name' => $student_info['full_name'],
                'nim' => $student_info['nim'],
                'status' => $status,
                'submission_time' => $submission_time
            ];
        }
        $course_data['assignments'][] = $assignment_progress;
    }
    $progress_data_for_display[] = $course_data;
}

// --- Calculate Overall Summary ---
$overall_summary_data['total_classes'] = count($courses_by_lecturer);
// Jumlah unik siswa yang diampu dosen ini di mata kuliah apapun
$sql_total_unique_students = "SELECT COUNT(DISTINCT ce.student_id) AS unique_students
                              FROM course_enrollments ce
                              JOIN courses c ON ce.course_id = c.course_id
                              WHERE c.lecturer_id = ?";
$stmt_total_unique_students = $conn->prepare($sql_total_unique_students);
if ($stmt_total_unique_students) {
    $stmt_total_unique_students->bind_param("i", $current_lecturer_id);
    $stmt_total_unique_students->execute();
    $result_unique_students = $stmt_total_unique_students->get_result();
    if ($row = $result_unique_students->fetch_assoc()) {
        $overall_summary_data['total_students'] = $row['unique_students'];
    }
    $stmt_total_unique_students->close();
}

$overall_summary_data['total_assignments'] = $all_assignments_count;

$overall_completion_percentage = 0;
if ($total_possible_submissions > 0) {
    $overall_completion_percentage = round(($total_actual_submissions / $total_possible_submissions) * 100);
}
$overall_summary_data['overall_completion_rate'] = $overall_completion_percentage;

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tugas Mahasiswa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
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

        .class-card {
            background: var(--color-white);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .class-card:hover {
            transform: translateY(-5px);
        }

        .class-header {
            background: linear-gradient(45deg, var(--color-secondary), var(--color-primary));
            color: var(--color-white);
            padding: 15px 20px;
            cursor: pointer;
        }

        .class-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin: 0;
        }

        .class-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .stat-item {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .class-content {
            padding: 20px;
            display: none;
        }

        .class-content.active {
            display: block;
        }

        .assignment-card {
            background: var(--color-light);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--color-accent);
        }

        .assignment-title {
            font-weight: bold;
            color: var(--color-accent);
            margin-bottom: 10px;
        }

        .deadline {
            color: #dc3545;
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .progress-bar-container {
            background: #e9ecef;
            border-radius: 10px;
            height: 25px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-segment {
            height: 100%;
            float: left;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            color: var(--color-white);
        }

        .completed {
            background: #28a745;
        }

        .late {
            background: #ffc107;
            color: #000 !important;
        }

        .not-submitted {
            background: #dc3545;
        }

        .student-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .student-item {
            background: var(--color-white);
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid;
            font-size: 0.9rem;
        }

        .student-completed {
            border-left-color: #28a745;
        }

        .student-late {
            border-left-color: #ffc107;
        }

        .student-not-submitted {
            border-left-color: #dc3545;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 5px;
        }

        .badge-completed {
            background: #28a745;
            color: white;
        }

        .badge-late {
            background: #ffc107;
            color: black;
        }

        .badge-not-submitted {
            background: #dc3545;
            color: white;
        }

        .toggle-icon {
            transition: transform 0.3s ease;
        }

        .toggle-icon.rotated {
            transform: rotate(180deg);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: var(--color-white);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
        }

        .summary-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--color-accent);
        }

        .summary-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="fas fa-calculator me-2"></i>
                Progress Tugas - Aljabar Linear & Matriks
            </span>
            <div class="d-flex align-items-center text-white">
                <i class="fas fa-user-tie me-2"></i>
                Selamat datang, <?php echo $lecturer_name_for_header; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-number" id="totalClasses"><?php echo $overall_summary_data['total_classes']; ?></div>
                <div class="summary-label">Total Kelas</div>
            </div>
            <div class="summary-card">
                <div class="summary-number" id="totalStudents"><?php echo $overall_summary_data['total_students']; ?></div>
                <div class="summary-label">Total Mahasiswa</div>
            </div>
            <div class="summary-card">
                <div class="summary-number" id="totalAssignments"><?php echo $overall_summary_data['total_assignments']; ?></div>
                <div class="summary-label">Total Tugas</div>
            </div>
            <div class="summary-card">
                <div class="summary-number" id="overallCompletion"><?php echo $overall_summary_data['overall_completion_rate']; ?>%</div>
                <div class="summary-label">Tingkat Penyelesaian</div>
            </div>
        </div>

        <div id="classContainer">
            <?php if (empty($progress_data_for_display)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                    <p>Tidak ada data progres tugas ditemukan untuk dosen ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($progress_data_for_display as $course): ?>
                    <div class="class-card">
                        <div class="class-header" onclick="toggleClass('content-<?php echo $course['course_id']; ?>', 'icon-<?php echo $course['course_id']; ?>')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="class-title">Kelas <?php echo htmlspecialchars($course['course_name']); ?></h3>
                                    <div class="class-stats">
                                        <span class="stat-item">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo count($course['students']); ?> Mahasiswa
                                        </span>
                                        <span class="stat-item">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <?php echo $course['total_completed']; ?> Selesai
                                        </span>
                                        <span class="stat-item">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo $course['total_late']; ?> Terlambat
                                        </span>
                                        <span class="stat-item">
                                            <i class="fas fa-times-circle me-1"></i>
                                            <?php echo $course['total_not_submitted']; ?> Belum
                                        </span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down toggle-icon" id="icon-<?php echo $course['course_id']; ?>"></i>
                            </div>
                        </div>
                        <div class="class-content" id="content-<?php echo $course['course_id']; ?>">
                            <?php if (empty($course['assignments'])): ?>
                                <p class="text-muted text-center py-3">Tidak ada tugas untuk kelas ini.</p>
                            <?php else: ?>
                                <?php foreach ($course['assignments'] as $assignment): ?>
                                    <?php
                                        $total_students_in_assignment = count($course['students']);
                                        $completed_percent = $total_students_in_assignment > 0 ? ($assignment['progress']['completed'] / $total_students_in_assignment) * 100 : 0;
                                        $late_percent = $total_students_in_assignment > 0 ? ($assignment['progress']['late'] / $total_students_in_assignment) * 100 : 0;
                                        $not_submitted_percent = $total_students_in_assignment > 0 ? ($assignment['progress']['not_submitted'] / $total_students_in_assignment) * 100 : 0;
                                    ?>
                                    <div class="assignment-card">
                                        <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                        <div class="deadline">
                                            <i class="fas fa-clock me-1"></i>
                                            Deadline: <?php echo formatDateForDisplay($assignment['due_date']); ?>
                                        </div>
                                        <div class="progress-bar-container">
                                            <div class="progress-segment completed" style="width: <?php echo $completed_percent; ?>%">
                                                <?php echo $assignment['progress']['completed'] > 0 ? $assignment['progress']['completed'] : ''; ?>
                                            </div>
                                            <div class="progress-segment late" style="width: <?php echo $late_percent; ?>%">
                                                <?php echo $assignment['progress']['late'] > 0 ? $assignment['progress']['late'] : ''; ?>
                                            </div>
                                            <div class="progress-segment not-submitted" style="width: <?php echo $not_submitted_percent; ?>%">
                                                <?php echo $assignment['progress']['not_submitted'] > 0 ? $assignment['progress']['not_submitted'] : ''; ?>
                                            </div>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 15px;">
                                            <span style="color: #28a745;"><strong>Selesai: <?php echo $assignment['progress']['completed']; ?></strong></span>
                                            <span style="color: #ffc107;"><strong>Terlambat: <?php echo $assignment['progress']['late']; ?></strong></span>
                                            <span style="color: #dc3545;"><strong>Belum: <?php echo $assignment['progress']['not_submitted']; ?></strong></span>
                                        </div>
                                        <div class="student-list">
                                            <?php foreach ($assignment['students_status'] as $student_status): ?>
                                                <?php
                                                    $statusClass = '';
                                                    $badgeClass = '';
                                                    $statusText = '';
                                                    switch($student_status['status']) {
                                                        case 'completed': $statusClass = 'student-completed'; $badgeClass = 'badge-completed'; $statusText = 'Selesai'; break;
                                                        case 'late': $statusClass = 'student-late'; $badgeClass = 'badge-late'; $statusText = 'Terlambat'; break;
                                                        case 'not-submitted': $statusClass = 'student-not-submitted'; $badgeClass = 'badge-not-submitted'; $statusText = 'Belum'; break;
                                                    }
                                                ?>
                                                <div class="student-item <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($student_status['full_name']); ?> (<?php echo htmlspecialchars($student_status['nim']); ?>)
                                                    <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle class content (JS murni)
        function toggleClass(contentId, iconId) {
            const content = document.getElementById(contentId);
            const icon = document.getElementById(iconId);
            
            content.classList.toggle('active');
            icon.classList.toggle('rotated');
        }

        // Helper for htmlspecialchars (since JS doesn't have it natively for HTML output)
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
        
        // Helper to format date for display (used in JS for clarity, though PHP formats it)
        function formatDateForDisplay(dateString) {
            const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            // Note: This JS function is mainly for consistency if date strings were passed to JS.
            // PHP is already formatting dates in this file.
            return new Date(dateString).toLocaleDateString('id-ID', options);
        }
    </script>
</body>
</html>