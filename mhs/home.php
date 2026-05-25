<?php
// muyovozi_home.php - Main Landing Page with Background Blur + Slideshow
session_start();
require_once '../controller/db_connect.php';

// Set page-specific CSS
$page_css = "css/home.css";

include 'header.php';

// Get stats
$stats_sql = "SELECT 
    COUNT(DISTINCT s.id) as total_students,
    COUNT(DISTINCT a.id) as total_staff,
    COUNT(DISTINCT CASE WHEN s.graduation_status = 'Graduated' THEN s.id END) as total_graduates
    FROM students s
    CROSS JOIN admins a";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

$founded_year = 2013;
$current_year = date('Y');
$years_of_excellence = $current_year - $founded_year;
?>

<main class="main-content">
    
    <!-- HERO SECTION WITH BLUR BACKGROUND + SLIDESHOW -->
    <section class="home-hero">
        
        <!-- Background Image 4 with Blur Filter -->
        <div class="hero-bg-blur" style="background-image: url('../images/image5.png');"></div>
        
        <!-- Slideshow Container (Images 1,2,3) -->
        <div class="slideshow-container">
            <div class="slide active" style="background-image: url('../images/image1.png');"></div>
            <div class="slide" style="background-image: url('../images/image2.png');"></div>
            <div class="slide" style="background-image: url('../images/image6.png');"></div>
        </div>
        
        <!-- Dark Overlay for text readability -->
        <div class="hero-overlay"></div>
        
        <!-- Slideshow Dots -->
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
                            <i class="fas fa-graduation-cap"></i> Excellence Since <?php echo $founded_year; ?>
                        </span>
                        <h1>Welcome to Muyovozi High School</h1>
                        <p class="lead">"Education For Life" — Nurturing minds, building character, and shaping the leaders of tomorrow.</p>
                        
                        <div class="hero-stats">
                            <div class="hero-stat-item">
                                <span class="hero-stat-number"><?php echo $years_of_excellence; ?></span>
                                <span class="hero-stat-label">Years</span>
                            </div>
                            <div class="hero-stat-item">
                                <span class="hero-stat-number"><?php echo number_format($stats['total_students'] ?? 1200); ?>+</span>
                                <span class="hero-stat-label">Students</span>
                            </div>
                            <div class="hero-stat-item">
                                <span class="hero-stat-number">98%</span>
                                <span class="hero-stat-label">Pass Rate</span>
                            </div>
                        </div>
                        
                        <div class="hero-buttons">
                            <a href="about.php" class="btn-hero btn-hero-primary">Learn More <i class="fas fa-arrow-right"></i></a>
                            <a href="contact.php" class="btn-hero btn-hero-outline">Contact Us</a>
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
                <h2>Why Choose Muyovozi?</h2>
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
    </div>
</main>

<!-- Slideshow JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    let currentSlide = 0;
    let slideInterval;
    
    function showSlide(index) {
        // Remove active class from all slides
        slides.forEach(slide => {
            slide.classList.remove('active');
        });
        
        // Remove active class from all dots
        dots.forEach(dot => {
            dot.classList.remove('active');
        });
        
        // Add active class to current slide and dot
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        currentSlide = index;
    }
    
    function nextSlide() {
        let nextIndex = currentSlide + 1;
        if (nextIndex >= slides.length) {
            nextIndex = 0;
        }
        showSlide(nextIndex);
    }
    
    // Start slideshow (change every 5 seconds - slow)
    function startSlideshow() {
        slideInterval = setInterval(nextSlide, 5000);
    }
    
    function stopSlideshow() {
        if (slideInterval) {
            clearInterval(slideInterval);
        }
    }
    
    // Add click event to dots
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            stopSlideshow();
            showSlide(index);
            startSlideshow();
        });
    });
    
    // Start the slideshow
    startSlideshow();
    
    // Pause slideshow on hover (optional)
    const heroSection = document.querySelector('.home-hero');
    if (heroSection) {
        heroSection.addEventListener('mouseenter', stopSlideshow);
        heroSection.addEventListener('mouseleave', startSlideshow);
    }
});
</script>

<?php include '../controller/footer.php'; ?>