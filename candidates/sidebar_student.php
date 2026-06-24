<?php
// sidebar_student.php
// This sidebar is only for students - no admin content

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    // Don't redirect here as it might break AJAX calls
    // Just return or show nothing
    return;
}

// Ensure database connection is available
if (!isset($conn) || !$conn) {
    // Try to include db_connect if not already included
    require_once '../controller/db_connect.php';
}

$student_id = $_SESSION['student_id'];
$student = null;
$school_id = null;
$school_name = "School Management System";
$school_logo_path = null;

// Fetch student info with school details
$student_sql = "SELECT s.*, 
                       sc.id as school_id, 
                       sc.school_name, 
                       sc.school_motto,
                       sc.school_code,
                       sc.logo_path as school_logo
                FROM students s
                JOIN schools sc ON s.school_id = sc.id
                WHERE s.id = ? AND s.status = 1 AND sc.status = 'Active'";
$stmt = mysqli_prepare($conn, $student_sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $student_result = mysqli_stmt_get_result($stmt);
    
    if ($student_result && mysqli_num_rows($student_result) > 0) {
        $student = mysqli_fetch_assoc($student_result);
        $school_id = $student['school_id'] ?? null;
        $school_name = $student['school_name'] ?? "School Management System";
        $school_logo_path = $student['school_logo'] ?? null;
    }
    mysqli_stmt_close($stmt);
}

// Set default values if student not found
if (!$student) {
    $user_firstname = 'Student';
    $user_lastname = 'User';
    $user_sex = 'Male';
    $user_class = '';
    $user_combination = '';
    $user_profile_image = '';
    $full_name = 'Student User';
    $initials = 'SU';
    $profile_image_path = '';
} else {
    $user_firstname = $student['first_name'] ?? 'Student';
    $user_lastname = $student['last_name'] ?? 'User';
    $user_sex = $student['sex'] ?? 'Male';
    $user_class = $student['class'] ?? '';
    $user_combination = $student['combination'] ?? '';
    $user_profile_image = $student['profile_image'] ?? '';
    
    $title = ($user_sex == 'Female') ? 'Ms.' : 'Mr.';
    $full_name = $title . ' ' . $user_firstname . ' ' . $user_lastname;
    $initials = substr($user_firstname, 0, 1) . substr($user_lastname, 0, 1);
    
    $profile_image_path = '';
    if (!empty($user_profile_image) && file_exists("../uploads/student_profiles/" . $user_profile_image)) {
        $profile_image_path = "../uploads/student_profiles/" . $user_profile_image;
    }
}

// Get unread notifications count with error handling
$unread_count = 0;
$unread_sql = "SELECT COUNT(DISTINCT n.id) as unread 
               FROM notifications n
               LEFT JOIN notification_views nv ON n.id = nv.notification_id AND nv.viewer_id = ? AND nv.viewer_type = 'student'
               WHERE n.status = 'active' 
               AND n.school_id = ?
               AND (n.visibility = 'public' OR n.visibility = 'students_only')
               AND nv.id IS NULL";
$unread_stmt = mysqli_prepare($conn, $unread_sql);

if ($unread_stmt) {
    mysqli_stmt_bind_param($unread_stmt, "ii", $student_id, $school_id);
    mysqli_stmt_execute($unread_stmt);
    $unread_result = mysqli_stmt_get_result($unread_stmt);
    if ($unread_result && mysqli_num_rows($unread_result) > 0) {
        $unread_data = mysqli_fetch_assoc($unread_result);
        $unread_count = $unread_data['unread'] ?? 0;
    }
    mysqli_stmt_close($unread_stmt);
}

// Get school logo for sidebar
$logo_path = '';
if (!empty($school_logo_path) && file_exists('../' . $school_logo_path)) {
    $logo_path = '../' . $school_logo_path;
} elseif (file_exists("../muyovozi.jpg")) {
    $logo_path = "../muyovozi.jpg";
}
?>

<nav class="sidebar" id="sidebar">
    <!-- School Logo in Sidebar -->
    <div class="sidebar-brand d-none d-lg-block">
        <?php if (!empty($logo_path)): ?>
            <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($school_name); ?>" class="sidebar-logo">
        <?php else: ?>
            <div class="sidebar-logo-placeholder">
                <?php echo substr($school_name, 0, 1); ?>
            </div>
        <?php endif; ?>
        <span class="sidebar-brand-text"><?php echo htmlspecialchars($school_name); ?></span>
    </div>

    <!-- Mobile User Profile (Hidden on Desktop) -->
    <div class="mobile-user-profile d-lg-none" id="mobileUserProfile">
        <div class="user-info">
            <div class="user-avatar">
                <?php if (!empty($profile_image_path)): ?>
                    <img src="<?php echo htmlspecialchars($profile_image_path); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo htmlspecialchars($initials); ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="user-role">Student - <?php echo htmlspecialchars($user_class . ' ' . $user_combination); ?></div>
            </div>
        </div>
    </div>

    <!-- Student Dashboard Section -->
    <div class="sidebar-section px-3 py-2 mt-2">
        <small class="text-white-50"><i class="fas fa-user-graduate me-1"></i>STUDENT PANEL</small>
    </div>

    <ul class="sidebar-menu" id="sidebarMenu">
        <!-- Dashboard -->
        <li>
            <a href="../candidates/dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>
        
        <!-- Academic Section -->
        <li class="sidebar-dropdown">
            <a href="#" class="<?php echo (in_array(basename($_SERVER['PHP_SELF']), ['results.php', 'timetable.php', 'assignments.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span class="menu-text">Academic</span>
                <span class="dropdown-arrow ms-auto">
                    <i class="fas fa-chevron-down"></i>
                </span>
            </a>
            <ul class="sub-menu">
                <li>
                    <a href="../candidates/results.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'results.php') ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>My Results</span>
                    </a>
                </li>
            </ul>
        </li>
        
        <!-- Discipline -->
        <li>
            <a href="../candidates/discipline.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'discipline.php') ? 'active' : ''; ?>">
                <i class="fas fa-balance-scale"></i>
                <span class="menu-text">Discipline</span>
            </a>
        </li>
        
        <!-- Financial Section -->
        <li>
            <a href="../candidates/fees.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'fees.php') ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>School Fee</span>
            </a>
        </li>
        
        <!-- Maintenance -->
        <li>
            <a href="../candidates/maintenance.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'maintenance.php') ? 'active' : ''; ?>">
                <i class="fas fa-tools"></i>
                <span>Maintenance</span>
            </a>
        </li>
        
        <!-- Dormitory -->
        <li>
            <a href="../candidates/my_dormitory.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'my_dormitory.php') ? 'active' : ''; ?>">
                <i class="fas fa-bed"></i>
                <span class="menu-text">My Dormitory</span>
            </a>
        </li>
     
        
        <!-- My Profile -->
        <li>
            <a href="../candidates/profile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
                <span class="menu-text">My Profile</span>
            </a>
        </li>
        
        <!-- Logout (styled as menu item) -->
        <li class="mt-4">
            <a href="../candidates/logout.php" class="text-danger logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>
        </li>
    </ul>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <small class="text-white-50">
            <i class="fas fa-school me-1"></i>
            <?php echo htmlspecialchars($school_name); ?>
        </small>
        <small class="text-white-50 d-block" style="font-size: 10px;">
            v2.0 &copy; <?php echo date('Y'); ?>
        </small>
    </div>
</nav>

<style>
/* Student Sidebar Specific Styles */
.sidebar {
    background: linear-gradient(180deg, var(--primary-color, #3B9DB3), var(--primary-dark, #2d7c8f));
    min-height: calc(100vh - 60px);
    box-shadow: 3px 0 15px rgba(0,0,0,0.1);
    padding: 0;
    transition: all 0.3s ease;
    position: fixed;
    left: -270px;
    top: 60px;
    width: 270px;
    z-index: 999;
    overflow-y: auto;
    max-height: calc(100vh - 60px);
    display: flex;
    flex-direction: column;
}

@media (min-width: 992px) {
    .sidebar {
        left: 0;
        width: 250px;
    }
}

.sidebar.active {
    left: 0;
}

/* Sidebar Brand / Logo */
.sidebar-brand {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    gap: 10px;
}

.sidebar-logo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.3);
}

.sidebar-logo-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    font-weight: bold;
    border: 2px solid rgba(255,255,255,0.3);
}

.sidebar-brand-text {
    color: white;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Sidebar Menu */
.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
    overflow-y: auto;
}

.sidebar-menu li {
    padding: 0;
    margin: 0;
}

.sidebar-menu a {
    color: rgba(255, 255, 255, 0.85);
    display: flex;
    align-items: center;
    padding: 12px 20px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    min-height: 48px;
    gap: 12px;
}

.sidebar-menu a:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: rgba(255, 255, 255, 0.5);
}

.sidebar-menu a.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    font-weight: 600;
    border-left-color: white;
}

.sidebar-menu i {
    width: 22px;
    text-align: center;
    font-size: 16px;
    flex-shrink: 0;
}

.sidebar-menu .menu-text {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Badge in sidebar */
.sidebar-menu .badge {
    font-size: 10px;
    padding: 3px 8px;
    min-width: 20px;
    text-align: center;
    background: #dc3545;
    color: white;
    border-radius: 20px;
}

/* Dropdown styles */
.sidebar-dropdown {
    position: relative;
}

.sidebar-dropdown > a {
    cursor: pointer;
    position: relative;
    display: flex;
    align-items: center;
}

.dropdown-arrow {
    font-size: 12px;
    transition: transform 0.3s ease;
    opacity: 0.7;
    margin-left: auto;
}

.sidebar-dropdown.active > a .dropdown-arrow {
    transform: rotate(180deg);
}

.sub-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(0, 0, 0, 0.2);
}

.sidebar-dropdown.active .sub-menu {
    max-height: 500px;
}

.sub-menu li {
    margin: 0;
}

.sub-menu a {
    display: flex;
    align-items: center;
    padding: 10px 15px 10px 52px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 13px;
    border-left: 3px solid transparent;
    min-height: 40px;
}

.sub-menu a:hover {
    background: rgba(255, 255, 255, 0.05);
    color: white;
    border-left-color: rgba(255, 255, 255, 0.5);
}

.sub-menu a.active {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-weight: 500;
    border-left-color: white;
}

.sub-menu i {
    width: 18px;
    text-align: center;
    margin-right: 10px;
    font-size: 12px;
}

/* Mobile User Profile */
.mobile-user-profile {
    display: none;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    margin: 10px;
    border-radius: 8px;
}

.mobile-user-profile .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.mobile-user-profile .user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background-color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color, #3B9DB3);
    font-weight: bold;
    font-size: 18px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    overflow: hidden;
    flex-shrink: 0;
}

.mobile-user-profile .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mobile-user-profile .user-name {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 2px;
    color: white;
}

.mobile-user-profile .user-role {
    font-size: 11px;
    opacity: 0.8;
    color: rgba(255, 255, 255, 0.9);
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 12px 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    text-align: center;
    margin-top: auto;
}

/* Logout button styling */
.logout-btn {
    color: #ff6b6b !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: 5px;
}

.logout-btn:hover {
    background: rgba(220, 53, 69, 0.2) !important;
    border-left-color: #dc3545 !important;
    color: #ff8a8a !important;
}

/* Section Titles */
.sidebar-section {
    padding: 10px 20px 5px;
}

.sidebar-section small {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    opacity: 0.6;
}

/* Mobile-specific adjustments */
@media (max-width: 991.98px) {
    .sidebar:not(.active) .sidebar-dropdown.active .sub-menu {
        display: none !important;
    }
    
    .sidebar.active .sidebar-dropdown.active .sub-menu {
        display: block !important;
    }
    
    .sidebar:not(.active) .sidebar-section,
    .sidebar:not(.active) .sidebar-footer,
    .sidebar:not(.active) .sidebar-brand,
    .sidebar:not(.active) .mobile-user-profile {
        display: none;
    }
    
    .sidebar.active .mobile-user-profile {
        display: block;
    }
}

/* Desktop optimizations */
@media (min-width: 992px) {
    .sub-menu {
        background: rgba(0, 0, 0, 0.15);
    }
    
    .mobile-user-profile {
        display: none !important;
    }
}

/* Hover effects for sidebar items */
.sidebar-menu a {
    transition: all 0.3s ease;
}

.sidebar-menu a:hover {
    transform: translateX(3px);
}

/* Active state improvements */
.sidebar-menu a.active {
    font-weight: 600;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.05));
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>

<script>
// Enhanced Dropdown Toggle for Sidebar
document.addEventListener('DOMContentLoaded', function() {
    // Get all dropdown toggles
    const dropdownToggles = document.querySelectorAll('.sidebar-dropdown > a');
    const sidebar = document.getElementById('sidebar');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parent = this.parentElement;
            const isMobile = window.innerWidth < 992;
            const isSidebarExpanded = sidebar.classList.contains('active') || window.innerWidth >= 992;
            
            // Only toggle dropdown if sidebar is expanded
            if (isSidebarExpanded) {
                if (parent.classList.contains('active')) {
                    parent.classList.remove('active');
                } else {
                    // Close other dropdowns
                    document.querySelectorAll('.sidebar-dropdown.active').forEach(dropdown => {
                        if (dropdown !== parent) {
                            dropdown.classList.remove('active');
                        }
                    });
                    parent.classList.add('active');
                }
            } else if (isMobile && !isSidebarExpanded) {
                // On mobile with collapsed sidebar, expand sidebar first
                sidebar.classList.add('active');
                const sidebarOverlay = document.getElementById('sidebarOverlay');
                if (sidebarOverlay) sidebarOverlay.classList.add('active');
                
                // Expand main content
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.classList.add('sidebar-open');
                    mainContent.classList.add('sidebar-open-full');
                }
                
                document.body.style.overflow = 'hidden';
                
                // Open dropdown after delay
                setTimeout(() => {
                    parent.classList.add('active');
                }, 300);
            }
        });
    });
    
    // Auto-open dropdowns with active links
    const activeLinks = document.querySelectorAll('.sidebar-menu a.active');
    activeLinks.forEach(link => {
        let parentDropdown = link.closest('.sidebar-dropdown');
        while (parentDropdown) {
            parentDropdown.classList.add('active');
            parentDropdown = parentDropdown.parentElement.closest('.sidebar-dropdown');
        }
    });
    
    // Prevent dropdown close when clicking inside
    document.querySelectorAll('.sub-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Close dropdowns when clicking outside (mobile)
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 992) {
            const isClickInsideSidebar = sidebar.contains(e.target);
            const sidebarToggle = document.getElementById('sidebarToggle');
            const logoContainer = document.getElementById('logoContainer');
            const isClickOnToggle = (sidebarToggle && sidebarToggle.contains(e.target)) || 
                                  (logoContainer && logoContainer.contains(e.target));
            
            if (!isClickInsideSidebar && !isClickOnToggle) {
                document.querySelectorAll('.sidebar-dropdown.active').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            // Desktop - keep active dropdowns open
            document.querySelectorAll('.sidebar-dropdown.active').forEach(dropdown => {
                dropdown.classList.add('active');
            });
        } else {
            // Mobile - close dropdowns if sidebar not active
            if (!sidebar.classList.contains('active')) {
                document.querySelectorAll('.sidebar-dropdown.active').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        }
    });
    
    // Initialize based on screen width
    if (window.innerWidth >= 992) {
        // Desktop - auto-open dropdowns with active links
        document.querySelectorAll('.sidebar-dropdown').forEach(dropdown => {
            const hasActiveChild = dropdown.querySelector('.sub-menu a.active');
            if (hasActiveChild) {
                dropdown.classList.add('active');
            }
        });
    }
});

// Function to update mobile user profile when profile image changes
function updateMobileUserProfile(imageUrl, initials) {
    const mobileAvatar = document.querySelector('.mobile-user-profile .user-avatar');
    if (mobileAvatar) {
        if (imageUrl) {
            mobileAvatar.innerHTML = `<img src="${imageUrl}" alt="Profile">`;
        } else {
            mobileAvatar.innerHTML = initials || 'SU';
        }
    }
}
</script>