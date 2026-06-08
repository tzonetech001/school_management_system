<?php
include 'header.php';

// Session start to track visit count
if (session_status() === PHP_SESSION_NONE) {
  
}

// Initialize visit counter if not exists
if (!isset($_SESSION['visit_count'])) {
    $_SESSION['visit_count'] = 1;
} else {
    $_SESSION['visit_count']++;
}

// Determine load time based on visit count
$visitCount = $_SESSION['visit_count'];
$isRefresh = isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'max-age=0';

if ($visitCount == 1 && !$isRefresh) {
    // First time load - 5 seconds
    $loadTime = 5000;
} elseif ($visitCount == 2 && !$isRefresh) {
    // Second time load - 3 seconds
    $loadTime = 3000;
} elseif ($isRefresh || $visitCount >= 3) {
    // Page refresh - 1 second
    $loadTime = 1000;
} else {
    // Default - 0.5 seconds
    $loadTime = 500;
}

// Store load time in session for consistency
$_SESSION['last_load_time'] = $loadTime;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="mhs/css/main.css">

    <!-- Favicon Icons -->
    <link rel="icon" type="image/x-icon" href="favicon.ico"> <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png"> <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png"> <link rel="icon" type="image/png" sizes="48x48" href="favicon.png"> <link rel="icon" type="image/png" sizes="96x96" href="favicon-96x96.png"> <link rel="icon" type="image/png" sizes="192x192" href="android-chrome-192x192.png">
    
    <style> /* Loader Styles */ .page-loader {position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #f5f5f5; display: flex; justify-content: center; align-items: center; z-index: 9999; transition: opacity 0.5s ease, visibility 0.5s ease; } .page-loader.hide {opacity: 0; visibility: hidden; } .loader {display: flex; gap: 6px; align-items: flex-end; } .loader div {width: 10px; background: #007bff; animation: load 1s infinite ease-in-out; } .loader div:nth-child(1) { height: 10px; animation-delay: 0s; } .loader div:nth-child(2) { height: 20px; animation-delay: 0.1s; } .loader div:nth-child(3) { height: 30px; animation-delay: 0.2s; } .loader div:nth-child(4) { height: 40px; animation-delay: 0.3s; } .loader div:nth-child(5) { height: 30px; animation-delay: 0.4s; } .loader div:nth-child(6) { height: 20px; animation-delay: 0.5s; } @keyframes load {0%, 100% { transform: scaleY(1); } 50% { transform: scaleY(2); } } /* Main content initially hidden */ .home-main {opacity: 0; transition: opacity 0.5s ease; } .home-main.visible {opacity: 1; } /* Loading text indicator */ .loader-text {margin-top: 20px; font-family: 'Poppins', sans-serif; font-size: 14px; color: #666; text-align: center; } .loader-container {text-align: center; } </style>
</head>
<body>

<!-- Loader -->
<div class="page-loader" id="pageLoader">
    <div class="loader-container">
        <div class="loader">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
        <div class="loader-text" id="loaderText">Loading...</div>
    </div>
</div>

<main class="home-main" id="mainContent">
    
    <!-- HERO SECTION -->
    <section class="home-hero">
        <div class="hero-bg-blur" style="background-image: url('images/image5.png');"></div>
        
        <div class="slideshow-container">
            <div class="slide active" style="background-image: url('images/image1.png');"></div>
            <div class="slide" style="background-image: url('images/image2.png');"></div>
            <div class="slide" style="background-image: url('images/image6.png');"></div>
        </div>
        
        <div class="hero-overlay"></div>
        
        <div class="slideshow-dots">
            <div class="dot active" data-slide="0"></div>
            <div class="dot" data-slide="1"></div>
            <div class="dot" data-slide="2"></div>
        </div>
        
        <div class="container">
            <div class="row">
                <div class="col-lg-7">
                    <div class="hero-content">
                        <span class="hero-badge">
                            <i class="fas fa-graduation-cap"></i> Excellence Since 2013
                        </span>
                        <h1>Welcome to School Management system</h1>
                        <p class="lead">"Education For Life" — Nurturing minds, building character, and shaping the leaders of tomorrow.</p>
                        
                        <div class="hero-stats">
                            <div class="hero-stat-item">
                                <span class="hero-stat-number">12+</span>
                                <span class="hero-stat-label">Years</span>
                            </div>
                            <div class="hero-stat-item">
                                <span class="hero-stat-number">1200+</span>
                                <span class="hero-stat-label">Students</span>
                            </div>
                            <div class="hero-stat-item">
                                <span class="hero-stat-number">98%</span>
                                <span class="hero-stat-label">Pass Rate</span>
                            </div>
                        </div>
                        
                        <div class="hero-buttons">
                            <a href="mhs/about.php" class="btn-hero btn-hero-primary">Learn More <i class="fas fa-arrow-right"></i></a>
                            <a href="mhs/contact.php" class="btn-hero btn-hero-outline">Contact Us</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Features Section -->
        <section class="features-section">
            <div class="section-header">
                <h2>A-LEVEL ADMINISTRATIVE SYSTEM</h2>
                <p>Discover what makes our school a center of academic excellence</p>
            </div>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h4>Expert Teachers</h4>
                    <p>Qualified and dedicated teachers committed to student success.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-flask"></i></div>
                    <h4>Modern Facilities</h4>
                    <p>Well-equipped labs, library, and sports facilities.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-users"></i></div>
                    <h4>Boarding Life</h4>
                    <p>Safe boarding with 24/7 security and dedicated matrons.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-trophy"></i></div>
                    <h4>Academic Excellence</h4>
                    <p>High pass rates in national examinations.</p>
                </div>
            </div>
        </section>
        
        <!-- SEO Text Content -->
        <div class="seo-content-section">
            <h2>School Management system | Advanced Level Secondary School Tanzania</h2>
            <p>School Management system is a premier advanced level secondary school located in Kasulu District, Kigoma Region, Tanzania. We take pride in being one of the leading advanced level schools in Kigoma with outstanding NECTA results.</p>
            
            
            <ul>
                <li><strong>Excellent NECTA Results</strong> - Consistently top performance in national examinations</li>
                <li><strong>Qualified & Experienced Teachers</strong> - All are certified and experienced in advanced level education</li>
                <li><strong>Safe Learning Environment</strong> - Boarding facilities with 24/7 security</li>
                <li><strong>Sports & Extracurricular Activities</strong> - Various clubs for student development</li>
                <li><strong>Modern Library & Facilities</strong> - Supporting students to achieve their goals</li>
            </ul>
        </div>
    </div>
</main>

<!-- Slideshow JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get load time from PHP
    const loadTime = <?php echo $loadTime; ?>;
    const visitCount = <?php echo $visitCount; ?>;
    const isRefresh = <?php echo $isRefresh ? 'true' : 'false'; ?>;
    
    // Update loader text based on visit type
    const loaderTextEl = document.getElementById('loaderText');
    if (loaderTextEl) {
        if (visitCount === 1 && !isRefresh) {
            loaderTextEl.innerHTML = 'School Management system...';
        } else if (visitCount === 2 && !isRefresh) {
            loaderTextEl.innerHTML = 'Welcome back! Loading...';
        } else if (isRefresh) {
            loaderTextEl.innerHTML = 'Refreshing page...';
        } else {
            loaderTextEl.innerHTML = 'Loading...';
        }
    }
    
    const loader = document.getElementById('pageLoader');
    const mainContent = document.getElementById('mainContent');
    
    // Hide loader after dynamic load time and show content
    setTimeout(function() {
        if (loader) {
            loader.classList.add('hide');
        }
        if (mainContent) {
            mainContent.classList.add('visible');
        }
    }, loadTime);
    
    // Slideshow functionality
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    let currentSlide = 0;
    let slideInterval;
    
    function showSlide(index) {
        if (!slides.length) return;
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
    
    function startSlideshow() {
        slideInterval = setInterval(nextSlide, 5000);
    }
    
    function stopSlideshow() {
        if (slideInterval) clearInterval(slideInterval);
    }
    
    if (dots.length) {
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                stopSlideshow();
                showSlide(index);
                startSlideshow();
            });
        });
    }
    
    startSlideshow();
    
    const heroSection = document.querySelector('.home-hero');
    if (heroSection) {
        heroSection.addEventListener('mouseenter', stopSlideshow);
        heroSection.addEventListener('mouseleave', startSlideshow);
    }
});
</script>

<noscript>
    <style>
        .page-loader { display: none; }
        .home-main { opacity: 1; }
    </style>
    <div style="text-align:center; margin-top:50px; padding:20px;">
        <h2>School Management system</h2>
        <p>Please enable JavaScript for better experience. <a href="mhs/">Click here →</a></p>
    </div>
</noscript>
</body>
</html>