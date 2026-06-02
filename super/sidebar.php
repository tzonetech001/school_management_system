<?php
// sidebar_super.php - Super Admin Sidebar (PURE HTML - NO DUPLICATE TAGS)
// This file should be included ONLY for Super Admin users

// Check if user is super admin
if (!isset($_SESSION['super_admin_id'])) {
    return;
}

// Get current page for active highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Helper function to check active menu
function is_active($pages, $current_page, $current_dir = '') {
    if (is_array($pages)) {
        foreach ($pages as $page) {
            if ($current_page == $page || ($current_dir == 'super' && $page == 'super')) {
                return true;
            }
        }
        return false;
    }
    return ($current_page == $pages || ($current_dir == 'super' && $pages == 'super'));
}

// Get preferences for sidebar state
$sidebar_collapsed = isset($preferences['sidebar_collapsed']) && $preferences['sidebar_collapsed'] === '1';
?>

<style>
    /* Sidebar Styles - Super Admin */
    .sidebar {
        position: fixed;
        top: 60px;
        left: 0;
        width: 250px;
        height: calc(100% - 60px);
        background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary-color) 100%);
        color: white;
        transition: all var(--animation-duration) ease;
        z-index: 999;
        overflow-y: auto;
        overflow-x: hidden;
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
    }

    .sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 5px;
    }

    /* Sidebar Hidden/Collapsed State */
    .sidebar.hidden {
        left: -250px;
    }

    .sidebar.desktop-visible {
        left: 0;
    }

    /* For mobile */
    @media (max-width: 991px) {
        .sidebar {
            left: -250px;
            z-index: 1050;
        }
        .sidebar.active {
            left: 0;
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            display: none;
        }
        .sidebar-overlay.active {
            display: block;
        }
    }

    @media (min-width: 992px) {
        .sidebar:not(.desktop-visible) {
            left: -250px;
        }
        .sidebar.desktop-visible {
            left: 0;
        }
    }

    /* Super Admin Badge in Sidebar */
    .super-badge-sidebar {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        padding: 5px 12px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-left: 8px;
        display: inline-block;
    }

    /* Sidebar User Info */
    .sidebar-user {
        padding: 20px 15px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        margin-bottom: 15px;
    }

    .sidebar-avatar {
        width: 70px;
        height: 70px;
        margin: 0 auto 12px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: bold;
        color: var(--primary-color);
        border: 3px solid rgba(255, 255, 255, 0.3);
    }

    .sidebar-avatar.has-image {
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    .sidebar-user-name {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 4px;
    }

    .sidebar-user-role {
        font-size: 0.7rem;
        opacity: 0.8;
        background: rgba(255, 255, 255, 0.15);
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
    }

    /* Sidebar Menu */
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-item {
        margin: 2px 12px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .sidebar-item a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: white;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        gap: 12px;
    }

    .sidebar-item a i {
        width: 22px;
        font-size: 1.1rem;
        text-align: center;
    }

    .sidebar-item a span {
        flex: 1;
        font-size: 0.9rem;
    }

    .sidebar-item:hover:not(.sidebar-header) a {
        background: rgba(255, 255, 255, 0.15);
        transform: translateX(5px);
    }

    .sidebar-item.active {
        background: rgba(255, 255, 255, 0.25);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .sidebar-item.active a {
        font-weight: 600;
    }

    /* Sidebar Header (section title) */
    .sidebar-header {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.6;
        padding: 15px 15px 8px 15px;
        margin-top: 5px;
    }

    /* Dropdown Submenu */
    .sidebar-dropdown {
        position: relative;
    }

    .dropdown-arrow {
        transition: transform 0.3s ease;
    }

    .sidebar-dropdown.active .dropdown-arrow {
        transform: rotate(90deg);
    }

    .sub-menu {
        list-style: none;
        padding-left: 45px;
        margin: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    .sidebar-dropdown.active .sub-menu {
        max-height: 300px;
    }

    .sub-menu li {
        margin: 2px 0;
    }

    .sub-menu li a {
        padding: 10px 15px;
        font-size: 0.85rem;
        opacity: 0.85;
    }

    .sub-menu li a i {
        font-size: 0.85rem;
        width: 20px;
    }

    .sub-menu li:hover a {
        opacity: 1;
        transform: translateX(5px);
    }

    .sub-menu li.active a {
        opacity: 1;
        font-weight: 500;
    }

    /* Sidebar Footer */
    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.7rem;
        text-align: center;
        opacity: 0.6;
    }

    /* Compact Mode Adjustments */
    <?php if ($compact_mode === '1'): ?>
    .sidebar-item a {
        padding: 8px 12px;
    }
    .sidebar-item a i {
        font-size: 1rem;
    }
    .sidebar-user {
        padding: 15px 10px;
    }
    .sidebar-avatar {
        width: 55px;
        height: 55px;
        font-size: 22px;
    }
    .sub-menu {
        padding-left: 38px;
    }
    <?php endif; ?>
</style>

<!-- Sidebar Overlay (for mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Super Admin Sidebar -->
<div class="sidebar" id="sidebar">
    
    <!-- User Info Section -->
    <div class="sidebar-user">
        <div class="sidebar-avatar <?php echo $profile_image_path ? 'has-image' : ''; ?>"
             style="<?php echo $profile_image_path ? 'background-image: url(\'' . $profile_image_path . '\')' : ''; ?>">
            <?php if (!$profile_image_path): ?>
                <i class="fas fa-crown"></i>
            <?php endif; ?>
        </div>
        <div class="sidebar-user-name">
            <?php echo htmlspecialchars($display_name_with_title); ?>
        </div>
        <div class="sidebar-user-role">
            <i class="fas fa-crown me-1" style="font-size: 0.6rem;"></i>
            System Administrator
        </div>
    </div>

    <!-- Sidebar Menu -->
    <ul class="sidebar-menu">
        
        <!-- DASHBOARD -->
        <li class="sidebar-item <?php echo is_active(['dashboard.php', 'index.php'], $current_page, $current_dir) ? 'active' : ''; ?>">
            <a href="../super/dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- PROFILE SECTION HEADER -->
        <li class="sidebar-header">
            <span><i class="fas fa-user-circle me-1"></i> ACCOUNT</span>
        </li>

        <!-- Profile -->
        <li class="sidebar-item <?php echo is_active(['profile.php', 'edit_profile.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../super/profile.php">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
        </li>

      

        <!-- SCHOOLS MANAGEMENT SECTION HEADER -->
        <li class="sidebar-header">
            <span><i class="fas fa-building me-1"></i> SCHOOLS</span>
        </li>


        <!-- Manage All Schools -->
        <li class="sidebar-item <?php echo is_active(['schools.php', 'view_school.php', 'edit_school.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../super/schools.php">
                <i class="fas fa-school"></i>
                <span>All Schools</span>
            </a>
        </li>

        

        <!-- All School Admins -->
        <li class="sidebar-item <?php echo is_active(['school_admins.php', 'view_admin.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../super/school_admins.php">
                <i class="fas fa-user-shield"></i>
                <span>School Admins</span>
            </a>
        </li>

    

        <!-- Super Admins Management -->
        <li class="sidebar-item <?php echo is_active(['super_admins.php', 'add_super_admin.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../super/super_admins.php">
                <i class="fas fa-crown"></i>
                <span>Super Admins</span>
            </a>
        </li>

        <!-- SYSTEM SECTION HEADER -->
        <li class="sidebar-header">
            <span><i class="fas fa-cog me-1"></i> SYSTEM</span>
        </li>

        <!-- System Settings -->
        <li class="sidebar-item <?php echo is_active(['settings.php', 'general_settings.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../profile/settings.php">
                <i class="fas fa-sliders-h"></i>
                <span>System Settings</span>
            </a>
        </li>

        <!-- Activity Logs -->
        <li class="sidebar-item <?php echo is_active(['activity_logs.php', 'logs.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../super/activity_logs.php">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
            </a>
        </li>

        <!-- Database Backup -->
        <li class="sidebar-item <?php echo is_active(['backup.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../super/backup.php">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>
        </li>

        <!-- REPORTS SECTION HEADER -->
        <li class="sidebar-header">
            <span><i class="fas fa-chart-bar me-1"></i> REPORTS</span>
        </li>

        <!-- System Reports -->
        <li class="sidebar-item <?php echo is_active(['reports.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../super/reports.php">
                <i class="fas fa-file-alt"></i>
                <span>System Reports</span>
            </a>
        </li>

        <!-- School Performance Reports -->
        <li class="sidebar-item <?php echo is_active(['performance_reports.php'], $current_page) ? 'active' : ''; ?>">
            <a href="../super/performance_reports.php">
                <i class="fas fa-chart-line"></i>
                <span>School Performance</span>
            </a>
        </li>

    </ul>

   
</div>

<!-- Sidebar Toggle JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    // Function to toggle sidebar on mobile
    function toggleSidebarMobile() {
        if (window.innerWidth < 992) {
            sidebar.classList.toggle('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
        } else {
            // On desktop, check if sidebar should be visible
            const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.remove('desktop-visible');
            } else {
                sidebar.classList.add('desktop-visible');
            }
        }
    }
    
    // Function to close sidebar on mobile
    function closeSidebarMobile() {
        if (window.innerWidth < 992) {
            sidebar.classList.remove('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
        }
    }
    
    // Toggle button click
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (window.innerWidth < 992) {
                toggleSidebarMobile();
            } else {
                // On desktop, toggle visibility and save preference
                sidebar.classList.toggle('desktop-visible');
                const isCollapsed = !sidebar.classList.contains('desktop-visible');
                localStorage.setItem('sidebar_collapsed', isCollapsed);
                
                // Update main content margin
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    if (isCollapsed) {
                        mainContent.classList.add('sidebar-hidden');
                    } else {
                        mainContent.classList.remove('sidebar-hidden');
                    }
                }
            }
        });
    }
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebarMobile);
    }
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 992) {
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('active');
                }
                sidebar.classList.remove('active');
                
                // Restore desktop state
                const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.remove('desktop-visible');
                } else {
                    sidebar.classList.add('desktop-visible');
                }
            } else {
                sidebar.classList.remove('desktop-visible');
            }
        }, 250);
    });
    
    // Dropdown submenu functionality
    const dropdowns = document.querySelectorAll('.sidebar-dropdown');
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                dropdown.classList.toggle('active');
            });
        }
    });
    
    // Initialize sidebar state from localStorage
    if (window.innerWidth >= 992) {
        const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.remove('desktop-visible');
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.classList.add('sidebar-hidden');
            }
        } else {
            sidebar.classList.add('desktop-visible');
        }
    }
});
</script>