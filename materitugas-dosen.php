<?php
include 'db_connection.php'; // Meng-include file koneksi database Anda

session_start(); // Pastikan session dimulai untuk mengelola status login

// --- Simulasi Data Dosen Login ---
// Di aplikasi nyata, user_id dan role akan didapatkan setelah proses login yang sukses.
// Untuk demo, kita set langsung ke ID dosen 2 (Dr. Budi Santosa) dari database_fix.sql
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // ID Dosen yang akan ditampilkan kelasnya (Dr. Budi Santosa)
    $_SESSION['role'] = 'lecturer';
}

// Pastikan hanya dosen yang bisa mengakses halaman ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php"); // Redirect ke halaman login jika tidak login atau bukan dosen
    exit();
}

$current_lecturer_id = $_SESSION['user_id'];

// Inisialisasi variabel
$current_course_id = null; // Akan ditentukan setelah fetch course yang diampu dosen
$current_pertemuan_id = null; // Asumsi pertemuan ID sama dengan course ID
$materials_data_for_current_course = [];
$assignments_data_for_current_course = [];
$courses_data_for_sidebar = []; // Data semua mata kuliah untuk sidebar
$course_name_for_header = "Materi & Tugas"; // Default course name for header
$lecturer_full_name_header = "Dosen";
$lecturer_gelar_header = "";

// --- Fungsi Helper ---
function getFileIcon($fileType) {
    if (!$fileType) return 'fas fa-file';
    $fileType = strtolower($fileType);
    if (strpos($fileType, 'pdf') !== false) return 'fas fa-file-pdf';
    if (strpos($fileType, 'doc') !== false || strpos($fileType, 'docx') !== false) return 'fas fa-file-word';
    if (strpos($fileType, 'ppt') !== false || strpos($fileType, 'pptx') !== false) return 'fas fa-file-powerpoint';
    if (strpos($fileType, 'xls') !== false || strpos($fileType, 'xlsx') !== false) return 'fas fa-file-excel';
    if (strpos($fileType, 'zip') !== false || strpos($fileType, 'rar') !== false) return 'fas fa-file-archive';
    if (strpos($fileType, 'jpg') !== false || strpos($fileType, 'jpeg') !== false || strpos($fileType, 'png') !== false || strpos($fileType, 'gif') !== false) return 'fas fa-file-image';
    if (strpos($fileType, 'txt') !== false) return 'fas fa-file-alt';
    return 'fas fa-file';
}

function formatDateForDisplay($dateString) {
    // Current time: Thursday, July 3, 2025 at 11:03:59 PM WIB.
    return date('d F Y H:i', strtotime($dateString)); // Contoh format Indonesia
}

// --- Handle File Upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_type'])) {
    $upload_type = $_POST['upload_type'];
    $upload_course_id = (int)$_POST['course_id_upload'];
    $upload_pertemuan_id = (int)$_POST['pertemuan_id_upload']; // Ambil dari hidden field

    // Pastikan ada file yang diupload dan tidak ada error
    if (isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'][0] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['uploaded_file']; // Ambil array lengkap dari file yang diupload (satu file)
        $uploaded_file_name = $uploaded_file['name'][0]; 
        $uploaded_file_tmp_name = $uploaded_file['tmp_name'][0];

        $target_dir = "uploads/"; // Pastikan folder 'uploads' ada di root proyek Anda
        // Membuat folder uploads jika belum ada
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // 0777 adalah permission penuh, sesuaikan jika perlu
        }

        $unique_file_name = uniqid() . '_' . basename($uploaded_file_name); // Hindari konflik nama file
        $target_file_path_on_server = $target_dir . $unique_file_name;
        $file_extension = strtolower(pathinfo($uploaded_file_name, PATHINFO_EXTENSION));

        // Validasi tipe file sederhana (sesuaikan dengan accept di HTML)
        $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_types)) {
            echo "<script>alert('Format file tidak diizinkan! Hanya PDF, DOC, PPT, TXT, JPG, PNG, GIF.');</script>";
        } else {
            if (move_uploaded_file($uploaded_file_tmp_name, $target_file_path_on_server)) {
                if ($upload_type === 'materi') {
                    $stmt = $conn->prepare("INSERT INTO materials (course_id, title, file_path, file_type) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("isss", $upload_course_id, $uploaded_file_name, $target_file_path_on_server, $file_extension);
                        if ($stmt->execute()) {
                            echo "<script>alert('Materi berhasil diupload!');</script>";
                        } else {
                            // Hapus file yang sudah diupload jika gagal disimpan ke DB
                            if (file_exists($target_file_path_on_server)) unlink($target_file_path_on_server);
                            echo "<script>alert('Gagal menyimpan data materi ke database: " . $stmt->error . "');</script>";
                        }
                        $stmt->close();
                    }
                } elseif ($upload_type === 'tugas') {
                    // Untuk tugas, kita membuat entri assignment baru
                    $assignment_title = "Tugas: " . pathinfo($uploaded_file_name, PATHINFO_FILENAME); // Judul tanpa ekstensi
                    $assignment_description = "Silakan unduh file tugas ini.";
                    // Atur due_date, misal 1 minggu dari sekarang
                    $due_date = date('Y-m-d H:i:s', strtotime('+1 week')); 

                    $stmt = $conn->prepare("INSERT INTO assignments (course_id, title, description, due_date) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("isss", $upload_course_id, $assignment_title, $assignment_description, $due_date);
                        if ($stmt->execute()) {
                            // Logika untuk menyimpan file tugas ke tabel submissions belum diimplementasikan di sini,
                            // tapi ini sudah berhasil menyimpan data tugas baru ke tabel assignments.
                            // Jika tugas memiliki file yang perlu disimpan di DB (seperti path di tabel `assignments`),
                            // Anda mungkin perlu menambahkan kolom `file_path` di tabel `assignments` juga.
                            // Saat ini, `assignments` belum memiliki `file_path`. Untuk demo, kita asumsikan file tugas diupload ke server tapi tidak dicatat pathnya di tabel assignments.
                            // NOTE: Jika `assignments` memiliki `file_path`, pastikan untuk menampung `$target_file_path_on_server` di situ.
                            echo "<script>alert('Tugas berhasil diupload!');</script>";
                        } else {
                            // Hapus file yang sudah diupload jika gagal disimpan ke DB
                            if (file_exists($target_file_path_on_server)) unlink($target_file_path_on_server);
                            echo "<script>alert('Gagal menyimpan data tugas ke database: " . $stmt->error . "');</script>";
                        }
                        $stmt->close();
                    }
                }
            } else {
                echo "<script>alert('Maaf, terjadi kesalahan saat mengupload file Anda. Error: " . $uploaded_file['error'][0] . "');</script>";
            }
        }
    } else {
        echo "<script>alert('Tidak ada file yang diupload atau terjadi error upload. Pastikan Anda memilih file.');</script>";
    }
    // Redirect untuk membersihkan POST dan param upload, dan refresh data dengan GET parameter yang sama
    header("Location: materitugas-dosen.php?course_id={$upload_course_id}&pertemuan_id={$upload_pertemuan_id}");
    exit;
}


// --- Handle Delete Action ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['type']) && isset($_GET['id'])) {
    $delete_type = $_GET['type'];
    $delete_id = (int)$_GET['id'];
    // Ambil course_id dan pertemuan_id untuk redirect kembali
    $redirect_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
    $redirect_pertemuan_id = isset($_GET['pertemuan_id']) ? (int)$_GET['pertemuan_id'] : null;

    $sql_delete = "";
    if ($delete_type === 'materi') {
        // Ambil file_path dulu untuk menghapus file fisik
        $stmt_path = $conn->prepare("SELECT file_path FROM materials WHERE material_id = ?");
        if ($stmt_path) {
            $stmt_path->bind_param("i", $delete_id);
            $stmt_path->execute();
            $result_path = $stmt_path->get_result();
            if ($row_path = $result_path->fetch_assoc()) {
                $file_to_delete = $row_path['file_path'];
                if (file_exists($file_to_delete) && is_file($file_to_delete)) { // Pastikan itu file, bukan folder
                    unlink($file_to_delete); // Hapus file fisik
                }
            }
            $stmt_path->close();
        }
        $sql_delete = "DELETE FROM materials WHERE material_id = ?";
    } elseif ($delete_type === 'tugas') {
        // Untuk tugas, kita asumsikan tidak ada file_path di tabel assignments,
        // jadi kita hanya menghapus entri dari DB.
        // Jika tugas ada file fisik, Anda perlu menambahkan `file_path` di tabel `assignments`
        // dan melakukan unlink seperti pada materi.
        $sql_delete = "DELETE FROM assignments WHERE assignment_id = ?";
    } else {
        echo "<script>alert('Tipe delete tidak valid!');</script>";
        header("Location: materitugas-dosen.php?course_id={$redirect_course_id}&pertemuan_id={$redirect_pertemuan_id}");
        exit;
    }

    $stmt_delete = $conn->prepare($sql_delete);
    if ($stmt_delete) {
        $stmt_delete->bind_param("i", $delete_id);
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                echo "<script>alert('Berhasil menghapus " . htmlspecialchars($delete_type) . "!');</script>";
            } else {
                echo "<script>alert('" . htmlspecialchars($delete_type) . " tidak ditemukan atau sudah dihapus.');</script>";
            }
        } else {
            echo "<script>alert('Gagal menghapus " . htmlspecialchars($delete_type) . ": " . $stmt_delete->error . "');</script>";
        }
        $stmt_delete->close();
    }
    // Redirect untuk menghapus parameter GET dari URL dan refresh data
    header("Location: materitugas-dosen.php?course_id={$redirect_course_id}&pertemuan_id={$redirect_pertemuan_id}");
    exit;
}

// --- Fetch data for sidebar (all courses assigned to the lecturer) ---
$courses_data_for_sidebar = [];
$sql_all_courses = "SELECT course_id, course_name, course_code FROM courses WHERE lecturer_id = ? ORDER BY course_id ASC";
$stmt_all_courses = $conn->prepare($sql_all_courses);
if ($stmt_all_courses) {
    $stmt_all_courses->bind_param("i", $current_lecturer_id);
    $stmt_all_courses->execute();
    $result_all_courses = $stmt_all_courses->get_result();
    while ($row = $result_all_courses->fetch_assoc()) {
        $courses_data_for_sidebar[] = $row;
    }
    $stmt_all_courses->close();
} else {
    error_log("Error preparing all courses query: " . $conn->error);
}

// Set current_course_id dan course_name_for_header jika ada perubahan di sidebar
if (!empty($courses_data_for_sidebar)) {
    // Jika current_course_id dari GET tidak valid atau tidak diampu dosen ini
    $course_id_from_get = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
    $course_found_and_valid = false;
    foreach ($courses_data_for_sidebar as $course) {
        if ($course['course_id'] === $course_id_from_get) {
            $current_course_id = $course_id_from_get;
            $course_name_for_header = htmlspecialchars($course['course_name']);
            $current_pertemuan_id = $current_course_id; // Pertemuan ID sama dengan Course ID
            $course_found_and_valid = true;
            break;
        }
    }
    // Jika course_id dari GET tidak valid atau tidak ada, default ke course pertama yang diampu
    if (!$course_found_and_valid) {
        $current_course_id = $courses_data_for_sidebar[0]['course_id'];
        $current_pertemuan_id = $current_course_id; // Pertemuan ID sama dengan Course ID
        $course_name_for_header = htmlspecialchars($courses_data_for_sidebar[0]['course_name']);
    }
} else {
    $current_course_id = null; // Tidak ada course untuk ditampilkan
    $current_pertemuan_id = null;
    $course_name_for_header = "Tidak Ada Mata Kuliah Diampu";
}


// --- Fetch data for current course materials and assignments ---
$materials_data_for_current_course = [];
$assignments_data_for_current_course = [];
if ($current_course_id) { // Hanya fetch jika ada course yang dipilih
    $sql_materials = "SELECT material_id, title, description, file_path, file_type, uploaded_at FROM materials WHERE course_id = ? ORDER BY uploaded_at DESC";
    $stmt_materials = $conn->prepare($sql_materials);
    if ($stmt_materials) {
        $stmt_materials->bind_param("i", $current_course_id);
        $stmt_materials->execute();
        $result_materials = $stmt_materials->get_result();
        while ($row = $result_materials->fetch_assoc()) {
            $materials_data_for_current_course[] = $row;
        }
        $stmt_materials->close();
    } else {
        error_log("Error preparing materials query: " . $conn->error);
    }

    $sql_assignments = "SELECT assignment_id, title, description, due_date, created_at FROM assignments WHERE course_id = ? ORDER BY due_date ASC";
    $stmt_assignments = $conn->prepare($sql_assignments);
    if ($stmt_assignments) {
        $stmt_assignments->bind_param("i", $current_course_id);
        $stmt_assignments->execute();
        $result_assignments = $stmt_assignments->get_result();
        while ($row = $result_assignments->fetch_assoc()) {
            $assignments_data_for_current_course[] = $row;
        }
        $stmt_assignments->close();
    } else {
        error_log("Error preparing assignments query: " . $conn->error);
    }
}


// --- Fetch dosen name for header ---
$sql_lecturer_header = "SELECT full_name, gelar FROM users WHERE user_id = ? AND role = 'lecturer'";
$stmt_lecturer_header = $conn->prepare($sql_lecturer_header);
if ($stmt_lecturer_header) {
    $stmt_lecturer_header->bind_param("i", $current_lecturer_id);
    $stmt_lecturer_header->execute();
    $result_lecturer_header = $stmt_lecturer_header->get_result();
    if ($row_lecturer = $result_lecturer_header->fetch_assoc()) {
        $lecturer_full_name_header = htmlspecialchars($row_lecturer['full_name']);
        if (!empty($row_lecturer['gelar'])) {
             $lecturer_full_name_header .= ", " . htmlspecialchars($row_lecturer['gelar']);
        }
    }
    $stmt_lecturer_header->close();
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi & Tugas - <?php echo htmlspecialchars($course_name_for_header); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS Anda yang sudah ada */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            min-height: calc(100vh - 40px);
        }

        .header {
            background: linear-gradient(135deg, #89CFF0, #6FA8DC);
            padding: 30px;
            text-align: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .content-wrapper {
            display: flex;
            min-height: calc(100vh - 140px);
        }

        .sidebar {
            background: linear-gradient(180deg, #B8D4F0, #A1C7E8);
            padding: 30px 20px;
            width: 300px;
            min-width: 300px;
        }

        .sidebar h3 {
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        .pertemuan-btn {
            display: block;
            width: 100%;
            background: #FFF8DC;
            border: none;
            border-radius: 25px;
            padding: 15px 20px;
            margin: 10px 0;
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .pertemuan-btn:hover {
            background: #F0E68C;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .pertemuan-btn.active {
            background: #FFD700;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8f9fa;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .section-header {
            background: linear-gradient(135deg, #4A90E2, #357ABD);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            margin: -25px -25px 20px -25px;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .content-box {
            background: #f8f9ff;
            border: 2px solid #e0e7ff;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            min-height: 80px;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            background: linear-gradient(135deg, #89CFF0, #6FA8DC);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            min-width: 150px;
        }

        .action-btn:hover {
            background: linear-gradient(135deg, #6FA8DC, #5A8AC7);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .file-input {
            display: none;
        }

        .upload-area {
            border: 2px dashed #4A90E2;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f8f9ff;
            margin: 15px 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: #357ABD;
            background: #f0f4ff;
        }

        .upload-area.dragover {
            border-color: #FFD700;
            background: #fffdf0;
        }

        .file-list {
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .file-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-item .file-name {
            font-weight: 500;
            color: #333;
        }

        .file-item .file-size {
            color: #666;
            font-size: 0.9rem;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .remove-btn:hover {
            background: #c82333;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #4A90E2, #357ABD);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }

        .btn-close {
            filter: brightness(0) invert(1);
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            display: none; /* Akan dikontrol oleh JS untuk notifikasi */
        }

        @media (max-width: 768px) {
            .content-wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                min-width: unset;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .action-btn {
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header">
            <i class="fas fa-graduation-cap me-3"></i>
            <?php echo htmlspecialchars($course_name_for_header); ?>
        </div>
        
        <div class="content-wrapper">
            <div class="sidebar">
                <h3><i class="fas fa-calendar-alt me-2"></i>Jadwal</h3>
                <?php if (empty($courses_data_for_sidebar)): ?>
                    <p class="text-center text-muted">Tidak ada mata kuliah yang diampu.</p>
                <?php else: ?>
                    <?php foreach ($courses_data_for_sidebar as $course): ?>
                        <button class="pertemuan-btn <?php echo ($current_course_id == $course['course_id']) ? 'active' : ''; ?>" onclick="window.location.href='materitugas-dosen.php?course_id=<?php echo htmlspecialchars($course['course_id']); ?>';">
                            <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($course['course_name']); ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="main-content">
                <?php if ($current_course_id === null): ?>
                    <div class="section-card">
                        <div class="section-header">
                            <i class="fas fa-info-circle me-2"></i>Informasi
                        </div>
                        <div class="content-box">
                            <p class="text-muted">Pilih mata kuliah dari sidebar untuk mengelola materi dan tugas.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="section-card">
                        <div class="section-header">
                            <i class="fas fa-file-alt me-2"></i>Materi
                        </div>
                        <div class="content-box" id="materiContent">
                            <?php if (empty($materials_data_for_current_course)): ?>
                                <p class="text-muted">Tidak ada materi untuk mata kuliah ini.</p>
                            <?php else: ?>
                                <p>Materi yang tersedia: <?php echo htmlspecialchars(implode(', ', array_column($materials_data_for_current_course, 'title'))); ?>. Selengkapnya di daftar file.</p>
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons">
                            <button class="action-btn" onclick="pilihMateri()">
                                <i class="fas fa-folder-open me-2"></i>Pilih Materi
                            </button>
                            <button class="action-btn" onclick="showUploadModal('materi')">
                                <i class="fas fa-upload me-2"></i>Upload Materi
                            </button>
                        </div>
                        <div class="file-list" id="materiFileList">
                            <?php if (!empty($materials_data_for_current_course)): ?>
                                <?php foreach ($materials_data_for_current_course as $material): ?>
                                    <div class="file-item">
                                        <div>
                                            <div class="file-name">
                                                <i class="<?php echo getFileIcon($material['file_type']); ?> me-2"></i>
                                                <a href="<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($material['title']); ?></a>
                                            </div>
                                            <div class="file-size">Diunggah: <?php echo formatDateForDisplay($material['uploaded_at']); ?></div>
                                        </div>
                                        <button class="remove-btn" onclick="confirmDeleteFile('materi', <?php echo $material['material_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        </div>
                    
                    <div class="section-card">
                        <div class="section-header">
                            <i class="fas fa-tasks me-2"></i>Tugas
                        </div>
                        <div class="content-box" id="tugasContent">
                            <?php if (empty($assignments_data_for_current_course)): ?>
                                <p class="text-muted">Tidak ada tugas untuk mata kuliah ini.</p>
                            <?php else: ?>
                                <p>Tugas yang tersedia: <?php echo htmlspecialchars(implode(', ', array_column($assignments_data_for_current_course, 'title'))); ?>.</p>
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons">
                            <button class="action-btn" onclick="pilihTugas()">
                                <i class="fas fa-folder-open me-2"></i>Pilih Tugas
                            </button>
                            <button class="action-btn" onclick="showUploadModal('tugas')">
                                <i class="fas fa-upload me-2"></i>Upload Tugas
                            </button>
                        </div>
                        <div class="file-list" id="tugasFileList">
                            <?php if (!empty($assignments_data_for_current_course)): ?>
                                <?php foreach ($assignments_data_for_current_course as $assignment): ?>
                                    <div class="file-item">
                                        <div>
                                            <div class="file-name">
                                                <i class="<?php echo getFileIcon('pdf'); ?> me-2"></i> <?php echo htmlspecialchars($assignment['title']); ?>
                                            </div>
                                            <div class="file-size">Deadline: <?php echo formatDateForDisplay($assignment['due_date']); ?></div>
                                        </div>
                                        <button class="remove-btn" onclick="confirmDeleteFile('tugas', <?php echo $assignment['assignment_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalTitle">
                        <i class="fas fa-upload me-2"></i>Upload File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" method="POST" action="materitugas-dosen.php" enctype="multipart/form-data">
                        <input type="hidden" name="upload_type" id="modalUploadType" value="">
                        <input type="hidden" name="course_id_upload" id="modalCourseIdUpload" value="<?php echo htmlspecialchars($current_course_id ?? ''); ?>">
                        <input type="hidden" name="pertemuan_id_upload" id="modalPertemuanIdUpload" value="<?php echo htmlspecialchars($current_pertemuan_id ?? ''); ?>">
                        
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-primary"></i>
                            <h5>Drag & Drop file di sini</h5>
                            <p class="text-muted">atau klik untuk memilih file</p>
                            <small class="text-muted">Format yang didukung: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, PNG, GIF</small>
                        </div>
                        <input type="file" id="modalFileInput" name="uploaded_file[]" required accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.png,.gif">
                        <div class="file-list" id="modalFileList"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // currentCourseId dan currentPertemuan akan diset dari PHP di bagian atas file
        // Digunakan untuk mengirimkan ID course saat upload
        const currentCourseId_js = <?php echo htmlspecialchars(json_encode($current_course_id)); ?>;
        const currentPertemuan_js = <?php echo htmlspecialchars(json_encode($current_pertemuan_id)); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Update hidden input fields in the modal form with current course_id
            document.getElementById('modalCourseIdUpload').value = currentCourseId_js;
            document.getElementById('modalPertemuanIdUpload').value = currentPertemuan_js;

            // Initialize drag and drop for modal upload area
            const uploadArea = document.getElementById('uploadArea');
            const modalFileInput = document.getElementById('modalFileInput');

            uploadArea.addEventListener('click', () => modalFileInput.click());

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
            });

            modalFileInput.addEventListener('change', (e) => {
                displayModalFiles(e.target.files);
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                modalFileInput.files = e.dataTransfer.files; // Assign dropped files to input
                displayModalFiles(e.dataTransfer.files);
            });
        });

        // Function to display selected files in modal file list
        function displayModalFiles(files) {
            const modalFileList = document.getElementById('modalFileList');
            modalFileList.innerHTML = ''; // Clear previous files

            if (files.length === 0) return;

            Array.from(files).forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div>
                        <div class="file-name">
                            <i class="fas fa-file me-2"></i>${file.name}
                        </div>
                        <div class="file-size">${formatFileSize(file.size)}</div>
                    </div>
                `;
                modalFileList.appendChild(fileItem);
            });
        }
        
        // Helper to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Placeholder for pilihMateri() and pilihTugas() - Tidak ada fungsionalitas di sini
        function pilihMateri() {
            alert('Fungsi "Pilih Materi" akan diimplementasikan untuk navigasi ke direktori server atau tampilan detail materi.');
        }

        function pilihTugas() {
            alert('Fungsi "Pilih Tugas" akan diimplementasikan untuk navigasi ke direktori server atau tampilan detail tugas.');
        }
        
        // Function to open upload modal
        function showUploadModal(type) {
            const title = type === 'materi' ? 'Upload Materi' : 'Upload Tugas';
            document.getElementById('uploadModalTitle').innerHTML = `<i class="fas fa-upload me-2"></i>${title}`;
            document.getElementById('modalUploadType').value = type;
            document.getElementById('modalFileInput').value = ''; // Clear selected files from input
            document.getElementById('modalFileList').innerHTML = ''; // Clear file list in modal
            
            // Perbarui juga action form modal jika diperlukan, walau sudah statis ke materitugas-dosen.php
            // document.getElementById('uploadForm').action = `materitugas-dosen.php?course_id=${currentCourseId_js}&pertemuan_id=${currentPertemuan_js}`;

            const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
            modal.show();
        }

        // Fungsi untuk konfirmasi delete file (akan redirect ke halaman PHP dengan parameter GET)
        function confirmDeleteFile(type, id) {
            if (confirm(`Apakah Anda yakin ingin menghapus ${type} ini?`)) {
                // Redirect ke PHP untuk aksi delete
                window.location.href = `materitugas-dosen.php?action=delete&type=${type}&id=${id}&course_id=${currentCourseId_js}&pertemuan_id=${currentPertemuan_js}`;
            }
        }
    </script>
</body>
</html>