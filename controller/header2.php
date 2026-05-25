<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muyovozi High School</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    /* Updated color system: primary #63E07E, background white, text dark for contrast */
    :root {
        --primary-color: #63E07E;
        --primary-dark: #4FB568;
        --primary-light: #A8F0B8;
        --white: #ffffff;
        --light-color: #f8f9fa;
        --gray: #e9ecef;
        --text-color: #212529;
        --text-light: #6c757d;
        --border-color: #dee2e6;
        --success-color: #28a745;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --info-color: #17a2b8;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--white);
        min-height: 100vh;
        padding-top: 60px;
        width: 100%;
        color: var(--text-color);
        line-height: 1.6;
    }
    
    .header {
        background-color: var(--primary-color);
        color: var(--white);
        padding: 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        height: 60px;
    }
    
    .logo-container {
        display: flex;
        align-items: center;
        height: 40px;
        cursor: pointer;
    }
    
    .logo-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--white);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }
    
    .logo-placeholder {
        width: 40px;
        height: 40px;
        background-color: var(--white);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-size: 18px;
        font-weight: bold;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        border: 2px solid var(--white);
    }
    
    .school-name {
        font-size: 16px;
        font-weight: 700;
        margin-left: 10px;
        letter-spacing: 0.5px;
        color: var(--white);
    }
    
    .school-center {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .school-main-name {
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.5px;
        line-height: 1.1;
        color: var(--white);
    }
    
    .school-motto {
        font-size: 11px;
        opacity: 0.9;
        line-height: 1.1;
        color: var(--white);
    }
    
    .user-profile-compact {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        height: 100%;
        gap: 10px;
    }
    
    .user-role-display {
        font-size: 11px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 10px;
        border-radius: 15px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 120px;
        color: var(--white);
    }
    
    .user-avatar-dropdown {
        position: relative;
    }
    
    .user-avatar-small {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-weight: bold;
        font-size: 14px;
        border: 2px solid var(--white);
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }
    
    .user-avatar-small:hover {
        transform: scale(1.05);
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
    }
    
    .user-avatar-small.has-image {
        background-size: cover;
        background-position: center;
    }
    
    .dropdown-indicator {
        position: absolute;
        bottom: -2px;
        right: -2px;
        background: var(--primary-dark);
        color: var(--white);
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        border: 2px solid var(--primary-color);
    }
    
    .user-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--white);
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        min-width: 280px;
        z-index: 1001;
        display: none;
        margin-top: 10px;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .user-dropdown-menu.show {
        display: block;
        animation: fadeIn 0.2s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .user-dropdown-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: var(--white);
        padding: 15px;
        border-radius: 8px 8px 0 0;
    }
    
    .user-dropdown-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-size: 22px;
        font-weight: bold;
        margin: 0 auto 10px;
        border: 3px solid rgba(255, 255, 255, 0.3);
    }
    
    .user-dropdown-avatar.has-image {
        background-size: cover;
        background-position: center;
    }
    
    .user-dropdown-name {
        font-size: 16px;
        font-weight: 600;
        text-align: center;
        margin-bottom: 5px;
        color: var(--white);
    }
    
    .user-dropdown-role {
        font-size: 12px;
        opacity: 0.9;
        text-align: center;
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 10px;
        border-radius: 15px;
        display: inline-block;
        margin: 0 auto;
        color: var(--white);
    }
    
    .user-dropdown-body {
        padding: 15px;
        background: var(--white);
    }
    
    .user-info-item {
        display: flex;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .user-info-item:last-child {
        border-bottom: none;
    }
    
    .user-info-icon {
        width: 30px;
        color: var(--primary-color);
        font-size: 14px;
    }
    
    .user-info-content {
        flex: 1;
    }
    
    .user-info-label {
        font-size: 11px;
        color: var(--text-light);
        margin-bottom: 2px;
    }
    
    .user-info-value {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-color);
    }
    
    .user-dropdown-footer {
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 0 0 8px 8px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
    }
    
/* Sidebar Toggle Button */
.sidebar-toggle {
    background: transparent;
    border: 2px solid var(--white);
    color: var(--white);
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-left: 5px;
    padding: 0;
}

.sidebar-toggle:hover {
    background: var(--white);
    color: var(--primary-color);
    transform: scale(1.05);
}

/* Sidebar - SINGLE DECLARATION ONLY */
.sidebar {
    background-color: var(--primary-color);
    min-height: calc(100vh - 60px);
    box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
    padding: 20px 0;
    transition: transform 0.3s ease;
    position: fixed;
    top: 60px;
    left: 0;
    width: 250px; /* Tumia width moja thabiti */
    z-index: 1000;
    overflow-y: auto;
    overflow-x: hidden !important;
    max-height: calc(100vh - 60px);
    display: flex;
    flex-direction: column;
    transform: translateX(-100%);
}

.sidebar.active {
    transform: translateX(0);
}

/* Desktop styles */
@media (min-width: 992px) {
    .sidebar {
        transform: translateX(0);
    }
    
    .sidebar-toggle {
        display: none;
    }
    
    .main-content {
        margin-left: 280px; /* Lazima iwe sawa na sidebar width */
    }
}
    
    /* Mobile styles */
    @media (max-width: 991.98px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .sidebar-toggle {
            display: flex;
        }
        
        .main-content {
            margin-left: 0;
        }
    }
    
    /* Small mobile devices (610-615px) */
    @media (max-width: 615px) {
        .school-center {
            display: none;
        }
        
        .user-role-display {
            max-width: 70px;
            font-size: 9px;
            padding: 2px 6px;
        }
        
        .user-avatar-small {
            width: 32px;
            height: 32px;
            font-size: 12px;
        }
        
        .school-name.d-md-none {
            font-size: 12px;
        }
        
        .logo-container {
            height: 36px;
        }
        
        .logo-img {
            width: 32px;
            height: 32px;
        }
    }
    
    /* Very small devices */
    @media (max-width: 400px) {
        .user-role-display {
            display: none;
        }
        
        .school-name.d-md-none {
            display: none !important;
        }
        
        .logo-container {
            justify-content: center;
        }
    }
    
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
        flex: 1;
        overflow-y: auto;
    }
    
    .sidebar-menu li {
        padding: 0;
    }
    
    .sidebar-menu a {
        color: var(--white);
        display: flex;
        align-items: center;
        padding: 12px 20px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        border-left: 4px solid transparent;
        min-height: 48px;
    }
    
    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        background-color: var(--primary-dark);
        border-left: 4px solid var(--white);
    }
    
    .sidebar-menu i {
        width: 30px;
        text-align: center;
        font-size: 16px;
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 60px;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 998;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .sidebar-overlay.active {
        display: block;
        opacity: 1;
    }
    
    .main-content {
        min-height: calc(100vh - 60px);
        padding: 20px;
        transition: margin-left 0.3s ease;
        background: var(--white);
        border-radius: 8px;
        margin-top: 5px;
    }
    
    .btn-logout {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: var(--white);
        border: none;
        padding: 6px 15px;
        border-radius: 5px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .btn-logout:hover {
        background: linear-gradient(135deg, #c82333, #bd2130);
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(220, 53, 69, 0.3);
    }
    
    .btn-profile {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: var(--white);
        border: none;
        padding: 6px 15px;
        border-radius: 5px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .btn-profile:hover {
        background: linear-gradient(135deg, var(--primary-dark), #4ca065);
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(99, 224, 126, 0.3);
    }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="container-fluid h-100">
            <div class="row align-items-center h-100">
                <!-- Logo on left side with toggle button for mobile -->
                <div class="col-5 col-sm-4 col-md-3">
                    <div class="d-flex align-items-center">
                        <button class="sidebar-toggle" id="sidebarToggle" type="button">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="logo-container" id="logoContainer">
                            <?php
                            require_once 'db_connect.php';
                            
                            $logoPath = "../muyovozi.jpg";
                            if (file_exists($logoPath)) {
                                echo '<img src="' . $logoPath . '" alt="Muyovozi High School Logo" class="logo-img">';
                            } else {
                                echo '<div class="logo-placeholder">M</div>';
                            }
                            ?>
                            <div class="school-name d-none d-md-block">MUYOVOZI HS</div>
                            <div class="school-name d-md-none">MHS</div>
                        </div>
                    </div>
                </div>
                
                <!-- School name in center - hidden on small devices -->
                <div class="col-4 col-md-6 d-none d-sm-flex justify-content-center">
                    <div class="school-center text-center">
                        <div class="school-main-name">MUYOVOZI HIGH SCHOOL</div>
                        <div class="school-motto">Education For Life</div>
                    </div>
                </div>
                
                <!-- Right side: User profile -->
                <div class="col-7 col-sm-8 col-md-3">
                    <div class="user-profile-compact">
                        <?php
                        
                        // Fetch admin details with roles from database
                        $user_sql = "SELECT a.*, 
                                    GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
                                    GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role
                                    FROM admins a
                                    LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
                                    LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                                    WHERE a.id = $admin_id
                                    GROUP BY a.id";
                                    
                        $user_result = mysqli_query($conn, $user_sql);
                        $user = mysqli_fetch_assoc($user_result);
                        
                        if ($user) {
                            $user_firstname = $user['first_name'] ?? 'Admin';
                            $user_lastname = $user['last_name'] ?? 'User';
                            $user_sex = $user['sex'] ?? 'Male';
                            $user_roles = $user['roles'] ?? 'Administrator';
                            $user_primary_role = $user['primary_role'] ?? 'Administrator';
                            $user_email = $user['email'] ?? '';
                            $user_phone = $user['phone_number'] ?? '';
                            $user_nida = $user['nida'] ?? '';
                            $user_profile_image = $user['profile_image'] ?? '';
                            $user_status = $user['status'] ?? 1;
                            $user_created = $user['created_at'] ?? '';
                            
                            // Determine title
                            $title = ($user_sex == 'Female') ? 'Ms.' : 'Mr.';
                            $short_name = $title . ' ' . $user_firstname;
                            $full_name = $title . ' ' . $user_firstname . ' ' . $user_lastname;
                            
                            // Get initials for avatar
                            $initials = substr($user_firstname, 0, 1) . substr($user_lastname, 0, 1);
                            
                            // Get profile image path
                            $profile_image_path = '';
                            if (!empty($user_profile_image) && file_exists("../uploads/profiles/" . $user_profile_image)) {
                                $profile_image_path = "../uploads/profiles/" . $user_profile_image;
                            }
                        } else {
                            // Default values if user not found
                            $user_firstname = 'Admin';
                            $user_lastname = 'User';
                            $user_sex = 'Male';
                            $user_primary_role = 'Administrator';
                            $user_roles = 'Administrator';
                            $title = 'Mr.';
                            $short_name = 'Mr. Admin';
                            $full_name = 'Mr. Admin User';
                            $initials = 'AU';
                            $profile_image_path = '';
                            $user_email = '';
                            $user_phone = '';
                            $user_nida = '';
                            $user_status = 1;
                            $user_created = date('Y-m-d');
                        }
                        ?>
                        
                        <!-- Role Display -->
                        <div class="user-role-display" title="<?php echo htmlspecialchars($user_roles); ?>">
                            <?php echo htmlspecialchars($user_primary_role); ?>
                        </div>
                        
                        <!-- User Avatar with Dropdown -->
                        <div class="user-avatar-dropdown">
                            <div class="user-avatar-small <?php echo $profile_image_path ? 'has-image' : ''; ?>" 
                                 id="userAvatar"
                                 style="<?php echo $profile_image_path ? 'background-image: url(\'' . $profile_image_path . '\')' : ''; ?>">
                                <?php if (!$profile_image_path): ?>
                                    <?php echo $initials; ?>
                                <?php endif; ?>
                                <div class="dropdown-indicator">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            
                            <!-- User Dropdown Menu -->
                            <div class="user-dropdown-menu" id="userDropdown">
                                <div class="user-dropdown-header">
                                    <div class="user-dropdown-avatar <?php echo $profile_image_path ? 'has-image' : ''; ?>"
                                         style="<?php echo $profile_image_path ? 'background-image: url(\'' . $profile_image_path . '\')' : ''; ?>">
                                        <?php if (!$profile_image_path): ?>
                                            <?php echo $initials; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-dropdown-name"><?php echo htmlspecialchars($full_name); ?></div>
                                    <div class="user-dropdown-role"><?php echo htmlspecialchars($user_primary_role); ?></div>
                                </div>
                                
                                <div class="user-dropdown-body">
                                    <div class="user-info-item">
                                        <div class="user-info-icon">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div class="user-info-content">
                                            <div class="user-info-label">Email</div>
                                            <div class="user-info-value"><?php echo htmlspecialchars($user_email); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="user-info-item">
                                        <div class="user-info-icon">
                                            <i class="fas fa-user-tag"></i>
                                        </div>
                                        <div class="user-info-content">
                                            <div class="user-info-label">All Roles</div>
                                            <div class="user-info-value">
                                                <?php 
                                                $roles_array = explode(', ', $user_roles);
                                                foreach ($roles_array as $role): 
                                                    if (!empty(trim($role))):
                                                ?>
                                                <span class="badge bg-secondary me-1 mb-1" style="font-size: 9px; background-color: var(--text-light); color: white;">
                                                    <?php echo htmlspecialchars(trim($role)); ?>
                                                </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                                
                                <div class="user-dropdown-footer">
                                    <a href="../profile/profile.php" class="btn-profile">
                                        <i class="fas fa-user-cog me-1"></i>Profile
                                    </a>
                                    <a href="../controller/logout.php" class="btn-logout">
                                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.querySelector('.main-content');
        const userAvatar = document.getElementById('userAvatar');
        const userDropdown = document.getElementById('userDropdown');
        
        // Toggle sidebar function
        function toggleSidebar() {
            if (sidebar) {
                sidebar.classList.toggle('active');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle('active');
                }
                
                // Prevent body scrolling when sidebar is open on mobile
                if (window.innerWidth < 992) {
                    if (sidebar.classList.contains('active')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = 'auto';
                    }
                }
            }
        }
        
        // Sidebar toggle button click
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        // Logo container click for mobile
        const logoContainer = document.getElementById('logoContainer');
        if (logoContainer && window.innerWidth < 992) {
            logoContainer.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSidebar();
            });
        }
        
        // Sidebar overlay click
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                if (sidebar) {
                    sidebar.classList.remove('active');
                }
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
        }
        
        // User avatar dropdown
        if (userAvatar && userDropdown) {
            userAvatar.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
            
            // Close dropdown on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    userDropdown.classList.remove('show');
                }
            });
        }
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= 992) {
                    // Desktop mode
                    if (sidebar) {
                        sidebar.classList.add('active');
                    }
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.remove('active');
                    }
                    document.body.style.overflow = 'auto';
                } else {
                    // Mobile mode
                    if (sidebar) {
                        sidebar.classList.remove('active');
                    }
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.remove('active');
                    }
                    // Close any open dropdowns
                    if (userDropdown) {
                        userDropdown.classList.remove('show');
                    }
                }
            }, 250);
        });
        
        // Close sidebar when clicking on a link (mobile only)
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    if (sidebar) {
                        sidebar.classList.remove('active');
                    }
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.remove('active');
                    }
                    document.body.style.overflow = 'auto';
                }
            });
        });
        
        // Initialize based on screen size
        if (window.innerWidth >= 992) {
            if (sidebar) {
                sidebar.classList.add('active');
            }
        } else {
            if (sidebar) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // Function to update user avatar
    function updateUserAvatar(imageUrl) {
        const avatarSmall = document.getElementById('userAvatar');
        const avatarLarge = document.querySelector('.user-dropdown-avatar');
        const initials = '<?php echo $initials; ?>';
        
        if (avatarSmall) {
            if (imageUrl) {
                avatarSmall.style.backgroundImage = `url('${imageUrl}')`;
                avatarSmall.classList.add('has-image');
                avatarSmall.innerHTML = '<div class="dropdown-indicator"><i class="fas fa-chevron-down"></i></div>';
            } else {
                avatarSmall.style.backgroundImage = '';
                avatarSmall.classList.remove('has-image');
                avatarSmall.innerHTML = initials + '<div class="dropdown-indicator"><i class="fas fa-chevron-down"></i></div>';
            }
        }
        
        if (avatarLarge) {
            if (imageUrl) {
                avatarLarge.style.backgroundImage = `url('${imageUrl}')`;
                avatarLarge.classList.add('has-image');
                avatarLarge.innerHTML = '';
            } else {
                avatarLarge.style.backgroundImage = '';
                avatarLarge.classList.remove('has-image');
                avatarLarge.innerHTML = initials;
            }
        }
    }
    </script>
</body>
</html>