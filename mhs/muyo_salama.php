<?php
// muyo_salama.php - Public View-Only Shule Salama Safety Portal
// This page displays safety announcements from the database in read-only mode
// Open to all visitors (no login required)

session_start();
require_once '../controller/db_connect.php';

// Include the school header
include 'header.php';

// Function to format time ago
function get_time_ago($datetime) {
    if (empty($datetime)) return "Recently";
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $total_seconds = ($now->getTimestamp() - $ago->getTimestamp());
    
    if ($total_seconds < 60) return "Just now";
    if ($total_seconds < 3600) return floor($total_seconds / 60) . " minutes ago";
    if ($total_seconds < 86400) return floor($total_seconds / 3600) . " hours ago";
    if ($total_seconds < 604800) return floor($total_seconds / 86400) . " days ago";
    if ($total_seconds < 2592000) return floor($total_seconds / 604800) . " weeks ago";
    if ($total_seconds < 31536000) return floor($total_seconds / 2592000) . " months ago";
    return floor($total_seconds / 31536000) . " years ago";
}

// Format file size for display
function format_file_size($bytes) {
    if ($bytes === null || $bytes === 0 || $bytes === '') return '';
    $bytes = (int)$bytes;
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

// Get file icon based on file type
function get_file_icon($file_type) {
    switch($file_type) {
        case 'image': return 'fa-image';
        case 'video': return 'fa-video';
        case 'audio': return 'fa-headphones';
        case 'document': return 'fa-file-pdf';
        case 'archive': return 'fa-file-archive';
        default: return 'fa-file';
    }
}

// ==================== DATABASE QUERIES ====================

// Get all active Shule Salama posts with public visibility
$posts_sql = "SELECT sp.*, 
              CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) as author_name, 
              a.profile_image as author_image
              FROM shule_salama_posts sp 
              LEFT JOIN admins a ON sp.admin_id = a.id 
              WHERE sp.status = 'active' 
              AND (sp.visibility = 'public' OR sp.visibility IS NULL OR sp.visibility = '')
              ORDER BY 
                CASE sp.priority 
                    WHEN 'emergency' THEN 1
                    WHEN 'critical' THEN 2
                    WHEN 'important' THEN 3
                    ELSE 4
                END, 
                sp.created_at DESC";

$posts_result = mysqli_query($conn, $posts_sql);
$total_posts = ($posts_result) ? mysqli_num_rows($posts_result) : 0;

// Get emergency/critical posts for banner
$emergency_sql = "SELECT COUNT(*) as emergency_count 
                  FROM shule_salama_posts sp 
                  WHERE sp.status = 'active' 
                  AND sp.priority IN ('emergency', 'critical')
                  AND (sp.visibility = 'public' OR sp.visibility IS NULL OR sp.visibility = '')";
$emergency_result = mysqli_query($conn, $emergency_sql);
$emergency_count = ($emergency_result && mysqli_num_rows($emergency_result) > 0) ? mysqli_fetch_assoc($emergency_result)['emergency_count'] : 0;

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN priority = 'emergency' THEN 1 ELSE 0 END) as emergency,
              SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical,
              SUM(CASE WHEN priority = 'important' THEN 1 ELSE 0 END) as important,
              SUM(CASE WHEN file_path IS NOT NULL AND file_path != '' THEN 1 ELSE 0 END) as with_files
              FROM shule_salama_posts 
              WHERE status = 'active' 
              AND (visibility = 'public' OR visibility IS NULL OR visibility = '')";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = ($stats_result && mysqli_num_rows($stats_result) > 0) ? mysqli_fetch_assoc($stats_result) : ['total' => 0, 'emergency' => 0, 'critical' => 0, 'important' => 0, 'with_files' => 0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <meta name="description" content="Shule Salama - Official Safety and Security Announcements Portal for Muyovozi High School. Stay informed with important updates, alerts, and emergency notifications.">
    <meta name="keywords" content="Muyovozi High School, Shule Salama, School Safety, Security Announcements, Tanzania Education">
    <meta name="author" content="Muyovozi High School">
    <title>Shule Salama - Safety Portal | Muyovozi High School</title>
    
    <!-- Bootstrap 5.3 + Font Awesome 6 + Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            --primary-color: #3B9DB3;
            --primary-dark: #2d7c8f;
            --primary-light: #8bc5d6;
            --accent-color: #ffc107;
            --danger-color: #dc3545;
            --warning-color: #ff8c42;
            --info-color: #17a2b8;
            --success-color: #28a745;
            --dark-color: #2c3e50;
            --gray-color: #6c757d;
            --light-color: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Main Content Area */
        .main-content {
            min-height: calc(100vh - 300px);
            padding: 100px 0 60px;
        }

        /* Hero Section with Background Image from home.php */
        .safety-hero {
            position: relative;
            padding: 80px 0;
            margin-bottom: 40px;
            color: white;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        /* Hero Background Slideshow */
        .hero-slideshow {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        
        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }
        
        .hero-slide.active {
            opacity: 1;
        }
        
        /* Slide backgrounds - using images from home.php */
        .hero-slide-1 { background-image: url('../images/class.png'); }
        .hero-slide-2 { background-image: url('../images/muyovozi.png'); }
        .hero-slide-3 { background-image: url('../images/necta.png'); }
        .hero-slide-4 { background-image: url('../images/ngao.png'); }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(26, 77, 94, 0.85) 0%, rgba(15, 46, 56, 0.85) 100%);
            z-index: 1;
        }
        
        .hero-content {
            padding: 0;
            position: relative;
            z-index: 2;
            text-align: center;
        }
        
        .hero-slide-dots {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 3;
        }
        
        .hero-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .hero-dot.active {
            background: var(--accent-color);
            width: 30px;
            border-radius: 10px;
        }
        
        .hero-dot:hover {
            background: white;
        }

        .safety-badge {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 10px 25px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 25px;
            border: 1px solid rgba(255,255,255,0.3);
            animation: fadeInDown 0.8s ease;
        }

        .hero-content h1 {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            animation: fadeInUp 0.8s ease;
        }

        .hero-content p {
            font-size: 20px;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.95;
            animation: fadeInUp 0.8s ease 0.2s both;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin: -40px 0 50px;
            position: relative;
            z-index: 10;
        }

        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(59,157,179,0.2);
        }

        .stat-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 26px;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark-color);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-color);
            font-weight: 500;
            margin-top: 5px;
        }

        /* Emergency Banner */
        .emergency-banner {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            padding: 18px 25px;
            border-radius: 20px;
            margin-bottom: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            animation: pulse 2s infinite;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .emergency-banner:hover {
            transform: scale(1.02);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.92; transform: scale(1.01); }
        }

        .emergency-banner i {
            font-size: 28px;
            animation: bellShake 1s infinite;
        }
        
        @keyframes bellShake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(15deg); }
            75% { transform: rotate(-15deg); }
        }

        .emergency-banner strong {
            font-size: 18px;
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 60px;
            padding: 8px;
            margin-bottom: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .search-wrapper {
            flex: 1;
            position: relative;
            min-width: 220px;
        }

        .search-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
            font-size: 16px;
        }

        .search-wrapper input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .search-wrapper input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(59,157,179,0.1);
        }

        .filter-select {
            padding: 14px 28px;
            border: 2px solid #e9ecef;
            border-radius: 50px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 160px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        /* Posts Grid */
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 30px;
        }

        /* Post Card */
        .post-card {
            background: white;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .post-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 50px rgba(59,157,179,0.2);
        }

        /* Priority Bar */
        .priority-bar {
            height: 6px;
            width: 100%;
        }
        .priority-emergency { background: linear-gradient(90deg, var(--danger-color), #ff6b6b); }
        .priority-critical { background: linear-gradient(90deg, var(--warning-color), #ffb347); }
        .priority-important { background: linear-gradient(90deg, var(--info-color), #4ecdc4); }
        .priority-normal { background: linear-gradient(90deg, var(--success-color), #51cf66); }

        /* Card Badge */
        .card-badge {
            position: absolute;
            top: 18px;
            right: 18px;
            z-index: 2;
        }

        .badge-priority {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .badge-emergency { background: var(--danger-color); color: white; }
        .badge-critical { background: var(--warning-color); color: white; }
        .badge-important { background: var(--info-color); color: white; }
        .badge-normal { background: var(--success-color); color: white; }

        /* Card Content */
        .card-content {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Author Info */
        .author-info {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
            flex-shrink: 0;
            border: 2px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .author-details {
            flex: 1;
        }

        .author-name {
            font-weight: 700;
            font-size: 16px;
            color: var(--dark-color);
            margin-bottom: 4px;
        }

        .post-time {
            font-size: 12px;
            color: var(--gray-color);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Card Title & Description */
        .card-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 14px;
            color: var(--dark-color);
            line-height: 1.4;
        }

        .card-description {
            color: #555;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 18px;
            flex: 1;
        }

        /* Card Meta */
        .card-meta {
            display: flex;
            gap: 20px;
            padding-top: 14px;
            border-top: 1px solid rgba(0,0,0,0.06);
            margin-bottom: 18px;
            font-size: 13px;
            color: var(--gray-color);
        }

        .meta-item i {
            color: var(--primary-color);
            margin-right: 6px;
            width: 16px;
        }

        /* Attachment Preview */
        .attachment-preview {
            background: var(--light-color);
            border-radius: 18px;
            padding: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .attachment-preview:hover {
            background: #f0f4f8;
            transform: translateX(5px);
        }

        .attachment-icon {
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .attachment-info {
            flex: 1;
            min-width: 0;
        }

        .attachment-name {
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attachment-size {
            font-size: 11px;
            color: var(--gray-color);
            margin-top: 2px;
        }

        .btn-download {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-download:hover {
            background: var(--primary-color);
            color: white;
            transform: scale(1.05);
        }

        /* View Button */
        .btn-view {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            color: white;
            padding: 12px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
            text-align: center;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, var(--primary-dark), #1a5a6b);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59,157,179,0.3);
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 32px;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 22px 28px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 28px;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }

        .modal-attachment {
            background: var(--light-color);
            border-radius: 20px;
            padding: 18px;
            margin-top: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 70px 30px;
            background: white;
            border-radius: 35px;
            grid-column: 1 / -1;
        }

        .empty-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(59,157,179,0.1), rgba(45,124,143,0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 50px;
            color: var(--primary-color);
        }

        /* Quick Actions */
        .quick-actions {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .quick-btn {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            box-shadow: 0 6px 18px rgba(59,157,179,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-btn:hover {
            transform: scale(1.1);
            background: var(--primary-dark);
            box-shadow: 0 10px 25px rgba(59,157,179,0.5);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 28px;
            grid-column: 1 / -1;
        }

        /* Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .animate-on-scroll.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .posts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .hero-content h1 {
                font-size: 42px;
            }
        }

        @media (max-width: 768px) {
            .hero-content h1 { font-size: 32px; }
            .hero-content p { font-size: 16px; }
            .stats-grid { 
                grid-template-columns: repeat(2, 1fr);
                margin-top: -20px;
            }
            .posts-grid { 
                grid-template-columns: 1fr;
            }
            .filter-bar { 
                border-radius: 25px; 
                flex-direction: column;
                padding: 15px;
            }
            .search-wrapper { width: 100%; }
            .filter-select { width: 100%; }
            .quick-actions { bottom: 20px; right: 20px; }
            .quick-btn { width: 45px; height: 45px; font-size: 18px; }
            .safety-badge { font-size: 12px; padding: 8px 18px; }
            .hero-slide-dots { bottom: 15px; }
        }

        @media (max-width: 576px) {
            .stats-grid { grid-template-columns: 1fr; }
            .hero-content h1 { font-size: 26px; }
            .card-title { font-size: 18px; }
            .stat-number { font-size: 28px; }
            .modal-body { padding: 20px; }
        }

        /* Print Styles */
        @media print {
            .main-header, .footer, .quick-actions, .filter-bar, .safety-hero, .stats-grid, .emergency-banner {
                display: none;
            }
            .main-content { padding-top: 20px; }
            .post-card { 
                break-inside: avoid; 
                box-shadow: none; 
                border: 1px solid #ddd;
                margin-bottom: 20px;
            }
            .btn-view { display: none; }
        }
    </style>
</head>
<body>

<main class="main-content">
    <!-- Hero Section with Background Slideshow (using images from home.php) -->
    <div class="safety-hero">
        <div class="hero-slideshow">
            <div class="hero-slide hero-slide-1 active"></div>
            <div class="hero-slide hero-slide-2"></div>
            <div class="hero-slide hero-slide-3"></div>
            <div class="hero-slide hero-slide-4"></div>
        </div>
        <div class="hero-overlay"></div>
        
        <div class="hero-slide-dots">
            <div class="hero-dot active" data-slide="0"></div>
            <div class="hero-dot" data-slide="1"></div>
            <div class="hero-dot" data-slide="2"></div>
            <div class="hero-dot" data-slide="3"></div>
        </div>
        
        <div class="container">
            <div class="hero-content">
                <div class="safety-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Shule Salama - Official Safety Portal</span>
                    <i class="fas fa-lock"></i>
                </div>
                <h1>Safety & Security<br>Announcements</h1>
                <p>Stay informed with official safety updates, alerts, and important announcements from Muyovozi High School administration. Your safety is our priority.</p>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Announcements</div>
            </div>
            <div class="stat-card animate-on-scroll" data-delay="100">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger-color), #c82333);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number"><?php echo ($stats['emergency'] + $stats['critical']); ?></div>
                <div class="stat-label">Active Alerts</div>
            </div>
            <div class="stat-card animate-on-scroll" data-delay="200">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info-color), #138496);">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-number"><?php echo $stats['important']; ?></div>
                <div class="stat-label">Important Updates</div>
            </div>
            <div class="stat-card animate-on-scroll" data-delay="300">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success-color), #1e7e34);">
                    <i class="fas fa-paperclip"></i>
                </div>
                <div class="stat-number"><?php echo $stats['with_files']; ?></div>
                <div class="stat-label">With Attachments</div>
            </div>
        </div>

        <!-- Emergency Alert Banner -->
        <?php if ($emergency_count > 0): ?>
        <div class="emergency-banner animate-on-scroll" onclick="document.getElementById('priorityFilter').value='emergency'; filterPosts();">
            <i class="fas fa-exclamation-circle fa-2x"></i>
            <div>
                <strong>🚨 EMERGENCY ALERT:</strong> <?php echo $emergency_count; ?> active emergency announcement(s). 
                <span style="text-decoration: underline;">Click here to view all alerts.</span>
            </div>
            <i class="fas fa-chevron-right"></i>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar animate-on-scroll" data-delay="100">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search announcements by title or content...">
            </div>
            <select id="priorityFilter" class="filter-select">
                <option value="all">📋 All Priorities</option>
                <option value="emergency">🔴 Emergency - Urgent</option>
                <option value="critical">🟠 Critical - High Priority</option>
                <option value="important">🟡 Important - Read</option>
                <option value="normal">🟢 Normal - General Info</option>
            </select>
        </div>

        <!-- Posts Grid -->
        <div class="posts-grid" id="postsGrid">
            <?php if ($posts_result && $total_posts > 0): ?>
                <?php while ($post = mysqli_fetch_assoc($posts_result)): 
                    // Determine priority classes
                    $priority_class = '';
                    $badge_class = '';
                    $priority_text = '';
                    $priority_icon = '';
                    
                    switch($post['priority']) {
                        case 'emergency':
                            $priority_class = 'priority-emergency';
                            $badge_class = 'badge-emergency';
                            $priority_text = '🔴 EMERGENCY';
                            $priority_icon = 'fa-exclamation-triangle';
                            break;
                        case 'critical':
                            $priority_class = 'priority-critical';
                            $badge_class = 'badge-critical';
                            $priority_text = '🟠 CRITICAL';
                            $priority_icon = 'fa-exclamation-circle';
                            break;
                        case 'important':
                            $priority_class = 'priority-important';
                            $badge_class = 'badge-important';
                            $priority_text = '🟡 IMPORTANT';
                            $priority_icon = 'fa-info-circle';
                            break;
                        default:
                            $priority_class = 'priority-normal';
                            $badge_class = 'badge-normal';
                            $priority_text = '🟢 NORMAL';
                            $priority_icon = 'fa-check-circle';
                    }
                    
                    $time_ago = get_time_ago($post['created_at']);
                    $author_name = !empty($post['author_name']) ? htmlspecialchars($post['author_name']) : 'School Administration';
                    $author_initial = strtoupper(substr($author_name, 0, 1));
                    $description_preview = strip_tags($post['description']);
                    $description_preview = strlen($description_preview) > 200 ? substr($description_preview, 0, 200) . '...' : $description_preview;
                    
                    $file_icon = get_file_icon($post['file_type']);
                    $file_size = format_file_size($post['file_size']);
                    $has_attachment = !empty($post['file_path']) && file_exists('../' . $post['file_path']);
                ?>
                <div class="post-card animate-on-scroll" data-priority="<?php echo $post['priority']; ?>" data-id="<?php echo $post['id']; ?>">
                    <div class="priority-bar <?php echo $priority_class; ?>"></div>
                    
                    <div class="card-badge">
                        <span class="badge-priority <?php echo $badge_class; ?>">
                            <i class="fas <?php echo $priority_icon; ?>"></i> <?php echo $priority_text; ?>
                        </span>
                    </div>
                    
                    <div class="card-content">
                        <div class="author-info">
                            <div class="author-avatar"><?php echo $author_initial; ?></div>
                            <div class="author-details">
                                <div class="author-name"><?php echo $author_name; ?></div>
                                <div class="post-time"><i class="far fa-clock"></i> <?php echo $time_ago; ?></div>
                            </div>
                        </div>
                        
                        <h5 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h5>
                        
                        <div class="card-description">
                            <?php echo nl2br(htmlspecialchars($description_preview)); ?>
                        </div>
                        
                        <div class="card-meta">
                            <span class="meta-item"><i class="fas fa-eye"></i> <?php echo number_format($post['views_count']); ?> views</span>
                            <span class="meta-item"><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                        </div>
                        
                        <?php if ($has_attachment): ?>
                        <div class="attachment-preview">
                            <div class="attachment-icon"><i class="fas <?php echo $file_icon; ?>"></i></div>
                            <div class="attachment-info">
                                <div class="attachment-name"><?php echo htmlspecialchars($post['file_name'] ?? 'Attachment'); ?></div>
                                <?php if ($file_size): ?><div class="attachment-size"><?php echo $file_size; ?></div><?php endif; ?>
                            </div>
                            <a href="../<?php echo $post['file_path']; ?>" download class="btn-download" title="Download attachment">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <button class="btn-view" onclick="viewPost(
                            <?php echo $post['id']; ?>, 
                            '<?php echo addslashes(htmlspecialchars($post['title'])); ?>', 
                            '<?php echo addslashes(nl2br(htmlspecialchars($post['description']))); ?>', 
                            '<?php echo $post['created_at']; ?>', 
                            '<?php echo addslashes($author_name); ?>', 
                            '<?php echo addslashes($post['file_path'] ?? ''); ?>', 
                            '<?php echo addslashes(htmlspecialchars($post['file_name'] ?? '')); ?>', 
                            '<?php echo $file_icon; ?>', 
                            '<?php echo $file_size; ?>'
                        )">
                            <i class="fas fa-eye"></i> Read Full Announcement
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state animate-on-scroll">
                    <div class="empty-icon"><i class="fas fa-shield-alt"></i></div>
                    <h4>No Announcements Yet</h4>
                    <p class="text-muted">There are currently no safety announcements available. Check back later for important updates from the school administration.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Post Detail Modal -->
<div class="modal fade" id="postModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i>Announcement Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-3 text-muted">Loading announcement...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <button class="quick-btn" onclick="scrollToTop()" title="Back to Top">
        <i class="fas fa-arrow-up"></i>
    </button>
    <button class="quick-btn" onclick="window.location.href='contact.php'" title="Contact School">
        <i class="fas fa-phone-alt"></i>
    </button>
    <button class="quick-btn" onclick="window.location.href='index.php'" title="Home">
        <i class="fas fa-home"></i>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Hero Background Slideshow (using images from home.php)
    (function() {
        const slides = document.querySelectorAll('.hero-slide');
        const dots = document.querySelectorAll('.hero-dot');
        if (!slides.length) return;
        
        let currentSlide = 0;
        let slideInterval;
        
        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            
            slides[index].classList.add('active');
            if (dots[index]) dots[index].classList.add('active');
            currentSlide = index;
        }
        
        function nextSlide() {
            let nextIndex = currentSlide + 1;
            if (nextIndex >= slides.length) nextIndex = 0;
            showSlide(nextIndex);
        }
        
        function startSlideshow() {
            if (slideInterval) clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 5000);
        }
        
        function stopSlideshow() {
            if (slideInterval) clearInterval(slideInterval);
        }
        
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                stopSlideshow();
                showSlide(index);
                startSlideshow();
            });
        });
        
        startSlideshow();
        
        const heroSection = document.querySelector('.safety-hero');
        if (heroSection) {
            heroSection.addEventListener('mouseenter', stopSlideshow);
            heroSection.addEventListener('mouseleave', startSlideshow);
        }
    })();

    // Search and Filter Functionality
    const searchInput = document.getElementById('searchInput');
    const priorityFilter = document.getElementById('priorityFilter');
    const posts = document.querySelectorAll('.post-card');
    let noResultsDiv = null;

    function filterPosts() {
        const searchTerm = searchInput.value.toLowerCase();
        const priority = priorityFilter.value;
        let visibleCount = 0;
        
        posts.forEach(post => {
            const title = post.querySelector('.card-title')?.textContent.toLowerCase() || '';
            const description = post.querySelector('.card-description')?.textContent.toLowerCase() || '';
            const postPriority = post.dataset.priority;
            
            const matchesSearch = searchTerm === '' || title.includes(searchTerm) || description.includes(searchTerm);
            const matchesPriority = priority === 'all' || postPriority === priority;
            
            if (matchesSearch && matchesPriority) {
                post.style.display = 'flex';
                visibleCount++;
            } else {
                post.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        const existingNoResults = document.querySelector('.no-results');
        if (visibleCount === 0 && posts.length > 0) {
            if (!existingNoResults) {
                noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results';
                noResultsDiv.innerHTML = `
                    <i class="fas fa-search fa-4x mb-4" style="color: var(--primary-color); opacity: 0.5;"></i>
                    <h5>No matching announcements</h5>
                    <p class="text-muted">Try adjusting your search or filter criteria.</p>
                    <button class="btn btn-outline-primary rounded-pill px-4 mt-2" onclick="resetFilters()">
                        <i class="fas fa-undo-alt me-2"></i>Reset Filters
                    </button>
                `;
                document.getElementById('postsGrid').appendChild(noResultsDiv);
            }
        } else if (existingNoResults && visibleCount > 0) {
            existingNoResults.remove();
        }
    }
    
    function resetFilters() {
        searchInput.value = '';
        priorityFilter.value = 'all';
        filterPosts();
    }

    searchInput.addEventListener('input', filterPosts);
    priorityFilter.addEventListener('change', filterPosts);

    // View Post Function (Modal)
    function viewPost(id, title, description, createdAt, authorName, filePath, fileName, fileIcon, fileSize) {
        const modal = new bootstrap.Modal(document.getElementById('postModal'));
        const modalBody = document.getElementById('modalBody');
        
        const date = new Date(createdAt);
        const formattedDate = date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        const formattedTime = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        
        let attachmentHtml = '';
        if (filePath && filePath.trim() !== '') {
            const fullPath = '../' + filePath;
            attachmentHtml = `
                <div class="modal-attachment">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 55px; height: 55px; background: linear-gradient(135deg, rgba(59,157,179,0.1), rgba(45,124,143,0.1)); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas ${fileIcon} fa-2x" style="color: var(--primary-color);"></i>
                        </div>
                        <div>
                            <div class="fw-bold">${escapeHtml(fileName || 'Attachment')}</div>
                            ${fileSize ? `<small class="text-muted">${fileSize}</small>` : ''}
                        </div>
                    </div>
                    <a href="${fullPath}" download class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                </div>
            `;
        }
        
        modalBody.innerHTML = `
            <div class="mb-4 pb-3 border-bottom">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width: 55px; height: 55px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 22px;">
                        ${escapeHtml(authorName.charAt(0).toUpperCase())}
                    </div>
                    <div>
                        <div class="fw-bold fs-5">${escapeHtml(authorName)}</div>
                        <div class="text-muted small"><i class="far fa-calendar-alt me-1"></i> ${formattedDate} at ${formattedTime}</div>
                    </div>
                </div>
                <h4 class="fw-bold mb-2">${escapeHtml(title)}</h4>
            </div>
            <div class="mb-4" style="line-height: 1.9; white-space: pre-wrap; font-size: 15px;">
                ${description}
            </div>
            ${attachmentHtml}
            <div class="alert alert-info mt-4 mb-0 small rounded-3">
                <i class="fas fa-info-circle me-2"></i>
                For any questions regarding this announcement, please contact the school administration.
            </div>
        `;
        
        modal.show();
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Scroll to Top Function
    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Animation on Scroll
    document.addEventListener('DOMContentLoaded', function() {
        const animateElements = document.querySelectorAll('.animate-on-scroll');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });
        
        animateElements.forEach(element => {
            observer.observe(element);
            const delay = element.getAttribute('data-delay');
            if (delay) {
                element.style.transitionDelay = delay + 'ms';
            }
        });
        
        // Add hover effects for cards
        const cards = document.querySelectorAll('.post-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-12px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    });
</script>
<?php include '../controller/footer.php'; ?>
</body>
</html>