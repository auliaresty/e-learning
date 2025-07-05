<?php
include 'db_connection.php';

// Asumsi lecturer_id dari sesi. Untuk demo, kita pakai 2
$current_lecturer_id = 2; // Ganti dengan $_SESSION['user_id'] setelah implementasi login

$lecturer_name_for_header = "Dosen"; // Default
$schedules_data = [];

// Ambil nama dosen untuk header
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

// Ambil data jadwal mengajar
$sql_schedules = "
    SELECT
        s.schedule_id,
        c.course_name,
        c.course_code,
        s.day_of_week,
        s.start_time,
        s.end_time,
        s.room,
        s.class_type
    FROM
        schedules s
    JOIN
        courses c ON s.course_id = c.course_id
    WHERE
        s.lecturer_id = ?
    ORDER BY
        FIELD(s.day_of_week, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
        s.start_time;
";
$stmt_schedules = $conn->prepare($sql_schedules);
if ($stmt_schedules) {
    $stmt_schedules->bind_param("i", $current_lecturer_id);
    $stmt_schedules->execute();
    $result_schedules = $stmt_schedules->get_result();
    while ($row = $result_schedules->fetch_assoc()) {
        $schedules_data[] = $row;
    }
    $stmt_schedules->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Dosen - Sistem Akademik</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Anda bisa copy CSS dari laporan-dosen.html atau file dosen lainnya */
        :root {
            --color-primary: #B6D0EF;
            --color-secondary: #63A3F1;
            --color-accent: #FAFFEE;
            --color-dark: #4F8A9E;
            --color-white: #FFFFFF;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(90deg, var(--color-dark) 0%, var(--color-secondary) 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            color: var(--color-white) !important;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .main-container {
            padding: 2rem;
            max-width: 1200px;
            margin: 2rem auto;
            background: var(--color-white);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .card-header {
            background: linear-gradient(90deg, var(--color-secondary) 0%, var(--color-primary) 100%);
            color: var(--color-white);
            border: none;
            padding: 1.5rem;
            font-weight: bold;
            font-size: 1.2rem;
            border-radius: 15px 15px 0 0;
        }

        .table thead th {
            background: var(--color-primary);
            color: var(--color-dark);
            border: none;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 1rem 0.5rem;
        }

        .table tbody td {
            text-align: center;
            vertical-align: middle;
            padding: 0.8rem 0.5rem;
            border-color: var(--color-primary);
        }

        .table tbody tr:hover {
            background-color: var(--color-accent);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                Sistem Akademik - Jadwal Dosen
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white">
                    <i class="fas fa-user me-2"></i>
                    Selamat datang, <?php echo $lecturer_name_for_header; ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-calendar-alt me-2"></i>Jadwal Mengajar
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Kuliah</th>
                                <th>Kode MK</th>
                                <th>Hari</th>
                                <th>Waktu Mulai</th>
                                <th>Waktu Selesai</th>
                                <th>Ruangan</th>
                                <th>Tipe Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schedules_data)): ?>
                                <tr><td colspan="8" class="text-muted text-center py-3">Tidak ada jadwal mengajar ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($schedules_data as $index => $schedule): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($schedule['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['day_of_week']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($schedule['start_time'], 0, 5)); ?></td>
                                        <td><?php echo htmlspecialchars(substr($schedule['end_time'], 0, 5)); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['room']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['class_type']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>