<?php
// Ganti dengan detail koneksi database Anda
$servername = "localhost"; // Biasanya 'localhost'
$username = "root";     // Username database Anda (misal: root untuk XAMPP/WAMP default)
$password = "";         // Password database Anda (misal: kosong untuk XAMPP/WAMP default)
$dbname = "database_fix"; // Nama database Anda sesuai dengan yang ada di phpMyAdmin

// Buat koneksi baru ke database
$conn = new mysqli($servername, $username, $password, $dbname);

// Periksa koneksi
if ($conn->connect_error) {
    // Jika koneksi gagal, tampilkan pesan error dan hentikan eksekusi
    die("Koneksi database GAGAL: " . $conn->connect_error);
} else {
    // Jika koneksi berhasil, tampilkan pesan sukses
    echo "Koneksi database BERHASIL!";
}

// Tutup koneksi
$conn->close();
?>