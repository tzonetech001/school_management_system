<?php
// clubs.php - School Clubs & Societies Page
session_start();
require_once '../controller/db_connect.php';

// Include header
include 'header.php';

// Sample club data (in production, fetch from database)
$clubs = [
    [
        'id' => 1,
        'name' => 'Debate & Public Speaking Club',
        'motto' => 'Speak Up, Stand Out',
        'description' => 'The Debate Club hones students\' critical thinking, research, and public speaking skills. Members participate in inter-school debates, parliamentary sessions, and public speaking competitions.',
        'meeting_day' => 'Every Tuesday',
        'meeting_time' => '3:30 PM - 5:00 PM',
        'venue' => 'Humanities Block, Room 201',
        'patron' => 'Mr. John Mwakibete',
        'achievements' => ['Regional Debate Champions 2023', 'Best Speaker Award - Sarah M.'],
        'icon' => 'fa-comments',
        'color' => '#3498db',
        'image' => '../images/debate-club.jpg',
        'member_count' => 45,
        'status' => 'active'
    ],
    [
        'id' => 2,
        'name' => 'Science & Innovation Club',
        'motto' => 'Innovate, Experiment, Discover',
        'description' => 'The Science Club encourages scientific curiosity through hands-on experiments, science fairs, and innovation challenges. Students explore physics, chemistry, biology, and emerging technologies.',
        'meeting_day' => 'Every Wednesday',
        'meeting_time' => '4:00 PM - 6:00 PM',
        'venue' => 'Science Laboratory',
        'patron' => 'Madam Grace Kileo',
        'achievements' => ['National Science Fair 1st Place 2024', 'Young Innovators Award'],
        'icon' => 'fa-flask',
        'color' => '#27ae60',
        'image' => '../images/science-club.jpg',
        'member_count' => 62,
        'status' => 'active'
    ],
    [
        'id' => 3,
        'name' => 'Arts & Culture Club',
        'motto' => 'Express Your Heritage',
        'description' => 'Celebrating Tanzania\'s rich cultural diversity through traditional dance, music, drama, and visual arts. The club performs at school events and cultural festivals.',
        'meeting_day' => 'Every Thursday',
        'meeting_time' => '3:30 PM - 5:30 PM',
        'venue' => 'Drama Hall',
        'patron' => 'Madam Esther Mushi',
        'achievements' => ['Best Cultural Performance - Kasulu Fest 2024', 'National Arts Competition Finalist'],
        'icon' => 'fa-palette',
        'color' => '#e74c3c',
        'image' => '../images/arts-club.jpg',
        'member_count' => 78,
        'status' => 'active'
    ],
    [
        'id' => 4,
        'name' => 'Sports Club',
        'motto' => 'Healthy Body, Healthy Mind',
        'description' => 'The Sports Club promotes physical fitness, teamwork, and sportsmanship through football, basketball, volleyball, athletics, and more. Students train and compete at district and regional levels.',
        'meeting_day' => 'Monday - Friday',
        'meeting_time' => '4:30 PM - 6:30 PM',
        'venue' => 'School Playgrounds',
        'patron' => 'Mr. Hassan Juma',
        'achievements' => ['District Football Champions 2024', 'Volleyball Tournament Winners 2023'],
        'icon' => 'fa-futbol',
        'color' => '#f39c12',
        'image' => '../images/sports-club.jpg',
        'member_count' => 120,
        'status' => 'active'
    ],
    [
        'id' => 5,
        'name' => 'Environmental Club',
        'motto' => 'Protect Our Planet',
        'description' => 'Dedicated to environmental conservation, tree planting, waste management, and raising awareness about climate change and sustainable living practices.',
        'meeting_day' => 'Every Friday',
        'meeting_time' => '2:00 PM - 4:00 PM',
        'venue' => 'Environmental Lab',
        'patron' => 'Madam Agnes Joseph',
        'achievements' => ['Most Trees Planted - District Competition', 'Eco-Schools Bronze Award'],
        'icon' => 'fa-leaf',
        'color' => '#2ecc71',
        'image' => '../images/environmental-club.jpg',
        'member_count' => 55,
        'status' => 'active'
    ],
    [
        'id' => 6,
        'name' => 'Journalism & Media Club',
        'motto' => 'Informing, Inspiring, Impacting',
        'description' => 'The Journalism Club produces the school newspaper, manages social media, and covers school events through photography, videography, and article writing.',
        'meeting_day' => 'Every Monday',
        'meeting_time' => '3:30 PM - 5:00 PM',
        'venue' => 'Media Center',
        'patron' => 'Mr. Charles Msigwa',
        'achievements' => ['Best School Magazine 2024', 'Student Reporter of the Year'],
        'icon' => 'fa-newspaper',
        'color' => '#1abc9c',
        'image' => '../images/media-club.jpg',
        'member_count' => 38,
        'status' => 'active'
    ],
    [
        'id' => 7,
        'name' => 'Red Cross & First Aid Club',
        'motto' => 'Saving Lives, Serving Humanity',
        'description' => 'The Red Cross Club trains students in first aid, emergency response, and health awareness. Members participate in community health outreach and school safety initiatives.',
        'meeting_day' => 'Every Saturday',
        'meeting_time' => '9:00 AM - 12:00 PM',
        'venue' => 'Health Room',
        'patron' => 'Madam Lucy Ndyetabura',
        'achievements' => ['Best School Red Cross Chapter 2023', 'Community Service Award'],
        'icon' => 'fa-hand-holding-heart',
        'color' => '#e74c3c',
        'image' => '../images/redcross-club.jpg',
        'member_count' => 42,
        'status' => 'active'
    ],
    [
        'id' => 8,
        'name' => 'Entrepreneurship Club',
        'motto' => 'Dream It, Build It',
        'description' => 'Nurturing young entrepreneurs through business planning, financial literacy, and hands-on projects. Students learn to identify opportunities and create small business ventures.',
        'meeting_day' => 'Every Thursday',
        'meeting_time' => '4:00 PM - 5:30 PM',
        'venue' => 'Economics Room',
        'patron' => 'Mr. Omari Said',
        'achievements' => ['Young Entrepreneurs Competition Winners', 'Best Business Plan Award'],
        'icon' => 'fa-chart-line',
        'color' => '#9b59b6',
        'image' => '../images/entrepreneurship-club.jpg',
        'member_count' => 34,
        'status' => 'active'
    ],
    [
        'id' => 9,
        'name' => 'ICT & Coding Club',
        'motto' => 'Code the Future',
        'description' => 'The ICT Club introduces students to computer programming, web development, robotics, and digital literacy. Members work on tech projects and represent the school at tech competitions.',
        'meeting_day' => 'Every Wednesday',
        'meeting_time' => '3:30 PM - 5:30 PM',
        'venue' => 'Computer Lab',
        'patron' => 'Mr. David Mwita',
        'achievements' => ['National Coding Challenge Finalists', 'Best School Website Award'],
        'icon' => 'fa-laptop-code',
        'color' => '#34495e',
        'image' => '../images/ict-club.jpg',
        'member_count' => 48,
        'status' => 'active'
    ]
];

// Get statistics
$total_members = array_sum(array_column($clubs, 'member_count'));
$active_clubs = count($clubs);
$total_achievements = 0;
foreach ($clubs as $club) {
    $total_achievements += count($club['achievements']);
}
?>

<style>
    /* Clubs Page Styles - FULL WIDTH with Background Images */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    :root {
        --primary-color: #3B9DB3;
        --primary-dark: #2d7c8f;
        --primary-light: #8bc5d6;
        --accent-color: #ffc107;
        --success-color: #28a745;
        --danger-color: #dc3545;
        --info-color: #17a2b8;
        --dark-color: #2c3e50;
        --light-color: #f8f9fa;
        --gray-color: #6c757d;
    }
    
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        color: var(--dark-color);
        line-height: 1.6;
        overflow-x: hidden;
        width: 100%;
    }
    
    .main-content {
        width: 100%;
        max-width: 100%;
        padding: 0;
        margin: 0;
        overflow-x: hidden;
    }
    
    .container-full {
        width: 100%;
        max-width: 100%;
        padding: 0 20px;
        margin: 0 auto;
    }
    
    @media (min-width: 1400px) {
        .container-full {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
        }
    }
    
    /* Hero Section with Background Slideshow */
    .clubs-hero {
        position: relative;
        min-height: 65vh;
        display: flex;
        align-items: center;
        overflow: hidden;
        margin-bottom: 60px;
    }
    
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
        z-index: 1;
    }
    
    .hero-slide.active {
        opacity: 1;
        z-index: 2;
    }
    
    .hero-slide-1 {
        background-image: url('../images/image1.png');
        background-size: cover;
        background-position: center;
        background-color: #1a4d5e;
    }
    
    .hero-slide-2 {
        background-image: url('../images/image2.png');
        background-size: cover;
        background-position: center;
        background-color: #2d7c8f;
    }
    
    .hero-slide-3 {
        background-image: url('../images/image3.png');
        background-size: cover;
        background-position: center;
        background-color: #0f2e38;
    }
    
    .hero-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(26, 77, 94, 0.85) 0%, rgba(15, 46, 56, 0.85) 100%);
        z-index: 3;
    }
    
    .hero-content {
        position: relative;
        z-index: 10;
        color: white;
        text-align: center;
        padding: 100px 20px;
    }
    
    .hero-content h1 {
        font-size: 56px;
        font-weight: 800;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    }
    
    .hero-content p {
        font-size: 20px;
        max-width: 800px;
        margin: 0 auto;
        opacity: 0.95;
        line-height: 1.8;
    }
    
    .hero-badge {
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        padding: 12px 30px;
        border-radius: 50px;
        display: inline-block;
        margin-bottom: 30px;
        border: 1px solid rgba(255,255,255,0.3);
        font-size: 16px;
        font-weight: 600;
    }
    
    .hero-badge i {
        margin-right: 8px;
        color: var(--accent-color);
    }
    
    .hero-dots {
        position: absolute;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 12px;
        z-index: 15;
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
    
    /* Section Headers */
    .section-header {
        text-align: center;
        margin-bottom: 50px;
    }
    
    .section-header h2 {
        font-size: 42px;
        font-weight: 800;
        color: var(--primary-color);
        margin-bottom: 20px;
        position: relative;
        display: inline-block;
    }
    
    .section-header h2::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        border-radius: 2px;
    }
    
    .section-header p {
        font-size: 18px;
        color: #666;
        max-width: 700px;
        margin: 25px auto 0;
        padding: 0 20px;
    }
    
    /* Statistics Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
        margin: 40px 0;
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 30px 20px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
    }
    
    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(59,157,179,0.15);
    }
    
    .stat-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        color: white;
        font-size: 30px;
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: 800;
        color: var(--dark-color);
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: var(--gray-color);
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    /* Filter Bar */
    .filter-bar {
        background: white;
        border-radius: 50px;
        padding: 8px;
        margin: 30px 0;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        justify-content: space-between;
    }
    
    .search-box {
        flex: 1;
        min-width: 250px;
        position: relative;
    }
    
    .search-box i {
        position: absolute;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-color);
    }
    
    .search-box input {
        width: 100%;
        padding: 12px 20px 12px 50px;
        border: 1px solid #e9ecef;
        border-radius: 40px;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .search-box input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59,157,179,0.1);
    }
    
    .filter-select {
        padding: 12px 25px;
        border: 1px solid #e9ecef;
        border-radius: 40px;
        font-size: 14px;
        background: white;
        cursor: pointer;
    }
    
    /* Clubs Grid */
    .clubs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 30px;
        margin: 40px 0;
    }
    
    .club-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        transition: all 0.4s ease;
        position: relative;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .club-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 25px 50px rgba(59,157,179,0.25);
    }
    
    .club-image {
        height: 200px;
        background-size: cover;
        background-position: center;
        position: relative;
        transition: transform 0.5s ease;
    }
    
    .club-card:hover .club-image {
        transform: scale(1.05);
    }
    
    .club-image-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
        padding: 20px;
    }
    
    .club-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: var(--accent-color);
        color: var(--dark-color);
        padding: 5px 15px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
        z-index: 2;
    }
    
    .club-icon {
        position: absolute;
        bottom: -25px;
        left: 20px;
        width: 60px;
        height: 60px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 3;
        transition: all 0.3s ease;
    }
    
    .club-card:hover .club-icon {
        transform: rotateY(360deg);
    }
    
    .club-content {
        padding: 35px 25px 25px;
        flex: 1;
    }
    
    .club-name {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 5px;
        color: var(--dark-color);
    }
    
    .club-motto {
        font-size: 13px;
        color: var(--primary-color);
        font-style: italic;
        margin-bottom: 15px;
        display: inline-block;
        padding: 3px 12px;
        background: rgba(59,157,179,0.1);
        border-radius: 20px;
    }
    
    .club-description {
        color: #555;
        font-size: 14px;
        line-height: 1.7;
        margin-bottom: 20px;
    }
    
    .club-details {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
        padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.05);
    }
    
    .detail-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #666;
    }
    
    .detail-item i {
        color: var(--primary-color);
        width: 20px;
    }
    
    .achievements-list {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .achievements-list h6 {
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--primary-color);
    }
    
    .achievement-tag {
        display: inline-block;
        background: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        margin: 3px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .member-count {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(59,157,179,0.1);
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        color: var(--primary-color);
    }
    
    .btn-join {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 12px;
        font-weight: 600;
        width: 100%;
        transition: all 0.3s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-join:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(59,157,179,0.3);
        background: var(--primary-dark);
    }
    
    /* Join Section with Background Image */
    .join-section {
        position: relative;
        padding: 80px 0;
        margin: 60px 0;
        border-radius: 40px;
        overflow: hidden;
    }
    
    .join-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('../images/join-club-bg.jpg');
        background-size: cover;
        background-position: center;
        z-index: 0;
    }
    
    .join-bg::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(59,157,179,0.92), rgba(45,124,143,0.92));
    }
    
    .join-content {
        position: relative;
        z-index: 2;
        text-align: center;
        color: white;
        padding: 0 20px;
    }
    
    .join-content h2 {
        font-size: 42px;
        font-weight: 800;
        margin-bottom: 20px;
    }
    
    .join-content p {
        font-size: 18px;
        max-width: 700px;
        margin: 0 auto 30px;
        opacity: 0.95;
    }
    
    .btn-join-club {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background: white;
        color: var(--primary-color);
        padding: 15px 40px;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .btn-join-club:hover {
        background: var(--accent-color);
        color: var(--dark-color);
        transform: translateY(-5px);
    }
    
    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 24px;
        overflow: hidden;
    }
    
    .modal-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 20px 30px;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .hero-content h1 { font-size: 42px; }
        .section-header h2 { font-size: 36px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .join-content h2 { font-size: 32px; }
    }
    
    @media (max-width: 768px) {
        .hero-content h1 { font-size: 32px; }
        .hero-content p { font-size: 16px; }
        .hero-badge { font-size: 14px; }
        .section-header h2 { font-size: 28px; }
        .clubs-grid { grid-template-columns: 1fr; }
        .filter-bar { border-radius: 20px; flex-direction: column; }
        .search-box { width: 100%; }
        .filter-select { width: 100%; }
        .stats-grid { grid-template-columns: 1fr; }
        .join-section { padding: 50px 0; }
        .join-content h2 { font-size: 28px; }
    }
    
    @media (max-width: 576px) {
        .hero-content h1 { font-size: 28px; }
        .club-name { font-size: 20px; }
        .btn-join-club { width: 100%; justify-content: center; }
    }
    
    /* Animations */
    .animate-on-scroll {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.6s ease;
    }
    
    .animate-on-scroll.animated {
        opacity: 1;
        transform: translateY(0);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 30px;
        grid-column: 1 / -1;
    }
    
    .empty-state-icon {
        width: 100px;
        height: 100px;
        background: var(--light-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 40px;
        color: var(--primary-color);
    }
</style>

<!-- Main Content -->
<main class="main-content">
    <!-- Hero Section with Background Slideshow -->
    <section class="clubs-hero">
        <div class="hero-slideshow">
            <div class="hero-slide hero-slide-1 active"></div>
            <div class="hero-slide hero-slide-2"></div>
            <div class="hero-slide hero-slide-3"></div>
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-dots">
            <div class="hero-dot active" data-slide="0"></div>
            <div class="hero-dot" data-slide="1"></div>
            <div class="hero-dot" data-slide="2"></div>
        </div>
        
        <div class="hero-content">
            <div class="hero-badge animate__animated animate__fadeInDown">
                <i class="fas fa-users"></i> Beyond the Classroom
            </div>
            <h1 class="animate__animated animate__fadeInUp">Clubs & Societies</h1>
            <p class="animate__animated animate__fadeInUp animate__delay-1s">
                Discover your passion, develop new skills, and make lifelong friends through our diverse range of clubs and societies at Muyovozi High School.
            </p>
        </div>
    </section>

    <div class="container-full">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-on-scroll">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $active_clubs; ?></div>
                <div class="stat-label">Active Clubs</div>
            </div>
            <div class="stat-card animate-on-scroll" data-delay="100">
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div class="stat-number"><?php echo $total_members; ?>+</div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-card animate-on-scroll" data-delay="200">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <div class="stat-number"><?php echo $total_achievements; ?></div>
                <div class="stat-label">Achievements</div>
            </div>
            <div class="stat-card animate-on-scroll" data-delay="300">
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                <div class="stat-number">Weekly</div>
                <div class="stat-label">Activities</div>
            </div>
        </div>

        <!-- Section Header -->
        <div class="section-header animate-on-scroll">
            <h2>Explore Our Clubs</h2>
            <p>Find the perfect club that matches your interests and talents</p>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar animate-on-scroll">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search clubs by name or description...">
            </div>
            <select id="filterSelect" class="filter-select">
                <option value="all">All Clubs</option>
                <option value="academic">Academic</option>
                <option value="arts">Arts & Culture</option>
                <option value="sports">Sports</option>
                <option value="technology">Technology</option>
                <option value="community">Community Service</option>
            </select>
        </div>

        <!-- Clubs Grid -->
        <div class="clubs-grid" id="clubsGrid">
            <?php foreach ($clubs as $club): ?>
            <div class="club-card animate-on-scroll" data-category="<?php 
                if (strpos($club['name'], 'Debate') !== false || strpos($club['name'], 'Science') !== false) echo 'academic';
                elseif (strpos($club['name'], 'Arts') !== false || strpos($club['name'], 'Culture') !== false) echo 'arts';
                elseif (strpos($club['name'], 'Sports') !== false) echo 'sports';
                elseif (strpos($club['name'], 'ICT') !== false || strpos($club['name'], 'Coding') !== false) echo 'technology';
                elseif (strpos($club['name'], 'Red Cross') !== false || strpos($club['name'], 'Environmental') !== false) echo 'community';
                else echo 'other';
            ?>">
                <div class="club-image" style="background-image: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('<?php echo $club['image']; ?>'); background-color: <?php echo $club['color']; ?>; background-size: cover; background-position: center;">
                    <div class="club-image-overlay">
                        <span class="club-badge"><i class="fas fa-users me-1"></i> Active</span>
                    </div>
                    <div class="club-icon" style="background: <?php echo $club['color']; ?>; color: white;">
                        <i class="fas <?php echo $club['icon']; ?>"></i>
                    </div>
                </div>
                <div class="club-content">
                    <h3 class="club-name"><?php echo htmlspecialchars($club['name']); ?></h3>
                    <span class="club-motto">"<?php echo htmlspecialchars($club['motto']); ?>"</span>
                    <p class="club-description"><?php echo htmlspecialchars($club['description']); ?></p>
                    
                    <div class="club-details">
                        <span class="detail-item"><i class="fas fa-calendar"></i> <?php echo $club['meeting_day']; ?></span>
                        <span class="detail-item"><i class="fas fa-clock"></i> <?php echo $club['meeting_time']; ?></span>
                        <span class="detail-item"><i class="fas fa-map-marker-alt"></i> <?php echo $club['venue']; ?></span>
                        <span class="detail-item"><i class="fas fa-chalkboard-teacher"></i> <?php echo $club['patron']; ?></span>
                    </div>
                    
                    <?php if (!empty($club['achievements'])): ?>
                    <div class="achievements-list">
                        <h6><i class="fas fa-trophy me-1"></i> Key Achievements</h6>
                        <?php foreach ($club['achievements'] as $achievement): ?>
                            <span class="achievement-tag"><i class="fas fa-award me-1"></i> <?php echo htmlspecialchars($achievement); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="member-count"><i class="fas fa-user-friends"></i> <?php echo $club['member_count']; ?> Members</span>
                    </div>
                    
                    <button class="btn-join" onclick="joinClub(<?php echo $club['id']; ?>, '<?php echo htmlspecialchars($club['name']); ?>')">
                        <i class="fas fa-hand-peace"></i> Join This Club
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Join Section with Background Image -->
        <section class="join-section animate-on-scroll">
            <div class="join-bg"></div>
            <div class="join-content">
                <h2>Ready to Join a Club?</h2>
                <p>Being part of a club helps you develop leadership skills, make new friends, and explore your passions outside the classroom.</p>
                <button class="btn-join-club" onclick="contactForClub()">
                    <i class="fas fa-envelope"></i> Contact Club Coordinator
                </button>
            </div>
        </section>
    </div>
</main>

<!-- Join Club Modal -->
<div class="modal fade" id="joinModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-hand-peace me-2"></i>Join Club</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="joinModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Animation Library -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// Hero Slideshow
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.hero-dot');
    let currentSlide = 0;
    let slideInterval;
    
    function showSlide(index) {
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        currentSlide = index;
    }
    
    function nextSlide() {
        let nextIndex = currentSlide + 1;
        if (nextIndex >= slides.length) nextIndex = 0;
        showSlide(nextIndex);
    }
    
    function startSlideshow() { slideInterval = setInterval(nextSlide, 5000); }
    function stopSlideshow() { if (slideInterval) clearInterval(slideInterval); }
    
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            stopSlideshow();
            showSlide(index);
            startSlideshow();
        });
    });
    
    startSlideshow();
    
    const heroSection = document.querySelector('.clubs-hero');
    if (heroSection) {
        heroSection.addEventListener('mouseenter', stopSlideshow);
        heroSection.addEventListener('mouseleave', startSlideshow);
    }
    
    // Animation on scroll
    const animateElements = document.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('animated');
        });
    }, { threshold: 0.1 });
    
    animateElements.forEach(element => observer.observe(element));
    
    animateElements.forEach(element => {
        const delay = element.getAttribute('data-delay') || 0;
        element.style.transitionDelay = delay + 'ms';
    });
});

// Search and Filter Function
const searchInput = document.getElementById('searchInput');
const filterSelect = document.getElementById('filterSelect');
const clubsGrid = document.getElementById('clubsGrid');
let clubs = <?php echo json_encode($clubs); ?>;

function filterClubs() {
    const searchTerm = searchInput.value.toLowerCase();
    const category = filterSelect.value;
    const cards = document.querySelectorAll('.club-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const name = card.querySelector('.club-name').textContent.toLowerCase();
        const description = card.querySelector('.club-description').textContent.toLowerCase();
        const cardCategory = card.dataset.category;
        
        const matchesSearch = searchTerm === '' || name.includes(searchTerm) || description.includes(searchTerm);
        const matchesCategory = category === 'all' || cardCategory === category;
        
        if (matchesSearch && matchesCategory) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Show empty state if no clubs visible
    const emptyState = document.querySelector('.empty-state');
    if (visibleCount === 0 && !emptyState) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'empty-state';
        emptyDiv.innerHTML = `
            <div class="empty-state-icon"><i class="fas fa-search"></i></div>
            <h3>No Clubs Found</h3>
            <p class="text-muted">Try adjusting your search or filter to find what you're looking for.</p>
        `;
        clubsGrid.appendChild(emptyDiv);
    } else if (visibleCount > 0 && emptyState) {
        emptyState.remove();
    }
}

searchInput.addEventListener('input', filterClubs);
filterSelect.addEventListener('change', filterClubs);

// Join Club Function
function joinClub(clubId, clubName) {
    Swal.fire({
        title: 'Join ' + clubName,
        html: `
            <form id="joinForm">
                <div class="mb-3">
                    <input type="text" class="form-control" placeholder="Your Full Name" id="studentName" required>
                </div>
                <div class="mb-3">
                    <input type="text" class="form-control" placeholder="Class/Form" id="studentClass" required>
                </div>
                <div class="mb-3">
                    <input type="email" class="form-control" placeholder="Email Address" id="studentEmail" required>
                </div>
                <div class="mb-3">
                    <input type="tel" class="form-control" placeholder="Phone Number" id="studentPhone">
                </div>
                <div class="mb-3">
                    <textarea class="form-control" placeholder="Why do you want to join this club? (Optional)" rows="3" id="reason"></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3B9DB3',
        confirmButtonText: 'Submit Application',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const name = document.getElementById('studentName').value;
            const studentClass = document.getElementById('studentClass').value;
            const email = document.getElementById('studentEmail').value;
            
            if (!name || !studentClass || !email) {
                Swal.showValidationMessage('Please fill in all required fields');
                return false;
            }
            return { name: name, class: studentClass, email: email };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Application Submitted!',
                text: `Your application to join ${clubName} has been submitted. The club patron will contact you soon.`,
                confirmButtonColor: '#3B9DB3'
            });
        }
    });
}

function contactForClub() {
    Swal.fire({
        icon: 'info',
        title: 'Club Coordinator',
        text: 'For more information about joining clubs, please visit the Academic Office or contact the Dean of Students.',
        confirmButtonColor: '#3B9DB3'
    });
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include '../controller/footer.php'; ?>