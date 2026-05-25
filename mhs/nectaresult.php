<?php
// nectaresult.php - NECTA Results Portal for Muyovozi High School
session_start();
require_once '../controller/db_connect.php';

// Include header
include 'header.php';

// Get current year for dynamic display
$current_year = date('Y');
?>

<style>
    /* NECTA Results Page Specific Styles */
    :root {
        --primary-color: #3B9DB3;
        --primary-dark: #2d7c8f;
        --primary-light: #8bc5d6;
        --accent-color: #ffc107;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --info-color: #17a2b8;
        --dark-color: #2c3e50;
        --light-color: #f8f9fa;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }

    .main-content {
        padding: 30px 0 60px;
        position: relative;
        min-height: calc(100vh - 200px);
    }

    /* Hero Section */
    .necta-hero {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        padding: 60px 0;
        margin-bottom: 50px;
        border-radius: 0 0 50px 50px;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .necta-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 50px 50px;
        transform: rotate(45deg);
        animation: moveBackground 30s linear infinite;
    }

    @keyframes moveBackground {
        0% { transform: rotate(45deg) translate(0, 0); }
        100% { transform: rotate(45deg) translate(50px, 50px); }
    }

    .hero-content {
        position: relative;
        z-index: 1;
        text-align: center;
    }

    .hero-badge {
        background: rgba(255,255,255,0.2);
        padding: 12px 30px;
        border-radius: 50px;
        display: inline-block;
        margin-bottom: 30px;
        border: 1px solid rgba(255,255,255,0.3);
        backdrop-filter: blur(5px);
        font-size: 18px;
        font-weight: 600;
    }

    .hero-badge i {
        margin-right: 10px;
        color: var(--accent-color);
    }

    .hero-content h1 {
        font-size: 56px;
        font-weight: 800;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        letter-spacing: 2px;
    }

    .hero-content p {
        font-size: 20px;
        max-width: 800px;
        margin: 0 auto;
        opacity: 0.95;
    }

    /* Results Container */
    .results-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }

    /* Level Cards */
    .level-cards {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
        margin-bottom: 40px;
    }

    .level-card {
        background: white;
        border-radius: 30px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        transition: all 0.4s ease;
        position: relative;
        cursor: pointer;
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
    }

    .level-card:hover {
        transform: translateY(-15px) scale(1.02);
        box-shadow: 0 30px 60px rgba(0,0,0,0.3);
    }

    .level-card.advanced {
        background: linear-gradient(145deg, #1e3c72, #2a5298);
    }

    .level-card.ordinary {
        background: linear-gradient(145deg, #11998e, #38ef7d);
    }

    .card-header {
        padding: 40px 30px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .card-header::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 1px, transparent 1px);
        background-size: 20px 20px;
        opacity: 0.3;
        pointer-events: none;
    }

    .level-icon {
        width: 120px;
        height: 120px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        font-size: 50px;
        color: white;
        border: 4px solid rgba(255,255,255,0.3);
        transition: all 0.3s ease;
    }

    .level-card:hover .level-icon {
        transform: rotate(360deg);
        background: rgba(255,255,255,0.3);
        border-color: white;
    }

    .level-title {
        font-size: 36px;
        font-weight: 800;
        color: white;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .level-subtitle {
        font-size: 18px;
        color: rgba(255,255,255,0.9);
        margin-bottom: 20px;
    }

    .card-body {
        padding: 30px;
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
    }

    .level-features {
        list-style: none;
        padding: 0;
        margin: 0 0 25px;
    }

    .level-features li {
        color: white;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
    }

    .level-features li i {
        color: var(--accent-color);
        width: 25px;
        font-size: 18px;
    }

    .btn-view-results {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        width: 100%;
        padding: 18px;
        border: none;
        border-radius: 50px;
        font-size: 20px;
        font-weight: 700;
        color: white;
        background: rgba(255,255,255,0.2);
        border: 2px solid rgba(255,255,255,0.3);
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        position: relative;
        overflow: hidden;
    }

    .btn-view-results::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn-view-results:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn-view-results:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }

    .btn-view-results i {
        font-size: 24px;
        transition: transform 0.3s ease;
    }

    .btn-view-results:hover i {
        transform: translateX(10px);
    }

    /* Results Viewer Section */
    .results-viewer-section {
        background: white;
        border-radius: 30px;
        padding: 40px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        margin-top: 40px;
        display: none;
        animation: slideUp 0.5s ease;
        text-align: center;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .results-viewer-section.active {
        display: block;
    }

    .viewer-header {
        text-align: center;
        margin-bottom: 40px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--primary-color);
    }

    .viewer-icon-large {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 50px;
        color: white;
        margin: 0 auto 25px;
        box-shadow: 0 10px 30px rgba(59,157,179,0.3);
    }

    .viewer-header h2 {
        font-size: 36px;
        font-weight: 700;
        color: var(--dark-color);
        margin-bottom: 10px;
    }

    .viewer-header p {
        color: #6c757d;
        font-size: 18px;
        max-width: 600px;
        margin: 0 auto;
    }

    /* Year Grid */
    .years-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 40px 0;
        max-width: 1000px;
        margin-left: auto;
        margin-right: auto;
    }

    .year-card {
        background: linear-gradient(145deg, #f8f9fa, #e9ecef);
        border-radius: 20px;
        padding: 25px;
        text-decoration: none;
        transition: all 0.3s ease;
        display: block;
        border: 2px solid transparent;
    }

    .year-card:hover {
        transform: translateY(-10px);
        border-color: var(--primary-color);
        box-shadow: 0 15px 40px rgba(59,157,179,0.2);
        background: white;
    }

    .year-number {
        font-size: 48px;
        font-weight: 800;
        color: var(--primary-color);
        margin-bottom: 10px;
    }

    .year-label {
        color: var(--dark-color);
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .btn-open-year {
        display: inline-block;
        padding: 8px 25px;
        background: var(--primary-color);
        color: white;
        border-radius: 50px;
        font-size: 14px;
        transition: all 0.3s ease;
        border: none;
    }

    .year-card:hover .btn-open-year {
        background: var(--primary-dark);
        transform: scale(1.05);
    }

    /* Direct Access Card */
    .direct-access-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 30px;
        padding: 40px;
        color: white;
        margin: 40px 0;
        position: relative;
        overflow: hidden;
    }

    .direct-access-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 30px 30px;
        opacity: 0.3;
    }

    .direct-access-content {
        position: relative;
        z-index: 1;
    }

    .direct-access-icon {
        font-size: 60px;
        margin-bottom: 20px;
    }

    .direct-access-title {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 15px;
    }

    .direct-access-text {
        font-size: 18px;
        opacity: 0.9;
        margin-bottom: 30px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .btn-direct-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        padding: 18px 45px;
        background: white;
        color: var(--primary-color);
        text-decoration: none;
        border-radius: 50px;
        font-weight: 700;
        font-size: 20px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }

    .btn-direct-link:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        background: var(--primary-color);
        color: white;
        border-color: white;
    }

    .btn-direct-link i {
        transition: transform 0.3s ease;
    }

    .btn-direct-link:hover i {
        transform: translateX(10px);
    }

    /* Quick Tips */
    .quick-tips {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 30px;
        margin-top: 30px;
        color: white;
    }

    .tips-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .tip-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: rgba(255,255,255,0.1);
        border-radius: 15px;
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .tip-icon {
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .tip-text {
        flex: 1;
        text-align: left;
    }

    .tip-text h6 {
        font-weight: 600;
        margin-bottom: 3px;
    }

    .tip-text p {
        font-size: 13px;
        opacity: 0.9;
        margin: 0;
    }

    /* Back Button */
    .btn-back {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 55px;
        height: 55px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        cursor: pointer;
        transition: all 0.3s ease;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        z-index: 1000;
    }

    .btn-back:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }

    .btn-back.show {
        display: flex;
        animation: bounce 2s infinite;
    }

    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
        40% {transform: translateY(-10px);}
        60% {transform: translateY(-5px);}
    }

    /* Statistics Section */
    .stats-section {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin: 40px 0;
    }

    .stat-item {
        background: white;
        border-radius: 20px;
        padding: 25px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .stat-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }

    .stat-number {
        font-size: 36px;
        font-weight: 800;
        color: var(--primary-color);
        margin-bottom: 5px;
    }

    .stat-label {
        color: #6c757d;
        font-size: 14px;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        background: rgba(59,157,179,0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 24px;
        color: var(--primary-color);
    }

    /* Search Tips */
    .search-tips {
        background: #f8f9fa;
        border-radius: 20px;
        padding: 30px;
        margin: 30px 0;
    }

    .search-tip-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border-bottom: 1px solid #dee2e6;
    }

    .search-tip-item:last-child {
        border-bottom: none;
    }

    .search-tip-icon {
        width: 50px;
        height: 50px;
        background: var(--primary-color);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }

    .search-tip-content {
        flex: 1;
        text-align: left;
    }

    .search-tip-content h6 {
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--dark-color);
    }

    .search-tip-content p {
        color: #6c757d;
        margin: 0;
        font-size: 14px;
    }

    .highlight {
        background: var(--accent-color);
        color: var(--dark-color);
        padding: 2px 8px;
        border-radius: 5px;
        font-weight: 600;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .level-cards {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .stats-section {
            grid-template-columns: repeat(2, 1fr);
        }

        .hero-content h1 {
            font-size: 42px;
        }

        .years-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .hero-content h1 {
            font-size: 32px;
        }

        .level-title {
            font-size: 28px;
        }

        .viewer-header h2 {
            font-size: 28px;
        }

        .years-grid {
            grid-template-columns: 1fr;
        }

        .tips-grid {
            grid-template-columns: 1fr;
        }

        .btn-back {
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            font-size: 18px;
        }

        .direct-access-title {
            font-size: 22px;
        }

        .btn-direct-link {
            padding: 15px 30px;
            font-size: 18px;
        }
    }

    @media (max-width: 576px) {
        .stats-section {
            grid-template-columns: 1fr;
        }

        .level-icon {
            width: 80px;
            height: 80px;
            font-size: 35px;
        }

        .viewer-icon-large {
            width: 70px;
            height: 70px;
            font-size: 35px;
        }
    }
</style>

<!-- Main Content -->
<main class="main-content">
    <!-- Hero Section -->
    <section class="necta-hero">
        <div class="container">
            <div class="hero-content">
                <span class="hero-badge animate__animated animate__fadeInDown">
                    <i class="fas fa-chart-line"></i>
                    NECTA Results Portal <?php echo $current_year; ?>
                </span>
                <h1 class="animate__animated animate__fadeInUp">National Examinations Results</h1>
                <p class="animate__animated animate__fadeInUp animate__delay-1s">
                    Access and view NECTA examination results for both Advanced and Ordinary Level
                </p>
            </div>
        </div>
    </section>

    <div class="results-container">
        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number">15,000+</div>
                <div class="stat-label">Students Accessed</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?php echo $current_year; ?></div>
                <div class="stat-label">Current Year</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-number">98%</div>
                <div class="stat-label">Pass Rate</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number">24/7</div>
                <div class="stat-label">Availability</div>
            </div>
        </div>

        <!-- Level Selection Cards -->
        <div class="level-cards">
            <!-- Advanced Level (ACSEE) Card -->
            <div class="level-card advanced" onclick="showResults('advanced')">
                <div class="card-header">
                    <div class="level-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h2 class="level-title">ACSEE</h2>
                    <p class="level-subtitle">Advanced Certificate of Secondary Education</p>
                </div>
                <div class="card-body">
                    <ul class="level-features">
                        <li><i class="fas fa-check-circle"></i> Form 5 & 6 Results</li>
                        <li><i class="fas fa-check-circle"></i> Advanced Level Examinations</li>
                        <li><i class="fas fa-check-circle"></i> University Entry Qualifications</li>
                        <li><i class="fas fa-check-circle"></i> Subject Combinations</li>
                    </ul>
                    <button class="btn-view-results">
                        <span>View A-Level Results</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Ordinary Level (CSEE) Card -->
            <div class="level-card ordinary" onclick="showResults('ordinary')">
                <div class="card-header">
                    <div class="level-icon">
                        <i class="fas fa-school"></i>
                    </div>
                    <h2 class="level-title">CSEE</h2>
                    <p class="level-subtitle">Certificate of Secondary Education Examination</p>
                </div>
                <div class="card-body">
                    <ul class="level-features">
                        <li><i class="fas fa-check-circle"></i> Form 4 Results</li>
                        <li><i class="fas fa-check-circle"></i> Ordinary Level Examinations</li>
                        <li><i class="fas fa-check-circle"></i> Secondary Education Completion</li>
                        <li><i class="fas fa-check-circle"></i> All Subjects Available</li>
                    </ul>
                    <button class="btn-view-results">
                        <span>View O-Level Results</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Results Viewer Section - Advanced Level -->
        <div class="results-viewer-section" id="advancedViewer">
            <div class="viewer-header">
                <div class="viewer-icon-large">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2>ACSEE Results - Advanced Level</h2>
                <p>Select year to view Advanced Level examination results</p>
            </div>

            <!-- Direct Access Card -->
            <div class="direct-access-card">
                <div class="direct-access-content">
                    <div class="direct-access-icon">
                        <i class="fas fa-external-link-alt"></i>
                    </div>
                    <h3 class="direct-access-title">Open NECTA Advanced Level Portal</h3>
                    <p class="direct-access-text">
                        Access all Advanced Level results directly on the official NECTA website
                    </p>
                    <a href="https://www.necta.go.tz/results/view/acsee" 
                       class="btn-direct-link"
                       target="_blank"
                       rel="noopener noreferrer">
                        <span>Open Advanced Level Portal</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Year Grid -->
            <h4 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Quick Access by Year</h4>
            <div class="years-grid">
                <?php
                $current_year = date('Y');
                $years = range($current_year, $current_year - 3);
                
                foreach ($years as $year) {
                    echo '
                    <a href="https://onlinesys.necta.go.tz/results/'.$year.'/acsee/index.htm" 
                       class="year-card"
                       target="_blank"
                       rel="noopener noreferrer">
                        <div class="year-number">'.$year.'</div>
                        <div class="year-label">Advanced Level</div>
                        <span class="btn-open-year">
                            <i class="fas fa-external-link-alt me-2"></i>View
                        </span>
                    </a>
                    ';
                }
                ?>
            </div>

            <!-- Search Tips -->
            <div class="search-tips">
                <h5 class="mb-4"><i class="fas fa-lightbulb me-2"></i>How to Find Muyovozi High School Results</h5>
                <div class="search-tip-item">
                    <div class="search-tip-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="search-tip-content">
                        <h6>Search by School Name</h6>
                        <p>Type <span class="highlight">"MUYOVOZI"</span> in the search box on NECTA website to filter our school results</p>
                    </div>
                </div>
                <div class="search-tip-item">
                    <div class="search-tip-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="search-tip-content">
                        <h6>Select Examination Year</h6>
                        <p>Choose the appropriate year from the dropdown menu before searching</p>
                    </div>
                </div>
                <div class="search-tip-item">
                    <div class="search-tip-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="search-tip-content">
                        <h6>Save or Print Results</h6>
                        <p>Use your browser's print function (Ctrl+P) to save results as PDF or print them</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="quick-tips">
                <h5><i class="fas fa-lightbulb me-2"></i>Quick Tips for Advanced Level Results</h5>
                <div class="tips-grid">
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-search"></i></div>
                        <div class="tip-text">
                            <h6>Search by School</h6>
                            <p>Enter "MUYOVOZI" to filter our school results</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-calendar"></i></div>
                        <div class="tip-text">
                            <h6>Select Year</h6>
                            <p>Choose the examination year from dropdown</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-print"></i></div>
                        <div class="tip-text">
                            <h6>Print Results</h6>
                            <p>Use print option to save or share results</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Viewer Section - Ordinary Level -->
        <div class="results-viewer-section" id="ordinaryViewer">
            <div class="viewer-header">
                <div class="viewer-icon-large">
                    <i class="fas fa-school"></i>
                </div>
                <h2>CSEE Results - Ordinary Level</h2>
                <p>Select year to view Ordinary Level examination results</p>
            </div>

            <!-- Direct Access Card -->
            <div class="direct-access-card">
                <div class="direct-access-content">
                    <div class="direct-access-icon">
                        <i class="fas fa-external-link-alt"></i>
                    </div>
                    <h3 class="direct-access-title">Open NECTA Ordinary Level Portal</h3>
                    <p class="direct-access-text">
                        Access all Ordinary Level results directly on the official NECTA website
                    </p>
                    <a href="https://www.necta.go.tz/results/view/csee" 
                       class="btn-direct-link"
                       target="_blank"
                       rel="noopener noreferrer">
                        <span>Open Ordinary Level Portal</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Year Grid -->
            <h4 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Quick Access by Year</h4>
            <div class="years-grid">
                <?php
                foreach ($years as $year) {
                    echo '
                    <a href="https://onlinesys.necta.go.tz/results/'.$year.'/csee/index.htm" 
                       class="year-card"
                       target="_blank"
                       rel="noopener noreferrer">
                        <div class="year-number">'.$year.'</div>
                        <div class="year-label">Ordinary Level</div>
                        <span class="btn-open-year">
                            <i class="fas fa-external-link-alt me-2"></i>View
                        </span>
                    </a>
                    ';
                }
                ?>
            </div>

            <!-- Search Tips -->
            <div class="search-tips">
                <h5 class="mb-4"><i class="fas fa-lightbulb me-2"></i>How to Find Muyovozi High School Results</h5>
                <div class="search-tip-item">
                    <div class="search-tip-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="search-tip-content">
                        <h6>Search by School Name</h6>
                        <p>Type <span class="highlight">"MUYOVOZI"</span> in the search box on NECTA website to filter our school results</p>
                    </div>
                </div>
                <div class="search-tip-item">
                    <div class="search-tip-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="search-tip-content">
                        <h6>Select Examination Year</h6>
                        <p>Choose the appropriate year from the dropdown menu before searching</p>
                    </div>
                </div>
                <div class="search-tip-item">
                    <div class="search-tip-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="search-tip-content">
                        <h6>Save or Print Results</h6>
                        <p>Use your browser's print function (Ctrl+P) to save results as PDF or print them</p>
                    </div>
                </div>
                <div class="search-tip-item">
                    <div class="search-tip-icon">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <div class="search-tip-content">
                        <h6>Share Results</h6>
                        <p>Share the direct link to specific year results with others</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="quick-tips">
                <h5><i class="fas fa-lightbulb me-2"></i>Quick Tips for Ordinary Level Results</h5>
                <div class="tips-grid">
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-search"></i></div>
                        <div class="tip-text">
                            <h6>Search by School</h6>
                            <p>Type "MUYOVOZI" to find our school results</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-download"></i></div>
                        <div class="tip-text">
                            <h6>Download Results</h6>
                            <p>Save results as PDF for your records</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-share-alt"></i></div>
                        <div class="tip-text">
                            <h6>Share Results</h6>
                            <p>Share directly via email or social media</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="alert alert-info mt-4" style="border-radius: 15px; border-left: 5px solid var(--primary-color);">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle fa-2x me-3" style="color: var(--primary-color);"></i>
                <div>
                    <h5 class="fw-bold mb-2">Important Notice</h5>
                    <p class="mb-0">
                        Results are sourced directly from the National Examinations Council of Tanzania (NECTA). 
                        For official verification, please visit <a href="https://www.necta.go.tz" target="_blank" class="fw-bold">www.necta.go.tz</a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Browser Compatibility Notice -->
        <div class="alert alert-warning mt-3" style="border-radius: 15px;">
            <div class="d-flex">
                <i class="fas fa-exclamation-triangle me-3 mt-1"></i>
                <div>
                    <strong>Note:</strong> The NECTA website opens in a new tab. Make sure your browser allows pop-ups for this site.
                    If the page doesn't open, you can copy and paste this link: 
                    <br><code class="d-inline-block mt-2 p-2 bg-light rounded">https://www.necta.go.tz/results/</code>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="btn-back" id="backToTop" onclick="scrollToTop()" title="Back to Top">
        <i class="fas fa-arrow-up"></i>
    </button>
</main>

<script>
    // Show selected results viewer
    function showResults(level) {
        // Hide both viewers first
        document.getElementById('advancedViewer').classList.remove('active');
        document.getElementById('ordinaryViewer').classList.remove('active');
        
        // Show selected viewer
        const viewer = document.getElementById(level + 'Viewer');
        viewer.classList.add('active');
        
        // Show back button
        document.getElementById('backToTop').classList.add('show');
        
        // Smooth scroll to viewer
        viewer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Scroll to top function
    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Show/hide back to top button based on scroll position
    window.addEventListener('scroll', function() {
        const backButton = document.getElementById('backToTop');
        if (window.pageYOffset > 300) {
            backButton.classList.add('show');
        } else {
            backButton.classList.remove('show');
        }
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check if URL has hash to open specific viewer
        if (window.location.hash === '#advanced') {
            showResults('advanced');
        } else if (window.location.hash === '#ordinary') {
            showResults('ordinary');
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Alt + A for Advanced Level
        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            showResults('advanced');
        }
        
        // Alt + O for Ordinary Level
        if (e.altKey && e.key === 'o') {
            e.preventDefault();
            showResults('ordinary');
        }
        
        // Escape to close fullscreen (if any element is in fullscreen)
        if (e.key === 'Escape' && document.fullscreenElement) {
            document.exitFullscreen();
        }
    });

    // Track outbound links (optional - for analytics)
    document.querySelectorAll('a[target="_blank"]').forEach(link => {
        link.addEventListener('click', function(e) {
            const url = this.href;
            const level = this.href.includes('acsee') ? 'advanced' : 'ordinary';
            console.log(`Opening ${level} level results: ${url}`);
            // You can add Google Analytics tracking here if needed
        });
    });

    // Add loading state to buttons when clicked
    document.querySelectorAll('.btn-direct-link, .year-card').forEach(button => {
        button.addEventListener('click', function(e) {
            // Only add loading state if it's not opening in new tab (for same target)
            if (!this.target || this.target !== '_blank') {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                
                // Restore original text after a delay (in case page doesn't unload immediately)
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 2000);
            }
        });
    });

    // Warn users before leaving the site
    let warningShown = false;
    document.querySelectorAll('a[target="_blank"]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!warningShown) {
                // Show subtle notification (optional)
                const toast = document.createElement('div');
                toast.className = 'position-fixed bottom-0 end-0 p-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header">
                            <i class="fas fa-external-link-alt me-2"></i>
                            <strong class="me-auto">Opening NECTA Website</strong>
                            <button type="button" class="btn-close" onclick="this.closest('.toast').remove()"></button>
                        </div>
                        <div class="toast-body">
                            You are being redirected to the official NECTA website.
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                
                // Remove toast after 3 seconds
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 3000);
                
                warningShown = true;
            }
        });
    });

    // Copy URL to clipboard functionality
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            const alert = document.createElement('div');
            alert.className = 'alert alert-success position-fixed top-50 start-50 translate-middle';
            alert.style.zIndex = '9999';
            alert.innerHTML = '<i class="fas fa-check-circle me-2"></i>Link copied to clipboard!';
            document.body.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 2000);
        }).catch(function() {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            alert('Link copied to clipboard!');
        });
    }

    // Add click event to code blocks for easy copying
    document.querySelectorAll('code').forEach(code => {
        code.style.cursor = 'pointer';
        code.title = 'Click to copy';
        code.addEventListener('click', function() {
            copyToClipboard(this.textContent);
        });
    });

    // Prefetch NECTA website when user hovers over links (optional)
    document.querySelectorAll('a[href*="necta.go.tz"]').forEach(link => {
        link.addEventListener('mouseenter', function() {
            const prefetch = document.createElement('link');
            prefetch.rel = 'prefetch';
            prefetch.href = this.href;
            document.head.appendChild(prefetch);
        });
    });
</script>

<!-- Add Animate.css for animations -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<!-- Add Font Awesome if not already included -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>

<?php
// Include footer
include 'footer.php';
?>