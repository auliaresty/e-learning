<?php
// Detail Koneksi Database Anda
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "database_fix";

// Buat objek koneksi MySQLi baru
$conn = new mysqli($servername, $username, $password, $dbname);

// Periksa koneksi
if ($conn->connect_error) {
    error_log("Koneksi database GAGAL: " . $conn->connect_error);
    die("Koneksi database GAGAL: " . $conn->connect_error);
}

// Opsional: Atur charset untuk koneksi (direkomendasikan)
$conn->set_charset("utf8mb4");
