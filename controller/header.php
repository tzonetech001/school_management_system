<?php
// header.php - Admin Header with Notification Bell & Session Management
// UPDATED: Added Super Admin support, School Motto from database, Multi-school support

// NO OUTPUT BEFORE THIS LINE - NOT EVEN SPACES OR BLANK LINES!

// Start session with custom settings
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters before starting session
    session_set_cookie_params([
        'lifetime' => 600, // 10 minutes
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}

// ==================== SESSION TIMEOUT (10 MINUTES) ====================
$inactivity_timeout = 600; // 10 minutes (600 seconds)

// Check for Super Admin first
$is_super_admin = isset($_SESSION['super_admin_id']);
$is_admin = isset($_SESSION['admin_id']);

if (!$is_super_admin && !$is_admin) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Location: ../index.php");
    exit();
}

// Super Admin session timeout check
if ($is_super_admin) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactivity_timeout) {
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: ../index.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
    
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

require_once 'db_connect.php';

// ==================== GET USER INFO BASED ON LOGIN TYPE ====================
$user = null;
$admin_id = null;
$school_id = null;
$school_name = "School Management System";
$school_motto = "Education For Life";
$unread_notification_count = 0;
$recent_notifications = [];

if ($is_super_admin) {
    // SUPER ADMIN - Get from super_admins table
    $super_id = $_SESSION['super_admin_id'];
    $super_sql = "SELECT * FROM super_admins WHERE id = $super_id AND status = 1";
    $super_result = mysqli_query($conn, $super_sql);
    $user = $super_result ? mysqli_fetch_assoc($super_result) : null;
    
    if (!$user) {
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: ../index.php");
        exit();
    }
    
    // For Super Admin, we don't have a single school - they see all schools
    $school_id = null;
    $school_name = "School Management System";
    
    // Get all schools for dropdown (optional)
    $schools_sql = "SELECT id, school_name, school_motto FROM schools WHERE status = 'Active' ORDER BY school_name";
    $schools_result = mysqli_query($conn, $schools_sql);
    $all_schools = [];
    if ($schools_result) {
        while ($row = mysqli_fetch_assoc($schools_result)) {
            $all_schools[] = $row;
        }
    }
    
    $full_name = $user['first_name'] . ' ' . $user['last_name'];
    $initials = strtoupper(substr($user['first_name'] ?? 'S', 0, 1) . substr($user['last_name'] ?? 'A', 0, 1));
    $title = $user['sex'] ?? 'Male' ? 'Mr.' : 'Ms.';
    $display_name_with_title = $title . ' ' . $user['first_name'] . ' ' . $user['last_name'];
    $profile_image_path = $user['profile_image'] ?? null;
    $user_role_display = 'System Administrator';
    
} else {
    // REGULAR ADMIN - Get from admins table with school info
    $admin_id = $_SESSION['admin_id'];
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactivity_timeout) {
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: ../index.php?timeout=1");
        exit();
    }
    
    $_SESSION['last_activity'] = time();
    
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Get admin with school info
    $user_sql = "SELECT a.*, 
                        s.id as school_id, 
                        s.school_name, 
                        s.school_motto,
                        s.school_code,
                        s.logo_path as school_logo
                FROM admins a
                JOIN schools s ON a.school_id = s.id
                WHERE a.id = $admin_id AND a.status = 1 AND s.status = 'Active'";
    $user_result = mysqli_query($conn, $user_sql);
    $user = $user_result ? mysqli_fetch_assoc($user_result) : null;
    
    if (!$user) {
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: ../index.php");
        exit();
    }
    
    $school_id = $user['school_id'];
    $school_name = $user['school_name'];
    $school_motto = !empty($user['school_motto']) ? $user['school_motto'] : "Education For Life";
    
    // Get admin roles
    $roles_sql = "SELECT GROUP_CONCAT(DISTINCT ar.role_name ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles,
                         GROUP_CONCAT(DISTINCT CASE WHEN ara.is_primary = 1 THEN ar.role_name END) as primary_role
                  FROM admin_role_assignments ara
                  LEFT JOIN admin_roles ar ON ara.role_id = ar.id
                  WHERE ara.admin_id = $admin_id";
    $roles_result = mysqli_query($conn, $roles_sql);
    $roles_row = $roles_result ? mysqli_fetch_assoc($roles_result) : null;
    
    $user['roles'] = $roles_row['roles'] ?? 'Staff';
    $user['primary_role'] = $roles_row['primary_role'] ?? 'Administrator';
    
    $full_name = $user['first_name'] . ' ' . $user['last_name'];
    $initials = strtoupper(substr($user['first_name'] ?? 'A', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1));
    $title = ($user['sex'] == 'Female') ? 'Ms.' : 'Mr.';
    $display_name_with_title = $title . ' ' . $user['first_name'] . ' ' . $user['last_name'];
    $profile_image_path = $user['profile_image'] ?? null;
    $user_role_display = $user['primary_role'] ?? 'Administrator';
    
    // ==================== GET UNREAD NOTIFICATION COUNT ====================
    // Get last notification check time
    $last_check_sql = "SELECT last_notification_check FROM admins WHERE id = $admin_id";
    $last_check_result = mysqli_query($conn, $last_check_sql);
    $last_check_row = mysqli_fetch_assoc($last_check_result);
    $last_check = $last_check_row ? $last_check_row['last_notification_check'] : null;
    
    // Get admin roles to determine visibility
    $admin_roles_sql = "SELECT GROUP_CONCAT(DISTINCT ar.role_name) as roles
                        FROM admin_role_assignments ara
                        JOIN admin_roles ar ON ara.role_id = ar.id
                        WHERE ara.admin_id = $admin_id";
    $admin_roles_result = mysqli_query($conn, $admin_roles_sql);
    $admin_roles_row = mysqli_fetch_assoc($admin_roles_result);
    $admin_roles = $admin_roles_row ? explode(',', $admin_roles_row['roles']) : [];
    $is_admin_user = in_array('Head Master', $admin_roles) || in_array('Second Master', $admin_roles) || in_array('Academic Master', $admin_roles);
    
    // Build query for unread notifications
    if ($last_check) {
        $unread_sql = "SELECT COUNT(DISTINCT n.id) as count 
                       FROM notifications n
                       LEFT JOIN notification_views nv ON n.id = nv.notification_id AND nv.viewer_id = $admin_id
                       WHERE n.status = 'active' 
                       AND n.school_id = $school_id
                       AND n.created_at > '$last_check'
                       AND nv.id IS NULL";
        
        if (!$is_admin_user) {
            $unread_sql .= " AND (n.visibility = 'public' OR n.admin_id = $admin_id)";
        }
        
        $unread_result = mysqli_query($conn, $unread_sql);
        if ($unread_result) {
            $unread_row = mysqli_fetch_assoc($unread_result);
            $unread_notification_count = $unread_row ? (int)$unread_row['count'] : 0;
        }
    }
    
    // Get recent notifications for dropdown (limit 3)
    $recent_sql = "SELECT n.*, 
                   CONCAT(a.first_name, ' ', a.last_name) as author_name,
                   (SELECT COUNT(*) FROM notification_views nv WHERE nv.notification_id = n.id AND nv.viewer_id = $admin_id) as is_viewed
                   FROM notifications n
                   JOIN admins a ON n.admin_id = a.id
                   WHERE n.status = 'active' 
                   AND n.school_id = $school_id ";
                   
    if (!$is_admin_user) {
        $recent_sql .= " AND (n.visibility = 'public' OR n.admin_id = $admin_id)";
    }
    
    $recent_sql .= " ORDER BY n.created_at DESC LIMIT 3";
    
    $recent_result = mysqli_query($conn, $recent_sql);
    if ($recent_result && mysqli_num_rows($recent_result) > 0) {
        while ($row = mysqli_fetch_assoc($recent_result)) {
            $recent_notifications[] = $row;
        }
    }
}

// ==================== THEME SETTINGS ====================
// Default theme colors
$default_colors = [
    'primary' => '#3B9DB3',
    'primary_dark' => '#2d7c8f',
    'primary_light' => '#8bc5d6',
    'light' => '#f8f9fa',
    'white' => '#ffffff',
    'gray' => '#e9ecef',
    'text' => '#333333',
    'text_light' => '#666666',
    'border' => '#e0e0e0',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8',
    'coral' => '#FF7F50',
    'forest_green' => '#2E7D32',
    'lime_green' => '#63E07E',
    'sky_blue' => '#66d9ff',
    'aqua_blue' => '#4dd2ff'
];

// Load user's theme settings (only for regular admins)
$theme_settings = [];
if (!$is_super_admin && $admin_id) {
    $query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row !== null && isset($row['setting_key']) && isset($row['setting_value'])) {
                $theme_settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
}
 
// Load preferences
$preferences = [];
if (!$is_super_admin && $admin_id) {
    $prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
    $prefs_result = mysqli_query($conn, $prefs_query);
    if ($prefs_result && mysqli_num_rows($prefs_result) > 0) {
        while ($row = mysqli_fetch_assoc($prefs_result)) {
            if ($row !== null && isset($row['preference_key']) && isset($row['preference_value'])) {
                $preferences[$row['preference_key']] = $row['preference_value'];
            }
        }
    }
}

$pref_defaults = [
    'background_opacity' => '65',
    'animations' => '1',
    'font_size' => '16',
    'compact_mode' => '0',
    'show_icons' => '1',
    'background_option' => 'image',
    'sidebar_collapsed' => '0',
    'animation_speed' => 'normal'
];

foreach ($pref_defaults as $key => $default) {
    if (!isset($preferences[$key]) || $preferences[$key] === null) {
        $preferences[$key] = $default;
    }
}

$colors = $default_colors;
foreach ($theme_settings as $key => $value) {
    if (array_key_exists($key, $colors) && $value !== null) {
        $colors[$key] = $value;
    }
}

$bg_opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;
$animations_enabled = $preferences['animations'];
$font_size = $preferences['font_size'];
$compact_mode = $preferences['compact_mode'];
$show_icons = $preferences['show_icons'];
$bg_option = $preferences['background_option'];
$sidebar_collapsed = $preferences['sidebar_collapsed'];
$animation_speed = $preferences['animation_speed'];

$animation_speeds = [
    'slow' => '0.5s',
    'normal' => '0.3s',
    'fast' => '0.15s'
];
$animation_duration = isset($animation_speeds[$animation_speed]) ? $animation_speeds[$animation_speed] : '0.3s';

$font_size_map = [
    '10' => '10px',
    '12' => '12px',
    '14' => '14px',
    '16' => '16px',
    '18' => '18px'
];
$font_size_value = isset($font_size_map[$font_size]) ? $font_size_map[$font_size] : '16px';

$background_colors = [
    'gray' => '#e9ecef',
    'eye_care' => '#c7e9c0',
    'milk' => '#fdf5e6',
    'dark_light' => '#2d2d2d'
];

if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('../muyovozi.png') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_color = isset($background_colors[$bg_option]) ? $background_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo $is_super_admin ? 'Super Admin Dashboard' : htmlspecialchars($school_name); ?> - School Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
   
    
    <style> /* Dynamic CSS variables */ :root {--primary-color: <?php echo isset($colors['primary']) ? $colors['primary'] : '#3B9DB3'; ?>; --primary-dark: <?php echo isset($colors['primary_dark']) ? $colors['primary_dark'] : '#2d7c8f'; ?>; --primary-light: <?php echo isset($colors['primary_light']) ? $colors['primary_light'] : '#8bc5d6'; ?>; --light-color: <?php echo isset($colors['light']) ? $colors['light'] : '#f8f9fa'; ?>; --white: <?php echo isset($colors['white']) ? $colors['white'] : '#ffffff'; ?>; --gray: <?php echo isset($colors['gray']) ? $colors['gray'] : '#e9ecef'; ?>; --text-color: <?php echo isset($colors['text']) ? $colors['text'] : '#333333'; ?>; --text-light: <?php echo isset($colors['text_light']) ? $colors['text_light'] : '#666666'; ?>; --border-color: <?php echo isset($colors['border']) ? $colors['border'] : '#e0e0e0'; ?>; --success-color: <?php echo isset($colors['success']) ? $colors['success'] : '#28a745'; ?>; --danger-color: <?php echo isset($colors['danger']) ? $colors['danger'] : '#dc3545'; ?>; --warning-color: <?php echo isset($colors['warning']) ? $colors['warning'] : '#ffc107'; ?>; --info-color: <?php echo isset($colors['info']) ? $colors['info'] : '#17a2b8'; ?>; --coral-color: <?php echo isset($colors['coral']) ? $colors['coral'] : '#FF7F50'; ?>; --forest-green: <?php echo isset($colors['forest_green']) ? $colors['forest_green'] : '#2E7D32'; ?>; --lime-green: <?php echo isset($colors['lime_green']) ? $colors['lime_green'] : '#63E07E'; ?>; --sky-blue: <?php echo isset($colors['sky_blue']) ? $colors['sky_blue'] : '#66d9ff'; ?>; --aqua-blue: <?php echo isset($colors['aqua_blue']) ? $colors['aqua_blue'] : '#4dd2ff'; ?>; --font-size-base: <?php echo $font_size_value; ?>; --spacing-base: <?php echo $compact_mode === '1' ? '0.75rem' : '1rem'; ?>; --animation-duration: <?php echo $animation_duration; ?>; } body {background: <?php echo $bg_style; ?>; background-size: <?php echo $bg_size; ?>; background-position: center; margin-top: 50px; } <?php if ($compact_mode === '1'): ?> .card-body { padding: 0.75rem !important; } .btn { padding: 0.5rem 1rem !important; } .form-control, .form-select { padding: 0.375rem 0.75rem !important; } .table td, .table th { padding: 0.5rem !important; } <?php endif; ?> <?php if ($animations_enabled !== '1'): ?> *, .sidebar, .sidebar * { transition: none !important; } <?php endif; ?> /* School Name - Times New Roman - Responsive */ .school-main-name {font-family: 'Times New Roman', Times, serif !important; font-weight: 800; letter-spacing: 1px; line-height: 1.2; white-space: nowrap; } @media (min-width: 1400px) {.school-main-name { font-size: 34px; } .school-motto { font-size: 14px; } } @media (min-width: 1200px) and (max-width: 1399px) {.school-main-name { font-size: 30px; } .school-motto { font-size: 12px; } } @media (min-width: 992px) and (max-width: 1199px) {.school-main-name { font-size: 26px; } .school-motto { font-size: 11px; } } @media (max-width: 991px) {.school-main-name { font-size: 22px; } .school-motto { font-size: 10px; } } /* Notification Bell Styles */ .notification-bell {position: relative; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; } .bell-icon {font-size: 20px; color: white; transition: all 0.3s ease; background: rgba(255,255,255,0.15); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; } @media (min-width: 768px) {.bell-icon {font-size: 22px; width: 40px; height: 40px; } } .bell-icon:hover {background: rgba(255,255,255,0.3); transform: scale(1.05); } .notification-badge {position: absolute; top: -5px; right: -8px; background: #dc3545; color: white; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 18px; display: flex; align-items: center; justify-content: center; padding: 0 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); animation: pulse 2s infinite; } @media (min-width: 768px) {.notification-badge {font-size: 11px; min-width: 20px; height: 20px; padding: 0 5px; } } @keyframes pulse {0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } } /* Notification Dropdown - Closer to header */ .notification-dropdown {position: fixed; top: 60px; right: 80px; width: 360px; max-width: calc(100vw - 20px); background: white; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.2); z-index: 1050; display: none; overflow: hidden; border: 1px solid rgba(0,0,0,0.1); } @media (min-width: 768px) {.notification-dropdown {right: 100px; width: 380px; } } @media (max-width: 576px) {.notification-dropdown {right: 10px; left: 10px; width: auto; max-width: none; } } .notification-dropdown.show {display: block; animation: slideDown 0.25s ease; } @keyframes slideDown {from {opacity: 0; transform: translateY(-10px); } to {opacity: 1; transform: translateY(0); } } .notification-dropdown-header {background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; } .notification-dropdown-header h6 {margin: 0; font-weight: 600; font-size: 14px; } .mark-all-read {background: rgba(255,255,255,0.2); border: none; color: white; font-size: 11px; padding: 4px 10px; border-radius: 20px; cursor: pointer; transition: all 0.3s ease; } .mark-all-read:hover {background: rgba(255,255,255,0.3); } .notification-list {max-height: 400px; overflow-y: auto; } .notification-item-dropdown {display: flex; align-items: flex-start; gap: 12px; padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: inherit; } .notification-item-dropdown:hover {background: #f8f9fa; } .notification-item-dropdown.unread {background: #e8f4f8; } .notification-icon-dropdown {width: 40px; height: 40px; background: rgba(59,157,179,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; } .notification-icon-dropdown i {color: var(--primary-color); font-size: 18px; } .notification-content-dropdown {flex: 1; min-width: 0; } .notification-title-dropdown {font-weight: 600; font-size: 14px; margin-bottom: 4px; color: #333; } .notification-message {font-size: 12px; color: #555; margin-bottom: 6px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-wrap: break-word; } .notification-time {font-size: 10px; color: #999; display: flex; align-items: center; gap: 4px; } .unread-dot-dropdown {width: 8px; height: 8px; background: #dc3545; border-radius: 50%; display: inline-block; margin-left: 8px; } .notification-dropdown-footer {padding: 10px 15px; text-align: center; border-top: 1px solid #f0f0f0; background: #f8f9fa; } .notification-dropdown-footer a {color: var(--primary-color); text-decoration: none; font-size: 13px; font-weight: 500; } .notification-dropdown-footer a:hover {text-decoration: underline; } .empty-notifications {text-align: center; padding: 40px 20px; color: #999; } .empty-notifications i {font-size: 40px; margin-bottom: 10px; opacity: 0.5; } .empty-notifications p {font-size: 13px; margin: 0; } /* User Name Display - Left side with bell (replacing role) */ .user-name-display {font-size: 11px; font-weight: 600; background: rgba(255, 255, 255, 0.2); padding: 5px 12px; border-radius: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; line-height: 1.3; } @media (min-width: 768px) {.user-name-display {font-size: 12px; padding: 6px 14px; max-width: 220px; } } @media (max-width: 480px) {.user-name-display {font-size: 10px; padding: 4px 8px; max-width: 140px; } } @media (max-width: 380px) {.user-name-display {display: none; } } /* Left side container for bell and user name */ .left-header-items {display: flex; align-items: center; gap: 8px; } @media (min-width: 768px) {.left-header-items {gap: 12px; } } /* Header Styles */ .header {background-color: var(--primary-color); color: white; padding: 0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; height: 60px; width: 100%; } .header .container-fluid {height: 100%; padding: 0 15px; max-width: 100%; } .header .row {height: 100%; margin: 0; flex-wrap: nowrap; } .header .col-4, .header .col-8, .header .col-md-3, .header .col-md-6 {padding: 0; height: 100%; display: flex; align-items: center; } /* Logo Styles */ .logo-container {display: flex; align-items: center; height: 40px; padding-left: 5px; } .logo-img {width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); } .logo-placeholder {width: 40px; height: 40px; background-color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-size: 18px; font-weight: bold; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); border: 2px solid white; } .school-name {font-size: 16px; font-weight: 700; margin-left: 10px; letter-spacing: 0.5px; white-space: nowrap; } /* Center School Name */ .school-center {display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; } .school-motto {opacity: 0.9; line-height: 1.1; white-space: nowrap; } /* Super Admin Badge */ .super-admin-badge {background: linear-gradient(135deg, #dc3545, #c82333); color: white; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; margin-left: 8px; white-space: nowrap; } @media (min-width: 768px) {.super-admin-badge {font-size: 11px; padding: 3px 10px; } } /* Right side - User Profile Section */ .user-profile-compact {display: flex; align-items: center; justify-content: flex-end; height: 100%; gap: 10px; width: 100%; padding-right: 10px; } @media (min-width: 768px) {.user-profile-compact {gap: 15px; padding-right: 15px; } } .user-avatar-dropdown {position: relative; flex-shrink: 0; } .user-avatar-small {width: 36px; height: 36px; border-radius: 50%; background-color: white; display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: bold; font-size: 14px; border: 2px solid white; cursor: pointer; position: relative; } @media (min-width: 768px) {.user-avatar-small {width: 40px; height: 40px; font-size: 16px; } } .user-avatar-small.has-image {background-size: cover; background-position: center; } .dropdown-indicator {position: absolute; bottom: -2px; right: -2px; background: var(--primary-dark); color: white; width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; border: 2px solid var(--primary-color); pointer-events: none; } /* Sidebar Toggle Button */ .sidebar-toggle {background: transparent; border: 2px solid var(--white); color: var(--white); width: 36px; height: 36px; border-radius: 8px; display: none; align-items: center; justify-content: center; cursor: pointer; margin-left: 5px; padding: 0; } @media (min-width: 992px) {.sidebar-toggle {display: none; } } @media (max-width: 991px) {.sidebar-toggle {display: flex; } } .sidebar-toggle:hover {background: rgba(255, 255, 255, 0.3); } /* User Dropdown Menu - Compact List Style */ .user-dropdown-menu {position: absolute; top: 100%; right: 0; background: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); min-width: 220px; max-width: 90vw; z-index: 1001; display: none; margin-top: 10px; border: 1px solid rgba(0, 0, 0, 0.08); overflow: hidden; } .user-dropdown-menu.show {display: block; animation: fadeInDown 0.2s ease; } @keyframes fadeInDown {from {opacity: 0; transform: translateY(-8px); } to {opacity: 1; transform: translateY(0); } } /* User info header */ .user-dropdown-header {background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; padding: 14px 16px; text-align: left; } .user-dropdown-name {font-size: 14px; font-weight: 600; margin-bottom: 4px; word-break: break-word; } .user-dropdown-role {font-size: 11px; opacity: 0.85; background: rgba(255, 255, 255, 0.2); padding: 2px 8px; border-radius: 12px; display: inline-block; } /* Menu items - Compact list */ .user-dropdown-menu-items {padding: 6px 0; } .user-dropdown-item {display: flex; align-items: center; gap: 10px; padding: 8px 16px; color: #333; text-decoration: none; font-size: 13px; transition: background 0.2s ease; } .user-dropdown-item:hover {background: #f5f5f5; color: var(--primary-color); } .user-dropdown-item i {width: 18px; font-size: 14px; color: var(--primary-color); } /* Divider */ .dropdown-divider {height: 1px; background: #eee; margin: 4px 0; } /* Main Content */ .main-content {min-height: calc(100vh - 60px); padding: 20px; transition: margin-left 0.3s ease; border-radius: 8px; margin-top: 5px; overflow-x: auto; } /* Desktop styles */ @media (min-width: 992px) {.main-content {margin-left: 250px; } .main-content.sidebar-hidden {margin-left: 0; } } /* Mobile styles */ @media (max-width: 991px) {.school-center {display: none; } .main-content {margin-left: 0; } } /* Responsive Breakpoints */ @media (max-width: 767px) {.school-name {font-size: 14px; } .logo-img, .logo-placeholder {width: 35px; height: 35px; font-size: 16px; } } /* common.css - Common styles */ .sidebar, .sidebar *, .sidebar a, .sidebar-menu, .sub-menu, .sidebar-dropdown .sub-menu {transition: none !important; } .sidebar, .sidebar a, .sidebar a i, .main-content {transition: all 0.3s ease; } .no-animation {transition: none !important; } .sidebar.active, .sidebar.desktop-visible {transition: left 0.3s ease; } body.preload * {transition: none !important; } .dropdown-arrow i {transition: transform 0.2s ease; } *, *::before, *::after {box-sizing: border-box; } .text-center {text-align: center; } .d-none {display: none; } .d-sm-none {display: none; } @media (min-width: 576px) {.d-sm-block {display: block; } .d-sm-none {display: none; } } .d-flex {display: flex; } .align-items-center {align-items: center; } .justify-content-center {justify-content: center; } .justify-content-end {justify-content: flex-end; } .gap-1 {gap: 0.25rem; } .gap-2 {gap: 0.5rem; } .gap-3 {gap: 1rem; } .container-fluid {width: 100%; padding-right: 15px; padding-left: 15px; margin-right: auto; margin-left: auto; } .row {display: flex; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; } .row > [class*="col-"] {padding-right: 15px; padding-left: 15px; } .col-4 {flex: 0 0 33.333333%; max-width: 33.333333%; } .col-8 {flex: 0 0 66.666667%; max-width: 66.666667%; } @media (min-width: 768px) {.col-md-3 {flex: 0 0 25%; max-width: 25%; } .col-md-6 {flex: 0 0 50%; max-width: 50%; } } .h-100 {height: 100%; } .m-0 {margin: 0; } .mt-2 {margin-top: 0.5rem; } .mb-2 {margin-bottom: 0.5rem; } .ml-2 {margin-left: 0.5rem; } .mr-2 {margin-right: 0.5rem; } .g-0 {margin: 0; } .g-0 > [class*="col-"] {padding: 0; } </style>
</head>
<body class="<?php echo $animations_enabled === '1' ? '' : 'no-animation'; ?>">
    <!-- Apply font size to all content -->
<style> /* Force font size inheritance from header for all pages including profile and maintenance */ body, .main-content, .card, .card-body, .table, .form-control, .btn, .stats-card, .activity-item, .dropdown-menu, .modal-content, .container-fluid, .row, [class*="col-"], .page-title, h1, h2, h3, h4, h5, h6, p, span, div, label, input, select, textarea, a, li, td, th {font-size: var(--font-size-base, 16px); } /* Specific overrides for card headers that might have gradients */ .card-header {font-size: calc(var(--font-size-base, 16px) * 1.1); } /* Small text adjustments */ small, .small, .text-muted, .form-text, .help-block {font-size: calc(var(--font-size-base, 16px) * 0.875); } /* Heading sizes relative to base font */ h1, .h1 { font-size: calc(var(--font-size-base, 16px) * 2.5); } h2, .h2 { font-size: calc(var(--font-size-base, 16px) * 2); } h3, .h3 { font-size: calc(var(--font-size-base, 16px) * 1.75); } h4, .h4 { font-size: calc(var(--font-size-base, 16px) * 1.5); } h5, .h5 { font-size: calc(var(--font-size-base, 16px) * 1.25); } h6, .h6 { font-size: calc(var(--font-size-base, 16px) * 1.1); } /* Stats cards numbers - maintain larger size */ .stats-card h3, .stats-card.simple-card h3 {font-size: calc(var(--font-size-base, 16px) * 1.8); } /* Table cell adjustments */ .table td, .table th {font-size: calc(var(--font-size-base, 16px) * 0.95); } /* Form controls */ .form-control, .form-select, .input-group-text {font-size: var(--font-size-base, 16px); } /* Badges */ .badge {font-size: calc(var(--font-size-base, 16px) * 0.875); } /* Modal content */ .modal-body, .modal-header, .modal-footer {font-size: var(--font-size-base, 16px); } /* Ensure all text scales properly on mobile */ @media (max-width: 768px) {body, .main-content, .card, .card-body, .table, .form-control, .btn {font-size: calc(var(--font-size-base, 16px) * 0.95); } .stats-card h3, .stats-card.simple-card h3 {font-size: calc(var(--font-size-base, 16px) * 1.5); } } /* Compact mode adjustments */ <?php if ($compact_mode === '1'): ?> body, .main-content, .card, .card-body, .table, .form-control, .btn {font-size: calc(var(--font-size-base, 16px) * 0.9); } <?php endif; ?> </style>

<!-- Force font size on all elements using JavaScript (fallback) -->
<script>
(function() {const rootStyles = getComputedStyle(document.documentElement); let fontSize = rootStyles.getPropertyValue('--font-size-base').trim(); if (!fontSize || fontSize === '') {fontSize = localStorage.getItem('preferredFontSize') || '16px'; } document.body.style.fontSize = fontSize; const mainContent = document.querySelector('.main-content'); if (mainContent) {mainContent.style.fontSize = fontSize; } localStorage.setItem('preferredFontSize', fontSize); const style = document.createElement('style'); style.textContent = ` body, .main-content, .card, .card-body, .table, .form-control, .btn, .stats-card, .activity-item, .container-fluid, p, span, div, label, input, select, textarea, a, li, td, th, .modal-content {font-size: ${fontSize} !important; } small, .small, .text-muted {font-size: calc(${fontSize} * 0.875) !important; } h1 { font-size: calc(${fontSize} * 2.5) !important; } h2 { font-size: calc(${fontSize} * 2) !important; } h3 { font-size: calc(${fontSize} * 1.75) !important; } h4 { font-size: calc(${fontSize} * 1.5) !important; } h5 { font-size: calc(${fontSize} * 1.25) !important; } h6 { font-size: calc(${fontSize} * 1.1) !important; } .stats-card h3 {font-size: calc(${fontSize} * 1.8) !important; } `; document.head.appendChild(style); })();
</script>
    <!-- Header Section -->
    <header class="header">
        <div class="container-fluid h-100">
            <div class="row align-items-center h-100 g-0">
                <!-- Logo on left side -->
                <div class="col-4 col-sm-4 col-md-3">
                    <div class="logo-container">
                        <?php
                        $logoPath = "../muyovozi.jpg";
                        if (file_exists($logoPath)) {
                            echo '<img src="' . $logoPath . '" alt="School Management System Logo" class="logo-img">';
                        } else {
                            echo '<div class="logo-placeholder">M</div>';
                        }
                        ?>
                        <div class="school-name d-none d-sm-block"> 
                            <?php echo $is_super_admin ? 'SUPER ADMIN' : 'SCHOOL SYSTEM'; ?>
                        </div>
                        <div class="school-name d-sm-none">
                            <?php echo $is_super_admin ? 'SADMIN' : 'MHS'; ?>
                        </div>
                    </div>
                </div>
                
                <!-- School name in center -->
                <div class="col-md-6 d-none d-md-flex justify-content-center">
                    <div class="school-center text-center">
                        <div class="school-main-name">
                            <?php if ($is_super_admin): ?>
                                Super Admin Dashboard
                                <span class="super-admin-badge">
                                    <i class="fas fa-crown me-1"></i>System Admin
                                </span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($school_name); ?>
                            <?php endif; ?>
                        </div>
                        <div class="school-motto">
                            <?php if ($is_super_admin): ?>
                                Manage All Schools & System Settings
                            <?php else: ?>
                                <?php echo htmlspecialchars($school_motto); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right side: Bell + User Name + User Profile + Toggle -->
                <div class="col-8 col-sm-8 col-md-3">
                    <div class="user-profile-compact">
                        <!-- Left side items (Bell + User Name) - Only for regular admins -->
                        <?php if (!$is_super_admin): ?>
                        <div class="left-header-items">
                            <!-- Notification Bell -->
                            <div class="notification-bell" id="notificationBell">
                                <div class="bell-icon">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <?php if ($unread_notification_count > 0): ?>
                                    <span class="notification-badge" id="notificationBadge">
                                        <?php echo $unread_notification_count > 9 ? '9+' : $unread_notification_count; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- User Name Display -->
                            <div class="user-name-display" title="<?php echo htmlspecialchars($display_name_with_title); ?>">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($display_name_with_title); ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Super Admin - Show name only without notification bell -->
                        <div class="left-header-items">
                            <div class="user-name-display" title="<?php echo htmlspecialchars($display_name_with_title); ?>">
                                <i class="fas fa-crown me-1"></i>
                                <?php echo htmlspecialchars($display_name_with_title); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
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
                            
                            <!-- User Dropdown Menu - Compact List Style -->
                            <div class="user-dropdown-menu" id="userDropdown">
                                <div class="user-dropdown-header">
                                    <div class="user-dropdown-name"><?php echo htmlspecialchars($display_name_with_title); ?></div>
                                    <div class="user-dropdown-role">
                                        <?php if ($is_super_admin): ?>
                                            <i class="fas fa-crown me-1"></i><?php echo $user_role_display; ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($user_role_display); ?>
                                            <span class="ms-1">· <?php echo htmlspecialchars($school_name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="user-dropdown-menu-items">
                                    <a href="../profile/profile" class="user-dropdown-item">
                                        <i class="fas fa-user-circle"></i>
                                        <span>Profile</span>
                                    </a>
                                    <?php if (!$is_super_admin): ?>
                                    <a href="../profile/settings" class="user-dropdown-item">
                                        <i class="fas fa-palette"></i>
                                        <span>Theme Settings</span>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($is_super_admin): ?>
                                    <a href="../super/schools" class="user-dropdown-item">
                                        <i class="fas fa-building"></i>
                                        <span>Manage Schools</span>
                                    </a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <a href="../controller/logout" class="user-dropdown-item" id="logoutBtn">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sidebar Toggle Button -->
                        <button class="sidebar-toggle" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Notification Dropdown - Only for regular admins -->
    <?php if (!$is_super_admin): ?>
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
            <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
            <?php if ($unread_notification_count > 0): ?>
                <button class="mark-all-read" id="markAllReadBtn">
                    <i class="fas fa-check-double me-1"></i>Mark all read
                </button>
            <?php endif; ?>
        </div>
        
        <div class="notification-list" id="notificationList">
            <?php if (empty($recent_notifications)): ?>
                <div class="empty-notifications">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_notifications as $notif): 
                    $is_unread = ($notif['is_viewed'] == 0);
                    $message = !empty($notif['description']) ? $notif['description'] : $notif['title'];
                    $message = strip_tags($message);
                    $preview = strlen($message) > 60 ? substr($message, 0, 60) . '...' : $message;
                ?>
                    <div class="notification-item-dropdown <?php echo $is_unread ? 'unread' : ''; ?>"
                         data-id="<?php echo $notif['id']; ?>"
                         data-title="<?php echo htmlspecialchars($notif['title']); ?>"
                         data-message="<?php echo htmlspecialchars($message); ?>"
                         data-time="<?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?>">
                        <div class="notification-icon-dropdown">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="notification-content-dropdown">
                            <div class="notification-title-dropdown">
                                <?php echo htmlspecialchars($notif['title']); ?>
                                <?php if ($is_unread): ?>
                                    <span class="unread-dot-dropdown"></span>
                                <?php endif; ?>
                            </div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($preview); ?>
                            </div>
                            <div class="notification-time">
                                <i class="far fa-clock"></i>
                                <?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-dropdown-footer">
            <a href="../notification/notifications">
                <i class="fas fa-arrow-right me-1"></i>View all notifications
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
 // Notification functions - Only for regular admins
 <?php if (!$is_super_admin): ?>
 const notificationBell = document.getElementById('notificationBell'); const notificationDropdown = document.getElementById('notificationDropdown'); if (notificationBell && notificationDropdown) {notificationBell.addEventListener('click', function(e) {e.preventDefault(); e.stopPropagation(); notificationDropdown.classList.toggle('show'); }); document.addEventListener('click', function(e) {if (notificationBell && notificationDropdown) {if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {notificationDropdown.classList.remove('show'); } } }); } function showNotificationMessage(title, message, time) {Swal.fire({title: title, html: '<div style="text-align: left;">' + '<p style="margin-bottom: 10px;"><strong><i class="fas fa-envelope me-2" style="color: #3B9DB3;"></i>Message:</strong></p>' + '<div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 12px;">' + '<p style="margin: 0; line-height: 1.5;">' + escapeHtml(message) + '</p>' + '</div>' + '<p style="margin: 0; color: #666; font-size: 12px;"><i class="far fa-clock me-1"></i> ' + time + '</p>' + '</div>', icon: 'info', confirmButtonText: 'OK', confirmButtonColor: '#3B9DB3', width: '90%', maxWidth: '450px', customClass: {popup: 'notification-swal-popup' } }); } function escapeHtml(text) {if (!text) return ''; return text .replace(/&/g, '&amp;') .replace(/</g, '&lt;') .replace(/>/g, '&gt;') .replace(/"/g, '&quot;') .replace(/'/g, '&#39;'); } document.querySelectorAll('.notification-item-dropdown').forEach(item => {item.addEventListener('click', function(e) {e.preventDefault(); e.stopPropagation(); const notificationId = this.getAttribute('data-id'); const title = this.getAttribute('data-title') || 'Notification'; const message = this.getAttribute('data-message') || ''; const time = this.getAttribute('data-time') || ''; showNotificationMessage(title, message, time); if (this.classList.contains('unread')) {fetch('notifications.php?action=mark_read&id=' + notificationId) .then(response => response.text()) .then(() => {this.classList.remove('unread'); const unreadDot = this.querySelector('.unread-dot-dropdown'); if (unreadDot) unreadDot.remove(); const badge = document.getElementById('notificationBadge'); if (badge) {let currentCount = parseInt(badge.textContent) || 0; if (currentCount > 0) {currentCount--; if (currentCount === 0) {badge.remove(); } else {badge.textContent = currentCount > 9 ? '9+' : currentCount; } } } }) .catch(error => console.error('Error:', error)); } notificationDropdown.classList.remove('show'); }); }); const markAllReadBtn = document.getElementById('markAllReadBtn'); if (markAllReadBtn) {markAllReadBtn.addEventListener('click', function() {fetch('notifications.php?action=mark_all_read') .then(response => response.text()) .then(() => {document.querySelectorAll('.notification-item-dropdown.unread').forEach(item => {item.classList.remove('unread'); const unreadDot = item.querySelector('.unread-dot-dropdown'); if (unreadDot) unreadDot.remove(); }); const badge = document.getElementById('notificationBadge'); if (badge) badge.remove(); markAllReadBtn.style.display = 'none'; Swal.fire({icon: 'success', title: 'Marked as read', text: 'All notifications have been marked as read.', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 }); }) .catch(error => console.error('Error:', error)); }); }
 <?php endif; ?>
 
 // Session timer functions
 let timeoutWarningTimer; let logoutTimer; function resetSessionTimer() {if (timeoutWarningTimer) clearTimeout(timeoutWarningTimer); if (logoutTimer) clearTimeout(logoutTimer); timeoutWarningTimer = setTimeout(function() {Swal.fire({title: 'Session Expiring Soon!', text: 'Your session will expire in 1 minute due to inactivity. Click OK to stay logged in.', icon: 'warning', confirmButtonText: 'Stay Logged In', confirmButtonColor: '#3085d6', timer: 30000, timerProgressBar: true, allowOutsideClick: false }).then((result) => {if (result.isConfirmed) {fetch('keep_alive.php', {method: 'POST', credentials: 'same-origin' }) .then(response => response.json()) .then(data => {if (data.success) {resetSessionTimer(); Swal.fire({title: 'Session Extended', text: 'Your session has been extended.', icon: 'success', timer: 2000, showConfirmButton: false }); } }) .catch(error => console.error('Error:', error)); } else {window.location.href = '../controller/logout.php?timeout=1'; } }); }, 9 * 60 * 1000); logoutTimer = setTimeout(function() {window.location.href = '../controller/logout.php?timeout=1'; }, 10 * 60 * 1000); } function resetInactivityTimer() {fetch('update_activity.php', { method: 'POST', credentials: 'same-origin' }) .catch(error => console.error('Error:', error)); resetSessionTimer(); } const activities = ['click', 'mousemove', 'keypress', 'scroll', 'touchstart']; activities.forEach(activity => {document.addEventListener(activity, resetInactivityTimer); }); resetSessionTimer(); document.addEventListener('DOMContentLoaded', function() {const sidebarToggle = document.getElementById('sidebarToggle'); const sidebarOverlay = document.getElementById('sidebarOverlay'); const sidebar = document.getElementById('sidebar'); const mainContent = document.querySelector('.main-content'); const userAvatar = document.getElementById('userAvatar'); const userDropdown = document.getElementById('userDropdown'); const isDesktop = window.innerWidth >= 992; if (sidebar) {if (isDesktop) {sidebar.classList.add('desktop-visible'); if (mainContent) mainContent.classList.remove('sidebar-hidden'); } else {sidebar.classList.remove('active'); if (sidebarOverlay) sidebarOverlay.classList.remove('active'); } } function toggleSidebar(e) {if (e) {e.preventDefault(); e.stopPropagation(); } if (window.innerWidth < 992) {if (sidebar) sidebar.classList.toggle('active'); if (sidebarOverlay) sidebarOverlay.classList.toggle('active'); if (sidebar && sidebar.classList.contains('active')) {document.body.style.overflow = 'hidden'; } else {document.body.style.overflow = 'auto'; } } else {if (sidebar) sidebar.classList.toggle('desktop-visible'); if (mainContent) mainContent.classList.toggle('sidebar-hidden'); } } function closeSidebar() {if (window.innerWidth < 992) {if (sidebar) sidebar.classList.remove('active'); if (sidebarOverlay) sidebarOverlay.classList.remove('active'); document.body.style.overflow = 'auto'; } } if (sidebarToggle) {sidebarToggle.addEventListener('click', toggleSidebar); } if (sidebarOverlay) {sidebarOverlay.addEventListener('click', closeSidebar); } if (userAvatar && userDropdown) {userAvatar.addEventListener('click', function(e) {e.preventDefault(); e.stopPropagation(); userDropdown.classList.toggle('show'); }); document.addEventListener('click', function(e) {if (userAvatar && userDropdown) {if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {userDropdown.classList.remove('show'); } } }); document.addEventListener('keydown', function(e) {if (e.key === 'Escape') {if (userDropdown) userDropdown.classList.remove('show'); if (notificationDropdown) notificationDropdown.classList.remove('show'); } }); if (userDropdown) {userDropdown.addEventListener('click', function(e) {e.stopPropagation(); }); } } let resizeTimer; window.addEventListener('resize', function() {clearTimeout(resizeTimer); resizeTimer = setTimeout(function() {if (window.innerWidth >= 992) {closeSidebar(); document.body.style.overflow = 'auto'; if (sidebar) sidebar.classList.add('desktop-visible'); if (mainContent) mainContent.classList.remove('sidebar-hidden'); } else {if (sidebar) {sidebar.classList.remove('desktop-visible'); sidebar.classList.remove('active'); } if (mainContent) mainContent.classList.remove('sidebar-hidden'); if (sidebarOverlay) sidebarOverlay.classList.remove('active'); } }, 250); }); if (sidebar) {const sidebarLinks = sidebar.querySelectorAll('a'); sidebarLinks.forEach(link => {link.addEventListener('click', function(e) {if (!this.classList.contains('dropdown-toggle')) {if (window.innerWidth < 992) {closeSidebar(); } } }); }); } if (sidebar) {sidebar.addEventListener('touchstart', function(e) {e.stopPropagation(); }); } }); function updateUserAvatar(imageUrl) {const avatarSmall = document.getElementById('userAvatar'); const avatarLarge = document.querySelector('.user-dropdown-avatar'); const initials = avatarSmall ? (avatarSmall.textContent?.replace(/<[^>]*>/g, '') || 'AU') : 'AU'; if (avatarSmall) {if (imageUrl) {avatarSmall.style.backgroundImage = `url('${imageUrl}')`; avatarSmall.classList.add('has-image'); avatarSmall.innerHTML = '<div class="dropdown-indicator"><i class="fas fa-chevron-down"></i></div>'; } else {avatarSmall.style.backgroundImage = ''; avatarSmall.classList.remove('has-image'); avatarSmall.innerHTML = initials + '<div class="dropdown-indicator"><i class="fas fa-chevron-down"></i></div>'; } } if (avatarLarge) {if (imageUrl) {avatarLarge.style.backgroundImage = `url('${imageUrl}')`; avatarLarge.classList.add('has-image'); avatarLarge.innerHTML = ''; } else {avatarLarge.style.backgroundImage = ''; avatarLarge.classList.remove('has-image'); avatarLarge.innerHTML = initials; } } } function showNotification(type, message) {Swal.fire({title: type === 'success' ? 'Success!' : 'Error!', text: message, icon: type, confirmButtonText: 'OK', confirmButtonColor: type === 'success' ? '#3085d6' : '#d33', timer: type === 'success' ? 3000 : undefined, timerProgressBar: type === 'success', toast: true, position: 'top-end', showConfirmButton: false }); } 
 <?php if (!$is_super_admin): ?>
 function refreshNotificationCount() {fetch('get_notification_count.php') .then(response => response.json()) .then(data => {if (data.count > 0) {const badge = document.getElementById('notificationBadge'); if (badge) {badge.textContent = data.count > 9 ? '9+' : data.count; } else {const bell = document.querySelector('.notification-bell'); if (bell) {const newBadge = document.createElement('span'); newBadge.className = 'notification-badge'; newBadge.id = 'notificationBadge'; newBadge.textContent = data.count > 9 ? '9+' : data.count; bell.appendChild(newBadge); } } } else {const badge = document.getElementById('notificationBadge'); if (badge) badge.remove(); } }) .catch(error => console.error('Error fetching notification count:', error)); } setInterval(refreshNotificationCount, 30000);
 <?php endif; ?>
    </script>
</body>
</html>