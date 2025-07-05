<?php
session_start(); // Mulai sesi
include 'db_connection.php'; 

// Periksa apakah pengguna sudah login dan memiliki peran 'student'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html"); // Redirect ke halaman login jika belum login atau bukan mahasiswa
    exit();
}

// Ambil user_id mahasiswa dari sesi
$current_student_id = $_SESSION['user_id']; 

$student_name = $_SESSION['full_name']; // Ambil nama dari sesi
$student_nim = 'Tidak diketahui'; // Default NIM
$enrolled_courses = []; 
$total_enrolled_courses = 0;

// Ambil NIM dari database (jika tidak disimpan di sesi atau perlu update)
$sql_student_nim = "SELECT nim FROM users WHERE user_id = ? AND role = 'student'";
$stmt_student_nim = $conn->prepare($sql_student_nim);
if ($stmt_student_nim) {
    $stmt_student_nim->bind_param("i", $current_student_id);
    $stmt_student_nim->execute();
    $result_student_nim = $stmt_student_nim->get_result();
    if ($row_student_nim = $result_student_nim->fetch_assoc()) {
        $student_nim = htmlspecialchars($row_student_nim['nim']);
    }
    $stmt_student_nim->close();
}


// ... (sisa kode Query PHP Anda tetap sama, tetapi pastikan semua query yang butuh student_id menggunakan $current_student_id) ...

$conn->close();
?>

<div class="col-md-2 col-sm-4 col-6">
    <div class="menu-card" onclick="window.location.href='jadwal-mahasiswa.php'"> <div class="menu-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="menu-text">Jadwal Mata Kuliah</div>
    </div>
</div>
<div class="col-md-2 col-sm-4 col-6">
    <div class="menu-card" onclick="window.location.href='deadline.php'"> <div class="menu-icon"><i class="fas fa-clock"></i></div>
        <div class="menu-text">Deadline Tugas</div>
    </div>
</div>
<div class="col-md-2 col-sm-4 col-6">
    <div class="menu-card" onclick="window.location.href='lpquiz.php'"> <div class="menu-icon"><i class="fas fa-question-circle"></i></div>
        <div class="menu-text">Quiz</div>
    </div>
</div>
<div class="col-md-2 col-sm-4 col-6">
    <div class="menu-card" onclick="window.location.href='pengunguman.php'"> <div class="menu-icon"><i class="fas fa-bullhorn"></i></div>
        <div class="menu-text">Pengumuman</div>
    </div>
</div>
<div class="col-md-2 col-sm-4 col-6">
    <div class="menu-card" onclick="window.location.href='nilai.php'"> <div class="menu-icon"><i class="fas fa-star"></i></div>
        <div class="menu-text">Nilai</div>
    </div>
</div>
<div class="col-md-2 col-sm-4 col-6">
    <div class="menu-card" onclick="window.location.href='ujian.php'"> <div class="menu-icon"><i class="fas fa-clipboard-check"></i></div>
        <div class="menu-text">Ujian</div>
    </div>
</div>
<button class="btn btn-light" onclick="window.location.href='logout.php'"> <i class="fas fa-sign-out-alt me-2"></i>Logout
</button>