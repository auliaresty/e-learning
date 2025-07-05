<?php
session_start(); // Mulai sesi
include 'db_connection.php'; // Sertakan file koneksi database

// Periksa apakah pengguna sudah login dan memiliki peran 'lecturer'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.html"); // Redirect ke halaman login jika belum login atau bukan dosen
    exit();
}

// Ambil user_id dosen dari sesi
$current_lecturer_id = $_SESSION['user_id']; 

// Inisialisasi variabel dengan nilai default
$total_mahasiswa = 0;
$kelas_aktif = 0;
$tugas_pending = 0;
$quiz_aktif = 0;
$dosen_name = $_SESSION['full_name']; // Ambil nama dari sesi
$dosen_gelar = ''; 

// Ambil gelar dosen dari database
$sql_dosen_gelar = "SELECT gelar FROM users WHERE user_id = ? AND role = 'lecturer'";
$stmt_dosen_gelar = $conn->prepare($sql_dosen_gelar);
if ($stmt_dosen_gelar) {
    $stmt_dosen_gelar->bind_param("i", $current_lecturer_id);
    $stmt_dosen_gelar->execute();
    $result_dosen_gelar = $stmt_dosen_gelar->get_result();
    if ($row_dosen_gelar = $result_dosen_gelar->fetch_assoc()) {
        $dosen_gelar = htmlspecialchars($row_dosen_gelar['gelar']);
    }
    $stmt_dosen_gelar->close();
}

// Query untuk mendapatkan kelas aktif (kelas yang diampu dosen ini)
$sql_kelas_aktif = "SELECT COUNT(course_id) AS total_courses FROM courses WHERE lecturer_id = ?";
$stmt_kelas_aktif = $conn->prepare($sql_kelas_aktif);
if ($stmt_kelas_aktif) {
    $stmt_kelas_aktif->bind_param("i", $current_lecturer_id);
    $stmt_kelas_aktif->execute();
    $result_kelas_aktif = $stmt_kelas_aktif->get_result();
    if ($result_kelas_aktif && $result_kelas_aktif->num_rows > 0) {
        $row = $result_kelas_aktif->fetch_assoc();
        $kelas_aktif = $row['total_courses'];
    }
    $stmt_kelas_aktif->close();
}

// Query untuk mendapatkan total mahasiswa yang diampu oleh dosen ini
// Ini akan lebih relevan daripada total semua mahasiswa di sistem
$sql_total_mahasiswa_diampu = "
    SELECT COUNT(DISTINCT ce.student_id) AS total_students 
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.course_id
    WHERE c.lecturer_id = ?";
$stmt_total_mahasiswa_diampu = $conn->prepare($sql_total_mahasiswa_diampu);
if ($stmt_total_mahasiswa_diampu) {
    $stmt_total_mahasiswa_diampu->bind_param("i", $current_lecturer_id);
    $stmt_total_mahasiswa_diampu->execute();
    $result_total_mahasiswa_diampu = $stmt_total_mahasiswa_diampu->get_result();
    if ($result_total_mahasiswa_diampu && $result_total_mahasiswa_diampu->num_rows > 0) {
        $row = $result_total_mahasiswa_diampu->fetch_assoc();
        $total_mahasiswa = $row['total_students'];
    }
    $stmt_total_mahasiswa_diampu->close();
}


// Query untuk mendapatkan tugas pending (yang deadline-nya belum lewat) untuk mata kuliah yang diampu dosen ini
$sql_tugas_pending = "SELECT COUNT(a.assignment_id) AS total_pending_assignments 
                      FROM assignments a 
                      JOIN courses c ON a.course_id = c.course_id 
                      WHERE a.due_date > NOW() AND c.lecturer_id = ?";
$stmt_tugas_pending = $conn->prepare($sql_tugas_pending);
if ($stmt_tugas_pending) {
    $stmt_tugas_pending->bind_param("i", $current_lecturer_id);
    $stmt_tugas_pending->execute();
    $result_tugas_pending = $stmt_tugas_pending->get_result();
    if ($result_tugas_pending && $result_tugas_pending->num_rows > 0) {
        $row = $result_tugas_pending->fetch_assoc();
        $tugas_pending = $row['total_pending_assignments'];
    }
    $stmt_tugas_pending->close();
}

// Query untuk mendapatkan quiz aktif (yang deadline-nya belum lewat) untuk mata kuliah yang diampu dosen ini
$sql_quiz_aktif = "SELECT COUNT(q.quiz_id) AS total_active_quizzes 
                   FROM quizzes q 
                   JOIN courses c ON q.course_id = c.course_id 
                   WHERE q.end_date > NOW() AND c.lecturer_id = ?";
$stmt_quiz_aktif = $conn->prepare($sql_quiz_aktif);
if ($stmt_quiz_aktif) {
    $stmt_quiz_aktif->bind_param("i", $current_lecturer_id);
    $stmt_quiz_aktif->execute();
    $result_quiz_aktif = $stmt_quiz_aktif->get_result();
    if ($result_quiz_aktif && $result_quiz_aktif->num_rows > 0) {
        $row = $result_quiz_aktif->fetch_assoc();
        $quiz_aktif = $row['total_active_quizzes'];
    }
    $stmt_quiz_aktif->close();
}

$conn->close(); // Tutup koneksi database
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Anda yang sudah ada, tidak ada perubahan */
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
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-green) 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .dashboard-container {
            min-height: 100vh;
            padding: 0;
        }

        .header {
            background: linear-gradient(135deg, var(--dark-teal) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 20px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .welcome-text {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .dashboard-grid {
            padding: 40px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 200px;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(99, 163, 241, 0.1), transparent);
            transition: left 0.6s;
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: var(--secondary-blue);
        }

        .feature-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: var(--dark-teal);
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            color: var(--secondary-blue);
            transform: scale(1.1) rotate(5deg);
        }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark-teal);
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }

        .feature-card:hover .feature-title {
            color: var(--secondary-blue);
        }

        .feature-description {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .stats-section {
            background: var(--white);
            margin: 20px;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-teal);
            display: block;
        }

        .stat-label {
            color: #666;
            font-size: 1rem;
            margin-top: 5px;
        }

        .quick-actions {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .fab-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-blue), var(--dark-teal));
            color: var(--white);
            border: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .fab-button:hover {
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 12px 35px rgba(0,0,0,0.3);
        }

        @media (max-width: 768px) {
            .welcome-text {
                font-size: 2rem;
            }
            
            .dashboard-grid {
                padding: 20px 10px;
            }
            
            .feature-card {
                margin-bottom: 20px;
                min-height: 180px;
                padding: 25px;
            }
            
            .feature-icon {
                font-size: 3rem;
            }
            
            .feature-title {
                font-size: 1.2rem;
            }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(182, 208, 239, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--white);
            border-top: 4px solid var(--dark-teal);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="dashboard-container">
        <div class="header">
            <div class="container-fluid header-content">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="welcome-text">
                            <i class="fas fa-chalkboard-teacher me-3"></i>
                            Dashboard Dosen
                        </h1>
                        <p class="subtitle">Kelola pembelajaran dan pantau progress mahasiswa Anda</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex align-items-center justify-content-end">
                            <div class="me-3">
                                <small class="d-block">Selamat datang,</small>
                                <strong><?php echo htmlspecialchars($dosen_name) . ($dosen_gelar ? ', ' . htmlspecialchars($dosen_gelar) : ''); ?></strong>
                            </div>
                            <div class="rounded-circle" onclick="navigateTo('profile-dosen.php')">
                            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; cursor: pointer;">
                                <i class="fas fa-user text-dark"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-section">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <span class="stat-number" id="totalMahasiswa"><?php echo $total_mahasiswa; ?></span>
                        <div class="stat-label">Mahasiswa Diampu</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <span class="stat-number" id="kelasAktif"><?php echo $kelas_aktif; ?></span>
                        <div class="stat-label">Kelas Aktif</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <span class="stat-number" id="tugasPending"><?php echo $tugas_pending; ?></span>
                        <div class="stat-label">Tugas Pending</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <span class="stat-number" id="quizAktif"><?php echo $quiz_aktif; ?></span>
                        <div class="stat-label">Quiz Aktif</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="row g-4">
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('kelas-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="feature-title">Mahasiswa</div>
                        <div class="feature-description">
                            Kelola kelas dan mahasiswa
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('materitugas-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="feature-title">Materi & Tugas</div>
                        <div class="feature-description">
                            Upload materi pembelajaran dan buat tugas baru
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('quiz-crud-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="feature-title">Quiz</div>
                        <div class="feature-description">
                            Buat dan kelola quiz untuk evaluasi mahasiswa
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('pengumuman-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="feature-title">Pengumuman</div>
                        <div class="feature-description">
                            Buat dan publikasikan pengumuman penting
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('progress-tugas-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="feature-title">Progress Tugas</div>
                        <div class="feature-description">
                            Monitor progress pengerjaan tugas mahasiswa
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('ujian-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="feature-title">Ujian</div>
                        <div class="feature-description">
                            Kelola ujian tengah semester dan ujian akhir
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('input-nilai-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div class="feature-title">Input Nilai</div>
                        <div class="feature-description">
                            Input dan kelola nilai mahasiswa
                        </div>
                    </div>
                </div>

                 <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('jadwal-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i> </div>
                        <div class="feature-title">Jadwal</div>
                        <div class="feature-description">
                            Jadwal Pengajaran Dosen
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="feature-card" onclick="navigateTo('laporan-dosen.php')">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="feature-title">Laporan</div>
                        <div class="feature-description">
                            Generate laporan akademik dan statistik
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <button class="fab-button" onclick="showQuickMenu()" title="Quick Actions">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Navigation function with loading animation
        function navigateTo(page) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('active');
            setTimeout(() => {
                window.location.href = page;
            }, 500);
        }

        // Quick menu function (static)
        function showQuickMenu() {
            const options = [
                { text: 'Buat Pengumuman Baru', action: () => navigateTo('pengumuman-dosen.php') },
                { text: 'Upload Materi', action: () => navigateTo('materitugas-dosen.php') },
                { text: 'Buat Quiz Baru', action: () => navigateTo('quiz-crud-dosen.php') },
                { text: 'Cek Progress Tugas', action: () => navigateTo('progress-tugas-dosen.php') }
            ];
            const chosen = prompt('Pilih aksi cepat:\n1. Buat Pengumuman Baru\n2. Upload Materi\n3. Buat Quiz Baru\n4. Cek Progress Tugas\n(Masukkan angka)');
            if (chosen && options[chosen - 1]) {
                options[chosen - 1].action();
            }
        }

        // Add some interactive animations on page load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.feature-card');
            const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
            });
            function updateTime() { const now = new Date(); const timeString = now.toLocaleTimeString('id-ID'); }
            setInterval(updateTime, 1000);
            updateTime();
        });
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case '1': e.preventDefault(); navigateTo('kelas-dosen.php'); break;
                    case '2': e.preventDefault(); navigateTo('materitugas-dosen.php'); break;
                    case '3': e.preventDefault(); navigateTo('quiz-crud-dosen.php'); break;
                    case '4': e.preventDefault(); navigateTo('pengumuman-dosen.php'); break;
                }
            }
        });
        document.documentElement.style.scrollBehavior = 'smooth';
        function preloadPages() {
            const criticalPages = [ 'kelas-dosen.php', 'materitugas-dosen.php', 'quiz-crud-dosen.php', 'pengumuman-dosen.php' ];
            criticalPages.forEach(page => { const link = document.createElement('link'); link.rel = 'prefetch'; link.href = page; document.head.appendChild(link); });
        }
        window.addEventListener('load', preloadPages);
    </script>
</body>
</html>