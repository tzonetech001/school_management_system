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

// Fetch student info with error handling
$student_sql = "SELECT * FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $student_sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $student_result = mysqli_stmt_get_result($stmt);
    
    if ($student_result && mysqli_num_rows($student_result) > 0) {
        $student = mysqli_fetch_assoc($student_result);
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
$unread_sql = "SELECT COUNT(*) as unread FROM notification_views WHERE viewer_id = ? ";
$unread_stmt = mysqli_prepare($conn, $unread_sql);

if ($unread_stmt) {
    mysqli_stmt_bind_param($unread_stmt, "i", $viewer_id);
    mysqli_stmt_execute($unread_stmt);
    $unread_result = mysqli_stmt_get_result($unread_stmt);
    if ($unread_result && mysqli_num_rows($unread_result) > 0) {
        $unread_data = mysqli_fetch_assoc($unread_result);
        $unread_count = $unread_data['unread'] ?? 0;
    }
    mysqli_stmt_close($unread_stmt);
}



?>

<nav class="sidebar" id="sidebar">
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
        
        <!-- Notifications with Badge -->
        <li>
            <a href="../candidates/notifications.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                <span class="menu-text">Notifications</span>
                <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_count; ?></span>
                <?php endif; ?>
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
                <li>
                    <a href="../candidates/timetable.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'timetable.php') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Class Timetable</span>
                    </a>
                </li>
               
            </ul>
        </li>
        
        <!-- Shule Salama -->
        <li>
            <a href="../candidates/shulesalama.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'shulesalama.php') ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span class="menu-text">Shule Salama</span>
                
            </a>
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
        
        <!-- Equipment & Assets -->
      <li>
         <a href="../candidates/equipment.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'equipment.php') ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i>
                        <span>Equipment</span>
                    </a>
                </li>
         <li>
            <a href="../candidates/maintenance.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'maintenance.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
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
        
        <!-- Library -->
        <li>
            <a href="../candidates/library.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'library.php') ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span class="menu-text">Library</span>
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

 
</nav>

<style>
/* Student Sidebar Specific Styles */
.student-info {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 10px;
}

.student-info .info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 12px;
    padding: 5px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.student-info .info-item:last-child {
    border-bottom: none;
}

.student-info .info-item i {
    width: 16px;
    text-align: center;
    color: rgba(255, 255, 255, 0.7);
}

.quick-info {
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Logout button styling */
.logout-btn {
    color: #ff6b6b !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: 10px;
}

.logout-btn:hover {
    background: rgba(220, 53, 69, 0.2) !important;
    border-left-color: #dc3545 !important;
    color: #ff8a8a !important;
}

/* Badge styling */
.badge {
    font-size: 10px;
    padding: 3px 6px;
    min-width: 20px;
    text-align: center;
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
    color: var(--primary-color);
    font-weight: bold;
    font-size: 18px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    overflow: hidden;
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
    background: rgba(0, 0, 0, 0.15);
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
    padding: 10px 15px 10px 45px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 13px;
    border-left: 3px solid transparent;
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

/* Section Titles */
.sidebar-section-title {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    margin: 5px 10px;
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
    .sidebar:not(.active) .quick-info,
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
        background: rgba(0, 0, 0, 0.1);
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
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
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