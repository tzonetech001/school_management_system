<?php
// sidebar.php - Clean mobile sidebar without inline CSS
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="mobile-sidebar" id="mobileSidebar">
    <div class="mobile-sidebar-header">
        <div class="mobile-sidebar-logo">
            <img src="muyovozi.png" alt="Muyovozi High School" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%233B9DB3%22/><text x=%2250%22 y=%2265%22 font-size=%2250%22 text-anchor=%22middle%22 fill=%22%23ffffff%22>M</text></svg>'">
        </div>
        <div class="mobile-sidebar-title">
            <span class="school-name">MUYOVOZI HIGH</span>
            <span class="school-motto-side">Education For Life</span>
        </div>
    </div>
    
    <div class="mobile-sidebar-content">
        <ul class="mobile-nav-menu">
            <li class="mobile-nav-item <?php echo $current_page == 'home.php' ? 'active' : ''; ?>">
                <a href="home" class="mobile-nav-link">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
            </li>
            <li class="mobile-nav-item <?php echo $current_page == 'login.php' ? 'active' : ''; ?>">
                <a href="login" class="mobile-nav-link">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
            </li>
            
            <!-- About Us Dropdown -->
            <li class="mobile-nav-item mobile-dropdown">
                <button class="mobile-dropdown-trigger" data-dropdown="aboutDropdown" aria-expanded="false">
                    <i class="fas fa-info-circle"></i>
                    <span>About Us</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <div class="mobile-dropdown-menu" id="aboutDropdown">
                    <a href="about" class="mobile-dropdown-link <?php echo $current_page == 'about.php' ? 'active' : ''; ?>">
                        <i class="fas fa-school"></i> About School
                    </a>
                    <a href="history" class="mobile-dropdown-link <?php echo $current_page == 'history.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> History & Heritage
                    </a>
                    <a href="mission" class="mobile-dropdown-link <?php echo $current_page == 'mission.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bullseye"></i> Mission & Vision
                    </a>
                </div>
            </li>
            
            <!-- Academics Dropdown -->
            <li class="mobile-nav-item mobile-dropdown">
                <button class="mobile-dropdown-trigger" data-dropdown="academicsDropdown" aria-expanded="false">
                    <i class="fas fa-book-open"></i>
                    <span>Academics</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <div class="mobile-dropdown-menu" id="academicsDropdown">
                    <a href="academic_subjects" class="mobile-dropdown-link <?php echo $current_page == 'academic_subjects.php' ? 'active' : ''; ?>">
                        <i class="fas fa-globe"></i> Arts Subjects
                    </a>
                    <a href="calendar" class="mobile-dropdown-link <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Academic Calendar
                    </a>
                    <a href="results" class="mobile-dropdown-link <?php echo $current_page == 'results.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i> School Result
                    </a>
                    <a href="nectaresult" class="mobile-dropdown-link <?php echo $current_page == 'nectaresult.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-signature"></i> Necta Result
                    </a>
                </div>
            </li>
            
            <!-- Student Life Dropdown -->
            <li class="mobile-nav-item mobile-dropdown">
                <button class="mobile-dropdown-trigger" data-dropdown="studentLifeDropdown" aria-expanded="false">
                    <i class="fas fa-users"></i>
                    <span>Student Life</span>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <div class="mobile-dropdown-menu" id="studentLifeDropdown">
                    <a href="student-life" class="mobile-dropdown-link <?php echo $current_page == 'student-life.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i> Overview
                    </a>
                    <a href="clubs" class="mobile-dropdown-link <?php echo $current_page == 'clubs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i> Clubs & Societies
                    </a>
                    <a href="sports" class="mobile-dropdown-link <?php echo $current_page == 'sports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-futbol"></i> Sports
                    </a>
                    <a href="muyo_salama" class="mobile-dropdown-link <?php echo $current_page == 'muyo_salama.php' ? 'active' : ''; ?>">
                        <i class="fas fa-heart"></i> Shule Salama
                    </a>
                    <a href="gallery" class="mobile-dropdown-link <?php echo $current_page == 'gallery.php' ? 'active' : ''; ?>">
                        <i class="fas fa-images"></i> Gallery
                    </a>
                </div>
            </li>
            
            <!-- News & Events -->
            <li class="mobile-nav-item <?php echo $current_page == 'news.php' ? 'active' : ''; ?>">
                <a href="news" class="mobile-nav-link">
                    <i class="fas fa-newspaper"></i>
                    <span>News & Events</span>
                </a>
            </li>
            
            <!-- Contact -->
            <li class="mobile-nav-item <?php echo $current_page == 'contact.php' ? 'active' : ''; ?>">
                <a href="contact" class="mobile-nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Contact</span>
                </a>
            </li>
            
            <!-- Gallery -->
            <li class="mobile-nav-item <?php echo $current_page == 'gallery.php' ? 'active' : ''; ?>">
                <a href="gallery" class="mobile-nav-link">
                    <i class="fas fa-images"></i>
                    <span>Gallery</span>
                </a>
            </li>
        </ul>
        
        <!-- Sidebar Footer -->
        <div class="mobile-sidebar-footer">
            <!-- <div class="sidebar-contact-item">
                <i class="fas fa-phone-alt"></i>
                <span>+2556 220 325 38</span>
            </div> -->
            <div class="sidebar-contact-item">
                <i class="fas fa-envelope"></i>
                <span>info@muyovozihigh.sc.tz</span>
            </div>
            <div class="sidebar-contact-item">
                <i class="fas fa-map-marker-alt"></i>
                <span>Kigoma, Tanzania</span>
            </div>
            
            <div class="sidebar-social-links">
                <a href="https://www.facebook.com/Muyovozi2014?mibextid=rS40aB7S9Ucbxw6v" class="social-link"><i class="fab fa-facebook-f"></i></a>
                <a href="https://www.tiktok.com/@frankkatabazi2025?_r=1&_t=ZS-95FzY4H40Zh" class="social-link"><i class="fab fa-tiktok"></i></a>
                <a href="https://youtu.be/-PuMDkImYF0?si=ttBkI-kox_XvJM3G" class="social-link"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </div>
</div>

<script>
// Mobile Dropdown Toggle - Simple no animation
document.addEventListener('DOMContentLoaded', function() {
    const dropdownTriggers = document.querySelectorAll('.mobile-dropdown-trigger');
    
    dropdownTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdownId = this.getAttribute('data-dropdown');
            const dropdownMenu = document.getElementById(dropdownId);
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            // Close all other dropdowns first
            document.querySelectorAll('.mobile-dropdown-menu').forEach(menu => {
                if (menu.id !== dropdownId) {
                    menu.classList.remove('show');
                }
            });
            document.querySelectorAll('.mobile-dropdown-trigger').forEach(trig => {
                if (trig !== this) {
                    trig.setAttribute('aria-expanded', 'false');
                }
            });
            
            // Toggle current
            this.setAttribute('aria-expanded', !isExpanded);
            if (dropdownMenu) {
                if (isExpanded) {
                    dropdownMenu.classList.remove('show');
                } else {
                    dropdownMenu.classList.add('show');
                }
            }
        });
    });
});
</script>