<?php
// Pastikan db_connection.php ada di direktori yang sama atau di path yang benar
include 'db_connection.php'; // Meng-include file koneksi database Anda

session_start(); // Mulai sesi untuk manajemen login

// Asumsi user_id dosen yang sedang login adalah 2 (Dr. Budi Santosa)
// Di aplikasi nyata, user_id ini akan didapat dari sesi login yang sebenarnya.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // Ganti dengan ID dosen yang sesuai dari database Anda
    $_SESSION['role'] = 'lecturer'; // Asumsi role juga diset di sesi
}

// Periksa apakah pengguna sudah login dan memiliki peran 'lecturer'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php"); // Redirect ke halaman login jika belum login atau bukan dosen
    exit();
}

$lecturer_user_id = $_SESSION['user_id']; // Dosen yang sedang login

// Inisialisasi variabel untuk data profil yang akan ditampilkan
// Nilai default "Memuat..." akan diganti oleh data dari DB
$profileData = [
    'nama' => 'Memuat...',
    'gelar' => 'Memuat...',
    'nip' => 'Memuat...',
    'fakultas' => 'Memuat...', // Fakultas tidak ada di tabel users, akan diisi statis atau disesuaikan
    'prodi' => 'Memuat...',
    'jabatan' => 'Memuat...',
    'bidang' => 'Memuat...',
    'status' => 'Memuat...',
    'email' => 'Memuat...',
    'telepon' => 'Memuat...',
    'ruang' => 'Memuat...',
    'jamKonsul' => 'Memuat...',
    'ttl_tempat' => 'Memuat...',
    'ttl_tanggal' => 'Memuat...',
    'gender' => 'Memuat...',
    'agama' => 'Memuat...',
    'alamat' => 'Memuat...',
];

// --- Handle Form Submission (UPDATE) ---
// Ketika form di modal disubmit, dia akan POST kembali ke halaman ini
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form POST
    $full_name = $_POST['nama'] ?? '';
    $gelar = $_POST['gelar'] ?? '';
    $nip = $_POST['nip'] ?? ''; // Menggunakan NIP dari form, akan disimpan ke kolom 'nik'
    $fakultas = $_POST['fakultas'] ?? ''; // Fakultas tidak ada di tabel users, ini bisa diabaikan atau disesuaikan jika ada tabel fakultas
    $prodi_dosen = $_POST['prodi'] ?? ''; // ini akan masuk ke study_program di tabel users
    $jabatan = $_POST['jabatan'] ?? '';
    $bidang = $_POST['bidang'] ?? '';
    $status_pegawai = $_POST['status'] ?? '';
    $email = $_POST['email'] ?? '';
    $telepon = $_POST['telepon'] ?? '';
    $ruang = $_POST['ruang'] ?? '';
    $jamKonsul = $_POST['jamKonsul'] ?? '';
    
    // Memisahkan TTL dari format "Tempat, DD Bulan YYYY"
    $ttl_input = $_POST['ttl'] ?? '';
    $ttl_parts = explode(', ', $ttl_input, 2); // Split once at ", "
    $place_of_birth = $ttl_parts[0] ?? null; // Jika tidak ada koma, tempat lahir adalah seluruh string
    $date_of_birth_str = $ttl_parts[1] ?? null; // Tanggal dalam format string 'DD Bulan YYYY'
    $date_of_birth = null;
    if ($date_of_birth_str) {
        // Coba parsing tanggal dengan DateTime::createFromFormat karena strtotime bisa kurang reliable
        $date_obj = DateTime::createFromFormat('d F Y', $date_of_birth_str);
        if ($date_obj) {
            $date_of_birth = $date_obj->format('Y-m-d'); // Format ke YYYY-MM-DD untuk MySQL
        }
    }

    $gender = $_POST['gender'] ?? '';
    $agama = $_POST['agama'] ?? '';
    $alamat = $_POST['alamat'] ?? '';

    // Validasi sederhana
    if (empty($full_name) || empty($email)) {
        echo "<script>alert('Nama lengkap dan email harus diisi!');</script>";
    } else {
        // Update data di tabel users
        $sql_update = "UPDATE users SET
                            full_name = ?,
                            gelar = ?,
                            nik = ?, -- Menyimpan NIP ke kolom nik
                            email = ?,
                            phone_number = ?,
                            gender = ?,
                            study_program = ?, -- Menyimpan Program Studi Dosen
                            religion = ?,
                            place_of_birth = ?,
                            date_of_birth = ?,
                            address = ?,
                            jabatan_akademik = ?,
                            bidang_keahlian = ?,
                            status_kepegawaian = ?,
                            ruang_kerja = ?,
                            jam_konsultasi = ?
                        WHERE user_id = ? AND role = 'lecturer'";

        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            // Perhatikan urutan dan tipe data di bind_param sesuai dengan urutan kolom di UPDATE query
            // s = string, i = integer, d = double, b = blob
            $stmt_update->bind_param("sssssssssssssssi",
                $full_name, $gelar, $nip, $email, $telepon, $gender, $prodi_dosen, $agama,
                $place_of_birth, $date_of_birth, $alamat, $jabatan, $bidang, $status_pegawai,
                $ruang, $jamKonsul, $lecturer_user_id
            );

            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0) {
                    echo "<script>alert('Profil berhasil diperbarui!');</script>";
                } else {
                    echo "<script>alert('Profil berhasil diperbarui (tidak ada perubahan terdeteksi).');</script>";
                }
            } else {
                echo "<script>alert('Gagal memperbarui profil: " . $stmt_update->error . "');</script>";
            }
            $stmt_update->close();
        } else {
            echo "<script>alert('Gagal menyiapkan statement UPDATE: " . $conn->error . "');</script>";
        }
    }
}

// --- Fetch Data for Display (Initial Load or After Update) ---
$sql_fetch = "SELECT
                full_name,
                gelar,
                nik AS nip, -- Mengambil NIK sebagai NIP untuk ditampilkan
                email,
                phone_number,
                gender,
                study_program, -- Akan digunakan sebagai Prodi Dosen
                religion,
                place_of_birth,
                date_of_birth,
                ruang_kerja,
                jam_konsultasi,
                jabatan_akademik,
                bidang_keahlian,
                status_kepegawaian,
                address
            FROM
                users
            WHERE
                user_id = ? AND role = 'lecturer'";

$stmt_fetch = $conn->prepare($sql_fetch);
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $lecturer_user_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    if ($row = $result_fetch->fetch_assoc()) {
        $profileData['nama'] = htmlspecialchars($row['full_name'] ?? 'N/A');
        $profileData['gelar'] = htmlspecialchars($row['gelar'] ?? 'N/A');
        $profileData['nip'] = htmlspecialchars($row['nip'] ?? 'N/A'); // Gunakan 'nik' dari DB sebagai NIP
        $profileData['fakultas'] = htmlspecialchars($row['study_program'] ? ($row['study_program'] === 'Teknik Informatika' ? 'Fakultas Ilmu Komputer' : 'Lainnya') : 'N/A'); // Asumsi mapping fakultas dari prodi
        $profileData['prodi'] = htmlspecialchars($row['study_program'] ?? 'N/A');
        $profileData['jabatan'] = htmlspecialchars($row['jabatan_akademik'] ?? 'N/A');
        $profileData['bidang'] = htmlspecialchars($row['bidang_keahlian'] ?? 'N/A');
        $profileData['status'] = htmlspecialchars($row['status_kepegawaian'] ?? 'N/A');
        $profileData['email'] = htmlspecialchars($row['email'] ?? 'N/A');
        $profileData['telepon'] = htmlspecialchars($row['phone_number'] ?? 'N/A');
        $profileData['ruang'] = htmlspecialchars($row['ruang_kerja'] ?? 'N/A');
        $profileData['jamKonsul'] = htmlspecialchars($row['jam_konsultasi'] ?? 'N/A');
        // Format tanggal lahir kembali ke format "DD Bulan YYYY"
        $profileData['ttl_tempat'] = htmlspecialchars($row['place_of_birth'] ?? 'N/A');
        $profileData['ttl_tanggal'] = ($row['date_of_birth'] ? (new DateTime($row['date_of_birth']))->format('d F Y') : 'N/A');
        $profileData['gender'] = htmlspecialchars($row['gender'] ?? 'N/A');
        $profileData['agama'] = htmlspecialchars($row['religion'] ?? 'N/A');
        $profileData['alamat'] = htmlspecialchars($row['address'] ?? 'N/A');
    }
    $stmt_fetch->close();
} else {
    echo "<script>alert('Gagal mengambil data profil: " . $conn->error . "');</script>";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Dosen - <?php echo htmlspecialchars($profileData['nama']); ?></title>
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

        .navbar {
            background: linear-gradient(90deg, var(--dark-teal) 0%, var(--secondary-blue) 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            color: var(--white) !important;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .user-info {
            color: var(--white);
            margin-left: auto;
        }

        .container-fluid {
            min-height: 100vh;
            padding: 2rem 0;
        }

        .profile-card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-header {
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--dark-teal) 100%);
            color: var(--white);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: var(--dark-teal);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .profile-name {
            font-size: 2.2rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .profile-title {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .edit-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .edit-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            color: var(--white);
        }

        .profile-content {
            padding: 2rem;
        }

        .info-section {
            background: var(--light-green);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 5px solid var(--secondary-blue);
        }

        .section-title {
            color: var(--dark-teal);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            background: var(--white);
            padding: 1.2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .info-item:hover {
            transform: translateY(-3px);
        }

        .info-label {
            font-weight: 600;
            color: var(--dark-teal);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .info-value {
            color: #333;
            font-size: 1.1rem;
            line-height: 1.4;
        }

        .btn-custom {
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--dark-teal) 100%);
            border: none;
            color: var(--white);
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 500;
            margin: 0.5rem;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: var(--white);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--secondary-blue) 0%, var(--dark-teal) 100%);
            color: var(--white);
            border-radius: 15px 15px 0 0;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--primary-blue);
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 0.2rem rgba(99, 163, 241, 0.25);
        }

        .academic-info {
            background: linear-gradient(135deg, var(--primary-blue) 0%, rgba(99, 163, 241, 0.1) 100%);
        }

        .contact-info {
            background: linear-gradient(135deg, var(--light-green) 0%, rgba(250, 255, 238, 0.7) 100%);
        }

        .personal-info {
            background: linear-gradient(135deg, rgba(79, 138, 158, 0.1) 0%, var(--primary-blue) 100%);
        }

        .save-changes-section {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(99, 163, 241, 0.1) 0%, rgba(182, 208, 239, 0.2) 100%);
            border-radius: 15px;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-name {
                font-size: 1.8rem;
            }
            
            .edit-btn {
                position: static;
                margin-top: 1rem;
            }

            .profile-header {
                padding: 1.5rem;
            }
        }

        .alert-custom {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-graduate me-2"></i>
                Portal Dosen
            </a>
            <div class="user-info">
                <span id="navUserName"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($profileData['nama']); ?></span>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="container">
            <div class="profile-card">
                <div class="profile-header">
                    <button class="btn edit-btn" data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="fas fa-edit me-2"></i>Edit Profil
                    </button>
                    
                    <div class="profile-avatar">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    
                    <h1 class="profile-name" id="displayName"><?php echo htmlspecialchars($profileData['nama'] . ', ' . $profileData['gelar']); ?></h1>
                    <p class="profile-title" id="displayPosition"><?php echo htmlspecialchars($profileData['jabatan']); ?></p>
                </div>

                <div class="profile-content">
                    <div class="info-section academic-info">
                        <h3 class="section-title">
                            <i class="fas fa-graduation-cap"></i>
                            Informasi Akademik
                        </h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">NIP</div>
                                <div class="info-value" id="displayNIP"><?php echo htmlspecialchars($profileData['nip']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Fakultas</div>
                                <div class="info-value" id="displayFakultas"><?php echo htmlspecialchars($profileData['fakultas']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Program Studi</div>
                                <div class="info-value" id="displayProdi"><?php echo htmlspecialchars($profileData['prodi']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Jabatan Akademik</div>
                                <div class="info-value" id="displayJabatan"><?php echo htmlspecialchars($profileData['jabatan']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bidang Keahlian</div>
                                <div class="info-value" id="displayBidang"><?php echo htmlspecialchars($profileData['bidang']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status Kepegawaian</div>
                                <div class="info-value" id="displayStatus"><?php echo htmlspecialchars($profileData['status']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="info-section contact-info">
                        <h3 class="section-title">
                            <i class="fas fa-address-book"></i>
                            Informasi Kontak
                        </h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Email Institusi</div>
                                <div class="info-value" id="displayEmail"><?php echo htmlspecialchars($profileData['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Telepon</div>
                                <div class="info-value" id="displayTelepon"><?php echo htmlspecialchars($profileData['telepon']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Ruang Kerja</div>
                                <div class="info-value" id="displayRuang"><?php echo htmlspecialchars($profileData['ruang']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Jam Konsultasi</div>
                                <div class="info-value" id="displayJamKonsul"><?php echo htmlspecialchars($profileData['jamKonsul']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="info-section personal-info">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Informasi Pribadi
                        </h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Tempat, Tanggal Lahir</div>
                                <div class="info-value" id="displayTTL"><?php echo htmlspecialchars($profileData['ttl_tempat'] . ', ' . $profileData['ttl_tanggal']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Jenis Kelamin</div>
                                <div class="info-value" id="displayGender"><?php echo htmlspecialchars($profileData['gender']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Agama</div>
                                <div class="info-value" id="displayAgama"><?php echo htmlspecialchars($profileData['agama']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Alamat Rumah</div>
                                <div class="info-value" id="displayAlamat"><?php echo htmlspecialchars($profileData['alamat']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit Profil Saya
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="profileForm" method="POST" action="profile-dosen.php">
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-graduation-cap me-2"></i>Informasi Akademik
                                </h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nama" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="nama" name="nama" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gelar" class="form-label">Gelar</label>
                                <input type="text" class="form-control" id="gelar" name="gelar">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nip" class="form-label">NIP</label>
                                <input type="text" class="form-control" id="nip" name="nip">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fakultas" class="form-label">Fakultas</label>
                                <select class="form-select" id="fakultas" name="fakultas">
                                    <option value="">Pilih Fakultas</option>
                                    <option value="Fakultas Teknik">Fakultas Teknik</option>
                                    <option value="Fakultas Ekonomi dan Bisnis">Fakultas Ekonomi dan Bisnis</option>
                                    <option value="Fakultas Hukum">Fakultas Hukum</option>
                                    <option value="Fakultas Matematika dan Ilmu Pengetahuan Alam">Fakultas Matematika dan Ilmu Pengetahuan Alam</option>
                                    <option value="Fakultas Ilmu Sosial dan Politik">Fakultas Ilmu Sosial dan Politik</option>
                                    <option value="Fakultas Kedokteran">Fakultas Kedokteran</option>
                                    <option value="Fakultas Ilmu Komputer">Fakultas Ilmu Komputer</option> </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prodi" class="form-label">Program Studi</label>
                                <input type="text" class="form-control" id="prodi" name="prodi">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="jabatan" class="form-label">Jabatan Akademik</label>
                                <select class="form-select" id="jabatan" name="jabatan">
                                    <option value="">Pilih Jabatan</option>
                                    <option value="Asisten Ahli">Asisten Ahli</option>
                                    <option value="Lektor">Lektor</option>
                                    <option value="Lektor Kepala">Lektor Kepala</option>
                                    <option value="Guru Besar">Guru Besar</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="bidang" class="form-label">Bidang Keahlian</label>
                                <input type="text" class="form-control" id="bidang" name="bidang">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status Kepegawaian</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Pilih Status</option>
                                    <option value="Pegawai Negeri Sipil">Pegawai Negeri Sipil</option>
                                    <option value="Pegawai Tetap">Pegawai Tetap</option>
                                    <option value="Pegawai Kontrak">Pegawai Kontrak</option>
                                </select>
                            </div>
                        </div>

                        <hr>

                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-address-book me-2"></i>Informasi Kontak
                                </h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Institusi</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telepon" class="form-label">Telepon</label>
                                <input type="text" class="form-control" id="telepon" name="telepon">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ruang" class="form-label">Ruang Kerja</label>
                                <input type="text" class="form-control" id="ruang" name="ruang">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="jamKonsul" class="form-label">Jam Konsultasi</label>
                                <input type="text" class="form-control" id="jamKonsul" name="jamKonsul">
                            </div>
                        </div>

                        <hr>

                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-user me-2"></i>Informasi Pribadi
                                </h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ttl" class="form-label">Tempat, Tanggal Lahir</label>
                                <input type="text" class="form-control" id="ttl" name="ttl">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Jenis Kelamin</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="agama" class="form-label">Agama</label>
                                <select class="form-select" id="agama" name="agama">
                                    <option value="">Pilih Agama</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                    <option value="Konghucu">Konghucu</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="alamat" class="form-label">Alamat Rumah</label>
                                <textarea class="form-control" id="alamat" name="alamat" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-custom">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Event listener untuk tombol "Edit Profil" untuk mengisi modal
            document.querySelector('.edit-btn').addEventListener('click', function() {
                // Mengambil nilai dari elemen tampilan di halaman dan mengisikannya ke form modal
                document.getElementById('nama').value = document.getElementById('displayName').textContent.split(',')[0].trim();
                // Mengambil gelar dari elemen displayName, bisa kosong jika tidak ada koma
                const displayGelar = document.getElementById('displayName').textContent.split(',')[1];
                document.getElementById('gelar').value = displayGelar ? displayGelar.trim() : '';
                
                document.getElementById('nip').value = document.getElementById('displayNIP').textContent;
                document.getElementById('fakultas').value = document.getElementById('displayFakultas').textContent;
                document.getElementById('prodi').value = document.getElementById('displayProdi').textContent;
                document.getElementById('jabatan').value = document.getElementById('displayJabatan').textContent;
                document.getElementById('bidang').value = document.getElementById('displayBidang').textContent;
                document.getElementById('status').value = document.getElementById('displayStatus').textContent;
                document.getElementById('email').value = document.getElementById('displayEmail').textContent;
                document.getElementById('telepon').value = document.getElementById('displayTelepon').textContent;
                document.getElementById('ruang').value = document.getElementById('displayRuang').textContent;
                document.getElementById('jamKonsul').value = document.getElementById('displayJamKonsul').textContent;
                document.getElementById('ttl').value = document.getElementById('displayTTL').textContent; // Sudah diformat "Tempat, DD Bulan YYYY"
                document.getElementById('gender').value = document.getElementById('displayGender').textContent;
                document.getElementById('agama').value = document.getElementById('displayAgama').textContent;
                document.getElementById('alamat').value = document.getElementById('displayAlamat').textContent;
            });
        });
    </script>
</body>
</html>