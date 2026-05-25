<?php

if (isset($_GET['source']) || isset($_GET['view-source'])) {
    die('Access denied');
}
// about.php - Comprehensive About Us Page with Modern Design
session_start();
require_once '../controller/db_connect.php';

// Set page-specific CSS
$page_css = "css/about.css";

include 'header.php';

// Get Head Master information
$headmaster_sql = "SELECT a.*, ar.role_name 
                   FROM admins a
                   JOIN admin_role_assignments ara ON a.id = ara.admin_id
                   JOIN admin_roles ar ON ara.role_id = ar.id
                   WHERE ar.role_name LIKE '%Head Master%'
                   AND a.status = 1
                   ORDER BY ara.is_primary DESC
                   LIMIT 1";
$headmaster_result = mysqli_query($conn, $headmaster_sql);
$headmaster = mysqli_fetch_assoc($headmaster_result);

// Get Academic Master information
$academic_master_sql = "SELECT a.*, ar.role_name 
                        FROM admins a
                        JOIN admin_role_assignments ara ON a.id = ara.admin_id
                        JOIN admin_roles ar ON ara.role_id = ar.id
                        WHERE ar.role_name LIKE '%Academic Master%'
                        AND a.status = 1
                        ORDER BY ara.is_primary DESC
                        LIMIT 1";
$academic_master_result = mysqli_query($conn, $academic_master_sql);
$academic_master = mysqli_fetch_assoc($academic_master_result);
?>

<main class="main-content">
    
    <!-- HERO SECTION WITH BACKGROUND BLUR -->
    <section class="about-hero">
        <!-- Background Image 2 with Blur -->
        <div class="hero-bg-blur" style="background-image: url('../images/image2.png');"></div>
        
        <!-- Gradient Overlay -->
        <div class="hero-overlay"></div>
        
        <div class="container">
            <div class="hero-content">
                <span class="hero-badge">
                    <i class="fas fa-school"></i> Established 2013
                </span>
                <h1>About School Management System</h1>
                <p>From the echoes of a refugee camp to the halls of academic excellence — a journey of transformation, resilience, and unwavering commitment to education.</p>
                
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo date('Y') - 2013; ?></span>
                        <span class="stat-label">Years of Excellence</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">5,000+</span>
                        <span class="stat-label">Graduates</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">98%</span>
                        <span class="stat-label">Pass Rate</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Welcome Message -->
        <div class="welcome-card">
            <div class="welcome-icon">
                <i class="fas fa-quote-right"></i>
            </div>
            <h2>Welcome to School Management System</h2>
            <p class="welcome-text">
                Welcome to School Management System, an institution with a unique and powerful story. 
                From the echoes of a refugee camp to the halls of academic excellence, our journey 
                is one of transformation, resilience, and unwavering commitment to education.
            </p>
            <p>
                Located in Kasulu District, Kigoma Region, near the historic Mtabila military area, 
                we stand on grounds that once hosted over 90,000 refugees. Today, these grounds nurture 
                the dreams and ambitions of Tanzania's future leaders.
            </p>
        </div>

        <!-- Mission & Vision Section -->
        <div class="mission-vision-grid">
            <div class="mission-card">
                <div class="card-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h3>Our Mission</h3>
                <p>To provide quality education that nurtures intellectual curiosity, moral integrity, and social responsibility, preparing students for lifelong learning and global citizenship.</p>
            </div>
            <div class="vision-card">
                <div class="card-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <h3>Our Vision</h3>
                <p>To be a center of academic excellence that transforms lives and communities through innovative education and character development.</p>
            </div>
            <div class="values-card">
                <div class="card-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h3>Core Values</h3>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Excellence</li>
                    <li><i class="fas fa-check-circle"></i> Integrity</li>
                    <li><i class="fas fa-check-circle"></i> Respect</li>
                    <li><i class="fas fa-check-circle"></i> Innovation</li>
                    <li><i class="fas fa-check-circle"></i> Community</li>
                </ul>
            </div>
        </div>

        <!-- Our Story Timeline -->
        <div class="story-section">
            <div class="section-header">
                <h2>Our Journey</h2>
                <p>From refugee camp to center of academic excellence</p>
            </div>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <span class="timeline-year">1993</span>
                        <h4>Refugee Camp Established</h4>
                        <p>Muyovozi and Mtabila become one of the largest refugee settlements in western Tanzania, hosting over 90,000 refugees from Burundi.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <span class="timeline-year">2002-2009</span>
                        <h4>Voluntary Repatriation</h4>
                        <p>As Burundi stabilizes, refugees return home under UNHCR's voluntary repatriation programme.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <span class="timeline-year">2010-2013</span>
                        <h4>Transition Period</h4>
                        <p>Government repurposes the land for local development and school planning begins.</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <span class="timeline-year">2013</span>
                        <h4>Muyovozi Secondary School Established</h4>
                        <p>Formally established as a government advanced-level boarding school (Forms 5 & 6).</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <span class="timeline-year">2013-Today</span>
                        <h4>Growth & Excellence</h4>
                        <p>Growing to become a crucial educational hub with consistently high pass rates.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leadership Section -->
        <div class="leadership-section">
            <div class="section-header">
                <h2>School Leadership</h2>
                <p>Dedicated professionals guiding our students toward excellence</p>
            </div>
            
            <div class="leadership-grid">
                <div class="leader-card">
                    <div class="leader-avatar">
                        <?php if ($headmaster && !empty($headmaster['profile_image'])): ?>
                            <img src="../uploads/profiles/<?php echo htmlspecialchars($headmaster['profile_image']); ?>" alt="Head Master">
                        <?php else: ?>
                            <i class="fas fa-user-tie"></i>
                        <?php endif; ?>
                    </div>
                    <h4><?php echo $headmaster ? htmlspecialchars($headmaster['first_name'] . ' ' . $headmaster['last_name']) : 'Head Master'; ?></h4>
                    <span class="leader-position">Head Master</span>
                    <p class="leader-quote">
                        "<?php echo $headmaster && !empty($headmaster['quote']) ? htmlspecialchars($headmaster['quote']) : 'Education is not just about passing exams; it\'s about building character, nurturing talents, and preparing students for life.'; ?>"
                    </p>
                </div>
                
                <div class="leader-card">
                    <div class="leader-avatar">
                        <?php if ($academic_master && !empty($academic_master['profile_image'])): ?>
                            <img src="../uploads/profiles/<?php echo htmlspecialchars($academic_master['profile_image']); ?>" alt="Academic Master">
                        <?php else: ?>
                            <i class="fas fa-chalkboard-user"></i>
                        <?php endif; ?>
                    </div>
                    <h4><?php echo $academic_master ? htmlspecialchars($academic_master['first_name'] . ' ' . $academic_master['last_name']) : 'Academic Master'; ?></h4>
                    <span class="leader-position">Academic Master</span>
                    <p class="leader-quote">
                        "<?php echo $academic_master && !empty($academic_master['quote']) ? htmlspecialchars($academic_master['quote']) : 'We strive for academic excellence through innovative teaching methods and personalized attention to each student\'s needs.'; ?>"
                    </p>
                </div>
            </div>
        </div>

        <!-- Campus Features Grid -->
        <div class="campus-section">
            <div class="section-header">
                <h2>Our Campus</h2>
                <p>A unique learning environment with a powerful history</p>
            </div>
            
            <div class="campus-grid">
                <div class="campus-card">
                    <div class="campus-image" style="background-image: url('../images/image1.png');"></div>
                    <div class="campus-content">
                        <h4>Historic Classrooms</h4>
                        <p>Some of our classroom blocks date back to the camp era, standing as silent witnesses to our transformation.</p>
                        <ul>
                            <li><i class="fas fa-check"></i> Original camp structures preserved</li>
                            <li><i class="fas fa-check"></i> Modernized with new facilities</li>
                            <li><i class="fas fa-check"></i> 15+ spacious classrooms</li>
                        </ul>
                    </div>
                </div>
                
                <div class="campus-card">
                    <div class="campus-image" style="background-image: url('../images/image3.png');"></div>
                    <div class="campus-content">
                        <h4>Modern Library</h4>
                        <p>A well-stocked library with thousands of books and a quiet study environment.</p>
                        <ul>
                            <li><i class="fas fa-check"></i> 5,000+ books & references</li>
                            <li><i class="fas fa-check"></i> Digital resources</li>
                            <li><i class="fas fa-check"></i> Quiet study areas</li>
                        </ul>
                    </div>
                </div>
                
                <div class="campus-card">
                    <div class="campus-image" style="background-image: url('../images/image4.png');"></div>
                    <div class="campus-content">
                        <h4>Boarding Facilities</h4>
                        <p>Separate dormitories for boys and girls with a conducive environment for studying.</p>
                        <ul>
                            <li><i class="fas fa-check"></i> Boys & girls hostels</li>
                            <li><i class="fas fa-check"></i> Study rooms in dorms</li>
                            <li><i class="fas fa-check"></i> 24/7 security</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Counter Section -->
        <div class="stats-counter-section">
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stats-number" data-count="5000">0</div>
                    <div class="stats-label">Total Graduates</div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-chalkboard-user"></i></div>
                    <div class="stats-number" data-count="85">0</div>
                    <div class="stats-label">Qualified Teachers</div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-building"></i></div>
                    <div class="stats-number" data-count="25">0</div>
                    <div class="stats-label">Classrooms</div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-trophy"></i></div>
                    <div class="stats-number" data-count="98">0</div>
                    <div class="stats-label">Pass Rate (%)</div>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="cta-section">
            <h2>Ready to Join Muyovozi?</h2>
            <p>Take the first step towards a bright future. Enroll today and become part of our excellence-driven community.</p>
            <a href="contact.php" class="btn-cta">
                <i class="fas fa-envelope"></i> Contact Admissions
            </a>
        </div>
    </div>
</main>

<!-- Counter Animation Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stats-number');
    
    function animateCounters() {
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-count'));
            let current = 0;
            const increment = target / 50;
            
            const updateCounter = () => {
                current += increment;
                if (current >= target) {
                    if (counter.getAttribute('data-count') === '98') {
                        counter.innerText = target + '%';
                    } else {
                        counter.innerText = Math.floor(target).toLocaleString();
                    }
                    clearInterval(timer);
                } else {
                    if (counter.getAttribute('data-count') === '98') {
                        counter.innerText = Math.floor(current) + '%';
                    } else {
                        counter.innerText = Math.floor(current).toLocaleString();
                    }
                }
            };
            
            const timer = setInterval(updateCounter, 30);
        });
    }
    
    // Trigger counter when stats section is in view
    const statsSection = document.querySelector('.stats-counter-section');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounters();
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    if (statsSection) {
        observer.observe(statsSection);
    }
});
</script>

<?php include '../controller/footer.php'; ?>