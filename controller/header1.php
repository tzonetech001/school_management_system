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
    :root {--primary-color:#3B9DB3;--primary-dark:#2d7c8f;--primary-light:#8bc5d6;--light-color:#f8f9fa;--white:#ffffff;--gray:#e9ecef;--text-color:#333333;--text-light:#666666;--border-color:#e0e0e0;--success-color:#28a745;--danger-color:#dc3545;--warning-color:#ffc107;--info-color:#17a2b8;}
    body {font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:linear-gradient(rgba(255,255,255,0.55),rgba(255,255,255,0.5)),url('../muyovozi.png') no-repeat center center fixed;background-size:90%;background-position-y:50px;min-height:100vh;padding-top:60px;width:100%;color:var(--text-color);line-height:1.6;}
    .header {background-color:var(--primary-color);color:white;padding:0;box-shadow:0 4px 12px rgba(0,0,0,0.1);position:fixed;top:0;left:0;right:0;z-index:1000;height:60px;}
    .logo-container {display:flex;align-items:center;height:40px;cursor:pointer;}
    .logo-img {width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.2);}
    .logo-placeholder {width:40px;height:40px;background-color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--primary-color);font-size:18px;font-weight:bold;box-shadow:0 2px 5px rgba(0,0,0,0.2);border:2px solid white;}
    .school-name {font-size:16px;font-weight:700;margin-left:10px;letter-spacing:0.5px;}
    .school-center {display:flex;flex-direction:column;align-items:center;justify-content:center;}
    .school-main-name {font-size:18px;font-weight:700;letter-spacing:0.5px;line-height:1.1;}
    .school-motto {font-size:11px;opacity:0.9;line-height:1.1;}
    .user-profile-compact {display:flex;align-items:center;justify-content:flex-end;height:100%;gap:10px;}
    .user-role-display {font-size:11px;font-weight:600;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px;}
    .user-avatar-dropdown {position:relative;}
    .user-avatar-small {width:36px;height:36px;border-radius:50%;background-color:white;display:flex;align-items:center;justify-content:center;color:var(--primary-color);font-weight:bold;font-size:14px;border:2px solid white;cursor:pointer;transition:all 0.3s;position:relative;}
    .user-avatar-small:hover {transform:scale(1.05);box-shadow:0 0 0 3px rgba(255,255,255,0.3);}
    .user-avatar-small.has-image {background-size:cover;background-position:center;}
    .dropdown-indicator {position:absolute;bottom:-2px;right:-2px;background:var(--primary-dark);color:white;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;border:2px solid var(--primary-color);}
    .user-dropdown-menu {position:absolute;top:100%;right:0;background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.15);min-width:280px;z-index:1001;display:none;margin-top:10px;border:1px solid rgba(0,0,0,0.1);}
    .user-dropdown-menu.show {display:block;animation:fadeIn 0.2s ease;}
    @keyframes fadeIn {from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
    .user-dropdown-header {background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white;padding:15px;border-radius:8px 8px 0 0;}
    .user-dropdown-avatar {width:60px;height:60px;border-radius:50%;background:white;display:flex;align-items:center;justify-content:center;color:var(--primary-color);font-size:22px;font-weight:bold;margin:0 auto 10px;border:3px solid rgba(255,255,255,0.3);}
    .user-dropdown-avatar.has-image {background-size:cover;background-position:center;}
    .user-dropdown-name {font-size:16px;font-weight:600;text-align:center;margin-bottom:5px;}
    .user-dropdown-role {font-size:12px;opacity:0.9;text-align:center;background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:15px;display:inline-block;margin:0 auto;}
    .user-dropdown-body {padding:15px;}
    .user-info-item {display:flex;align-items:center;padding:8px 0;border-bottom:1px solid rgba(0,0,0,0.05);}
    .user-info-item:last-child {border-bottom:none;}
    .user-info-icon {width:30px;color:var(--primary-color);font-size:14px;}
    .user-info-content {flex:1;}
    .user-info-label {font-size:11px;color:#666;margin-bottom:2px;}
    .user-info-value {font-size:13px;font-weight:500;color:#333;}
    .user-dropdown-footer {padding:10px 15px;background:#f8f9fa;border-radius:0 0 8px 8px;border-top:1px solid rgba(0,0,0,0.05);display:flex;justify-content:space-between;}
    .sidebar {background-color:var(--primary-color);min-height:calc(100vh - 80px);box-shadow:3px 0 10px rgba(0,0,0,0.1);padding:20px 0;transition:all 0.3s;position:fixed;left:-250px;top:80px;width:250px;z-index:999;overflow-y:auto;max-height:calc(100vh - 80px);display:flex;flex-direction:column;}
    .sidebar.active {left:0;}
    .sidebar-menu {list-style:none;padding:0;margin:0;flex:1;overflow-y:auto;}
    .sidebar-menu li {padding:0;}
    .sidebar-menu a {color:white;display:flex;align-items:center;padding:15px 25px;text-decoration:none;font-size:16px;font-weight:500;transition:all 0.3s;border-left:4px solid transparent;min-height:56px;}
    .sidebar-menu a:hover,.sidebar-menu a.active {background-color:var(--primary-dark);border-left:4px solid white;}
    .sidebar-menu i {width:30px;text-align:center;}
    .sidebar-overlay {position:fixed;top:80px;left:0;right:0;bottom:0;background-color:rgba(0,0,0,0.5);z-index:998;display:none;}
    .sidebar-overlay.active {display:block;}
    .main-content {min-height:calc(100vh - 60px);padding:20px;box-shadow:-3px 0 10px rgba(0,0,0,0.05);margin-left:0;transition:margin-left 0.3s ease;background:linear-gradient(rgba(255,255,255,0.55),rgba(255,255,255,0.5)),url('../muyovozi.png') no-repeat center center fixed;border-radius:8px;margin-top:5px;}
    .main-content.sidebar-open {margin-left:250px;}
    .card {border:none;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.05);transition:all 0.3s ease;overflow:hidden;margin-bottom:1.5rem;}
    .card:hover {box-shadow:0 8px 25px rgba(0,0,0,0.1);}
    .card-header {background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:var(--white);padding:1.25rem 1.5rem;border-bottom:none;border-radius:12px 12px 0 0 !important;font-weight:500;}
    .card-body {padding:1.5rem;background-color:lightgray;}
    .btn {border-radius:8px;padding:0.75rem 1.5rem;font-weight:500;font-size:1rem;transition:all 0.3s ease;border:2px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;}
    .btn-primary {background-color:var(--primary-color);border-color:var(--primary-color);}
    .btn-primary:hover {background-color:var(--primary-dark);border-color:var(--primary-dark);transform:translateY(-2px);box-shadow:0 4px 12px rgba(59,157,179,0.3);}
    .btn-outline-primary {color:var(--primary-color);border-color:var(--primary-color);background-color:transparent;}
    .btn-outline-primary:hover {background-color:var(--primary-color);color:var(--white);transform:translateY(-2px);}
    .btn-sm {padding:0.375rem 0.75rem;font-size:0.875rem;}
    .notification-badge {position:absolute;top:-5px;right:-5px;background:#dc3545;color:white;border-radius:50%;width:20px;height:20px;font-size:0.7rem;display:flex;align-items:center;justify-content:center;}
    .file-type-icon {width:50px;height:50px;display:flex;align-items:center;justify-content:center;border-radius:8px;margin-right:15px;}
    .file-type-icon.image {background:#e3f2fd;color:#1976d2;}
    .file-type-icon.video {background:#f3e5f5;color:#7b1fa2;}
    .file-type-icon.audio {background:#e8f5e8;color:#388e3c;}
    .file-type-icon.document {background:#fff3e0;color:#f57c00;}
    .file-type-icon.archive {background:#fce4ec;color:#c2185b;}
    @media (max-width:991.98px){.main-content.sidebar-open{margin-left:70px;}.main-content.sidebar-open-full{margin-left:250px;}.school-center .school-main-name{font-size:14px;}.school-center .school-motto{font-size:10px;display:none;}.user-role-display{font-size:9px;padding:2px 6px;max-width:90px;}.user-avatar-small{width:32px;height:32px;font-size:12px;}.user-dropdown-menu{position:fixed;top:60px;right:10px;left:10px;width:auto;}}
    @media (max-width:767.98px){.school-center{display:none;}.user-role-display{max-width:70px;font-size:8px;}.sidebar-toggle{width:36px;height:36px;font-size:14px;}}
    @media (max-width:575.98px){.header{padding:0;}.logo-container{height:36px;}.logo-img,.logo-placeholder{width:32px;height:32px;font-size:14px;}.school-name{font-size:11px;margin-left:6px;}.user-role-display{font-size:7px;padding:1px 4px;max-width:60px;}.user-avatar-small{width:28px;height:28px;font-size:15px;}}
    @media (min-width:700px){.sidebar{left:0;width:220px;}.main-content{margin-left:250px;}.sidebar-overlay{display:none!important;}.sidebar-toggle{display:none;}}
    .btn-logout {background:linear-gradient(135deg,#dc3545,#c82333);color:white;border:none;padding:6px 15px;border-radius:5px;font-size:13px;font-weight:500;transition:all 0.3s;}
    .btn-logout:hover {background:linear-gradient(135deg,#c82333,#bd2130);transform:translateY(-1px);box-shadow:0 3px 10px rgba(220,53,69,0.3);}
    .btn-profile {background:linear-gradient(135deg,var(--primary-color),var(--primary-dark));color:white;border:none;padding:6px 15px;border-radius:5px;font-size:13px;font-weight:500;transition:all 0.3s;}
    .btn-profile:hover {background:linear-gradient(135deg,var(--primary-dark),#2a7a8c);transform:translateY(-1px);box-shadow:0 3px 10px rgba(59,157,179,0.3);}
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="container-fluid h-100">
            <div class="row align-items-center h-100">
                <!-- Logo on left side -->
                <div class="col-4 col-md-3">
                    <div class="logo-container" id="logoContainer">
                        <?php
                        
                        require_once 'db_connect.php';
                        
                        $logoPath = "../muyovozi.jpg";
                        if (file_exists($logoPath)) {
                            echo '<img src="' . $logoPath . '" alt="Muyovozi High School Logo" class="logo-img">';
                        } else {
                           echo '<img src="' . $logoPath . '" alt="Muyovozi High School Logo" class="logo-img">';
                        }
                        ?>
                        <div class="school-name d-none d-md-block">MUYOVOZI HS</div>
                        <div class="school-name d-md-none">MUYOVOZI</div>
                    </div>
                </div>
                
                <!-- School name in center -->
                <div class="col-4 col-md-6 d-none d-sm-flex justify-content-center">
                    <div class="school-center text-center">
                        <div class="school-main-name">MUYOVOZI HIGH SCHOOL</div>
                        <div class="school-motto">Education For Life</div>
                    </div>
                </div>
                
                <!-- Right side: Toggle + User profile -->
                <div class="col-8 col-md-3">
                    <div class="user-profile-compact">
                        <?php
                        // Get user info from session
                        $admin_id = $_SESSION['admin_id'] ?? 0;
                        
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
                                            <i class="fas fa-phone"></i>
                                        </div>
                                        <div class="user-info-content">
                                            <div class="user-info-label">Phone</div>
                                            <div class="user-info-value"><?php echo htmlspecialchars($user_phone); ?></div>
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
                                                <span class="badge bg-secondary me-1 mb-1" style="font-size: 9px;">
                                                    <?php echo htmlspecialchars(trim($role)); ?>
                                                </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    
                                    <div class="user-info-item">
                                        <div class="user-info-icon">
                                            <i class="fas fa-circle"></i>
                                        </div>
                                        <div class="user-info-content">
                                            <div class="user-info-label">Status</div>
                                            <div class="user-info-value">
                                                <span class="badge <?php echo $user_status ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $user_status ? 'Active' : 'Inactive'; ?>
                                                </span>
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
                        <!-- Sidebar Toggle Button for Mobile -->
                        <button class="sidebar-toggle d-md-none" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar will be included here -->
    
    <script>
    document.addEventListener('DOMContentLoaded',function(){const sidebarToggle=document.getElementById('sidebarToggle');const logoContainer=document.getElementById('logoContainer');const sidebar=document.getElementById('sidebar');const sidebarOverlay=document.getElementById('sidebarOverlay');const mainContent=document.querySelector('.main-content');const userAvatar=document.getElementById('userAvatar');const userDropdown=document.getElementById('userDropdown');function toggleSidebar(){if(window.innerWidth<992){sidebar.classList.toggle('active');sidebarOverlay.classList.toggle('active');if(mainContent){if(sidebar.classList.contains('active')){mainContent.classList.add('sidebar-open');if(sidebar.offsetWidth===250){mainContent.classList.add('sidebar-open-full');}}else{mainContent.classList.remove('sidebar-open');mainContent.classList.remove('sidebar-open-full');}}if(sidebar.classList.contains('active')){document.body.style.overflow='hidden';}else{document.body.style.overflow='auto';}}}if(sidebarToggle){sidebarToggle.addEventListener('click',toggleSidebar);}if(logoContainer){logoContainer.addEventListener('click',function(){if(window.innerWidth<992){toggleSidebar();}});}if(sidebarOverlay){sidebarOverlay.addEventListener('click',function(){sidebar.classList.remove('active');sidebarOverlay.classList.remove('active');if(mainContent){mainContent.classList.remove('sidebar-open');mainContent.classList.remove('sidebar-open-full');}document.body.style.overflow='auto';});}if(userAvatar&&userDropdown){userAvatar.addEventListener('click',function(e){e.stopPropagation();userDropdown.classList.toggle('show');});document.addEventListener('click',function(e){if(!userAvatar.contains(e.target)&&!userDropdown.contains(e.target)){userDropdown.classList.remove('show');}});userDropdown.addEventListener('click',function(e){if(e.target.tagName==='A'||e.target.closest('a')){userDropdown.classList.remove('show');}});document.addEventListener('keydown',function(e){if(e.key==='Escape'){userDropdown.classList.remove('show');}});}let resizeTimer;window.addEventListener('resize',function(){clearTimeout(resizeTimer);resizeTimer=setTimeout(function(){if(window.innerWidth>=992){sidebar.classList.add('active');sidebarOverlay.classList.remove('active');if(mainContent){mainContent.classList.add('sidebar-open');}document.body.style.overflow='auto';}else{sidebar.classList.remove('active');sidebarOverlay.classList.remove('active');if(mainContent){mainContent.classList.remove('sidebar-open');mainContent.classList.remove('sidebar-open-full');}userDropdown.classList.remove('show');}},250);});const sidebarLinks=document.querySelectorAll('.sidebar-menu a');sidebarLinks.forEach(link=>{link.addEventListener('click',function(){if(window.innerWidth<992){sidebar.classList.remove('active');sidebarOverlay.classList.remove('active');document.body.style.overflow='auto';if(mainContent){mainContent.classList.remove('sidebar-open');mainContent.classList.remove('sidebar-open-full');}}});});if(window.innerWidth>=992){if(sidebar)sidebar.classList.add('active');if(mainContent)mainContent.classList.add('sidebar-open');}});function updateUserAvatar(imageUrl){const avatarSmall=document.getElementById('userAvatar');const avatarLarge=document.querySelector('.user-dropdown-avatar');if(avatarSmall){if(imageUrl){avatarSmall.style.backgroundImage=`url('${imageUrl}')`;avatarSmall.classList.add('has-image');avatarSmall.innerHTML='';}else{avatarSmall.style.backgroundImage='';avatarSmall.classList.remove('has-image');avatarSmall.innerHTML=avatarSmall.dataset.initials||'AU';}}if(avatarLarge){if(imageUrl){avatarLarge.style.backgroundImage=`url('${imageUrl}')`;avatarLarge.classList.add('has-image');avatarLarge.innerHTML='';}else{avatarLarge.style.backgroundImage='';avatarLarge.classList.remove('has-image');avatarLarge.innerHTML=avatarLarge.dataset.initials||'AU';}}}
    </script>
</body>
</html>