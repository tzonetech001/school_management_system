<?php
// mission.php - School Mission & Vision Page
session_start();
require_once '../controller/db_connect.php';

// Include header
include 'header.php';

// Get current year for strategic plan
$current_year = date('Y');
$next_year = $current_year + 5;

// Statistics for impact display
$stats_sql = "SELECT 
    COUNT(DISTINCT s.id) as total_students,
    COUNT(DISTINCT a.id) as total_staff,
    COUNT(DISTINCT CASE WHEN s.graduation_status = 'Graduated' THEN s.id END) as total_graduates
    FROM students s
    CROSS JOIN admins a";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<style>
    /* Mission Page Styles - FULL WIDTH with Background Images */
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
    
    /* Hero Section with Background Image Slideshow */
    .mission-hero {
        position: relative;
        min-height: 70vh;
        display: flex;
        align-items: center;
        overflow: hidden;
        margin-bottom: 60px;
    }
    
    /* Slideshow Container for Hero Background */
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
        background-image: url('../images/class.png');
        background-size: cover;
        background-position: center;
    }
    
    .hero-slide-2 {
        background-image: url('../images/muyovozi.png');
        background-size: cover;
        background-position: center;
    }
    
    .hero-slide-3 {
        background-image: url('../images/ngao.png');
        background-size: cover;
        background-position: center;
    }
    
    /* Fallback background colors if images don't load */
    .hero-slide-1 { background-color: #1a4d5e; }
    .hero-slide-2 { background-color: #2d7c8f; }
    .hero-slide-3 { background-color: #0f2e38; }
    
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
    
    /* Slideshow Dots */
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
    
    /* Mission & Vision Cards */
    .mv-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
        margin: 50px 0;
    }
    
    .mv-card {
        background: white;
        border-radius: 30px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .mv-card:hover {
        transform: translateY(-15px);
        box-shadow: 0 30px 60px rgba(59,157,179,0.2);
    }
    
    .mv-header {
        padding: 40px;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .mv-header.mission {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    }
    
    .mv-header.vision {
        background: linear-gradient(135deg, #6f42c1, #8a6de9);
    }
    
    .mv-header i {
        font-size: 60px;
        margin-bottom: 20px;
        background: rgba(255,255,255,0.2);
        padding: 20px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .mv-header h3 {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 10px;
    }
    
    .mv-body {
        padding: 40px;
    }
    
    .mv-body p {
        font-size: 18px;
        line-height: 1.8;
        color: #555;
        margin-bottom: 30px;
        font-style: italic;
    }
    
    .mv-stats {
        display: flex;
        justify-content: space-around;
        margin-top: 30px;
        padding-top: 30px;
        border-top: 1px solid rgba(0,0,0,0.05);
    }
    
    .mv-stat-number {
        font-size: 28px;
        font-weight: 800;
        color: var(--primary-color);
        display: block;
    }
    
    .mv-stat-label {
        font-size: 14px;
        color: #999;
        text-transform: uppercase;
    }
    
    /* Core Values Section with Background */
    .values-section {
        background: white;
        border-radius: 40px;
        padding: 60px;
        margin: 50px 0;
        box-shadow: 0 20px 40px rgba(0,0,0,0.05);
    }
    
    .values-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }
    
    .value-card {
        text-align: center;
        padding: 40px 30px;
        background: linear-gradient(135deg, #f8f9fa, #ffffff);
        border-radius: 20px;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
        position: relative;
        overflow: hidden;
    }
    
    .value-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
    }
    
    .value-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(59,157,179,0.15);
    }
    
    .value-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        color: white;
        font-size: 35px;
        transition: all 0.3s ease;
    }
    
    .value-card:hover .value-icon {
        transform: rotateY(360deg);
    }
    
    .value-card h4 {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 15px;
        color: var(--dark-color);
    }
    
    .value-card p {
        color: #666;
        font-size: 15px;
        line-height: 1.6;
    }
    
    /* Educational Philosophy */
    .philosophy-section {
        background: linear-gradient(135deg, #f8f9fa, #ffffff);
        border-radius: 40px;
        padding: 60px;
        margin: 50px 0;
    }
    
    .philosophy-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 40px;
        margin-top: 40px;
    }
    
    .philosophy-card {
        text-align: center;
        padding: 30px;
    }
    
    .philosophy-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, rgba(59,157,179,0.1), rgba(45,124,143,0.1));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        font-size: 40px;
        color: var(--primary-color);
    }
    
    .philosophy-card h4 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 15px;
        color: var(--dark-color);
    }
    
    .philosophy-card p {
        color: #666;
        font-size: 16px;
        line-height: 1.8;
    }
    
    /* Impact Section with Background Image */
    .impact-section {
        position: relative;
        padding: 80px 0;
        margin: 60px 0;
        border-radius: 40px;
        overflow: hidden;
    }
    
    .impact-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('../images/impact-bg.jpg');
        background-size: cover;
        background-position: center;
        z-index: 0;
    }
    
    .impact-bg::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(59,157,179,0.9), rgba(45,124,143,0.9));
    }
    
    .impact-content {
        position: relative;
        z-index: 2;
        color: white;
    }
    
    .impact-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
    }
    
    .impact-item {
        text-align: center;
        padding: 30px;
        background: rgba(255,255,255,0.1);
        border-radius: 20px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        transition: all 0.3s ease;
    }
    
    .impact-item:hover {
        transform: translateY(-10px);
        background: rgba(255,255,255,0.2);
    }
    
    .impact-number {
        font-size: 48px;
        font-weight: 800;
        margin-bottom: 10px;
    }
    
    .impact-label {
        font-size: 18px;
        opacity: 0.9;
    }
    
    .impact-icon {
        font-size: 40px;
        margin-bottom: 20px;
        color: var(--accent-color);
    }
    
    /* Strategic Goals Timeline */
    .goals-section {
        margin: 50px 0;
    }
    
    .goals-timeline {
        position: relative;
        max-width: 1000px;
        margin: 40px auto;
    }
    
    .goals-timeline::before {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, var(--primary-color), var(--accent-color));
        border-radius: 2px;
    }
    
    .goal-item {
        position: relative;
        margin-bottom: 50px;
        width: 50%;
        padding: 0 40px;
    }
    
    .goal-item.left { left: 0; }
    .goal-item.right { left: 50%; }
    
    .goal-item::after {
        content: '';
        position: absolute;
        top: 30px;
        width: 20px;
        height: 20px;
        background: white;
        border: 4px solid var(--primary-color);
        border-radius: 50%;
        z-index: 1;
        transition: all 0.3s ease;
    }
    
    .goal-item.left::after { right: -10px; }
    .goal-item.right::after { left: -10px; }
    
    .goal-content {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }
    
    .goal-content:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(59,157,179,0.15);
    }
    
    .goal-year {
        display: inline-block;
        padding: 5px 20px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border-radius: 30px;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 15px;
    }
    
    .goal-content h4 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 15px;
        color: var(--dark-color);
    }
    
    .goal-progress {
        margin-top: 20px;
    }
    
    .progress-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        font-size: 13px;
    }
    
    .progress-bar-custom {
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        border-radius: 4px;
        transition: width 1s ease;
    }
    
    /* CTA Section */
    .cta-section {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 40px;
        padding: 60px;
        margin: 60px 0;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .cta-section h2 {
        font-size: 36px;
        font-weight: 800;
        margin-bottom: 20px;
    }
    
    .cta-section p {
        font-size: 18px;
        max-width: 600px;
        margin: 0 auto 30px;
        opacity: 0.9;
    }
    
    .btn-cta {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 15px 40px;
        background: white;
        color: var(--primary-color);
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-cta:hover {
        background: var(--accent-color);
        color: var(--dark-color);
        transform: translateY(-5px);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .mv-grid { grid-template-columns: 1fr; }
        .philosophy-grid { grid-template-columns: 1fr; }
        .hero-content h1 { font-size: 42px; }
        
        .goals-timeline::before { left: 30px; }
        .goal-item {
            width: 100%;
            padding-left: 70px;
            padding-right: 20px;
            left: 0 !important;
        }
        .goal-item.left::after, .goal-item.right::after { left: 20px; }
    }
    
    @media (max-width: 768px) {
        .hero-content h1 { font-size: 32px; }
        .hero-content p { font-size: 16px; }
        .section-header h2 { font-size: 28px; }
        .mv-header h3 { font-size: 24px; }
        .mv-body p { font-size: 16px; }
        .values-section, .philosophy-section, .cta-section { padding: 40px 20px; }
        .impact-grid { grid-template-columns: repeat(2, 1fr); }
        .values-grid { grid-template-columns: 1fr; }
    }
    
    @media (max-width: 576px) {
        .hero-content h1 { font-size: 28px; }
        .impact-grid { grid-template-columns: 1fr; }
        .mv-stats { flex-direction: column; gap: 15px; text-align: center; }
        .btn-cta { width: 100%; justify-content: center; }
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
</style>

<!-- Main Content -->
<main class="main-content">
    <!-- Hero Section with Background Slideshow -->
    <section class="mission-hero">
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
                <i class="fas fa-bullseye"></i> Our Guiding Light Since 2014
            </div>
            <h1 class="animate__animated animate__fadeInUp">Mission & Vision</h1>
            <p class="animate__animated animate__fadeInUp animate__delay-1s">
                "Education For Life" - Shaping tomorrow's leaders through excellence, integrity, and holistic development.
            </p>
        </div>
    </section>

    <div class="container-full">
        <!-- Mission & Vision Cards -->
        <div class="mv-grid">
            <div class="mv-card animate-on-scroll">
                <div class="mv-header mission">
                    <i class="fas fa-bullseye"></i>
                    <h3>Our Mission</h3>
                </div>
                <div class="mv-body">
                    <p>
                        "To provide quality, holistic education that nurtures intellectual curiosity, 
                        fosters character development, and empowers students to become responsible, 
                        innovative, and compassionate leaders who positively impact their communities 
                        and the world at large."
                    </p>
                    <div class="mv-stats">
                        <div class="mv-stat-item">
                            <span class="mv-stat-number"><?php echo number_format($stats['total_students'] ?? 1500); ?>+</span>
                            <span class="mv-stat-label">Students Impacted</span>
                        </div>
                        <div class="mv-stat-item">
                            <span class="mv-stat-number"><?php echo date('Y') - 2014; ?></span>
                            <span class="mv-stat-label">Years of Excellence</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mv-card animate-on-scroll" data-delay="100">
                <div class="mv-header vision">
                    <i class="fas fa-eye"></i>
                    <h3>Our Vision</h3>
                </div>
                <div class="mv-body">
                    <p>
                        "To be a center of academic excellence and character development, recognized 
                        nationally and internationally as a model institution that produces well-rounded, 
                        ethical, and globally competitive graduates who drive positive change in society."
                    </p>
                    <div class="mv-stats">
                        <div class="mv-stat-item">
                            <span class="mv-stat-number">5,000+</span>
                            <span class="mv-stat-label">Graduates</span>
                        </div>
                        <div class="mv-stat-item">
                            <span class="mv-stat-number">98%</span>
                            <span class="mv-stat-label">Success Rate</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Core Values -->
        <section class="values-section animate-on-scroll">
            <div class="section-header">
                <h2>Our Core Values</h2>
                <p>The principles that guide everything we do at Muyovozi High School</p>
            </div>
            
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-star"></i></div>
                    <h4>Excellence</h4>
                    <p>Striving for the highest standards in academics, character, and personal achievement.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-handshake"></i></div>
                    <h4>Integrity</h4>
                    <p>Upholding honesty, transparency, and moral principles in all interactions.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-heart"></i></div>
                    <h4>Compassion</h4>
                    <p>Showing empathy, kindness, and respect for others, fostering a caring community.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-lightbulb"></i></div>
                    <h4>Innovation</h4>
                    <p>Embracing creativity, critical thinking, and continuous improvement.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-users"></i></div>
                    <h4>Community</h4>
                    <p>Building strong relationships and working together for the common good.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-balance-scale"></i></div>
                    <h4>Responsibility</h4>
                    <p>Taking ownership of actions and contributing positively to society.</p>
                </div>
            </div>
        </section>

        <!-- Educational Philosophy -->
        <section class="philosophy-section animate-on-scroll">
            <div class="section-header">
                <h2>Our Educational Philosophy</h2>
                <p>The beliefs that shape our approach to education</p>
            </div>
            
            <div class="philosophy-grid">
                <div class="philosophy-card">
                    <div class="philosophy-icon"><i class="fas fa-brain"></i></div>
                    <h4>Holistic Development</h4>
                    <p>We believe in educating the whole person - mind, body, and spirit. Our curriculum balances academic excellence with sports, arts, and character development.</p>
                </div>
                <div class="philosophy-card">
                    <div class="philosophy-icon"><i class="fas fa-seedling"></i></div>
                    <h4>Student-Centered Learning</h4>
                    <p>Every student is unique with different talents and learning styles. We create an environment that nurtures individual potential.</p>
                </div>
                <div class="philosophy-card">
                    <div class="philosophy-icon"><i class="fas fa-globe-africa"></i></div>
                    <h4>Global Citizenship</h4>
                    <p>We prepare students to thrive in an interconnected world, fostering cultural awareness and environmental consciousness.</p>
                </div>
                <div class="philosophy-card">
                    <div class="philosophy-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <h4>Lifelong Learning</h4>
                    <p>Education doesn't end at graduation. We instill a love for learning that continues throughout life.</p>
                </div>
            </div>
        </section>

        <!-- Impact Section with Background Image -->
        <section class="impact-section animate-on-scroll">
            <div class="impact-bg"></div>
            <div class="impact-content">
                <div class="section-header" style="color: white;">
                    <h2 style="color: white;">Our Impact in Numbers</h2>
                    <p style="color: rgba(255,255,255,0.9);">The measurable difference we've made in our community</p>
                </div>
                
                <div class="impact-grid">
                    <div class="impact-item">
                        <div class="impact-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="impact-number"><?php echo date('Y') - 2014; ?></div>
                        <div class="impact-label">Years of Excellence</div>
                    </div>
                    <div class="impact-item">
                        <div class="impact-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="impact-number">5,000+</div>
                        <div class="impact-label">Graduates</div>
                    </div>
                    <div class="impact-item">
                        <div class="impact-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="impact-number"><?php echo number_format($stats['total_staff'] ?? 85); ?>+</div>
                        <div class="impact-label">Qualified Teachers</div>
                    </div>
                    <div class="impact-item">
                        <div class="impact-icon"><i class="fas fa-trophy"></i></div>
                        <div class="impact-number">98%</div>
                        <div class="impact-label">Pass Rate</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Strategic Goals -->
        <section class="goals-section">
            <div class="section-header">
                <h2>Strategic Goals (<?php echo $current_year; ?> - <?php echo $next_year; ?>)</h2>
                <p>Our roadmap for continuous improvement and excellence</p>
            </div>
            
            <div class="goals-timeline">
                <div class="goal-item left animate-on-scroll">
                    <div class="goal-content">
                        <span class="goal-year">Year 1-2</span>
                        <h4>Academic Excellence Enhancement</h4>
                        <p>Implement advanced teaching methodologies and modern learning resources to improve pass rate to 99%.</p>
                        <div class="goal-progress">
                            <div class="progress-label"><span>Implementation</span><span>45%</span></div>
                            <div class="progress-bar-custom"><div class="progress-fill" style="width: 45%"></div></div>
                        </div>
                    </div>
                </div>
                
                <div class="goal-item right animate-on-scroll" data-delay="100">
                    <div class="goal-content">
                        <span class="goal-year">Year 2-3</span>
                        <h4>Infrastructure Modernization</h4>
                        <p>Construction of new arts block, library expansion, and digital learning centers.</p>
                        <div class="goal-progress">
                            <div class="progress-label"><span>Progress</span><span>30%</span></div>
                            <div class="progress-bar-custom"><div class="progress-fill" style="width: 30%"></div></div>
                        </div>
                    </div>
                </div>
                
                <div class="goal-item left animate-on-scroll" data-delay="200">
                    <div class="goal-content">
                        <span class="goal-year">Year 3-4</span>
                        <h4>Teacher Professional Development</h4>
                        <p>Advanced training programs and workshop opportunities for all teaching staff.</p>
                        <div class="goal-progress">
                            <div class="progress-label"><span>Completed</span><span>60%</span></div>
                            <div class="progress-bar-custom"><div class="progress-fill" style="width: 60%"></div></div>
                        </div>
                    </div>
                </div>
                
                <div class="goal-item right animate-on-scroll" data-delay="300">
                    <div class="goal-content">
                        <span class="goal-year">Year 4-5</span>
                        <h4>Student Support Services</h4>
                        <p>Enhanced counseling services, career guidance, and mentorship programs.</p>
                        <div class="goal-progress">
                            <div class="progress-label"><span>In Progress</span><span>25%</span></div>
                            <div class="progress-bar-custom"><div class="progress-fill" style="width: 25%"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section animate-on-scroll">
            <h2>Join Us in Our Mission</h2>
            <p>Be part of a community that is shaping the future of Tanzania through quality education.</p>
            <a href="contact.php" class="btn-cta"><i class="fas fa-envelope"></i> Contact Admissions</a>
        </section>
    </div>
</main>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

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
    
    const heroSection = document.querySelector('.mission-hero');
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

function scrollToTop() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
</script>

<?php include '../controller/footer.php'; ?>