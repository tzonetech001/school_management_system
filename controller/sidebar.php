 <?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once 'db_connect.php';

$admin_id = $_SESSION['admin_id'];

// Get user's basic info
$user_sql = "SELECT a.* FROM admins a WHERE a.id = $admin_id";
$user_result = mysqli_query($conn, $user_sql);
$user = $user_result ? mysqli_fetch_assoc($user_result) : null;

// Get user's roles
$roles_sql = "SELECT ar.id, ar.role_name, ara.is_primary 
              FROM admin_roles ar
              INNER JOIN admin_role_assignments ara ON ar.id = ara.role_id
              WHERE ara.admin_id = $admin_id";
$roles_result = mysqli_query($conn, $roles_sql);

$user_role_ids = [];
$user_roles = [];
$primary_role = 'Administrator';

if ($roles_result && mysqli_num_rows($roles_result) > 0) {
    while ($role = mysqli_fetch_assoc($roles_result)) {
        $user_role_ids[] = $role['id'];
        $user_roles[] = $role['role_name'];
        if ($role['is_primary'] == 1) {
            $primary_role = $role['role_name'];
        }
    }
}

// Function to check if user has specific role
function hasRole($role_ids, $allowed_roles) {
    if (empty($role_ids) || empty($allowed_roles)) return false;
    foreach ($role_ids as $role_id) {
        if (in_array($role_id, $allowed_roles)) {
            return true;
        }
    }
    return false;
}

// Define role permissions
$is_head_master = hasRole($user_role_ids, [1]);
$is_second_master = hasRole($user_role_ids, [2]);
$is_academic_master = hasRole($user_role_ids, [3]);
$is_discipline_master = hasRole($user_role_ids, [4]);
$is_class_teacher = hasRole($user_role_ids, [5]);
$is_sports = hasRole($user_role_ids, [6]);
$is_dormitory = hasRole($user_role_ids, [7]);
$is_bursar_store = hasRole($user_role_ids, [8]);
$is_production = hasRole($user_role_ids, [9]);
$is_ins_coach = hasRole($user_role_ids, [10]);
$is_food_store = hasRole($user_role_ids, [11]);
$is_ps = hasRole($user_role_ids, [12]);
$is_librarian = hasRole($user_role_ids, [13]);
$is_shule_salama = hasRole($user_role_ids, [14]);
$is_normal_teacher = hasRole($user_role_ids, [15]);
$is_maintenance = hasRole($user_role_ids, [16]);

$has_any_role = !empty($user_role_ids);

// Get user details for mobile profile
if ($user) {
    $user_firstname = $user['first_name'] ?? 'Admin';
    $user_lastname = $user['last_name'] ?? 'User';
    $user_sex = $user['sex'] ?? 'Male';
    $user_profile_image = $user['profile_image'] ?? '';
    
    $title = ($user_sex == 'Female') ? 'Ms.' : 'Mr.';
    $full_name = $title . ' ' . $user_firstname . ' ' . $user_lastname;
    $initials = substr($user_firstname, 0, 1) . substr($user_lastname, 0, 1);
    
    $profile_image_path = '';
    if (!empty($user_profile_image) && file_exists("../uploads/profiles/" . $user_profile_image)) {
        $profile_image_path = "../uploads/profiles/" . $user_profile_image;
    }
} else {
    $full_name = 'Mr. Admin User';
    $initials = 'AU';
    $profile_image_path = '';
    $primary_role = 'Administrator';
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Load user preferences
$preferences = [];
$prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
$prefs_result = mysqli_query($conn, $prefs_query);
if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
    while ($row = mysqli_fetch_assoc($prefs_result)) {
        if ($row !== null && isset($row['preference_key']) && isset($row['preference_value'])) {
            $preferences[$row['preference_key']] = $row['preference_value'];
        }
    }
}

$animations_enabled = isset($preferences['animations']) ? $preferences['animations'] : '1';
?>
<style> /* sidebar.css - Styles for admin sidebar - NO FLASHING */ /* Sidebar Styles */ .sidebar {background-color: var(--primary-color); min-height: calc(100vh - 60px); box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1); padding: 20px 0; position: fixed; top: 60px; width: 250px; z-index: 999; overflow-y: auto; max-height: calc(100vh - 60px); display: flex; flex-direction: column; transition: left 0.3s ease; } /* Desktop styles */ @media (min-width: 992px) {.sidebar {left: 0; } .sidebar.desktop-visible {left: 0; } } /* Mobile styles */ @media (max-width: 991px) {.sidebar {left: -250px; } .sidebar.active {left: 0; } } /* Sidebar Menu */ .sidebar-menu {list-style: none; padding: 0; margin: 0; flex: 1; overflow-y: auto; } .sidebar-menu li {padding: 0; } .sidebar-menu a {color: white; display: flex; align-items: center; padding: 12px 20px; text-decoration: none; font-size: 14px; font-weight: 500; border-left: 4px solid transparent; min-height: 45px; } .sidebar-menu a:hover, .sidebar-menu a.active {background-color: var(--primary-dark); border-left: 4px solid white; } .sidebar-menu i {width: 30px; text-align: center; font-size: 16px; } /* Dropdown Styles */ .sidebar-dropdown {position: relative; } .sidebar-dropdown > a {cursor: pointer; position: relative; display: flex; align-items: center; } .dropdown-arrow {font-size: 12px; margin-left: auto; opacity: 0.7; } .sidebar-dropdown.active > a .dropdown-arrow {transform: rotate(180deg); } /* Sub-menu - No transition to prevent flashing */ .sub-menu {list-style: none; padding: 0; margin: 0; display: none; background: rgba(0, 0, 0, 0.1); } .sidebar-dropdown.active .sub-menu {display: block; } .sub-menu li {margin: 0; } .sub-menu a {display: flex; align-items: center; padding: 8px 15px 8px 45px; color: rgba(255, 255, 255, 0.9); text-decoration: none; font-size: 13px; border-left: 3px solid transparent; min-height: 35px; } .sub-menu a:hover {background: rgba(255, 255, 255, 0.1); color: white; border-left-color: rgba(255, 255, 255, 0.5); } .sub-menu a.active {background: rgba(255, 255, 255, 0.15); color: white; font-weight: 500; border-left-color: white; } .sub-menu i {width: 20px; text-align: center; margin-right: 10px; font-size: 12px; } /* Section Titles */ .sidebar-section {margin-top: 15px; } .sidebar-section-title {padding: 8px 15px; background: rgba(0, 0, 0, 0.1); border-radius: 4px; margin: 5px 10px; } .sidebar-section-title small {color: rgba(255, 255, 255, 0.7); font-size: 11px; font-weight: 600; letter-spacing: 0.5px; } /* Mobile User Profile */ .mobile-user-profile {display: none; padding: 15px; background: rgba(255, 255, 255, 0.1); margin: 10px; border-radius: 8px; } .mobile-user-profile .user-info {display: flex; align-items: center; gap: 12px; } .mobile-user-profile .user-avatar {width: 45px; height: 45px; border-radius: 50%; background-color: white; display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: bold; font-size: 16px; border: 2px solid rgba(255, 255, 255, 0.3); overflow: hidden; } .mobile-user-profile .user-avatar img {width: 100%; height: 100%; object-fit: cover; } .mobile-user-profile .user-details {flex: 1; } .mobile-user-profile .user-name {font-size: 14px; font-weight: 600; margin-bottom: 2px; color: white; } .mobile-user-profile .user-role {font-size: 12px; opacity: 0.8; color: white; } /* Scrollbar styling */ .sidebar::-webkit-scrollbar {width: 5px; } .sidebar::-webkit-scrollbar-track {background: rgba(255, 255, 255, 0.1); } .sidebar::-webkit-scrollbar-thumb {background: rgba(255, 255, 255, 0.3); border-radius: 10px; } .sidebar::-webkit-scrollbar-thumb:hover {background: rgba(255, 255, 255, 0.5); } /* Responsive */ @media (max-width: 991.98px) {.mobile-user-profile {display: block; } } /* Menu Text - ensure consistent width */ .menu-text {flex: 1; } /* common.css - Common styles for both header and sidebar */ /* Reset transitions for sidebar to prevent flashing */ .sidebar, .sidebar *, .sidebar a, .sidebar-menu, .sub-menu, .sidebar-dropdown .sub-menu {transition: none !important; } /* Only allow transitions on specific elements that need it */ .sidebar, .sidebar a, .sidebar a i, .main-content {transition: all 0.3s ease; } /* Animation speed control - NO FLASHING */ .no-animation {transition: none !important; } /* Fast animations for sidebar toggle only */ .sidebar.active, .sidebar.desktop-visible {transition: left 0.3s ease; } /* Disable transitions on page load to prevent flash */ body.preload * {transition: none !important; } /* Fix for dropdown arrows */ .dropdown-arrow i {transition: transform 0.2s ease; } /* Ensure consistent box sizing */ *, *::before, *::after {box-sizing: border-box; } /* Utility classes */ .text-center {text-align: center; } .d-none {display: none; } .d-sm-none {display: none; } @media (min-width: 576px) {.d-sm-block {display: block; } .d-sm-none {display: none; } } /* Flex utilities */ .d-flex {display: flex; } .align-items-center {align-items: center; } .justify-content-center {justify-content: center; } .justify-content-end {justify-content: flex-end; } .gap-1 {gap: 0.25rem; } .gap-2 {gap: 0.5rem; } .gap-3 {gap: 1rem; } /* Container */ .container-fluid {width: 100%; padding-right: 15px; padding-left: 15px; margin-right: auto; margin-left: auto; } /* Row */ .row {display: flex; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; } .row > [class*="col-"] {padding-right: 15px; padding-left: 15px; } /* Columns */ .col-4 {flex: 0 0 33.333333%; max-width: 33.333333%; } .col-8 {flex: 0 0 66.666667%; max-width: 66.666667%; } @media (min-width: 768px) {.col-md-3 {flex: 0 0 25%; max-width: 25%; } .col-md-6 {flex: 0 0 50%; max-width: 50%; } } /* Height utilities */ .h-100 {height: 100%; } /* Margin utilities */ .m-0 {margin: 0; } .mt-2 {margin-top: 0.5rem; } .mb-2 {margin-bottom: 0.5rem; } .ml-2 {margin-left: 0.5rem; } .mr-2 {margin-right: 0.5rem; } /* Gutter removal */ .g-0 {margin: 0; } .g-0 > [class*="col-"] {padding: 0; } </style>
<nav class="sidebar" id="sidebar">
    <!-- Mobile User Profile (only visible on mobile) -->
    <div class="mobile-user-profile">
        <div class="user-info">
            <?php if ($profile_image_path): ?>
                <img src="<?php echo $profile_image_path; ?>" alt="Profile" class="user-avatar">
            <?php else: ?>
                <div class="user-avatar"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($primary_role); ?></div>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu" id="sidebarMenu">
        <!-- Dashboard - All admin roles can view -->
        <?php if ($has_any_role): ?>
        <li>
            <a href="../muyo/dashboard" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>
        <?php endif; ?>
 
        <!-- Academic Dropdown -->
        <?php if ($has_any_role): ?>
        <li class="sidebar-dropdown">
            <a href="#" class="dropdown-toggle <?php echo (in_array($current_page, ['academic.php', 'timetable.php', 'exams.php','exam_type_manager.php', 'results_entry_five.php', 'results_entry_six.php','assign_subject.php', 'view_results.php','results_report.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span class="menu-text">Academic</span>
                <span class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </span>
            </a>
            <ul class="sub-menu">
                <li>
                    <a href="../academic/academic" class="<?php echo ($current_page == 'academic.php') ? 'active' : ''; ?>">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Academic Overview</span>
                    </a>
                </li>
                <?php if ($is_head_master || $is_second_master || $is_academic_master): ?>
                    <li>
                    <a href="../academic/exam_type_manager" class="<?php echo ($current_page == 'exam_type_manager.php') ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Manage Exams type</span>
                    </a>
                </li>
                <li>
                    <a href="../academic/results_entry_five" class="<?php echo ($current_page == 'results_entry_five.php') ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Form Five Results</span>
                    </a>
                </li>
                <li>
                    <a href="../academic/results_entry_six" class="<?php echo ($current_page == 'results_entry_six.php') ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Form Six Results</span>
                    </a>
                </li>
                <li>
                    <a href="../academic/parent_sms_results" class="<?php echo ($current_page == 'parent_sms_results.php') ? 'active' : ''; ?>">
                        <i class="fas fa-comment-dots"></i>
                        <span>Parent sms Results</span>
                    </a>
                </li>
                <li>
                    <a href="../academic/assign_subject" class="<?php echo ($current_page == 'assign_subject.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Manage Subject</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="../academic/subject_entry" class="<?php echo ($current_page == 'subject_entry.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Teacher Subject</span>
                    </a>
                </li>
                <li>
                    <a href="../academic/view_results" class="<?php echo ($current_page == 'view_results.php') ? 'active' : ''; ?>">
                        <i class="fas fa-eye"></i>
                        <span>View Results</span>
                    </a>
                </li>
                <li>
                    <a href="../academic/timetable" class="<?php echo ($current_page == 'timetable.php') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Timetable</span>
                    </a>
                </li>
                <li>
                    <a href="../academic/exams" class="<?php echo ($current_page == 'exams.php') ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Exams</span>
                    </a>
                </li>
                <?php if ($is_head_master || $is_second_master || $is_academic_master || $is_ps): ?>

                <li>
                    <a href="../academic/results_report" class="<?php echo ($current_page == 'results_report.php') ? 'active' : ''; ?>">
                        <i class="fas fa-download"></i>
                        <span>Export Report</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        
        <!-- Staff Management -->
        <?php if ($is_head_master || $is_second_master): ?>
        <li class="sidebar-dropdown">
            <a href="#" class="dropdown-toggle <?php echo (in_array($current_page, ['admins.php', 'register_admin.php', 'report_admin.php', 'otp.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span class="menu-text">Manage Staff</span>
                <span class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </span>
            </a>
            <ul class="sub-menu">
                <?php if ($is_head_master): ?>
                <li>
                    <a href="../staff/register_admin" class="<?php echo ($current_page == 'register_admin.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i>
                        <span>New Staff</span>
                    </a>
                </li>
                <li>
                    <a href="../staff/admins" class="<?php echo ($current_page == 'admins.php') ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i>
                        <span>Manage Staff</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($is_head_master || $is_second_master): ?>
                
                <li>
                    <a href="../staff/report_admin" class="<?php echo ($current_page == 'report_admin.php') ? 'active' : ''; ?>">
                        <i class="fas fa-download"></i>
                        <span>Export Report</span>
                    </a>
                </li>
            <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <!-- Staff Management -->
        <?php if ($is_head_master || $is_second_master): ?>
        <li class="sidebar-dropdown">
            <a href="#" class="dropdown-toggle <?php echo (in_array($current_page, ['non_staff.php', 'register_non_staff.php', 'report_non_staff.php', 'otp.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span class="menu-text">Non-Staff</span>
                <span class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </span>
            </a>
            <ul class="sub-menu">
                <?php if ($is_head_master || $is_second_master): ?>
                <li>
                    <a href="../non_staff/register_non_staff" class="<?php echo ($current_page == 'register_non_staff.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i>
                        <span>New Non-Staff</span>
                    </a>
                </li>
                <li>
                    <a href="../non_staff/non_staff" class="<?php echo ($current_page == 'non_staff.php') ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i>
                        <span>Manage Non-Staff</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($is_head_master || $is_second_master): ?>
                
                <li>
                    <a href="../non_staff/report_non_staff" class="<?php echo ($current_page == 'report_non_staff.php') ? 'active' : ''; ?>">
                        <i class="fas fa-download"></i>
                        <span>Export Report</span>
                    </a>
                </li>
            <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Student Management -->
        <?php if ($is_head_master || $is_second_master || $is_academic_master || $is_ps): ?>
        <li class="sidebar-dropdown">
            <a href="#" class="dropdown-toggle <?php echo (in_array($current_page, ['students.php', 'register.php', 'leavers.php', 'report_student.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span class="menu-text">Manage Students</span>
                <span class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </span>
            </a>
            <ul class="sub-menu">
                <?php if ($is_head_master || $is_second_master || $is_academic_master): ?>
                <li>
                    <a href="../student/register" class="<?php echo ($current_page == 'register.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i>
                        <span>New Student</span>
                    </a>
                </li>
                <li>
                    <a href="../student/students" class="<?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>All Students</span>
                    </a>
                </li>
                <li>
                    <a href="../student/students?class=Form+Five" class="<?php echo ($current_page == 'students.php' && isset($_GET['class']) && $_GET['class'] == 'Form Five') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Form Five</span>
                    </a>
                </li>
                <li>
                    <a href="../student/students?class=Form+Six" class="<?php echo ($current_page == 'students.php' && isset($_GET['class']) && $_GET['class'] == 'Form Six') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Form Six</span>
                    </a>
                </li>
                <li>
                    <a href="../student/leavers" class="<?php echo ($current_page == 'leavers.php') ? 'active' : ''; ?>">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Leavers</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($is_head_master || $is_second_master || $is_academic_master || $is_ps): ?>
                <li>
                    <a href="../student/report_student" class="<?php echo ($current_page == 'report_student.php') ? 'active' : ''; ?>">
                        <i class="fas fa-download"></i>
                        <span>Export Report</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        
        <!-- Discipline -->
        <?php if ($is_head_master || $is_second_master || $is_discipline_master): ?>
        <li>
            <a href="../discipline/discipline" class="<?php echo ($current_page == 'discipline.php') ? 'active' : ''; ?>">
                <i class="fas fa-balance-scale"></i>
                <span class="menu-text">Discipline</span>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Dormitory Management -->
        <?php if ($is_head_master || $is_second_master || $is_dormitory): ?>
        <li class="sidebar-dropdown">
            <a href="#" class="dropdown-toggle <?php echo (in_array($current_page, ['dormitory.php', 'female.php', 'male.php', 'rooms.php', 'reports.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span class="menu-text">Dormitory</span>
                <span class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </span>
            </a>
            <ul class="sub-menu">
                <li>
                    <a href="../dormitory/dormitory" class="<?php echo ($current_page == 'dormitory.php') ? 'active' : ''; ?>">
                        <i class="fas fa-bed"></i>
                        <span>Manage Dorms</span>
                    </a>
                </li>
                <li>
                    <a href="../dormitory/male" class="<?php echo ($current_page == 'male.php') ? 'active' : ''; ?>">
                        <i class="fas fa-male"></i>
                        <span>Male Dorms</span>
                    </a>
                </li>
                <li>
                    <a href="../dormitory/female" class="<?php echo ($current_page == 'female.php') ? 'active' : ''; ?>">
                        <i class="fas fa-female"></i>
                        <span>Female Dorms</span>
                    </a>
                </li>
                <li>
                    <a href="../dormitory/reports" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>
        
        <!-- Notifications -->
        <?php if ($has_any_role): ?>
        <li>
            <a href="../notification/notifications" class="<?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                <span class="menu-text">Notifications</span>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- School Services Section -->
        <li class="sidebar-section">
            <div class="sidebar-section-title">
                <small><i class="fas fa-school me-1"></i>SCHOOL SERVICES</small>
            </div>
        </li>
        
        <!-- Message Center -->
        <?php if ($is_head_master || $is_second_master || $is_academic_master): ?>
        <li>
            <a href="../sms/sms" class="<?php echo ($current_page == 'sms.php') ? 'active' : ''; ?>">
                <i class="fas fa-comment-dots"></i>
                <span class="menu-text">Message Center</span>
            </a>
        </li>
        <?php endif; ?>

        
        
        
        <!-- Maintenance -->
        <?php if ($is_head_master || $is_second_master || $is_maintenance): ?>
        <li class="sidebar-dropdown">
            <a href="#" class="dropdown-toggle <?php echo (in_array($current_page, ['maintenance.php', 'inventory.php', 'student_main.php', 'staff_main.php', 'maintenance_logs.php', 'report_maintenance.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-tools"></i>
                <span class="menu-text">Maintenance</span>
                <span class="dropdown-arrow">
                    <i class="fas fa-chevron-down"></i>
                </span>
            </a>
            <ul class="sub-menu">
                <li>
                    <a href="../maintenance/maintenance" class="<?php echo ($current_page == 'maintenance.php') ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="../maintenance/inventory" class="<?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>">
                        <i class="fas fa-list-alt"></i>
                        <span>Inventory</span>
                    </a>
                </li>
                <li>
                    <a href="../maintenance/student_main" class="<?php echo ($current_page == 'student_main.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i>
                        <span>Assign to Students</span>
                    </a>
                </li>
                <li>
                    <a href="../maintenance/staff_main" class="<?php echo ($current_page == 'staff_main.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Assign to Staff</span>
                    </a>
                </li>
                
                <li>
                    <a href="../maintenance/report_maintenance" class="<?php echo ($current_page == 'report_maintenance.php') ? 'active' : ''; ?>">
                        <i class="fas fa-download"></i>
                        <span>Export Report</span>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>
    
      
        
        <!-- Support Section -->
        <?php if ($has_any_role): ?>
        <li class="sidebar-section">
            <div class="sidebar-section-title">
                <small><i class="fas fa-cog me-1"></i>SUPPORT</small>
            </div>
        </li>
        
        <!-- Profile -->
        <li>
            <a href="../profile/profile" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span class="menu-text">Profile</span>
            </a>
        </li>
        
        <!-- Settings -->
        <li>
            <a href="../profile/settings" class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                <span class="menu-text">Theme Settings</span>
            </a>
        </li>
        
        <!-- Help & Support -->
        <li>
            <a href="../help/help" class="<?php echo ($current_page == 'help.php') ? 'active' : ''; ?>">
                <i class="fas fa-question-circle"></i>
                <span class="menu-text">Help & Support</span>
            </a>
        </li>
        
        <!-- logout -->
        <li>
            <a href="../controller/logout" class="<?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>

<script>

document.addEventListener('DOMContentLoaded', function() {const dropdownToggles = document.querySelectorAll('.sidebar-dropdown > a'); dropdownToggles.forEach(toggle => {toggle.addEventListener('click', function(e) {e.preventDefault(); const parent = this.parentElement; if (parent.classList.contains('active')) {parent.classList.remove('active'); } else {document.querySelectorAll('.sidebar-dropdown.active').forEach(dropdown => {dropdown.classList.remove('active'); }); parent.classList.add('active'); } }); }); const activeLinks = document.querySelectorAll('.sidebar-menu a.active'); activeLinks.forEach(link => {const parentDropdown = link.closest('.sidebar-dropdown'); if (parentDropdown) {parentDropdown.classList.add('active'); } }); });
</script>
