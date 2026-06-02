<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../controller/db_connect.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check if user is logged in (either admin or super admin)
$is_super_admin = false;
$admin_id = null;
$school_id = null;
$super_admin_id = null;

// First check if super admin is logged in (from super_admins table)
if (isset($_SESSION['super_admin_id'])) {
    $is_super_admin = true;
    $super_admin_id = $_SESSION['super_admin_id'];
    
    // Get super admin info
    $super_query = "SELECT * FROM super_admins WHERE id = $super_admin_id AND status = 1";
    $super_result = mysqli_query($conn, $super_query);
    $super_admin = mysqli_fetch_assoc($super_result);
    
    if (!$super_admin) {
        session_destroy();
        header("Location: ../super_admin/login.php");
        exit();
    }
    
    // Get selected school from session or default to 1
    $school_id = isset($_SESSION['selected_school_id']) ? $_SESSION['selected_school_id'] : 1;
    
} elseif (isset($_SESSION['admin_id'])) {
    // Regular admin logged in
    $is_super_admin = false;
    $admin_id = $_SESSION['admin_id'];
    
    // Get admin info
    $admin_query = "SELECT * FROM admins WHERE id = $admin_id";
    $admin_result = mysqli_query($conn, $admin_query);
    $admin = mysqli_fetch_assoc($admin_result);
    
    if (!$admin || $admin['status'] != 1) {
        session_destroy();
        header("Location: ../index.php");
        exit();
    }
    
    $school_id = $admin['school_id'] ?? 1;
} else {
    // No user logged in
    header("Location: ../index.php");
    exit();
}

// Handle school selection for super admin
if ($is_super_admin && isset($_POST['select_school'])) {
    $new_school_id = intval($_POST['school_id']);
    $_SESSION['selected_school_id'] = $new_school_id;
    $school_id = $new_school_id;
    header("Location: settings.php");
    exit();
}

// Get school system defaults
$school_query = "SELECT system_theme, system_preferences, allowed_customization, logo_path, school_name, school_code, address, phone, email, school_motto FROM schools WHERE id = $school_id";
$school_result = mysqli_query($conn, $school_query);
$school_data = mysqli_fetch_assoc($school_result);

if (!$school_data) {
    // School not found, redirect to first available
    $default_school = mysqli_query($conn, "SELECT id FROM schools LIMIT 1");
    if ($default_school && mysqli_num_rows($default_school) > 0) {
        $default = mysqli_fetch_assoc($default_school);
        $school_id = $default['id'];
        $_SESSION['selected_school_id'] = $school_id;
        header("Location: settings.php");
        exit();
    }
    die("No school found in database");
}

// Parse system defaults
$system_theme = json_decode($school_data['system_theme'] ?? '', true);
$system_preferences = json_decode($school_data['system_preferences'] ?? '', true);
$allowed_customization = json_decode($school_data['allowed_customization'] ?? '{"theme":true,"preferences":true}', true);

// Default colors (fallback)
$default_colors = $system_theme ?: [
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

// Default preferences (fallback)
$pref_defaults = $system_preferences ?: [
    'sidebar_collapsed' => '0',
    'font_size' => '16',
    'animations' => '1',
    'compact_mode' => '0',
    'background_opacity' => '65',
    'background_option' => 'image',
    'animation_speed' => 'normal'
];

// Check if tables exist and create if not (only for regular admins)
if (!$is_super_admin) {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'theme_settings'");
    if ($table_check && mysqli_num_rows($table_check) == 0) {
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS theme_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_admin_setting (admin_id, setting_key),
            FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        )");
        
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS user_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            preference_key VARCHAR(100) NOT NULL,
            preference_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_admin_preference (admin_id, preference_key),
            FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        )");
    }
}

// Load user's custom settings (only for regular admins)
$user_theme = [];
$user_preferences = [];

if (!$is_super_admin && $admin_id) {
    $theme_query = "SELECT setting_key, setting_value FROM theme_settings WHERE admin_id = $admin_id";
    $theme_result = mysqli_query($conn, $theme_query);
    if ($theme_result) {
        while ($row = mysqli_fetch_assoc($theme_result)) {
            $user_theme[$row['setting_key']] = $row['setting_value'];
        }
    }

    $prefs_query = "SELECT preference_key, preference_value FROM user_preferences WHERE admin_id = $admin_id";
    $prefs_result = mysqli_query($conn, $prefs_query);
    if ($prefs_result) {
        while ($row = mysqli_fetch_assoc($prefs_result)) {
            $user_preferences[$row['preference_key']] = $row['preference_value'];
        }
    }
}

// Merge: User settings override system defaults
$colors = $default_colors;
foreach ($user_theme as $key => $value) {
    if (array_key_exists($key, $colors) && $value !== null) {
        $colors[$key] = $value;
    }
}

$preferences = $pref_defaults;
foreach ($user_preferences as $key => $value) {
    if (array_key_exists($key, $preferences) && $value !== null) {
        $preferences[$key] = $value;
    }
}

// Background options
$background_options = [
    'image' => 'School Image',
    'gray' => 'Gray',
    'eye_care' => 'Eye Care',
    'milk' => 'Milk',
    'dark_light' => 'Dark-Light'
];

$background_colors = [
    'gray' => '#e9ecef',
    'eye_care' => '#c7e9c0',
    'milk' => '#fdf5e6',
    'dark_light' => '#2d2d2d'
];

// Font sizes
$font_sizes = [
    '10' => '10px',
    '12' => '12px',
    '14' => '14px',
    '16' => '16px',
    '18' => '18px'
];

// Get all schools for super admin dropdown
$all_schools = [];
if ($is_super_admin) {
    $schools_query = "SELECT id, school_name, school_code FROM schools ORDER BY school_name";
    $schools_result = mysqli_query($conn, $schools_query);
    while ($row = mysqli_fetch_assoc($schools_result)) {
        $all_schools[] = $row;
    }
}

// Handle AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $response = ['success' => false, 'message' => ''];
            
            // SUPER ADMIN: Save system defaults for selected school
            if ($is_super_admin && isset($_POST['save_system_defaults'])) {
                $system_theme_data = [];
                $color_keys = ['primary', 'primary_dark', 'primary_light', 'light', 'white', 'gray', 
                               'text', 'text_light', 'border', 'success', 'danger', 'warning', 'info',
                               'coral', 'forest_green', 'lime_green', 'sky_blue', 'aqua_blue'];
                
                foreach ($color_keys as $key) {
                    $post_key = $key . '_color';
                    if (isset($_POST[$post_key]) && !empty($_POST[$post_key])) {
                        $system_theme_data[$key] = $_POST[$post_key];
                    }
                }
                
                $system_prefs_data = [
                    'sidebar_collapsed' => $_POST['sidebar_collapsed'] ?? '0',
                    'font_size' => $_POST['font_size'] ?? '16',
                    'animations' => $_POST['animations'] ?? '1',
                    'compact_mode' => $_POST['compact_mode'] ?? '0',
                    'background_opacity' => $_POST['background_opacity'] ?? '65',
                    'background_option' => $_POST['background_option'] ?? 'image',
                    'animation_speed' => $_POST['animation_speed'] ?? 'normal'
                ];
                
                $allowed = [
                    'theme' => isset($_POST['allow_theme_custom']) ? true : false,
                    'preferences' => isset($_POST['allow_prefs_custom']) ? true : false
                ];
                
                $system_theme_json = mysqli_real_escape_string($conn, json_encode($system_theme_data));
                $system_prefs_json = mysqli_real_escape_string($conn, json_encode($system_prefs_data));
                $allowed_json = mysqli_real_escape_string($conn, json_encode($allowed));
                
                $update_school = "UPDATE schools SET 
                                  system_theme = '$system_theme_json',
                                  system_preferences = '$system_prefs_json',
                                  allowed_customization = '$allowed_json'
                                  WHERE id = $school_id";
                
                if (mysqli_query($conn, $update_school)) {
                    // Option to apply to all users in this school
                    if (isset($_POST['apply_to_all']) && $_POST['apply_to_all'] == '1') {
                        // Clear all user settings for this school
                        mysqli_query($conn, "DELETE FROM theme_settings WHERE admin_id IN (SELECT id FROM admins WHERE school_id = $school_id)");
                        mysqli_query($conn, "DELETE FROM user_preferences WHERE admin_id IN (SELECT id FROM admins WHERE school_id = $school_id)");
                    }
                    $response['success'] = true;
                    $response['message'] = "System defaults for " . htmlspecialchars($school_data['school_name']) . " updated successfully!";
                } else {
                    $response['message'] = "Error updating system defaults: " . mysqli_error($conn);
                }
            }
            
            // Regular user save theme (only if allowed and not super admin)
            if (!$is_super_admin && isset($_POST['save_theme']) && ($allowed_customization['theme'] ?? true)) {
                $color_keys = [
                    'primary', 'primary_dark', 'primary_light', 'light', 'white', 'gray',
                    'text', 'text_light', 'border', 'success', 'danger', 'warning', 'info',
                    'coral', 'forest_green', 'lime_green', 'sky_blue', 'aqua_blue'
                ];
                
                $saved_count = 0;
                foreach ($color_keys as $key) {
                    $post_key = $key . '_color';
                    if (isset($_POST[$post_key]) && !empty($_POST[$post_key])) {
                        $value = mysqli_real_escape_string($conn, $_POST[$post_key]);
                        
                        $check = mysqli_query($conn, "SELECT id FROM theme_settings WHERE admin_id = $admin_id AND setting_key = '$key'");
                        if ($check && mysqli_num_rows($check) > 0) {
                            mysqli_query($conn, "UPDATE theme_settings SET setting_value = '$value' WHERE admin_id = $admin_id AND setting_key = '$key'");
                        } else {
                            mysqli_query($conn, "INSERT INTO theme_settings (admin_id, setting_key, setting_value) VALUES ($admin_id, '$key', '$value')");
                        }
                        $saved_count++;
                    }
                }
                
                $response['success'] = true;
                $response['message'] = "$saved_count theme colors saved successfully!";
            }
            
            // Regular user save preferences (only if allowed and not super admin)
            if (!$is_super_admin && isset($_POST['save_preferences']) && ($allowed_customization['preferences'] ?? true)) {
                $pref_keys = [
                    'sidebar_collapsed', 'font_size', 'animations', 
                    'compact_mode', 'background_opacity', 'background_option',
                    'animation_speed'
                ];
                
                $saved_count = 0;
                foreach ($pref_keys as $key) {
                    $value = isset($_POST[$key]) ? mysqli_real_escape_string($conn, $_POST[$key]) : '0';
                    
                    $check = mysqli_query($conn, "SELECT id FROM user_preferences WHERE admin_id = $admin_id AND preference_key = '$key'");
                    if ($check && mysqli_num_rows($check) > 0) {
                        mysqli_query($conn, "UPDATE user_preferences SET preference_value = '$value' WHERE admin_id = $admin_id AND preference_key = '$key'");
                    } else {
                        mysqli_query($conn, "INSERT INTO user_preferences (admin_id, preference_key, preference_value) VALUES ($admin_id, '$key', '$value')");
                    }
                    $saved_count++;
                }
                
                $response['success'] = true;
                $response['message'] = "$saved_count preferences saved successfully!";
            }
            
            // Reset to system default (for regular admins only)
            if (!$is_super_admin && isset($_POST['reset_to_system_default'])) {
                mysqli_query($conn, "DELETE FROM theme_settings WHERE admin_id = $admin_id");
                mysqli_query($conn, "DELETE FROM user_preferences WHERE admin_id = $admin_id");
                $response['success'] = true;
                $response['message'] = "Reset to school defaults successfully!";
            }
            
            echo json_encode($response);
            exit();
        }
    } catch (Exception $e) {
        error_log("Exception in AJAX handler: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// Regular form submission (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // SUPER ADMIN: Save system defaults (non-AJAX fallback)
    if ($is_super_admin && isset($_POST['save_system_defaults'])) {
        $system_theme_data = [];
        $color_keys = ['primary', 'primary_dark', 'primary_light', 'light', 'white', 'gray', 
                       'text', 'text_light', 'border', 'success', 'danger', 'warning', 'info',
                       'coral', 'forest_green', 'lime_green', 'sky_blue', 'aqua_blue'];
        
        foreach ($color_keys as $key) {
            $post_key = $key . '_color';
            if (isset($_POST[$post_key])) {
                $system_theme_data[$key] = $_POST[$post_key];
            }
        }
        
        $system_prefs_data = [
            'sidebar_collapsed' => $_POST['sidebar_collapsed'] ?? '0',
            'font_size' => $_POST['font_size'] ?? '16',
            'animations' => $_POST['animations'] ?? '1',
            'compact_mode' => $_POST['compact_mode'] ?? '0',
            'background_opacity' => $_POST['background_opacity'] ?? '65',
            'background_option' => $_POST['background_option'] ?? 'image',
            'animation_speed' => $_POST['animation_speed'] ?? 'normal'
        ];
        
        $allowed = [
            'theme' => isset($_POST['allow_theme_custom']),
            'preferences' => isset($_POST['allow_prefs_custom'])
        ];
        
        $system_theme_json = mysqli_real_escape_string($conn, json_encode($system_theme_data));
        $system_prefs_json = mysqli_real_escape_string($conn, json_encode($system_prefs_data));
        $allowed_json = mysqli_real_escape_string($conn, json_encode($allowed));
        
        $update_school = "UPDATE schools SET 
                          system_theme = '$system_theme_json',
                          system_preferences = '$system_prefs_json',
                          allowed_customization = '$allowed_json'
                          WHERE id = $school_id";
        
        if (mysqli_query($conn, $update_school)) {
            if (isset($_POST['apply_to_all_users'])) {
                mysqli_query($conn, "DELETE FROM theme_settings WHERE admin_id IN (SELECT id FROM admins WHERE school_id = $school_id)");
                mysqli_query($conn, "DELETE FROM user_preferences WHERE admin_id IN (SELECT id FROM admins WHERE school_id = $school_id)");
            }
            $_SESSION['success'] = "System defaults for " . htmlspecialchars($school_data['school_name']) . " updated successfully!";
        }
        header("Location: settings.php");
        exit();
    }
    
    // Regular user save theme (only if not super admin)
    if (!$is_super_admin && isset($_POST['save_theme']) && ($allowed_customization['theme'] ?? true)) {
        $color_keys = [
            'primary', 'primary_dark', 'primary_light', 'light', 'white', 'gray',
            'text', 'text_light', 'border', 'success', 'danger', 'warning', 'info',
            'coral', 'forest_green', 'lime_green', 'sky_blue', 'aqua_blue'
        ];
        
        foreach ($color_keys as $key) {
            $post_key = $key . '_color';
            if (isset($_POST[$post_key]) && !empty($_POST[$post_key])) {
                $value = mysqli_real_escape_string($conn, $_POST[$post_key]);
                
                $check = mysqli_query($conn, "SELECT id FROM theme_settings WHERE admin_id = $admin_id AND setting_key = '$key'");
                if ($check && mysqli_num_rows($check) > 0) {
                    mysqli_query($conn, "UPDATE theme_settings SET setting_value = '$value' WHERE admin_id = $admin_id AND setting_key = '$key'");
                } else {
                    mysqli_query($conn, "INSERT INTO theme_settings (admin_id, setting_key, setting_value) VALUES ($admin_id, '$key', '$value')");
                }
            }
        }
        
        $_SESSION['success'] = "Theme colors saved successfully!";
        header("Location: settings.php");
        exit();
    }
    
    // Regular user save preferences (only if not super admin)
    if (!$is_super_admin && isset($_POST['save_preferences']) && ($allowed_customization['preferences'] ?? true)) {
        $pref_keys = [
            'sidebar_collapsed', 'font_size', 'animations', 
            'compact_mode', 'background_opacity', 'background_option',
            'animation_speed'
        ];
        
        foreach ($pref_keys as $key) {
            $value = isset($_POST[$key]) ? mysqli_real_escape_string($conn, $_POST[$key]) : '0';
            
            $check = mysqli_query($conn, "SELECT id FROM user_preferences WHERE admin_id = $admin_id AND preference_key = '$key'");
            if ($check && mysqli_num_rows($check) > 0) {
                mysqli_query($conn, "UPDATE user_preferences SET preference_value = '$value' WHERE admin_id = $admin_id AND preference_key = '$key'");
            } else {
                mysqli_query($conn, "INSERT INTO user_preferences (admin_id, preference_key, preference_value) VALUES ($admin_id, '$key', '$value')");
            }
        }
        
        $_SESSION['success'] = "Preferences saved successfully!";
        header("Location: settings.php");
        exit();
    }
    
    // Reset to system default (for regular admins only)
    if (!$is_super_admin && isset($_POST['reset_to_system_default'])) {
        mysqli_query($conn, "DELETE FROM theme_settings WHERE admin_id = $admin_id");
        mysqli_query($conn, "DELETE FROM user_preferences WHERE admin_id = $admin_id");
        $_SESSION['success'] = "Reset to school defaults successfully!";
        header("Location: settings.php");
        exit();
    }
}

// Determine background style
$bg_style = '';
$bg_option = isset($preferences['background_option']) ? $preferences['background_option'] : 'image';
$bg_opacity = isset($preferences['background_opacity']) ? $preferences['background_opacity'] / 100 : 0.65;
$school_logo = $school_data['logo_path'] ?? '../muyovozi.png';
$school_name = $school_data['school_name'] ?? 'Muyovozi High School';

if ($bg_option === 'image') {
    $bg_style = "linear-gradient(rgba(255,255,255,{$bg_opacity}), rgba(255,255,255,{$bg_opacity})), url('{$school_logo}') no-repeat center center fixed";
    $bg_size = 'cover';
} else {
    $bg_color = isset($background_colors[$bg_option]) ? $background_colors[$bg_option] : '#e9ecef';
    $bg_style = $bg_color;
    $bg_size = 'auto';
}

// Get session messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Settings - <?php echo htmlspecialchars($school_name); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo $colors['primary'] ?? '#3B9DB3'; ?>;
            --primary-dark: <?php echo $colors['primary_dark'] ?? '#2d7c8f'; ?>;
            --primary-light: <?php echo $colors['primary_light'] ?? '#8bc5d6'; ?>;
            --light-color: <?php echo $colors['light'] ?? '#f8f9fa'; ?>;
            --white: <?php echo $colors['white'] ?? '#ffffff'; ?>;
            --gray: <?php echo $colors['gray'] ?? '#e9ecef'; ?>;
            --text-color: <?php echo $colors['text'] ?? '#333333'; ?>;
            --text-light: <?php echo $colors['text_light'] ?? '#666666'; ?>;
            --border-color: <?php echo $colors['border'] ?? '#e0e0e0'; ?>;
            --success-color: <?php echo $colors['success'] ?? '#28a745'; ?>;
            --danger-color: <?php echo $colors['danger'] ?? '#dc3545'; ?>;
            --warning-color: <?php echo $colors['warning'] ?? '#ffc107'; ?>;
            --info-color: <?php echo $colors['info'] ?? '#17a2b8'; ?>;
            --coral-color: <?php echo $colors['coral'] ?? '#FF7F50'; ?>;
            --forest-green: <?php echo $colors['forest_green'] ?? '#2E7D32'; ?>;
            --lime-green: <?php echo $colors['lime_green'] ?? '#63E07E'; ?>;
            --sky-blue: <?php echo $colors['sky_blue'] ?? '#66d9ff'; ?>;
            --aqua-blue: <?php echo $colors['aqua_blue'] ?? '#4dd2ff'; ?>;
            --font-size-base: <?php 
                $fs = $preferences['font_size'] ?? '16';
                echo $font_sizes[$fs] ?? '16px'; 
            ?>;
            --spacing-base: <?php echo ($preferences['compact_mode'] ?? '0') === '1' ? '0.75rem' : '1rem'; ?>;
            --animation-speed: <?php 
                $speed = $preferences['animation_speed'] ?? 'normal';
                echo $speed === 'slow' ? '0.5s' : ($speed === 'fast' ? '0.15s' : '0.3s'); 
            ?>;
        }

        * {
            transition: <?php echo ($preferences['animations'] ?? '1') === '1' ? 'all var(--animation-speed) ease' : 'none'; ?>;
        }

        body {
            background: <?php echo $bg_style; ?>;
            background-size: <?php echo $bg_size; ?>;
            background-position: center;
            min-height: 100vh;
            color: var(--text-color);
            line-height: 1.6;
            font-size: var(--font-size-base);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all var(--animation-speed) ease;
        }

        .main-content.sidebar-collapsed {
            margin-left: 70px;
        }

        <?php if (($preferences['compact_mode'] ?? '0') === '1'): ?>
        .card-body {
            padding: 0.75rem !important;
        }
        .btn {
            padding: 0.5rem 1rem !important;
        }
        .form-control, .form-select {
            padding: 0.375rem 0.75rem !important;
        }
        <?php endif; ?>

        /* Settings Page Specific Styles */
        .settings-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .settings-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
            background: var(--white);
        }

        .settings-card .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 1.25rem 1.5rem;
            border-bottom: none;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .settings-card .card-header i {
            margin-right: 10px;
        }

        .settings-card .card-body {
            padding: 2rem;
            background: var(--white);
        }

        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }

        .color-preview:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .theme-preset {
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 10px;
            padding: 10px;
            transition: all 0.3s;
            text-align: center;
            background: white;
        }

        .theme-preset:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .theme-preset.active {
            border-color: var(--primary-color);
            background: rgba(59, 157, 179, 0.1);
        }

        .theme-preset .color-dots {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin: 10px 0;
        }

        .theme-preset .color-dot {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--text-light);
            font-weight: 600;
            padding: 12px 25px;
            position: relative;
        }

        .nav-tabs .nav-link:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-color);
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background: transparent;
        }

        .nav-tabs .nav-link.active:after {
            transform: scaleX(1);
        }

        .range-slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--primary-light), var(--primary-dark));
            outline: none;
            -webkit-appearance: none;
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }

        .range-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            background: var(--primary-dark);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .font-size-badge {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            border: 2px solid var(--border-color);
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            font-size: 14px;
        }

        .font-size-badge:hover,
        .font-size-badge.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59,157,179,0.3);
        }

        .save-actions {
            position: sticky;
            bottom: 20px;
            background: var(--white);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 -5px 30px rgba(0,0,0,0.1);
            z-index: 1000;
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .save-actions .btn {
            padding: 12px 30px;
            font-weight: 600;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .btn-save {
            animation: pulse 2s infinite;
        }

        .bg-option-preview {
            width: 100%;
            height: 100px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            margin-top: 10px;
            background-size: cover !important;
            background-position: center !important;
        }

        .color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .color-input-group input[type="text"] {
            flex: 1;
        }

        .system-badge {
            background: #ffc107;
            color: #856404;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 10px;
        }

        .restricted-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            margin-left: 10px;
        }

        .school-selector {
            background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            color: white;
        }

        .school-selector select {
            background: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            margin-left: 10px;
        }

        .colored-toast.swal2-icon-success {
            background-color: #28a745 !important;
        }
        
        .colored-toast.swal2-icon-error {
            background-color: #dc3545 !important;
        }
        
        .colored-toast .swal2-title {
            color: white !important;
        }
        
        .colored-toast .swal2-close {
            color: white !important;
        }
        
        .colored-toast .swal2-html-container {
            color: white !important;
        }
    </style>
</head>
<body>
    <!-- Header (Same for both) -->
    <?php include '../controller/header.php'; ?>
    
    <!-- Sidebar (Different based on user type) -->
    <?php if ($is_super_admin): ?>
        <?php include '../super/sidebar.php'; ?>
    <?php else: ?>
        <?php include '../controller/sidebar.php'; ?>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="main-content <?php echo ($preferences['sidebar_collapsed'] ?? '0') === '1' ? 'sidebar-collapsed' : 'sidebar-open'; ?>">
        <div class="container-fluid settings-container">
            
            <?php if ($is_super_admin): ?>
            <!-- School Selector for Super Admin -->
            <div class="school-selector">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-building me-2"></i>
                        <strong>Currently Managing:</strong> 
                        <span class="ms-2"><?php echo htmlspecialchars($school_data['school_name']); ?> (<?php echo htmlspecialchars($school_data['school_code']); ?>)</span>
                    </div>
                    <form method="POST" action="" class="d-inline">
                        <select name="school_id" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                            <?php foreach ($all_schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>" <?php echo $school['id'] == $school_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['school_name']); ?> (<?php echo htmlspecialchars($school['school_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="select_school" value="1">
                    </form>
                </div>
                <div class="mt-2 small">
                    <i class="fas fa-info-circle me-1"></i>
                    You are configuring default theme for this school. School admins can customize their own theme.
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1" style="color: var(--text-color);">
                        <i class="fas fa-palette me-2" style="color: var(--primary-color);"></i>
                        Theme Settings
                        <?php if ($is_super_admin): ?>
                            <span class="badge bg-warning text-dark ms-2">Super Admin</span>
                        <?php endif; ?>
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if ($is_super_admin): ?>
                            Configure <strong><?php echo htmlspecialchars($school_data['school_name']); ?></strong> default theme
                        <?php else: ?>
                            Customize your personal dashboard appearance
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="previewChanges()">
                        <i class="fas fa-eye me-2"></i>Preview
                    </button>
                    <button class="btn btn-primary" onclick="saveAll()">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>

            <!-- Session Messages -->
            <?php if ($success_message): ?>
                <div id="successMessage" data-message="<?php echo htmlspecialchars($success_message); ?>"></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div id="errorMessage" data-message="<?php echo htmlspecialchars($error_message); ?>"></div>
            <?php endif; ?>

            <!-- Theme Presets -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-magic"></i>
                    Quick Theme Presets
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 col-6 mb-3">
                            <div class="theme-preset" onclick="applyPreset('default')" id="preset-default">
                                <div class="color-dots">
                                    <div class="color-dot" style="background: #3B9DB3;"></div>
                                    <div class="color-dot" style="background: #2d7c8f;"></div>
                                    <div class="color-dot" style="background: #8bc5d6;"></div>
                                </div>
                                <strong>Ocean Blue</strong>
                                <small class="d-block text-muted">Default</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="theme-preset" onclick="applyPreset('coral')" id="preset-coral">
                                <div class="color-dots">
                                    <div class="color-dot" style="background: #FF7F50;"></div>
                                    <div class="color-dot" style="background: #FF6347;"></div>
                                    <div class="color-dot" style="background: #FFA07A;"></div>
                                </div>
                                <strong>Sunset Coral</strong>
                                <small class="d-block text-muted">Warm Orange</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="theme-preset" onclick="applyPreset('forest')" id="preset-forest">
                                <div class="color-dots">
                                    <div class="color-dot" style="background: #2E7D32;"></div>
                                    <div class="color-dot" style="background: #1B5E20;"></div>
                                    <div class="color-dot" style="background: #81C784;"></div>
                                </div>
                                <strong>Forest Green</strong>
                                <small class="d-block text-muted">Natural</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="theme-preset" onclick="applyPreset('lime')" id="preset-lime">
                                <div class="color-dots">
                                    <div class="color-dot" style="background: #63E07E;"></div>
                                    <div class="color-dot" style="background: #4CAF50;"></div>
                                    <div class="color-dot" style="background: #A5D6A7;"></div>
                                </div>
                                <strong>Lime Green</strong>
                                <small class="d-block text-muted">Fresh</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="theme-preset" onclick="applyPreset('sky')" id="preset-sky">
                                <div class="color-dots">
                                    <div class="color-dot" style="background: #66d9ff;"></div>
                                    <div class="color-dot" style="background: #4dd2ff;"></div>
                                    <div class="color-dot" style="background: #80e5ff;"></div>
                                </div>
                                <strong>Sky Blue</strong>
                                <small class="d-block text-muted">Light</small>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="theme-preset" onclick="applyPreset('aqua')" id="preset-aqua">
                                <div class="color-dots">
                                    <div class="color-dot" style="background: #4dd2ff;"></div>
                                    <div class="color-dot" style="background: #33ccff;"></div>
                                    <div class="color-dot" style="background: #99e6ff;"></div>
                                </div>
                                <strong>Aqua Blue</strong>
                                <small class="d-block text-muted">65%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Settings Tabs -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-sliders-h"></i>
                    <?php echo $is_super_admin ? 'School Default Theme Configuration' : 'Customize Your Theme'; ?>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="colors-tab" data-bs-toggle="tab" data-bs-target="#colors" type="button" role="tab">
                                <i class="fas fa-paint-brush me-2"></i>Colors
                                <?php if (!$is_super_admin && !($allowed_customization['theme'] ?? true)): ?>
                                    <span class="restricted-badge">Locked</span>
                                <?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="layout-tab" data-bs-toggle="tab" data-bs-target="#layout" type="button" role="tab">
                                <i class="fas fa-layout me-2"></i>Layout
                                <?php if (!$is_super_admin && !($allowed_customization['preferences'] ?? true)): ?>
                                    <span class="restricted-badge">Locked</span>
                                <?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="typography-tab" data-bs-toggle="tab" data-bs-target="#typography" type="button" role="tab">
                                <i class="fas fa-font me-2"></i>Typography
                                <?php if (!$is_super_admin && !($allowed_customization['preferences'] ?? true)): ?>
                                    <span class="restricted-badge">Locked</span>
                                <?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="background-tab" data-bs-toggle="tab" data-bs-target="#background" type="button" role="tab">
                                <i class="fas fa-image me-2"></i>Background
                                <?php if (!$is_super_admin && !($allowed_customization['preferences'] ?? true)): ?>
                                    <span class="restricted-badge">Locked</span>
                                <?php endif; ?>
                            </button>
                        </li>
                        <?php if ($is_super_admin): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                <i class="fas fa-server me-2"></i>School Defaults
                                <span class="system-badge">Super Admin</span>
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <form method="POST" action="" id="themeForm">
                        <div class="tab-content mt-4">
                            <!-- Colors Tab -->
                            <div class="tab-pane fade show active" id="colors" role="tabpanel">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <h6 class="mb-3" style="color: var(--primary-color);">
                                            <i class="fas fa-palette me-2"></i>Primary Colors
                                        </h6>
                                        
                                        <!-- Primary Color -->
                                        <div class="mb-4">
                                            <label class="form-label">Primary Color</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['primary'] ?? '#3B9DB3'; ?>;" 
                                                     onclick="document.getElementById('primary_picker').click()"></div>
                                                <input type="text" class="form-control" id="primary_color" name="primary_color" 
                                                       value="<?php echo $colors['primary'] ?? '#3B9DB3'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="primary_picker" style="display: none;" 
                                                       value="<?php echo $colors['primary'] ?? '#3B9DB3'; ?>" onchange="updateColorInput('primary', this.value)">
                                            </div>
                                        </div>

                                        <!-- Primary Dark -->
                                        <div class="mb-4">
                                            <label class="form-label">Primary Dark</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['primary_dark'] ?? '#2d7c8f'; ?>;" 
                                                     onclick="document.getElementById('primary_dark_picker').click()"></div>
                                                <input type="text" class="form-control" id="primary_dark_color" name="primary_dark_color" 
                                                       value="<?php echo $colors['primary_dark'] ?? '#2d7c8f'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="primary_dark_picker" style="display: none;" 
                                                       value="<?php echo $colors['primary_dark'] ?? '#2d7c8f'; ?>" onchange="updateColorInput('primary_dark', this.value)">
                                            </div>
                                        </div>

                                        <!-- Primary Light -->
                                        <div class="mb-4">
                                            <label class="form-label">Primary Light</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['primary_light'] ?? '#8bc5d6'; ?>;" 
                                                     onclick="document.getElementById('primary_light_picker').click()"></div>
                                                <input type="text" class="form-control" id="primary_light_color" name="primary_light_color" 
                                                       value="<?php echo $colors['primary_light'] ?? '#8bc5d6'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="primary_light_picker" style="display: none;" 
                                                       value="<?php echo $colors['primary_light'] ?? '#8bc5d6'; ?>" onchange="updateColorInput('primary_light', this.value)">
                                            </div>
                                        </div>

                                        <!-- Coral Color -->
                                        <div class="mb-4">
                                            <label class="form-label">Coral Color</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['coral'] ?? '#FF7F50'; ?>;" 
                                                     onclick="document.getElementById('coral_picker').click()"></div>
                                                <input type="text" class="form-control" id="coral_color" name="coral_color" 
                                                       value="<?php echo $colors['coral'] ?? '#FF7F50'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="coral_picker" style="display: none;" 
                                                       value="<?php echo $colors['coral'] ?? '#FF7F50'; ?>" onchange="updateColorInput('coral', this.value)">
                                            </div>
                                        </div>

                                        <!-- Forest Green -->
                                        <div class="mb-4">
                                            <label class="form-label">Forest Green</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['forest_green'] ?? '#2E7D32'; ?>;" 
                                                     onclick="document.getElementById('forest_green_picker').click()"></div>
                                                <input type="text" class="form-control" id="forest_green_color" name="forest_green_color" 
                                                       value="<?php echo $colors['forest_green'] ?? '#2E7D32'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="forest_green_picker" style="display: none;" 
                                                       value="<?php echo $colors['forest_green'] ?? '#2E7D32'; ?>" onchange="updateColorInput('forest_green', this.value)">
                                            </div>
                                        </div>

                                        <!-- Lime Green -->
                                        <div class="mb-4">
                                            <label class="form-label">Lime Green</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['lime_green'] ?? '#63E07E'; ?>;" 
                                                     onclick="document.getElementById('lime_green_picker').click()"></div>
                                                <input type="text" class="form-control" id="lime_green_color" name="lime_green_color" 
                                                       value="<?php echo $colors['lime_green'] ?? '#63E07E'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="lime_green_picker" style="display: none;" 
                                                       value="<?php echo $colors['lime_green'] ?? '#63E07E'; ?>" onchange="updateColorInput('lime_green', this.value)">
                                            </div>
                                        </div>

                                        <!-- Sky Blue -->
                                        <div class="mb-4">
                                            <label class="form-label">Sky Blue</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['sky_blue'] ?? '#66d9ff'; ?>;" 
                                                     onclick="document.getElementById('sky_blue_picker').click()"></div>
                                                <input type="text" class="form-control" id="sky_blue_color" name="sky_blue_color" 
                                                       value="<?php echo $colors['sky_blue'] ?? '#66d9ff'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="sky_blue_picker" style="display: none;" 
                                                       value="<?php echo $colors['sky_blue'] ?? '#66d9ff'; ?>" onchange="updateColorInput('sky_blue', this.value)">
                                            </div>
                                        </div>

                                        <!-- Aqua Blue -->
                                        <div class="mb-4">
                                            <label class="form-label">Aqua Blue</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['aqua_blue'] ?? '#4dd2ff'; ?>;" 
                                                     onclick="document.getElementById('aqua_blue_picker').click()"></div>
                                                <input type="text" class="form-control" id="aqua_blue_color" name="aqua_blue_color" 
                                                       value="<?php echo $colors['aqua_blue'] ?? '#4dd2ff'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="aqua_blue_picker" style="display: none;" 
                                                       value="<?php echo $colors['aqua_blue'] ?? '#4dd2ff'; ?>" onchange="updateColorInput('aqua_blue', this.value)">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <h6 class="mb-3" style="color: var(--primary-color);">
                                            <i class="fas fa-tint me-2"></i>Text & Status Colors
                                        </h6>

                                        <!-- Text Color -->
                                        <div class="mb-4">
                                            <label class="form-label">Text Color</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['text'] ?? '#333333'; ?>;" 
                                                     onclick="document.getElementById('text_picker').click()"></div>
                                                <input type="text" class="form-control" id="text_color" name="text_color" 
                                                       value="<?php echo $colors['text'] ?? '#333333'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="text_picker" style="display: none;" 
                                                       value="<?php echo $colors['text'] ?? '#333333'; ?>" onchange="updateColorInput('text', this.value)">
                                            </div>
                                        </div>

                                        <!-- Light Text -->
                                        <div class="mb-4">
                                            <label class="form-label">Light Text</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['text_light'] ?? '#666666'; ?>;" 
                                                     onclick="document.getElementById('text_light_picker').click()"></div>
                                                <input type="text" class="form-control" id="text_light_color" name="text_light_color" 
                                                       value="<?php echo $colors['text_light'] ?? '#666666'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="text_light_picker" style="display: none;" 
                                                       value="<?php echo $colors['text_light'] ?? '#666666'; ?>" onchange="updateColorInput('text_light', this.value)">
                                            </div>
                                        </div>

                                        <!-- Border Color -->
                                        <div class="mb-4">
                                            <label class="form-label">Border Color</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['border'] ?? '#e0e0e0'; ?>;" 
                                                     onclick="document.getElementById('border_picker').click()"></div>
                                                <input type="text" class="form-control" id="border_color" name="border_color" 
                                                       value="<?php echo $colors['border'] ?? '#e0e0e0'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="border_picker" style="display: none;" 
                                                       value="<?php echo $colors['border'] ?? '#e0e0e0'; ?>" onchange="updateColorInput('border', this.value)">
                                            </div>
                                        </div>

                                        <!-- Success Color -->
                                        <div class="mb-4">
                                            <label class="form-label">Success Color</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['success'] ?? '#28a745'; ?>;" 
                                                     onclick="document.getElementById('success_picker').click()"></div>
                                                <input type="text" class="form-control" id="success_color" name="success_color" 
                                                       value="<?php echo $colors['success'] ?? '#28a745'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="success_picker" style="display: none;" 
                                                       value="<?php echo $colors['success'] ?? '#28a745'; ?>" onchange="updateColorInput('success', this.value)">
                                            </div>
                                        </div>

                                        <!-- Danger Color -->
                                        <div class="mb-4">
                                            <label class="form-label">Danger Color</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['danger'] ?? '#dc3545'; ?>;" 
                                                     onclick="document.getElementById('danger_picker').click()"></div>
                                                <input type="text" class="form-control" id="danger_color" name="danger_color" 
                                                       value="<?php echo $colors['danger'] ?? '#dc3545'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="danger_picker" style="display: none;" 
                                                       value="<?php echo $colors['danger'] ?? '#dc3545'; ?>" onchange="updateColorInput('danger', this.value)">
                                            </div>
                                        </div>

                                        <!-- Warning Color -->
                                        <div class="mb-4">
                                            <label class="form-label">Warning Color</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['warning'] ?? '#ffc107'; ?>;" 
                                                     onclick="document.getElementById('warning_picker').click()"></div>
                                                <input type="text" class="form-control" id="warning_color" name="warning_color" 
                                                       value="<?php echo $colors['warning'] ?? '#ffc107'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="warning_picker" style="display: none;" 
                                                       value="<?php echo $colors['warning'] ?? '#ffc107'; ?>" onchange="updateColorInput('warning', this.value)">
                                            </div>
                                        </div>

                                        <!-- Info Color -->
                                        <div class="mb-4">
                                            <label class="form-label">Info Color</label>
                                            <div class="color-input-group">
                                                <div class="color-preview" style="background: <?php echo $colors['info'] ?? '#17a2b8'; ?>;" 
                                                     onclick="document.getElementById('info_picker').click()"></div>
                                                <input type="text" class="form-control" id="info_color" name="info_color" 
                                                       value="<?php echo $colors['info'] ?? '#17a2b8'; ?>" readonly
                                                       <?php echo (!$is_super_admin && !($allowed_customization['theme'] ?? true)) ? 'disabled' : ''; ?>>
                                                <input type="color" id="info_picker" style="display: none;" 
                                                       value="<?php echo $colors['info'] ?? '#17a2b8'; ?>" onchange="updateColorInput('info', this.value)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Layout Tab -->
                            <div class="tab-pane fade" id="layout" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h6 class="mb-3">Sidebar Settings</h6>
                                                
                                                <div class="mb-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="sidebar_collapsed" 
                                                               name="sidebar_collapsed" value="1" 
                                                               <?php echo ($preferences['sidebar_collapsed'] ?? '0') === '1' ? 'checked' : ''; ?>
                                                               <?php echo (!$is_super_admin && !($allowed_customization['preferences'] ?? true)) ? 'disabled' : ''; ?>>
                                                        <label class="form-check-label" for="sidebar_collapsed">
                                                            <strong>Collapsed Sidebar</strong>
                                                            <p class="text-muted small mb-0">Sidebar will be collapsed by default on desktop (hover to expand)</p>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="mb-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="compact_mode" 
                                                               name="compact_mode" value="1" 
                                                               <?php echo ($preferences['compact_mode'] ?? '0') === '1' ? 'checked' : ''; ?>
                                                               <?php echo (!$is_super_admin && !($allowed_customization['preferences'] ?? true)) ? 'disabled' : ''; ?>>
                                                        <label class="form-check-label" for="compact_mode">
                                                            <strong>Compact Mode</strong>
                                                            <p class="text-muted small mb-0">Reduce spacing for more content</p>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h6 class="mb-3">Animation Settings</h6>
                                                
                                                <div class="mb-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="animations" 
                                                               name="animations" value="1" 
                                                               <?php echo ($preferences['animations'] ?? '1') === '1' ? 'checked' : ''; ?>
                                                               <?php echo (!$is_super_admin && !($allowed_customization['preferences'] ?? true)) ? 'disabled' : ''; ?>>
                                                        <label class="form-check-label" for="animations">
                                                            <strong>Enable Animations</strong>
                                                            <p class="text-muted small mb-0">Smooth transitions and effects</p>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="mb-4">
                                                    <label class="form-label"><strong>Animation Speed</strong></label>
                                                    <select class="form-select" id="animation_speed" name="animation_speed"
                                                            <?php echo (!$is_super_admin && !($allowed_customization['preferences'] ?? true)) ? 'disabled' : ''; ?>>
                                                        <option value="slow" <?php echo ($preferences['animation_speed'] ?? 'normal') === 'slow' ? 'selected' : ''; ?>>Slow (0.5s)</option>
                                                        <option value="normal" <?php echo ($preferences['animation_speed'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>Normal (0.3s)</option>
                                                        <option value="fast" <?php echo ($preferences['animation_speed'] ?? 'normal') === 'fast' ? 'selected' : ''; ?>>Fast (0.15s)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Typography Tab -->
                            <div class="tab-pane fade" id="typography" role="tabpanel">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="mb-3">Font Size</h6>
                                                <p class="text-muted small">Choose your preferred font size</p>
                                                
                                                <div class="d-flex flex-wrap">
                                                    <?php foreach ($font_sizes as $size => $label): ?>
                                                    <div class="font-size-badge <?php echo ($preferences['font_size'] ?? '16') === $size ? 'active' : ''; ?>" 
                                                         onclick="setFontSize('<?php echo $size; ?>', this)"
                                                         <?php echo (!$is_super_admin && !($allowed_customization['preferences'] ?? true)) ? 'style="opacity:0.5;cursor:not-allowed;"' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <input type="hidden" id="font_size" name="font_size" value="<?php echo $preferences['font_size'] ?? '16'; ?>"
                                                       <?php echo (!$is_super_admin && !($allowed_customization['preferences'] ?? true)) ? 'disabled' : ''; ?>>
                                                
                                                <div class="mt-4 p-3 border rounded">
                                                    <h6>Preview:</h6>
                                                    <p style="font-size: var(--font-size-base);">This is how your text will look with the selected font size.</p>
                                                    <p class="small" style="font-size: calc(var(--font-size-base) * 0.875);">Small text example</p>
                                                    <p class="h4" style="font-size: calc(var(--font-size-base) * 1.5);">Heading example</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Background Tab -->
                            <div class="tab-pane fade" id="background" role="tabpanel">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="mb-3">Background Settings</h6>
                                                
                                                <div class="mb-4">
                                                    <label class="form-label"><strong>Background Type</strong></label>
                                                    <p class="text-muted small mb-2">Select your background preference</p>
                                                    
                                                    <select class="form-select" id="background_option" name="background_option" onchange="updateBackgroundPreview()"
                                                            <?php echo (!$is_super_admin && !($allowed_customization['preferences'] ?? true)) ? 'disabled' : ''; ?>>
                                                        <option value="image" <?php echo ($preferences['background_option'] ?? 'image') === 'image' ? 'selected' : ''; ?>>School Image</option>
                                                        <option value="gray" <?php echo ($preferences['background_option'] ?? 'image') === 'gray' ? 'selected' : ''; ?>>Gray</option>
                                                        <option value="eye_care" <?php echo ($preferences['background_option'] ?? 'image') === 'eye_care' ? 'selected' : ''; ?>>Eye Care</option>
                                                        <option value="milk" <?php echo ($preferences['background_option'] ?? 'image') === 'milk' ? 'selected' : ''; ?>>Milk</option>
                                                        <option value="dark_light" <?php echo ($preferences['background_option'] ?? 'image') === 'dark_light' ? 'selected' : ''; ?>>Dark-Light</option>
                                                    </select>
                                                </div>

                                                <div class="mb-4">
                                                    <label class="form-label"><strong>Background Opacity (65% default)</strong></label>
                                                    <p class="text-muted small mb-2">Adjust the transparency of the background</p>
                                                    <input type="range" class="range-slider" id="background_opacity" 
                                                           name="background_opacity" min="0" max="100" 
                                                           value="<?php echo $preferences['background_opacity'] ?? '65'; ?>"
                                                           oninput="updateOpacity(this.value)"
                                                           <?php echo (!$is_super_admin && !($allowed_customization['preferences'] ?? true)) ? 'disabled' : ''; ?>>
                                                    <div class="d-flex justify-content-between mt-2">
                                                        <span>Transparent</span>
                                                        <span id="opacityValue"><?php echo $preferences['background_opacity'] ?? '65'; ?>%</span>
                                                        <span>Opaque</span>
                                                    </div>
                                                </div>

                                                <div class="mt-4">
                                                    <label class="form-label"><strong>Live Preview</strong></label>
                                                    <div class="bg-option-preview" id="backgroundPreview" style="background: <?php echo $bg_style; ?>; background-size: <?php echo $bg_size; ?>;"></div>
                                                    <p class="text-muted small mt-2">This is how your background will look</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($is_super_admin): ?>
                            <!-- System Defaults Tab (Super Admin Only) -->
                            <div class="tab-pane fade" id="system" role="tabpanel">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="mb-3 text-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    School Default Settings - <?php echo htmlspecialchars($school_data['school_name']); ?>
                                                </h6>
                                                <p class="text-muted mb-4">
                                                    These settings will be applied as defaults for ALL users in <strong><?php echo htmlspecialchars($school_data['school_name']); ?></strong>.
                                                    School admins can override these unless you restrict customization below.
                                                </p>
                                                
                                                <div class="mb-4">
                                                    <label class="form-label"><strong>Restrict User Customization</strong></label>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="allow_theme_custom" 
                                                                       name="allow_theme_custom" value="1"
                                                                       <?php echo ($allowed_customization['theme'] ?? true) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">Allow school admins to customize colors</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="allow_prefs_custom" 
                                                                       name="allow_prefs_custom" value="1"
                                                                       <?php echo ($allowed_customization['preferences'] ?? true) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">Allow school admins to customize preferences</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="apply_to_all_users" name="apply_to_all_users" value="1">
                                                        <label class="form-check-label">
                                                            <strong>Apply these defaults to ALL existing admins in this school</strong>
                                                            <p class="text-muted small mb-0">This will overwrite all admins' current settings</p>
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>Note:</strong> School-specific logo and background image will be used from school settings.
                                                </div>
                                                
                                                <div class="mt-3">
                                                    <button type="button" class="btn btn-warning" onclick="saveSystemDefaults()">
                                                        <i class="fas fa-save me-2"></i>Save as School Defaults
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Hidden submit buttons -->
                        <button type="submit" name="save_theme" id="saveTheme" style="display: none;"></button>
                        <button type="submit" name="save_preferences" id="savePreferences" style="display: none;"></button>
                        <?php if ($is_super_admin): ?>
                        <button type="submit" name="save_system_defaults" id="saveSystemDefaultsBtn" style="display: none;"></button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Save Actions -->
            <div class="save-actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <?php if (!$is_super_admin): ?>
                <button type="button" class="btn btn-outline-info" onclick="resetToSystemDefault()">
                    <i class="fas fa-undo-alt me-2"></i>Reset to School Defaults
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-save" onclick="saveAll()">
                    <i class="fas fa-save me-2"></i>Save All Changes
                </button>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Background colors mapping
        const bgColors = {
            'gray': '#e9ecef',
            'eye_care': '#c7e9c0',
            'milk': '#fdf5e6',
            'dark_light': '#2d2d2d'
        };

        // Font size mapping
        const fontSizes = {
            '10': '10px',
            '12': '12px',
            '14': '14px',
            '16': '16px',
            '18': '18px'
        };

        // Show SweetAlert2 notifications
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            
            if (successMessage) {
                const message = successMessage.getAttribute('data-message');
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: message,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                    customClass: {
                        popup: 'colored-toast'
                    }
                });
            }
            
            if (errorMessage) {
                const message = errorMessage.getAttribute('data-message');
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: message,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                    customClass: {
                        popup: 'colored-toast'
                    }
                });
            }

            // Initialize background preview
            updateBackgroundPreview();
        });

        // Update color input when picker changes
        function updateColorInput(colorId, value) {
            document.getElementById(colorId + '_color').value = value;
            updatePreview();
        }

        // Update live preview
        function updatePreview() {
            const primary = document.getElementById('primary_color')?.value || '#3B9DB3';
            document.documentElement.style.setProperty('--primary-color', primary);
            
            const primaryDark = document.getElementById('primary_dark_color')?.value || '#2d7c8f';
            document.documentElement.style.setProperty('--primary-dark', primaryDark);
            
            const text = document.getElementById('text_color')?.value || '#333333';
            document.documentElement.style.setProperty('--text-color', text);
        }

        // Update opacity display
        function updateOpacity(value) {
            document.getElementById('opacityValue').textContent = value + '%';
            updateBackgroundPreview();
        }

        // Update background preview
        function updateBackgroundPreview() {
            const option = document.getElementById('background_option')?.value || 'image';
            const opacity = (document.getElementById('background_opacity')?.value || 65) / 100;
            const preview = document.getElementById('backgroundPreview');
            const schoolLogo = '<?php echo $school_logo; ?>';
            
            if (option === 'image') {
                preview.style.background = `linear-gradient(rgba(255,255,255,${opacity}), rgba(255,255,255,${opacity})), url('${schoolLogo}') no-repeat center center`;
                preview.style.backgroundSize = 'cover';
            } else {
                const color = bgColors[option] || '#e9ecef';
                preview.style.background = color;
                preview.style.backgroundSize = 'auto';
            }
        }

        // Set font size
        function setFontSize(size, element) {
            const fontSizeInput = document.getElementById('font_size');
            if (fontSizeInput && !fontSizeInput.disabled) {
                fontSizeInput.value = size;
                
                // Update active class
                document.querySelectorAll('.font-size-badge').forEach(badge => {
                    badge.classList.remove('active');
                });
                if (element) {
                    element.classList.add('active');
                }
                
                // Preview font size
                document.documentElement.style.setProperty('--font-size-base', fontSizes[size]);
            }
        }

        // Apply preset themes
        function applyPreset(preset) {
            const presets = {
                'default': {
                    primary: '#3B9DB3',
                    primary_dark: '#2d7c8f',
                    primary_light: '#8bc5d6',
                    coral: '#FF7F50',
                    forest_green: '#2E7D32',
                    lime_green: '#63E07E',
                    sky_blue: '#66d9ff',
                    aqua_blue: '#4dd2ff',
                    text: '#333333',
                    text_light: '#666666',
                    border: '#e0e0e0',
                    success: '#28a745',
                    danger: '#dc3545',
                    warning: '#ffc107',
                    info: '#17a2b8'
                },
                'coral': {
                    primary: '#FF7F50',
                    primary_dark: '#FF6347',
                    primary_light: '#FFA07A',
                    coral: '#FF7F50',
                    forest_green: '#2E7D32',
                    lime_green: '#63E07E',
                    sky_blue: '#66d9ff',
                    aqua_blue: '#4dd2ff',
                    text: '#333333',
                    text_light: '#666666',
                    border: '#e0e0e0',
                    success: '#28a745',
                    danger: '#dc3545',
                    warning: '#ffc107',
                    info: '#17a2b8'
                },
                'forest': {
                    primary: '#2E7D32',
                    primary_dark: '#1B5E20',
                    primary_light: '#81C784',
                    coral: '#FF7F50',
                    forest_green: '#2E7D32',
                    lime_green: '#63E07E',
                    sky_blue: '#66d9ff',
                    aqua_blue: '#4dd2ff',
                    text: '#333333',
                    text_light: '#666666',
                    border: '#e0e0e0',
                    success: '#28a745',
                    danger: '#dc3545',
                    warning: '#ffc107',
                    info: '#17a2b8'
                },
                'lime': {
                    primary: '#63E07E',
                    primary_dark: '#4CAF50',
                    primary_light: '#A5D6A7',
                    coral: '#FF7F50',
                    forest_green: '#2E7D32',
                    lime_green: '#63E07E',
                    sky_blue: '#66d9ff',
                    aqua_blue: '#4dd2ff',
                    text: '#333333',
                    text_light: '#666666',
                    border: '#e0e0e0',
                    success: '#28a745',
                    danger: '#dc3545',
                    warning: '#ffc107',
                    info: '#17a2b8'
                },
                'sky': {
                    primary: '#66d9ff',
                    primary_dark: '#4dd2ff',
                    primary_light: '#80e5ff',
                    coral: '#FF7F50',
                    forest_green: '#2E7D32',
                    lime_green: '#63E07E',
                    sky_blue: '#66d9ff',
                    aqua_blue: '#4dd2ff',
                    text: '#333333',
                    text_light: '#666666',
                    border: '#e0e0e0',
                    success: '#28a745',
                    danger: '#dc3545',
                    warning: '#ffc107',
                    info: '#17a2b8'
                },
                'aqua': {
                    primary: '#4dd2ff',
                    primary_dark: '#33ccff',
                    primary_light: '#99e6ff',
                    coral: '#FF7F50',
                    forest_green: '#2E7D32',
                    lime_green: '#63E07E',
                    sky_blue: '#66d9ff',
                    aqua_blue: '#4dd2ff',
                    text: '#333333',
                    text_light: '#666666',
                    border: '#e0e0e0',
                    success: '#28a745',
                    danger: '#dc3545',
                    warning: '#ffc107',
                    info: '#17a2b8'
                }
            };
            
            const colors = presets[preset];
            if (!colors) return;
            
            for (let [key, value] of Object.entries(colors)) {
                const element = document.getElementById(key + '_color');
                if (element && !element.disabled) {
                    element.value = value;
                    
                    const picker = document.getElementById(key + '_picker');
                    if (picker) {
                        picker.value = value;
                    }
                }
            }
            
            // Update active class on presets
            document.querySelectorAll('.theme-preset').forEach(p => {
                p.classList.remove('active');
            });
            const presetElement = document.getElementById('preset-' + preset);
            if (presetElement) {
                presetElement.classList.add('active');
            }
            
            updatePreview();
            
            // Show preview notification
            Swal.fire({
                icon: 'info',
                title: 'Preset Applied',
                text: preset.charAt(0).toUpperCase() + preset.slice(1) + ' theme previewed',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }

        // Save all changes via AJAX
        function saveAll() {
            Swal.fire({
                title: 'Saving...',
                text: 'Please wait while we save your settings',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const form = document.getElementById('themeForm');
            const formData = new FormData(form);
            
            <?php if (!$is_super_admin): ?>
            formData.append('save_theme', '1');
            formData.append('save_preferences', '1');
            <?php endif; ?>
            
            // Add checkbox values manually
            const sidebarCollapsed = document.getElementById('sidebar_collapsed');
            if (sidebarCollapsed && !sidebarCollapsed.checked) {
                formData.set('sidebar_collapsed', '0');
            }
            const compactMode = document.getElementById('compact_mode');
            if (compactMode && !compactMode.checked) {
                formData.set('compact_mode', '0');
            }
            const animations = document.getElementById('animations');
            if (animations && !animations.checked) {
                formData.set('animations', '0');
            }
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Error saving settings',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#d33'
                    });
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while saving settings',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
            });
        }

        <?php if ($is_super_admin): ?>
        // Super admin: Save system defaults for selected school
        function saveSystemDefaults() {
            Swal.fire({
                title: 'Save School Defaults?',
                text: 'These settings will be applied as defaults for all admins in this school.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'Yes, save defaults!'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Saving...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    const form = document.getElementById('themeForm');
                    const formData = new FormData(form);
                    formData.append('save_system_defaults', '1');
                    
                    const applyToAll = document.getElementById('apply_to_all_users');
                    if (applyToAll && applyToAll.checked) {
                        formData.append('apply_to_all', '1');
                    }
                    
                    const allowTheme = document.getElementById('allow_theme_custom');
                    if (allowTheme && !allowTheme.checked) {
                        formData.append('allow_theme_custom', '0');
                    }
                    
                    const allowPrefs = document.getElementById('allow_prefs_custom');
                    if (allowPrefs && !allowPrefs.checked) {
                        formData.append('allow_prefs_custom', '0');
                    }
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message,
                                timer: 3000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message
                            });
                        }
                    });
                }
            });
        }
        <?php endif; ?>

        // Reset to system default (for regular admins only)
        function resetToSystemDefault() {
            Swal.fire({
                title: 'Reset to School Defaults?',
                text: 'This will reset all your custom settings to your school\'s default theme.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, reset!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Resetting...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    const formData = new FormData();
                    formData.append('reset_to_system_default', '1');
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message,
                                timer: 3000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message
                            });
                        }
                    });
                }
            });
        }

        // Preview changes
        function previewChanges() {
            updatePreview();
            updateBackgroundPreview();
            
            const fontSize = document.getElementById('font_size')?.value || '16';
            document.documentElement.style.setProperty('--font-size-base', fontSizes[fontSize]);
            
            Swal.fire({
                icon: 'info',
                title: 'Preview Mode',
                text: 'Changes are now visible in preview',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview();
            
            // Mark active preset based on primary color
            const primary = document.getElementById('primary_color')?.value;
            if (primary === '#3B9DB3') {
                document.getElementById('preset-default')?.classList.add('active');
            } else if (primary === '#FF7F50') {
                document.getElementById('preset-coral')?.classList.add('active');
            } else if (primary === '#2E7D32') {
                document.getElementById('preset-forest')?.classList.add('active');
            } else if (primary === '#63E07E') {
                document.getElementById('preset-lime')?.classList.add('active');
            } else if (primary === '#66d9ff') {
                document.getElementById('preset-sky')?.classList.add('active');
            } else if (primary === '#4dd2ff') {
                document.getElementById('preset-aqua')?.classList.add('active');
            }
            
            // Initialize opacity display
            const opacityInput = document.getElementById('background_opacity');
            if (opacityInput) {
                document.getElementById('opacityValue').textContent = opacityInput.value + '%';
            }
        });
    </script>
</body>
</html>