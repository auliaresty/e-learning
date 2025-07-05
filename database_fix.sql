-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2025 at 01:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `database_fix`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `published_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lecturer_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `title`, `content`, `published_at`, `lecturer_id`, `course_id`) VALUES
(1, 'Pembatalan Kuliah', 'Kuliah Aljabar Linear & Matriks pada tanggal 3 Juni 2025 ditiadakan karena dosen, Dr. Budi Santosa, M.Si., sedang menghadiri seminar nasional.', '2025-06-02 01:00:00', 1, 1),
(2, 'Perubahan Ruangan', 'Kuliah Pemrograman Web pada tanggal 4 Juni 2025 dipindahkan ke Ruang 301 Gedung C karena ruang sebelumnya digunakan untuk rapat fakultas.', '2025-06-02 02:00:00', 2, 2),
(3, 'Kuliah Pengganti', 'Kuliah Analisis Desain yang batal pada 30 Mei 2025 akan diganti pada 5 Juni 2025 pukul 13:00 di Ruang 204 Gedung B.', '2025-06-01 08:00:00', 3, 3),
(4, 'Pembatalan Kuliah', 'Kuliah Multimedia pada tanggal 4 Juni 2025 ditiadakan karena dosen, Prof. Andi Wijaya, M.M., sedang sakit.', '2025-06-02 03:00:00', 4, 4),
(5, 'Perubahan Jadwal', 'Kuliah Big Data pada 3 Juni 2025 diubah waktunya menjadi pukul 15:00 di Ruang 405 Gedung D karena ada kegiatan kampus.', '2025-06-02 00:30:00', 5, 5),
(6, 'Pembatalan Kuliah', 'Kuliah Kecerdasan Buatan pada tanggal 5 Juni 2025 ditiadakan karena dosen, Prof. Dewi Sartika, Ph.D., sedang dinas luar.', '2025-06-02 04:00:00', 6, 6),
(7, 'Perubahan Ruangan', 'Kuliah Basis Data pada tanggal 6 Juni 2025 dipindahkan ke Ruang 102 Gedung A karena renovasi ruang sebelumnya.', '2025-06-02 05:00:00', 8, 7),
(8, 'Kuliah Pengganti', 'Kuliah Mikrokontroler yang batal pada 1 Juni 2025 akan diganti pada 7 Juni 2025 pukul 10:00 di Ruang 305 Gedung C.', '2025-06-02 07:00:00', 7, 8);

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime NOT NULL,
  `max_grade` decimal(5,2) DEFAULT 100.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`assignment_id`, `course_id`, `title`, `description`, `due_date`, `max_grade`, `created_at`, `updated_at`) VALUES
(1, 1, 'Sistem Persamaan Linear', 'Selesaikan 10 soal sistem persamaan linear menggunakan metode eliminasi Gauss-Jordan dan aturan Cramer. Sertakan langkah-langkah penyelesaian.', '2025-06-03 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(2, 1, 'Dekomposisi Matriks', 'Lakukan dekomposisi LU dan QR pada matriks 4x4. Jelaskan aplikasi dalam penyelesaian sistem persamaan dan optimasi numerik.', '2025-06-10 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(3, 2, 'Website Portfolio Dinamis', 'Buat website portfolio dinamis menggunakan HTML, CSS, dan JavaScript dengan framework Bootstrap. Tambahkan animasi dan fitur interaktif.', '2025-06-05 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(4, 2, 'Aplikasi Web dengan API', 'Kembangkan aplikasi web yang mengintegrasikan API publik (contoh: OpenWeather). Tampilkan data secara dinamis dengan UI responsif.', '2025-06-12 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(5, 3, 'Desain UI/UX Aplikasi', 'Rancang UI/UX untuk aplikasi mobile e-learning menggunakan Figma. Sertakan wireframe, mockup, dan laporan user testing.', '2025-06-07 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(6, 4, 'Video Promosi Produk', 'Buat video promosi produk berdurasi 2-3 menit menggunakan Adobe Premiere. Sertakan efek transisi, color grading, dan audio mixing.', '2025-06-09 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(7, 4, 'Desain Grafis Poster', 'Buat desain poster promosi acara kampus menggunakan Adobe Photoshop. Gunakan teknik layering dan typography yang menarik.', '2025-06-14 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(8, 5, 'Analisis Data Penjualan', 'Analisis dataset penjualan menggunakan Apache Spark. Buat visualisasi data dengan Power BI dan laporkan pola yang ditemukan.', '2025-06-11 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(9, 6, 'Model Prediksi Harga', 'Bangun model machine learning untuk prediksi harga rumah menggunakan Python dan scikit-learn. Evaluasi model dengan metrik akurasi.', '2025-06-13 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(10, 6, 'Sistem Rekomendasi', 'Kembangkan sistem rekomendasi berbasis collaborative filtering untuk platform streaming. Gunakan dataset publik dan library Python.', '2025-06-18 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(11, 7, 'Desain Database Tokoh Online', 'Rancang database untuk toko online menggunakan PostgreSQL. Sertakan ERD, normalisasi, dan contoh query untuk laporan penjualan.', '2025-06-15 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(12, 7, 'Tuning Performa Database', 'Optimalkan performa database dengan indexing dan query tuning. Uji pada dataset besar dan laporkan peningkatan waktu eksekusi.', '2025-06-20 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(13, 8, 'Sistem Irigasi Otomatis', 'Rancang sistem irigasi otomatis menggunakan Arduino dan sensor kelembapan tanah. Sertakan kode dan dokumentasi hasil pengujian.', '2025-06-17 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(14, 8, 'Prototipe Smart Home', 'Buat prototipe smart home menggunakan ESP32 untuk mengontrol lampu dan kipas via aplikasi mobile. Sertakan kode dan laporan.', '2025-06-22 23:59:59', 100.00, '2025-06-20 10:30:10', '2025-06-20 10:30:10');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `credits` int(11) NOT NULL DEFAULT 3,
  `lecturer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_name`, `course_code`, `description`, `credits`, `lecturer_id`, `created_at`, `updated_at`) VALUES
(1, 'Aljabar Linear & Matriks', 'IF1401', 'Mempelajari konsep dasar aljabar linear dan matriks serta aplikasinya.', 3, 1, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(2, 'Pemrograman Web', 'IF1402', 'Pengembangan aplikasi web dinamis menggunakan teknologi front-end dan back-end.', 3, 2, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(3, 'Analisis Desain Sistem Informasi', 'IF1403', 'Menganalisis dan merancang sistem informasi yang efektif dan efisien.', 2, 3, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(4, 'Multimedia', 'IF1404', 'Konsep dasar dan aplikasi teknologi multimedia.', 3, 4, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(5, 'Big Data', 'IF1405', 'Pengenalan dan analisis data berukuran besar.', 3, 5, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(6, 'Kecerdasan Buatan', 'IF1406', 'Mempelajari konsep dan implementasi kecerdasan buatan.', 3, 6, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(7, 'Basis Data', 'IF1407', 'Struktur dan pengelolaan basis data relasional.', 2, 8, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(8, 'Mikrokontroler', 'IF1408', 'Pengenalan dan pemrograman mikrokontroler.', 1, 7, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(9, 'Riset Operasi', 'UMUM101', 'Pengantar Riset Operasi.', 3, 1, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(10, 'Algoritma', 'UMUM102', 'Pengantar Algoritma dan Struktur Data.', 3, 3, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(11, 'Fotografi', 'UMUM103', 'Dasar-dasar fotografi digital.', 2, 4, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(12, 'Pendidikan Agama', 'UMUM104', 'Pendidikan Agama Islam', 2, NULL, '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(13, 'Pendidikan Kewarganegaraan', 'UMUM105', 'Pendidikan tentang kewarganegaraan.', 2, NULL, '2025-06-20 10:30:10', '2025-06-20 10:30:10');

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_enrollments`
--

INSERT INTO `course_enrollments` (`enrollment_id`, `student_id`, `course_id`, `enrollment_date`) VALUES
(1, 1, 1, '2025-06-20 10:30:10'),
(2, 1, 2, '2025-06-20 10:30:10'),
(3, 1, 3, '2025-06-20 10:30:10'),
(4, 1, 4, '2025-06-20 10:30:10'),
(5, 1, 5, '2025-06-20 10:30:10'),
(6, 1, 6, '2025-06-20 10:30:10'),
(7, 1, 7, '2025-06-20 10:30:10'),
(8, 1, 8, '2025-06-20 10:30:10'),
(9, 1, 9, '2025-06-20 10:30:10'),
(10, 1, 10, '2025-06-20 10:30:10'),
(11, 1, 11, '2025-06-20 10:30:10'),
(12, 1, 12, '2025-06-20 10:30:10'),
(13, 1, 13, '2025-06-20 10:30:10');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `exam_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `exam_type` enum('UTS','UAS','Quiz') NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `online_link` varchar(255) DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `total_questions` int(11) DEFAULT NULL,
  `exam_status` enum('Scheduled','Active','Completed','Canceled') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `course_id`, `title`, `exam_type`, `exam_date`, `start_time`, `end_time`, `room`, `is_online`, `online_link`, `duration_minutes`, `total_questions`, `exam_status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Ujian Tengah Semester Aljabar Linear', 'UTS', '2025-07-07', '08:00:00', '10:00:00', 'Ruang 201', 0, NULL, 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(2, 9, 'Ujian Tengah Semester Riset Operasi', 'UTS', '2025-07-08', '10:00:00', '12:00:00', 'Dashboard Online', 1, 'online_exam_riset_operasi_uts.html', 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(3, 10, 'Ujian Tengah Semester Algoritma', 'UTS', '2025-07-09', '13:00:00', '15:00:00', 'Lab Komputer 1', 0, NULL, 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(4, 2, 'Ujian Tengah Semester Pemrograman', 'UTS', '2025-07-10', '08:00:00', '10:00:00', 'Dashboard Online', 1, 'online_exam_pemrograman_uts.html', 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(5, 11, 'Ujian Tengah Semester Fotografi', 'UTS', '2025-07-11', '10:00:00', '12:00:00', 'Studio Foto', 0, NULL, 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(6, 12, 'Ujian Tengah Semester Pendidikan Agama', 'UTS', '2025-07-14', '13:00:00', '15:00:00', 'Dashboard Online', 1, 'online_exam_pendidikan_agama_uts.html', 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(7, 13, 'Ujian Tengah Semester Pendidikan Kewarganegaraan', 'UTS', '2025-07-15', '08:00:00', '10:00:00', 'Ruang 105', 0, NULL, 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(8, 4, 'Ujian Tengah Semester Multimedia', 'UTS', '2025-07-16', '10:00:00', '12:00:00', 'Lab Multimedia', 0, NULL, 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(9, 3, 'Ujian Tengah Semester Analisis Desain', 'UTS', '2025-07-17', '13:00:00', '15:00:00', 'Dashboard Online', 1, 'online_exam_analisis_desain_uts.html', 120, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(10, 1, 'Ujian Akhir Semester Aljabar Linear', 'UAS', '2025-08-04', '08:00:00', '11:00:00', 'Ruang 201', 0, NULL, 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(11, 9, 'Ujian Akhir Semester Riset Operasi', 'UAS', '2025-08-05', '10:00:00', '13:00:00', 'Dashboard Online', 1, 'online_exam_riset_operasi_uas.html', 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(12, 10, 'Ujian Akhir Semester Algoritma', 'UAS', '2025-08-06', '13:00:00', '16:00:00', 'Lab Komputer 1', 0, NULL, 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(13, 2, 'Ujian Akhir Semester Pemrograman', 'UAS', '2025-08-07', '08:00:00', '11:00:00', 'Dashboard Online', 1, 'online_exam_pemrograman_uas.html', 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(14, 11, 'Ujian Akhir Semester Fotografi', 'UAS', '2025-08-08', '10:00:00', '13:00:00', 'Galeri Seni', 0, NULL, 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(15, 12, 'Ujian Akhir Semester Pendidikan Agama', 'UAS', '2025-08-11', '13:00:00', '16:00:00', 'Dashboard Online', 1, 'online_exam_pendidikan_agama_uas.html', 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(16, 13, 'Ujian Akhir Semester Pendidikan Kewarganegaraan', 'UAS', '2025-08-12', '08:00:00', '11:00:00', 'Ruang 105', 0, NULL, 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(17, 4, 'Ujian Akhir Semester Multimedia', 'UAS', '2025-08-13', '10:00:00', '13:00:00', 'Lab Multimedia', 0, NULL, 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(18, 3, 'Ujian Akhir Semester Analisis Desain', 'UAS', '2025-08-14', '13:00:00', '16:00:00', 'Dashboard Online', 1, 'online_exam_analisis_desain_uas.html', 180, NULL, 'Scheduled', '2025-06-20 10:30:10', '2025-06-20 10:30:10');

-- --------------------------------------------------------

--
-- Table structure for table `exam_attempts`
--

CREATE TABLE `exam_attempts` (
  `attempt_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` datetime DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_attempts`
--

INSERT INTO `exam_attempts` (`attempt_id`, `exam_id`, `student_id`, `start_time`, `end_time`, `score`, `is_completed`) VALUES
(1, 1, 1, '2025-07-07 00:55:00', '2025-07-07 09:50:00', 88.00, 1),
(2, 2, 1, '2025-07-08 02:58:00', '2025-07-08 11:55:00', 92.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `grade_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `grade_value` decimal(5,2) NOT NULL,
  `grade_letter` varchar(5) NOT NULL,
  `grade_points` decimal(3,2) NOT NULL,
  `grade_type` enum('Assignment','Quiz','UTS','UAS','Final Course','Final GPA') NOT NULL,
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `material_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`material_id`, `course_id`, `title`, `description`, `file_path`, `file_type`, `uploaded_at`) VALUES
(1, 1, 'Materi 1 - Pengenalan Sistem Persamaan Linear', 'Pengenalan dasar sistem persamaan linear dan metode penyelesaian.', 'aljabar-linear-materi-1.pdf', 'pdf', '2025-06-01 02:00:00'),
(2, 1, 'Materi 2 - Dekomposisi Matriks', 'Penjelasan tentang dekomposisi LU dan QR.', 'aljabar-linear-materi-2.pdf', 'pdf', '2025-06-02 03:00:00'),
(3, 2, 'Materi 1 - Pengenalan HTML & CSS', 'Dasar-dasar HTML dan CSS untuk pengembangan web.', 'pemrograman-web-materi-1.pdf', 'pdf', '2025-06-01 02:30:00'),
(4, 2, 'Materi 2 - API Integration', 'Cara mengintegrasikan API pihak ketiga dalam aplikasi web.', 'pemrograman-web-materi-2.pdf', 'pdf', '2025-06-02 04:00:00'),
(5, 3, 'Materi 1 - Prinsip UI/UX', 'Prinsip-prinsip desain antarmuka pengguna dan pengalaman pengguna.', 'analisis-desain-materi-1.pdf', 'pdf', '2025-06-01 03:00:00'),
(6, 4, 'Materi 1 - Dasar Video Editing', 'Pengenalan tools dan teknik dasar editing video.', 'multimedia-materi-1.pdf', 'pdf', '2025-06-01 03:30:00'),
(7, 4, 'Materi 2 - Desain Grafis', 'Dasar-dasar desain grafis dan penggunaan software.', 'multimedia-materi-2.pdf', 'pdf', '2025-06-02 05:00:00'),
(8, 5, 'Materi 1 - Pengenalan Big Data', 'Konsep dasar, karakteristik, dan teknologi Big Data.', 'big-data-materi-1.pdf', 'pdf', '2025-06-01 04:00:00'),
(9, 6, 'Materi 1 - Machine Learning Dasar', 'Pengenalan algoritma dasar Machine Learning.', 'kecerdasan-buatan-materi-1.pdf', 'pdf', '2025-06-01 04:30:00'),
(10, 6, 'Materi 2 - Sistem Rekomendasi', 'Cara kerja dan jenis-jenis sistem rekomendasi.', 'kecerdasan-buatan-materi-2.pdf', 'pdf', '2025-06-02 06:00:00'),
(11, 7, 'Materi 1 - Desain Database', 'Teknik normalisasi dan perancangan ERD.', 'basis-data-materi-1.pdf', 'pdf', '2025-06-01 05:00:00'),
(12, 7, 'Materi 2 - Optimasi Query', 'Teknik-teknik optimasi query SQL.', 'basis-data-materi-2.pdf', 'pdf', '2025-06-02 07:00:00'),
(13, 8, 'Materi 1 - Pengenalan Arduino', 'Dasar-dasar pemrograman dan penggunaan Arduino.', 'mikrokontroler-materi-1.pdf', 'pdf', '2025-06-01 05:30:00'),
(14, 8, 'Materi 2 - IoT dengan ESP32', 'Membangun proyek IoT dengan modul ESP32.', 'mikrokontroler-materi-2.pdf', 'pdf', '2025-06-02 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `question_options`
--

CREATE TABLE `question_options` (
  `option_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `question_options`
--

INSERT INTO `question_options` (`option_id`, `question_id`, `option_text`, `is_correct`) VALUES
(1, 1, 'Matriks yang semua elemennya bernilai 1', 0),
(2, 1, 'Matriks persegi yang elemen diagonal utamanya 1 dan elemen lainnya 0', 1),
(3, 1, 'Matriks yang determinannya sama dengan 1', 0),
(4, 1, 'Matriks yang tidak dapat diinvers', 0),
(5, 2, '3×4', 1),
(6, 2, '2×2', 0),
(7, 2, '3×2', 0),
(8, 2, 'Tidak dapat dikalikan', 0),
(9, 3, '5', 1),
(10, 3, '8', 0),
(11, 3, '11', 0),
(12, 3, '14', 0),
(13, 4, 'Solusi tunggal', 0),
(14, 4, 'Tidak ada solusi', 0),
(15, 4, 'Solusi trivial (x = 0)', 1),
(16, 4, 'Solusi tak hingga', 0),
(17, 5, '(1,2) dan (2,4)', 0),
(18, 5, '(1,0) dan (0,1)', 1),
(19, 5, '(3,6) dan (1,2)', 0),
(20, 5, '(0,0) dan (1,1)', 0),
(21, 6, 'Jumlah baris matriks', 0),
(22, 6, 'Jumlah kolom matriks', 0),
(23, 6, 'Jumlah maksimum baris atau kolom yang linear independen', 1),
(24, 6, 'Determinan matriks', 0),
(25, 7, 'A adalah matriks persegi', 0),
(26, 7, 'det(A) ≠ 0', 1),
(27, 7, 'A adalah matriks diagonal', 0),
(28, 7, 'Semua elemen A positif', 0),
(29, 8, '(7,18)', 1),
(30, 8, '(5,12)', 0),
(31, 8, '(4,9)', 0),
(32, 8, '(3,7)', 0);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quiz_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `passing_score` decimal(5,2) NOT NULL DEFAULT 70.00,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`quiz_id`, `course_id`, `title`, `description`, `duration_minutes`, `total_questions`, `passing_score`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, 1, 'Quiz Aljabar Linear & Matriks', 'Kuis untuk menguji pemahaman materi Aljabar Linear & Matriks.', 90, 8, 70.00, '2025-08-01 00:00:00', '2025-08-15 23:59:59', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(2, 2, 'Quiz Pemrograman Web Dasar', 'Kuis untuk menguji pemahaman dasar Pemrograman Web (HTML, CSS, JS).', 60, 10, 60.00, '2025-07-01 00:00:00', '2025-07-10 23:59:59', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(3, 7, 'Quiz Basis Data Fundamental', 'Kuis dasar tentang konsep basis data.', 45, 12, 65.00, '2025-08-05 00:00:00', '2025-08-20 23:59:59', '2025-06-20 10:30:10', '2025-06-20 10:30:10');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answers`
--

CREATE TABLE `quiz_answers` (
  `answer_id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `essay_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_answers`
--

INSERT INTO `quiz_answers` (`answer_id`, `attempt_id`, `question_id`, `selected_option_id`, `essay_answer`, `is_correct`) VALUES
(1, 1, 1, 2, NULL, 1),
(2, 1, 2, 5, NULL, 1),
(3, 1, 3, 9, NULL, 1),
(4, 1, 4, 15, NULL, 1),
(5, 1, 5, 18, NULL, 1),
(6, 1, 6, 23, NULL, 1),
(7, 1, 7, 26, NULL, 1),
(8, 1, 8, 29, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `attempt_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` datetime DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_attempts`
--

INSERT INTO `quiz_attempts` (`attempt_id`, `quiz_id`, `student_id`, `start_time`, `end_time`, `score`, `is_completed`) VALUES
(1, 1, 1, '2025-06-19 03:00:00', '2025-06-19 10:45:00', 87.50, 1);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `question_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_formula` text DEFAULT NULL,
  `question_type` enum('multiple_choice','essay','code') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`question_id`, `quiz_id`, `question_text`, `question_formula`, `question_type`) VALUES
(1, 1, 'Apa yang dimaksud dengan matriks identitas?', NULL, 'multiple_choice'),
(2, 1, 'Jika A adalah matriks 3×2 dan B adalah matriks 2×4, maka hasil perkalian A×B menghasilkan matriks berukuran:', NULL, 'multiple_choice'),
(3, 1, 'Determinan dari matriks 2×2 berikut ini adalah:', 'A = [2  3]\n    [1  4]', 'multiple_choice'),
(4, 1, 'Suatu sistem persamaan linear homogen selalu memiliki:', NULL, 'multiple_choice'),
(5, 1, 'Vektor-vektor berikut yang membentuk basis untuk R² adalah:', NULL, 'multiple_choice'),
(6, 1, 'Rank dari matriks adalah:', NULL, 'multiple_choice'),
(7, 1, 'Matriks A dapat diinvers jika dan hanya jika:', NULL, 'multiple_choice'),
(8, 1, 'Dalam transformasi linear T: R² → R², jika T(1,0) = (2,3) dan T(0,1) = (1,4), maka T(2,3) adalah:', NULL, 'multiple_choice');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `day_of_week` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `class_type` enum('Teori','Praktikum') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`schedule_id`, `course_id`, `lecturer_id`, `day_of_week`, `start_time`, `end_time`, `room`, `class_type`, `created_at`, `updated_at`) VALUES
(1, 7, 8, 'Senin', '10:40:00', '12:20:00', 'VR.01.08', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(2, 1, 1, 'Selasa', '07:00:00', '08:40:00', '07.01.04', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(3, 2, 2, 'Selasa', '08:50:00', '10:30:00', '05.02.03', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(4, 2, 2, 'Selasa', '10:40:00', '12:20:00', 'L 2.4.5', 'Praktikum', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(5, 3, 3, 'Selasa', '13:20:00', '15:00:00', '05.04.02', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(6, 4, 4, 'Rabu', '07:00:00', '08:40:00', '7.5.3', 'Praktikum', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(7, 4, 4, 'Rabu', '10:40:00', '12:20:00', '05.04.04', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(8, 5, 5, 'Rabu', '13:20:00', '15:00:00', '05.04.07', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(9, 6, 6, 'Kamis', '08:50:00', '10:30:00', '05.04.03', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(10, 5, 5, 'Kamis', '13:20:00', '15:00:00', '05.02.03', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(11, 8, 7, 'Kamis', '15:30:00', '17:05:00', '05.04.02', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(12, 7, 8, 'Jumat', '07:00:00', '08:00:00', '05.02.03', 'Teori', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(13, 8, 7, 'Jumat', '08:50:00', '10:30:00', 'L 2.4.3', 'Praktikum', '2025-06-20 10:30:10', '2025-06-20 10:30:10');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `submission_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_file_path` varchar(255) DEFAULT NULL,
  `submission_text` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `grade` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`submission_id`, `assignment_id`, `student_id`, `submission_file_path`, `submission_text`, `submitted_at`, `grade`, `feedback`) VALUES
(1, 1, 1, 'aljabar-linear-sp.pdf', NULL, '2025-06-01 03:00:00', 90.00, 'Kerja bagus, jawaban sangat akurat.'),
(2, 3, 1, 'web-portfolio.zip', NULL, '2025-06-04 08:30:00', 85.00, 'Desain menarik, fungsionalitas baik.'),
(3, 5, 1, NULL, NULL, '2025-06-20 10:30:10', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','lecturer','admin') NOT NULL,
  `nim` varchar(20) DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `gender` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `study_program` varchar(100) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT 'Indonesia',
  `place_of_birth` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `previous_school` varchar(255) DEFAULT NULL,
  `nisn` varchar(50) DEFAULT NULL,
  `school_city` varchar(100) DEFAULT NULL,
  `profile_picture_url` varchar(255) DEFAULT 'default_profile.svg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `nim`, `nik`, `gender`, `study_program`, `religion`, `nationality`, `place_of_birth`, `date_of_birth`, `phone_number`, `previous_school`, `nisn`, `school_city`, `profile_picture_url`, `created_at`, `updated_at`) VALUES
(1, 'Aulia Resty Nur Aini', 'aulia.resty@students.amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'student', '23.11.5571', '3304011234567890', 'Perempuan', 'Teknik Informatika', 'Islam', 'Indonesia', 'Yogyakarta', '2004-05-15', '081234567890', 'SMA Negeri 1 Yogyakarta', '9991112223', 'Yogyakarta', 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(2, 'Dr. Budi Santosa, M.Sc.', 'budi.santosa@amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'lecturer', NULL, NULL, 'Laki-laki', 'Teknik Informatika', 'Islam', 'Indonesia', 'Jakarta', '1975-01-20', '081122334455', NULL, NULL, NULL, 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(3, 'Ir. Rika Handayani, M.Kom.', 'rika.handayani@amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'lecturer', NULL, NULL, 'Perempuan', 'Teknik Informatika', 'Islam', 'Indonesia', 'Bandung', '1980-03-10', '085678901234', NULL, NULL, NULL, 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(4, 'Dr. Eng. Andi Pratama, S.T., M.T.', 'andi.pratama@amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'lecturer', NULL, NULL, 'Laki-laki', 'Teknik Informatika', 'Islam', 'Indonesia', 'Surabaya', '1978-07-25', '087812345678', NULL, NULL, NULL, 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(5, 'Yuli Astuti, S.Sn., M.Sn.', 'yuli.astuti@amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'lecturer', NULL, NULL, 'Perempuan', 'Seni & Desain', 'Kristen', 'Indonesia', 'Semarang', '1985-09-01', '089988776655', NULL, NULL, NULL, 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(6, 'Dr. Ahmad Zulkarnain, M.Kom.', 'ahmad.zulkarnain@amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'lecturer', NULL, NULL, 'Laki-laki', 'Teknik Informatika', 'Islam', 'Indonesia', 'Medan', '1970-11-11', '081298765432', NULL, NULL, NULL, 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(7, 'Prof. Dr. Hendra Wijaya, M.T.', 'hendra.wijaya@amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'lecturer', NULL, NULL, 'Laki-laki', 'Teknik Informatika', 'Islam', 'Indonesia', 'Palembang', '1965-04-03', '081345678901', NULL, NULL, NULL, 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(8, 'Ir. Teguh Raharjo, M.Eng.', 'teguh.raharjo@amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'lecturer', NULL, NULL, 'Laki-laki', 'Teknik Elektro', 'Islam', 'Indonesia', 'Makassar', '1972-06-18', '082109876543', NULL, NULL, NULL, 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10'),
(9, 'Dewi Lestari, S.Kom., M.Kom.', 'dewi.lestari@amikom.ac.id', '$2y$10$f/9s.Z2k.3j0s.j5G5N9O.uI7D.J7P.U7D.J7K.J7F.J7C.J7E.J7W.J7N.J7L', 'lecturer', NULL, NULL, 'Perempuan', 'Teknik Informatika', 'Islam', 'Indonesia', 'Surakarta', '1983-02-28', '081555667788', NULL, NULL, NULL, 'default_profile.svg', '2025-06-20 10:30:10', '2025-06-20 10:30:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`exam_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD UNIQUE KEY `exam_id` (`exam_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`option_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quiz_id`),
  ADD KEY `course_id` (`course_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
