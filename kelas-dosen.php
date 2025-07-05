<?php
include 'db_connection.php'; // Meng-include file koneksi database

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

$lecturer_user_id = $_SESSION['user_id'];
$all_classes_data_for_js = []; // Variabel PHP yang akan di-JSON-encode untuk JavaScript
$lecturer_name_for_header = "Dosen"; // Nama default untuk header

// --- Ambil data Kelas yang diajar oleh Dosen ini ---
$classes_data_raw = [];
$sql_courses = "SELECT course_id, course_name, course_code, credits, lecturer_id FROM courses WHERE lecturer_id = ? ORDER BY course_name ASC";
$stmt_courses = $conn->prepare($sql_courses);
if ($stmt_courses) {
    $stmt_courses->bind_param("i", $lecturer_user_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($row_course = $result_courses->fetch_assoc()) {
        $classes_data_raw[$row_course['course_id']] = $row_course;
    }
    $stmt_courses->close();
}

// --- Ambil semua mahasiswa yang terdaftar di mata kuliah yang diajar oleh Dosen ini ---
$students_by_course = [];
if (!empty($classes_data_raw)) {
    // Buat daftar course_id yang diajar dosen ini untuk query mahasiswa
    $course_ids_in = implode(',', array_keys($classes_data_raw));
    
    $sql_students_enrollments = "
        SELECT
            u.user_id AS student_id,
            u.full_name AS student_name,
            u.nim,
            ce.course_id
        FROM
            users u
        JOIN
            course_enrollments ce ON u.user_id = ce.student_id
        WHERE
            u.role = 'student' AND ce.course_id IN ($course_ids_in)
        ORDER BY
            ce.course_id, u.full_name;
    ";
    // Untuk IN clause, tidak bisa menggunakan prepared statement dengan bind_param langsung untuk array.
    // Kita harus memvalidasi $course_ids_in atau membuat prepared statement dinamis.
    // Karena ini adalah ID integer yang sudah berasal dari database sebelumnya,
    // kita asumsikan aman untuk langsung dimasukkan, tapi idealnya harus divalidasi lebih lanjut
    // atau menggunakan teknik prepared statement dinamis.
    $result_students_enrollments = $conn->query($sql_students_enrollments);
    if ($result_students_enrollments) {
        while ($row_student = $result_students_enrollments->fetch_assoc()) {
            $course_id = $row_student['course_id'];
            if (!isset($students_by_course[$course_id])) {
                $students_by_course[$course_id] = [];
            }
            $students_by_course[$course_id][] = [
                'id' => $row_student['student_id'],
                'name' => $row_student['student_name'],
                'nim' => $row_student['nim']
            ];
        }
    } else {
        error_log("Error fetching student enrollments: " . $conn->error);
    }
}


// Gabungkan data untuk ALL_CLASSES_DATA JavaScript
foreach ($classes_data_raw as $course_id => $class_info) {
    $students_in_this_course = $students_by_course[$course_id] ?? [];
    $all_classes_data_for_js[htmlspecialchars($class_info['course_name'])] = [ // Gunakan nama kelas sebagai key
        'id' => $class_info['course_id'],
        'code' => $class_info['course_code'],
        'name' => $class_info['course_name'],
        'credits' => $class_info['credits'],
        'students' => $students_in_this_course
    ];
}

// Ambil nama dosen untuk header
$sql_lecturer_name = "SELECT full_name, gelar FROM users WHERE user_id = ? AND role = 'lecturer'";
$stmt_lecturer_name = $conn->prepare($sql_lecturer_name);
if ($stmt_lecturer_name) {
    $stmt_lecturer_name->bind_param("i", $lecturer_user_id);
    $stmt_lecturer_name->execute();
    $result_lecturer_name = $stmt_lecturer_name->get_result();
    if ($row_lecturer = $result_lecturer_name->fetch_assoc()) {
        $lecturer_name_for_header = htmlspecialchars($row_lecturer['full_name']);
        if (!empty($row_lecturer['gelar'])) {
             $lecturer_name_for_header .= ", " . htmlspecialchars($row_lecturer['gelar']);
        }
    }
    $stmt_lecturer_name->close();
}


$conn->close(); // Tutup koneksi database
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Halaman Dosen - Kelas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&family=Playfair+Display:wght@400;500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background-color: #f0f2f5;
      font-family: 'Poppins', sans-serif;
    }

    .header {
      background-color: #b3cce6;
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header-title {
      font-weight: 600;
      color: #333;
      font-size: 1.2rem;
      margin: 0;
      font-family: 'Montserrat', sans-serif;
    }

    .header-buttons {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .header-buttons a {
      color: #333;
      text-decoration: none;
      font-weight: 500;
    }

    .avatar-header {
      width: 30px;
      height: 30px;
      background-color: #ffffff;
      border-radius: 50%;
      border: 2px solid #b3cce6;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .kelas-card {
      background-color: #ffffff;
      border-radius: 10px;
      border: 2px solid #b3cce6;
      overflow: hidden;
      margin-bottom: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: transform 0.3s, box-shadow 0.3s;
      cursor: pointer;
      height: 100%;
    }

    .kelas-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }

    .kelas-header {
      background-color: #b3cce6;
      padding: 15px;
      position: relative;
    }

    .kelas-title {
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
      font-family: 'Montserrat', sans-serif;
    }

    .kelas-subtitle {
      color: #555;
      font-size: 0.9rem;
      font-family: 'Poppins', sans-serif;
    }

    .kelas-body {
      padding: 15px;
    }

    .kelas-info {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
    }

    .kelas-info-item {
      font-size: 0.85rem;
      color: #666;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .kelas-progress {
      height: 8px;
      margin-bottom: 15px;
    }

    .kelas-action {
      display: flex;
      justify-content: flex-end;
      align-items: center;
    }

    .btn-join {
      background-color: #b3cce6;
      color: #333;
      border: none;
      padding: 5px 15px;
      border-radius: 5px;
      font-weight: 500;
      transition: background-color 0.3s;
    }

    .btn-join:hover {
      background-color: #9abbe0;
    }

    .main-container {
      padding: 0 15px;
    }

    /* Anggota Kelas Styles */
    .anggota-page {
      display: none;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px 0;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 1000;
      overflow-y: auto;
    }

    .header-container {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 20px 30px;
      margin-bottom: 30px;
      box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
      border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .header-title-anggota {
      color: white;
      font-weight: 600;
      font-size: 1.5rem;
      margin: 0;
      text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .header-btn {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      padding: 8px 16px;
      border-radius: 12px;
      font-weight: 500;
      transition: all 0.3s ease;
      backdrop-filter: blur(5px);
    }

    .header-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
      color: white;
    }

    .back-btn {
      background: rgba(108, 117, 125, 0.8) !important;
    }

    .back-btn:hover {
      background: rgba(108, 117, 125, 1) !important;
    }

    .students-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 15px;
      max-width: 800px;
      margin: 0 auto;
    }

    .student-card {
      background: rgba(255, 255, 255, 0.9);
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }

    .student-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .student-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
      background: rgba(255, 255, 255, 0.95);
    }

    .student-name {
      font-weight: 600;
      font-size: 1.1rem;
      color: #2c3e50;
      margin: 0;
      text-align: center;
    }

    .student-card.selected {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      transform: scale(1.02);
    }

    .student-card.selected .student-name {
      color: white;
    }

    .search-container {
      max-width: 500px;
      margin: 0 auto 30px auto;
      position: relative;
    }

    .search-input {
      width: 100%;
      padding: 15px 50px 15px 20px;
      border: none;
      border-radius: 15px;
      background: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }

    .search-input:focus {
      outline: none;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
      background: white;
    }

    .search-icon {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
    }

    @media (max-width: 768px) {
      .students-grid {
        grid-template-columns: 1fr;
        gap: 12px;
      }
      
      .header-container {
        padding: 15px 20px;
      }
      
      .header-title-anggota {
        font-size: 1.2rem;
      }
      
      .header-actions {
        gap: 10px;
      }
      
      .student-card {
        padding: 15px;
      }
    }

    .fade-in {
      animation: fadeIn 0.5s ease-in;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>
<body>
  <div id="dosenPage" class="container mt-4 mb-4">
    <div class="header">
      <h1 class="header-title">Halaman Dosen</h1>
      <div class="header-buttons">
        <a href="#" onclick="showPesan()">Pesan</a>
        <span>|</span>
        <a href="#" onclick="logout()">Logout</a>
        <div class="avatar-header">
          <i class="fas fa-chalkboard-teacher"></i>
        </div>
      </div>
    </div>

    <div class="main-container">
      <div class="row" id="kelasContainer">
        <div class="text-center text-muted py-5">
            <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
            <p>Memuat daftar kelas...</p>
        </div>
      </div>
    </div>
  </div>

  <div id="anggotaPage" class="anggota-page">
    <div class="container">
      <div class="header-container d-flex justify-content-between align-items-center">
        <h1 class="header-title-anggota" id="anggotaTitle"></h1>
        <div class="header-actions">
          <button class="header-btn back-btn" onclick="backToDosen()">
            <i class="fas fa-arrow-left me-2"></i>Kembali
          </button>
          <button class="header-btn" onclick="showPesanAnggota()">
            <i class="fas fa-envelope me-2"></i>Pesan
          </button>
        </div>
      </div>
      
      <div class="search-container">
        <input type="text" class="search-input" id="searchInput" placeholder="Cari nama mahasiswa..." onkeyup="searchStudents()">
        <i class="fas fa-search search-icon"></i>
      </div>
      
      <div class="students-grid" id="studentsGrid">
        </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Global variables to store PHP-rendered data for client-side JS manipulation (search, select)
    let ALL_CLASSES_DATA = <?php echo json_encode($all_classes_data_for_js); ?>; //
    let CURRENT_STUDENTS_LIST = []; // The list of students for the currently displayed class
    let SELECTED_STUDENTS = []; // Stores currently selected students in anggotaPage
    let CURRENT_KELAS_NAME = ''; // Stores the name of the currently viewed class in anggotaPage

    document.addEventListener('DOMContentLoaded', function() {
        renderClassCards(); // Dipanggil saat DOM siap
    });

    // Function to render class cards based on ALL_CLASSES_DATA
    function renderClassCards() {
        const kelasContainer = document.getElementById('kelasContainer');
        kelasContainer.innerHTML = ''; // Clear loading message/previous cards

        const classesArray = Object.values(ALL_CLASSES_DATA); // Convert object to array for easier iteration

        if (classesArray.length === 0) {
            kelasContainer.innerHTML = `
                <div class="col-12 text-center text-muted py-5">
                    <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                    <p>Tidak ada kelas yang ditemukan untuk dosen ini.</p>
                </div>
            `;
            return;
        }

        classesArray.forEach(kelas => {
            const numStudents = kelas.students.length || 0; // Use actual student count from rendered data
            // Simulasi progress karena tidak ada di database_fix.sql
            const progressPercentage = Math.floor(Math.random() * (90 - 60 + 1)) + 60; // 60-90% random progress

            const cardHtml = `
                <div class="col-md-4 mb-4">
                    <div class="kelas-card" onclick="showAnggotaKelas('${kelas.name}')">
                        <div class="kelas-header">
                            <h3 class="kelas-title">Kelas ${kelas.name}</h3>
                            <p class="kelas-subtitle">${kelas.code} - ${kelas.name}</p>
                        </div>
                        <div class="kelas-body">
                            <div class="kelas-info">
                                <div class="kelas-info-item">
                                    <i class="fas fa-book"></i>
                                    ${kelas.credits} SKS
                                </div>
                                <div class="kelas-info-item">
                                    <i class="fas fa-user-graduate"></i>
                                    ${numStudents} Mahasiswa
                                </div>
                            </div>
                            <div class="progress kelas-progress">
                                <div class="progress-bar bg-info" role="progressbar" style="width: ${progressPercentage}%" aria-valuenow="${progressPercentage}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="kelas-action">
                                <button class="btn btn-join" onclick="event.stopPropagation(); showAnggotaKelas('${kelas.name}');">Masuk Kelas</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            kelasContainer.insertAdjacentHTML('beforeend', cardHtml);
        });
    }

    // Function to show class members page
    function showAnggotaKelas(kelasName) {
      CURRENT_KELAS_NAME = kelasName; // Store the current class name
      document.getElementById('dosenPage').style.display = 'none';
      document.getElementById('anggotaPage').style.display = 'block';
      document.getElementById('anggotaTitle').textContent = `Anggota Kelas ${kelasName}`;
      
      // Set CURRENT_STUDENTS_LIST for the selected class
      CURRENT_STUDENTS_LIST = ALL_CLASSES_DATA[kelasName] ? ALL_CLASSES_DATA[kelasName].students : [];

      // Generate student cards
      generateStudentCards(CURRENT_STUDENTS_LIST);
      
      // Reset selections and search input
      SELECTED_STUDENTS = [];
      document.getElementById('searchInput').value = '';
    }

    // Function to go back to the main lecturer page
    function backToDosen() {
      document.getElementById('anggotaPage').style.display = 'none';
      document.getElementById('dosenPage').style.display = 'block';
      SELECTED_STUDENTS = [];
      CURRENT_STUDENTS_LIST = [];
      CURRENT_KELAS_NAME = '';
      document.getElementById('searchInput').value = ''; // Clear search when going back
    }

    // Function to dynamically generate student cards for a given class
    function generateStudentCards(students) {
      const studentsGrid = document.getElementById('studentsGrid');
      studentsGrid.innerHTML = ''; // Clear previous cards

      if (students.length === 0) {
        studentsGrid.innerHTML = `
            <div class="col-12 text-center text-white-50 py-5">
                <i class="fas fa-user-graduate fa-3x mb-3"></i>
                <p>Tidak ada mahasiswa terdaftar di kelas ini.</p>
            </div>
        `;
        return;
      }

      students.forEach((student, index) => {
        const studentCardContainer = document.createElement('div');
        studentCardContainer.className = 'col-md-6'; // Bootstrap column for grid
        studentCardContainer.innerHTML = `
            <div class="student-card fade-in" data-id="${student.id}" data-name="${student.name}" data-nim="${student.nim}" onclick="selectStudent(this)">
                <h3 class="student-name">${student.name} <small class="text-muted" style="font-size: 0.8em;">(${student.nim})</small></h3>
            </div>
        `;
        studentsGrid.appendChild(studentCardContainer);
      });
    }

    // Function to filter students based on search input
    function searchStudents() {
      const searchTerm = document.getElementById('searchInput').value.toLowerCase();
      const filteredStudents = CURRENT_STUDENTS_LIST.filter(student => 
        student.name.toLowerCase().includes(searchTerm) || student.nim.toLowerCase().includes(searchTerm)
      );
      generateStudentCards(filteredStudents); // Re-render only filtered students
      
      // Reselect any previously selected students that are still visible
      SELECTED_STUDENTS.forEach(selected => {
        const cardElement = document.querySelector(`.student-card[data-id="${selected.id}"]`);
        if (cardElement) {
          cardElement.classList.add('selected');
        }
      });
    }

    // Function to select/deselect a student card
    function selectStudent(card) {
      const studentId = card.getAttribute('data-id');
      const studentName = card.getAttribute('data-name');
      const studentNim = card.getAttribute('data-nim');
      
      const existingIndex = SELECTED_STUDENTS.findIndex(s => s.id === studentId);

      if (existingIndex !== -1) {
        card.classList.remove('selected');
        SELECTED_STUDENTS.splice(existingIndex, 1); // Remove from array
      } else {
        card.classList.add('selected');
        SELECTED_STUDENTS.push({ id: studentId, name: studentName, nim: studentNim }); // Add to array
      }
      
      // Add click animation (purely aesthetic)
      card.style.transform = 'scale(0.98)';
      setTimeout(() => {
        card.style.transform = '';
      }, 150);
    }

    // Function to display message for selected students (example action)
    function showPesanAnggota() {
      if (SELECTED_STUDENTS.length === 0) {
        alert('Pilih mahasiswa terlebih dahulu!');
        return;
      }
      
      const message = `Mengirim pesan ke mahasiswa terpilih dari Kelas ${CURRENT_KELAS_NAME}:\n`;
      const studentNames = SELECTED_STUDENTS.map(s => `${s.name} (${s.nim})`).join('\n');
      alert(message + studentNames);
    }

    // Placeholder functions for other menu items (static alerts)
    function showPesan() {
      alert('Fitur pesan akan segera hadir!');
    }

    function logout() {
      if (confirm('Apakah Anda yakin ingin logout?')) {
        alert('Logout berhasil!');
        // Redirect to login page or refresh
        window.location.href = 'login.php'; // assuming login.php handles logout
      }
    }

    // Smooth scroll and hover effects for aesthetic
    document.addEventListener('DOMContentLoaded', function() {
      // Hover effects for dynamically added cards
      document.getElementById('kelasContainer').addEventListener('mouseenter', function(e) {
        if (e.target.closest('.kelas-card')) {
          e.target.closest('.kelas-card').style.transform = 'translateY(-5px)';
        }
      }, true); // Use capture phase
      
      document.getElementById('kelasContainer').addEventListener('mouseleave', function(e) {
        if (e.target.closest('.kelas-card')) {
          e.target.closest('.kelas-card').style.transform = 'translateY(0)';
        }
      }, true); // Use capture phase
    });

    // Keyboard navigation for search (e.g., Escape to go back)
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && document.getElementById('anggotaPage').style.display === 'block') {
        backToDosen();
      }
    });

  </script>
</body>
</html>