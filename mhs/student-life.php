<?php
// student-life.php - Student Life for Form Five and Form Six (A-Level Only)
session_start();
require_once '../controller/db_connect.php';

// Set page-specific CSS
$page_css = "css/student-life.css";

include 'header.php';

// Get current year
$current_year = date('Y');
?>

<main class="main-content">
    
    <!-- HERO SECTION -->
    <section class="student-hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-users"></i>
                <span>A-Level Experience</span>
            </div>
            <h1>Student Life at Muyovozi</h1>
            <p>Discover the vibrant life of our Advanced Level students — Form Five and Form Six</p>
        </div>
    </section>

    <div class="container">
        
        <!-- Intro Section -->
        <div class="intro-section">
            <h2>Life in Forms Five & Six</h2>
            <p>At Muyovozi High School, we offer a unique and transformative experience for our Advanced Level students. Form Five and Form Six are critical years that prepare students for university education and beyond. Our students enjoy a balanced life of academics, leadership, sports, and personal growth.</p>
            <div class="level-badges">
                <span class="level-badge form5"><i class="fas fa-arrow-right"></i> Form Five</span>
                <span class="level-badge form6"><i class="fas fa-graduation-cap"></i> Form Six</span>
                <span class="level-badge al"><i class="fas fa-star"></i> Advanced Level</span>
            </div>
        </div>
        
        <!-- Daily Schedule Section -->
        <div class="schedule-section">
            <h3><i class="fas fa-clock"></i> Daily Schedule (A-Level)</h3>
            <div class="schedule-grid">
                <div class="schedule-item">
                    <span class="schedule-time">05:30 AM</span>
                    <span class="schedule-activity">Wake Up & Morning Preparation</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">06:00 AM</span>
                    <span class="schedule-activity">Morning Physical Exercise / Cleanlines</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">07:00 AM</span>
                    <span class="schedule-activity">Morning session starts</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">11:00 AM</span>
                    <span class="schedule-activity">Breakfast</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">11:30 AM - 01:30 PM</span>
                    <span class="schedule-activity">Afternon Session</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">01:30 PM</span>
                    <span class="schedule-activity">Lunch Break</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">02:30 PM - 04:00 PM</span>
                    <span class="schedule-activity">Evening Session</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">04:00 PM - 06:00 PM</span>
                    <span class="schedule-activity">Sports & Extracurricular Activities</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">06:30 PM</span>
                    <span class="schedule-activity">Dinner </span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">07:00 PM - 08:00 PM</span>
                    <span class="schedule-activity">God Time (pray)</span>
                </div>
                <div class="schedule-item">
                    <span class="schedule-time">08:00 PM</span>
                    <span class="schedule-activity">Preps (Self-Study)</span>
                </div>
              
                <div class="schedule-item">
                    <span class="schedule-time">10:30 PM</span>
                    <span class="schedule-activity">Lights Out & Bedtime</span>
                </div>
            </div>
        </div>
        
      
        
        <!-- Extracurricular Activities Section -->
        <div class="activities-section">
            <h3><i class="fas fa-futbol"></i> Extracurricular Activities</h3>
            <div class="activities-grid">
                <div class="activity-card">
                    <div class="activity-icon"><i class="fas fa-futbol"></i></div>
                    <h4>Sports</h4>
                    <p>Football, Basketball, Volleyball, Athletics, Netball</p>
                </div>
                <div class="activity-card">
                    <div class="activity-icon"><i class="fas fa-music"></i></div>
                    <h4>Arts & Culture</h4>
                    <p>Drama, Traditional Dance, Music, Choir, Debating Club</p>
                </div>
                <div class="activity-card">
                    <div class="activity-icon"><i class="fas fa-microphone"></i></div>
                    <h4>Leadership</h4>
                    <p>Student Government, Prefects Body, Class Representatives</p>
                </div>
                <div class="activity-card">
                    <div class="activity-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <h4>Community Service</h4>
                    <p>Environmental Club, Red Cross, Peer Education</p>
                </div>
                <div class="activity-card">
                    <div class="activity-icon"><i class="fas fa-calculator"></i></div>
                    <h4>Academic Clubs</h4>
                    <p>Science Club, Mathematics Club, English Club, Geography Club</p>
                </div>
                <div class="activity-card">
                    <div class="activity-icon"><i class="fas fa-camera"></i></div>
                    <h4>Media & Journalism</h4>
                    <p>School Magazine, Photography Club, Broadcasting Club</p>
                </div>
            </div>
        </div>
        
        <!-- Boarding Life Section -->
        <div class="boarding-section">
            <h3><i class="fas fa-bed"></i> Boarding Life for A-Level Students</h3>
            <div class="boarding-grid">
                <div class="boarding-card">
                    <div class="boarding-image"><i class="fas fa-home"></i></div>
                    <h4>Accommodation</h4>
                    <p>Separate hostels for boys and girls with comfortable dormitories, study areas, and common rooms.</p>
                </div>
                <div class="boarding-card">
                    <div class="boarding-image"><i class="fas fa-utensils"></i></div>
                    <h4>Dining</h4>
                    <p>Nutritious meals served three times daily with special consideration for dietary needs.</p>
                </div>
                <div class="boarding-card">
                    <div class="boarding-image"><i class="fas fa-shield-alt"></i></div>
                    <h4>Safety & Security</h4>
                    <p>24/7 security, matrons, and wardens ensuring student safety and well-being.</p>
                </div>
                <div class="boarding-card">
                    <div class="boarding-image"><i class="fas fa-book"></i></div>
                    <h4>Study Environment</h4>
                    <p>Dedicated evening preps and night reading sessions with teacher supervision.</p>
                </div>
            </div>
        </div>
        
        <!-- Leadership Opportunities -->
        <div class="leadership-section">
            <h3><i class="fas fa-user-tie"></i> Leadership Opportunities for Form Five & Six</h3>
            <div class="leadership-grid">
                <div class="leadership-card">
                    <div class="leadership-icon"><i class="fas fa-crown"></i></div>
                    <h4>Head Prefect</h4>
                    <p>Overall student leader representing the student body</p>
                </div>
                <div class="leadership-card">
                    <div class="leadership-icon"><i class="fas fa-hand-paper"></i></div>
                    <h4>Academic Prefect</h4>
                    <p>Leading academic initiatives and peer tutoring</p>
                </div>
                <div class="leadership-card">
                    <div class="leadership-icon"><i class="fas fa-futbol"></i></div>
                    <h4>Sports Captain</h4>
                    <p>Organizing sports events and teams</p>
                </div>
                <div class="leadership-card">
                    <div class="leadership-icon"><i class="fas fa-building"></i></div>
                    <h4>Dormitory Prefect</h4>
                    <p>Maintaining discipline in hostels</p>
                </div>
                <div class="leadership-card">
                    <div class="leadership-icon"><i class="fas fa-calendar-alt"></i></div>
                    <h4>Events Coordinator</h4>
                    <p>Planning school events and functions</p>
                </div>
                <div class="leadership-card">
                    <div class="leadership-icon"><i class="fas fa-heart"></i></div>
                    <h4>Health Prefect</h4>
                    <p>Promoting health and hygiene awareness</p>
                </div>
            </div>
        </div>
        
        <!-- Form Five vs Form Six Comparison -->
        <div class="comparison-section">
            <h3><i class="fas fa-chart-line"></i> Form Five vs Form Six Journey</h3>
            <div class="comparison-grid">
                <div class="comparison-card form5-card">
                    <h4><i class="fas fa-arrow-right"></i> Form Five</h4>
                    <ul>
                        <li><i class="fas fa-check"></i> Transition from O-Level to A-Level</li>
                        <li><i class="fas fa-check"></i> New subject combinations</li>
                        <li><i class="fas fa-check"></i> Adjusting to advanced curriculum</li>
                        <li><i class="fas fa-check"></i> Building study habits for A-Level</li>
                        <li><i class="fas fa-check"></i> Mid-year examinations</li>
                        <li><i class="fas fa-check"></i> Leadership orientation</li>
                    </ul>
                </div>
                <div class="comparison-card form6-card">
                    <h4><i class="fas fa-graduation-cap"></i> Form Six</h4>
                    <ul>
                        <li><i class="fas fa-check"></i> Mastery of A-Level content</li>
                        <li><i class="fas fa-check"></i> Final preparations for ACSEE</li>
                        <li><i class="fas fa-check"></i> University application guidance</li>
                        <li><i class="fas fa-check"></i> Career counseling sessions</li>
                        <li><i class="fas fa-check"></i> Mock examinations</li>
                        <li><i class="fas fa-check"></i> Graduation ceremony</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Gallery Preview -->
        <div class="gallery-preview">
            <h3><i class="fas fa-images"></i> Life at Muyovozi - A-Level Moments</h3>
            <div class="preview-grid">
                <div class="preview-item">
                    <img src="../images/class.png" alt="Classroom" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22%3E%3Crect width=%22200%22 height=%22150%22 fill=%22%233B9DB3%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22white%22%3EClass%3C/text%3E%3C/svg%3E'">
                    <div class="preview-overlay">
                        <span>Academic Excellence</span>
                    </div>
                </div>
                <div class="preview-item">
                    <img src="../images/sports.jpg" alt="Sports" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22%3E%3Crect width=%22200%22 height=%22150%22 fill=%22%233B9DB3%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22white%22%3ESports%3C/text%3E%3C/svg%3E'">
                    <div class="preview-overlay">
                        <span>Sports & Fitness</span>
                    </div>
                </div>
                <div class="preview-item">
                    <img src="../images/library.jpg" alt="Library" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22%3E%3Crect width=%22200%22 height=%22150%22 fill=%22%233B9DB3%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22white%22%3ELibrary%3C/text%3E%3C/svg%3E'">
                    <div class="preview-overlay">
                        <span>Library & Studies</span>
                    </div>
                </div>
                <div class="preview-item">
                    <img src="../images/lab.jpg" alt="Laboratory" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22%3E%3Crect width=%22200%22 height=%22150%22 fill=%22%233B9DB3%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22white%22%3ELab%3C/text%3E%3C/svg%3E'">
                    <div class="preview-overlay">
                        <span>Science Labs</span>
                    </div>
                </div>
            </div>
            <div class="view-all">
                <a href="gallery.php" class="view-all-btn">View Full Gallery <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        
        <!-- Quote Section -->
        <div class="quote-section">
            <div class="quote-card">
                <i class="fas fa-quote-left"></i>
                <p>"Form Five and Form Six are the most transformative years of your academic journey. At Muyovozi, we prepare you not just for exams, but for life."</p>
                <span>- Head Master, Muyovozi High School</span>
            </div>
        </div>
        
        <!-- Call to Action -->
        <div class="cta-section">
            <h2>Ready to Join Muyovozi?</h2>
            <p>Enroll for Form Five and experience the best A-Level education in Kigoma Region</p>
            <div class="cta-buttons">
                <a href="contact.php" class="btn-cta-primary"><i class="fas fa-envelope"></i> Contact Admissions</a>
                <a href="academic_subjects.php" class="btn-cta-secondary"><i class="fas fa-book"></i> View Subjects</a>
            </div>
        </div>
        
    </div>
</main>

<?php include '../controller/footer.php'; ?>