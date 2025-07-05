<?php
session_start(); // Mulai sesi di awal skrip
include 'db_connection.php'; 

// Cek apakah request datang dari form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = $_POST['username'] ?? ''; // Bisa NIM/NIK/Email
    $password_input = $_POST['password'] ?? '';
    $role_input = $_POST['role'] ?? '';

    // Validasi input dasar
    if (empty($username_input) || empty($password_input) || empty($role_input)) {
        echo "<script>alert('Mohon lengkapi semua field!'); window.location.href='login.html';</script>";
        exit;
    }

    // Menggunakan Prepared Statement untuk mencegah SQL Injection
    // Mencari user berdasarkan NIM, NIK, atau Email, dan role
    // Mencari berdasarkan NIM atau NIK untuk student, dan email atau NIK untuk lecturer/admin
    // Perhatikan bahwa NIK saat ini belum digunakan dalam skenario login ini karena di DB NIK dosen NULL
    $sql = "SELECT user_id, full_name, password_hash, role, nim, nik, email FROM users WHERE role = ? AND (nim = ? OR email = ?)";
    $stmt = $conn->prepare($sql);

    // Pastikan tipe data 's' (string) cocok untuk semua parameter
    // Kita bind $username_input dua kali untuk NIM dan Email
    $stmt->bind_param("sss", $role_input, $username_input, $username_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();

        // Verifikasi Password menggunakan password_verify()
        // Ini adalah cara yang benar jika password di DB Anda di-hash dengan password_hash().
        // Berdasarkan database_fix (3).sql, password sudah di-hash.
        if (password_verify($password_input, $user_data['password_hash'])) {
            // Login berhasil
            $_SESSION['user_id'] = $user_data['user_id'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['full_name'] = $user_data['full_name'];
            
            // Opsional: simpan NIM/NIK/Email sesuai peran ke sesi
            if ($user_data['role'] === 'student') {
                $_SESSION['identifier'] = $user_data['nim'];
            } elseif ($user_data['role'] === 'lecturer') {
                $_SESSION['identifier'] = $user_data['email']; // atau NIK jika digunakan
            }

            echo "<script>alert('Login berhasil sebagai " . htmlspecialchars($user_data['full_name']) . "!');</script>";
            
            // Redirect ke dashboard yang sesuai
            if ($user_data['role'] === 'lecturer') {
                echo "<script>window.location.href='dash-dosen.php';</script>";
            } elseif ($user_data['role'] === 'student') {
                echo "<script>window.location.href='dash-mahasiswa.php';</script>";
            }
            exit; // Penting untuk menghentikan eksekusi setelah redirect
        } else {
            // Password salah
            echo "<script>alert('Username atau password salah.'); window.location.href='login.html';</script>";
            exit;
        }
    } else {
        // Username tidak ditemukan atau role tidak cocok
        echo "<script>alert('Username atau password salah atau pengguna tidak ditemukan.'); window.location.href='login.html';</script>";
        exit;
    }

    $stmt->close();
    $conn->close();
} else {
    // Jika halaman login.php diakses langsung tanpa metode POST,
    // arahkan kembali ke halaman login.html
    header("Location: login.html");
    exit;
}
?>