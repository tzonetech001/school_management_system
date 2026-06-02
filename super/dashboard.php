<?php
// super/dashboard.php - Super Admin Dashboard (Simplified)
session_start();

// Check if Super Admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Location: ../mhs/login.php");
    exit();
}

require_once '../controller/db_connect.php';

// Get Super Admin info
$super_id = $_SESSION['super_admin_id'];
$super_sql = "SELECT * FROM super_admins WHERE id = $super_id AND status = 1";
$super_result = mysqli_query($conn, $super_sql);
$super_admin = mysqli_fetch_assoc($super_result);

if (!$super_admin) {
    session_destroy();
    header("Location: ../mhs/login.php");
    exit();
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');
$current_time = date('l, F j, Y');
$hour = date('H');

if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good Morning 🌄';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = 'Good Afternoon ☀️';
} elseif ($hour >= 17 && $hour < 21) {
    $greeting = 'Good Evening 🌆';
} else {
    $greeting = 'Good Night 🌃';
}

// ==================== SYSTEM-WIDE STATISTICS ====================

// School Statistics (simplified)
$schools_sql = "SELECT 
                COUNT(*) as total_schools,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_schools,
                SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) as suspended_schools,
                SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) as expired_schools
                FROM schools";
$schools_result = mysqli_query($conn, $schools_sql);
$school_stats = mysqli_fetch_assoc($schools_result);

// Get all schools with basic stats
$all_schools_sql = "SELECT s.*,
                    (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.is_leaver = 0 AND st.status = 1) as total_students,
                    (SELECT COUNT(*) FROM admins a WHERE a.school_id = s.id AND a.status = 1) as total_admins,
                    (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.sex = 'Male' AND st.is_leaver = 0) as male_students,
                    (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.sex = 'Female' AND st.is_leaver = 0) as female_students
                    FROM schools s
                    ORDER BY s.created_at DESC";
$all_schools_result = mysqli_query($conn, $all_schools_sql);
$all_schools = [];
while ($row = mysqli_fetch_assoc($all_schools_result)) {
    $all_schools[] = $row;
}

// Total students and admins across all schools
$total_students = array_sum(array_column($all_schools, 'total_students'));
$total_admins = array_sum(array_column($all_schools, 'total_admins'));
$total_male = array_sum(array_column($all_schools, 'male_students'));
$total_female = array_sum(array_column($all_schools, 'female_students'));

// Get recent activities
$recent_activities_sql = "SELECT al.*, a.first_name, a.last_name, a.email, s.school_name
                          FROM admin_logs al
                          JOIN admins a ON al.admin_id = a.id
                          JOIN schools s ON a.school_id = s.id
                          ORDER BY al.created_at DESC
                          LIMIT 10";
$recent_activities_result = mysqli_query($conn, $recent_activities_sql);
$recent_activities = [];
while ($row = mysqli_fetch_assoc($recent_activities_result)) {
    $recent_activities[] = $row;
}

// Get top performing schools (based on student count)
$top_schools_sql = "SELECT s.id, s.school_name, s.school_code, s.status, s.school_motto,
                    (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.is_leaver = 0 AND st.status = 1) as total_students
                    FROM schools s
                    WHERE s.status = 'Active'
                    ORDER BY total_students DESC
                    LIMIT 5";
$top_schools_result = mysqli_query($conn, $top_schools_sql);
$top_schools = [];
while ($row = mysqli_fetch_assoc($top_schools_result)) {
    $top_schools[] = $row;
}

// Get recent schools added (last 5)
$recent_schools_sql = "SELECT * FROM schools ORDER BY created_at DESC LIMIT 5";
$recent_schools_result = mysqli_query($conn, $recent_schools_sql);
$recent_schools = [];
while ($row = mysqli_fetch_assoc($recent_schools_result)) {
    $recent_schools[] = $row;
}

// Load theme settings for super admin
$colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'danger' => '#dc3545',
    'success' => '#28a745',
    'warning' => '#ffc107',
    'info' => '#17a2b8'
];

$font_size_value = '16px';
$bg_style = "linear-gradient(rgba(255,255,255,0.65), rgba(255,255,255,0.65)), url('../muyovozi.png') no-repeat center center fixed";
$bg_size = 'cover';
$animations_enabled = '1';
$compact_mode = '0';
$animation_duration = '0.3s';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - School Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <style>
        :root {
            --primary-color: <?php echo $colors['primary']; ?>;
            --primary-dark: <?php echo $colors['primary_dark']; ?>;
            --primary-light: <?php echo $colors['primary_light']; ?>;
            --danger-color: <?php echo $colors['danger']; ?>;
            --success-color: <?php echo $colors['success']; ?>;
            --warning-color: <?php echo $colors['warning']; ?>;
            --info-color: <?php echo $colors['info']; ?>;
            --font-size-base: <?php echo $font_size_value; ?>;
            --animation-duration: <?php echo $animation_duration; ?>;
        }

        * {
            transition: <?php echo $animations_enabled === '1' ? 'all var(--animation-duration) ease' : 'none'; ?>;
        }

        body {
            background: <?php echo $bg_style; ?>;
            background-size: <?php echo $bg_size; ?>;
            background-position: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: var(--font-size-base);
        }

        <?php if ($compact_mode === '1'): ?>
        .card-body { padding: 0.75rem !important; }
        .btn { padding: 0.5rem 1rem !important; }
        <?php endif; ?>

        .main-content {
            min-height: calc(100vh - 60px);
            padding: 20px;
            transition: margin-left var(--animation-duration) ease;
            margin-top: 5px;
        }

        @media (min-width: 992px) {
            .main-content { margin-left: 250px; }
            .main-content.sidebar-hidden { margin-left: 0; }
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 30px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.25);
            position: relative;
            overflow: hidden;
        }

        .welcome-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .welcome-card h2 { font-weight: 700; margin-bottom: 10px; position: relative; z-index: 1; }
        .welcome-card p { opacity: 0.9; position: relative; z-index: 1; }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            border-left: 4px solid var(--primary-color);
            position: relative;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .stat-card .stat-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
            opacity: 0.7;
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 5px 0;
            color: var(--primary-dark);
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Chart Containers */
        .chart-container {
            background: white;
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .chart-container:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }

        .chart-container h5 {
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .chart-container h5 i { color: var(--primary-color); margin-right: 8px; }

        /* Schools Table */
        .schools-table {
            width: 100%;
            font-size: 0.85rem;
        }
        .schools-table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 10px;
            font-weight: 600;
        }
        .schools-table tbody tr:hover { background: rgba(0,0,0,0.02); }

        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Suspended { background: #f8d7da; color: #721c24; }
        .status-Expired { background: #fff3cd; color: #856404; }
        .status-Inactive { background: #e2e3e5; color: #383d41; }

        .quick-action-btn {
            padding: 3px 6px;
            margin: 0 2px;
            border-radius: 5px;
            font-size: 0.7rem;
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .welcome-card h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<?php include '../controller/header.php'; ?>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="container-fluid">
        
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($super_admin['first_name'] . ' ' . $super_admin['last_name']); ?>!</h2>
                    <p class="mb-2">Today is: <?php echo $current_time; ?></p>
                    <div class="mt-3">
                        <span class="badge bg-white text-dark me-2 px-3 py-2">
                            <i class="fas fa-crown me-1" style="color: var(--primary-color);"></i>
                            System Administrator
                        </span>
                        <span class="badge bg-white text-dark px-3 py-2">
                            <i class="fas fa-globe me-1" style="color: var(--primary-color);"></i>
                            Manage All Schools
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <i class="fas fa-school fa-4x opacity-50"></i>
                </div>
            </div>
        </div>

        <!-- Quick Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-school"></i></div>
                <h3><?php echo number_format($school_stats['total_schools'] ?? 0); ?></h3>
                <div class="stat-label">Total Schools</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle text-success"></i></div>
                <h3><?php echo number_format($school_stats['active_schools'] ?? 0); ?></h3>
                <div class="stat-label">Active Schools</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3><?php echo number_format($total_students); ?></h3>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3><?php echo number_format($total_admins); ?></h3>
                <div class="stat-label">Total Staff</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- School Status Distribution -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie"></i> School Status Distribution</h5>
                    <div style="height: 300px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top 5 Schools by Student Population -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-bar"></i> Top Schools by Student Population</h5>
                    <div style="height: 300px; position: relative;">
                        <canvas id="topSchoolsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gender Distribution Chart -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h5><i class="fas fa-venus-mars"></i> Student Gender Distribution (All Schools)</h5>
                    <div style="height: 300px; position: relative;">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Schools Table -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h5><i class="fas fa-building"></i> All Schools</h5>
                    <div class="table-responsive">
                        <table id="schoolsTable" class="table schools-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>School Name</th>
                                    <th>Motto</th>
                                    <th>Address</th>
                                    <th>Students</th>
                                    <th>Staff</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_schools as $school): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($school['school_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($school['school_motto'] ?? '-', 0, 30)); ?></td>
                                    <td><?php echo htmlspecialchars(substr($school['address'] ?? '-', 0, 40)); ?>...</td>
                                    <td><?php echo number_format($school['total_students'] ?? 0); ?></td>
                                    <td><?php echo number_format($school['total_admins'] ?? 0); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $school['status']; ?>">
                                            <?php echo $school['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($school['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h5><i class="fas fa-history"></i> Recent System Activities</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Admin</th>
                                    <th>School</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td><small><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['school_name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($activity['action']); ?></span></td>
                                    <td><?php echo htmlspecialchars(substr($activity['description'] ?? 'N/A', 0, 50)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_activities)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No recent activities</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="activity_logs.php" class="btn btn-sm btn-outline-primary">
                            View All Activities <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('#schoolsTable').DataTable({
        pageLength: 10,
        order: [[0, 'asc']],
        responsive: true,
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
});

// School Status Distribution Chart (Pie)
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'pie',
    data: {
        labels: ['Active', 'Suspended', 'Expired', 'Inactive'],
        datasets: [{
            data: [
                <?php echo $school_stats['active_schools'] ?? 0; ?>,
                <?php echo $school_stats['suspended_schools'] ?? 0; ?>,
                <?php echo $school_stats['expired_schools'] ?? 0; ?>,
                0
            ],
            backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: function(context) { return `${context.label}: ${context.raw} schools`; } } }
        }
    }
});

// Top Schools Chart (Bar)
const topSchoolsCtx = document.getElementById('topSchoolsChart').getContext('2d');
new Chart(topSchoolsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($top_schools, 'school_name')); ?>,
        datasets: [{
            label: 'Number of Students',
            data: <?php echo json_encode(array_column($top_schools, 'total_students')); ?>,
            backgroundColor: '<?php echo $colors['primary']; ?>',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Number of Students' } }
        }
    }
});

// Gender Distribution Chart (Pie)
const genderCtx = document.getElementById('genderChart').getContext('2d');
new Chart(genderCtx, {
    type: 'doughnut',
    data: {
        labels: ['Male Students', 'Female Students'],
        datasets: [{
            data: [<?php echo $total_male; ?>, <?php echo $total_female; ?>],
            backgroundColor: ['<?php echo $colors['primary']; ?>', '<?php echo $colors['warning']; ?>'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: function(context) { return `${context.label}: ${context.raw} students (${((context.raw / (<?php echo $total_students ?: 1; ?>)) * 100).toFixed(1)}%)`; } } }
        }
    }
});
</script>

<?php include '../controller/footer.php'; ?>
</body>
</html>