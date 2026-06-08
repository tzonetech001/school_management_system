<?php
// header - COMPLETE SEO OPTIMIZED VERSION with FULL Browser & Search Engine Support
// Version 2.0 - Fixed FOUC and CSS loading issues

// Start output buffering to prevent partial rendering
ob_start();



// Get current year for dynamic display
$current_year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    
    <!-- CRITICAL CSS - Loads immediately to prevent FOUC -->
    <style> /* CRITICAL CSS - HII ITAPAPUKA MARA MOJA KABISA */ * {margin: 0; padding: 0; box-sizing: border-box; } body {font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding-top: 140px; background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); visibility: visible; opacity: 1; } /* Page Loader - Inaonekana mara moja na kuficha kila kitu */ .page-loader-critical {position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, #1a2f3a, #2c5f8a); display: flex; justify-content: center; align-items: center; z-index: 999999; transition: opacity 0.5s ease, visibility 0.5s ease; } .page-loader-critical.hide {opacity: 0; visibility: hidden; } .loader-content {text-align: center; } .loader-spinner {width: 60px; height: 60px; border: 4px solid rgba(255,255,255,0.2); border-top: 4px solid #ffc107; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 20px; } @keyframes spin {0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } .loader-title {color: white; font-size: 18px; font-weight: 600; margin-bottom: 8px; } .loader-subtitle {color: rgba(255,255,255,0.8); font-size: 13px; } /* Ficha maudhui yote mpaka CSS ipakie kamili */ .main-header, .desktop-nav, .mobile-sidebar, .home-main, .main-footer, [class*="content"] {visibility: visible; opacity: 1; } /* Responsive body padding */ @media (max-width: 992px) {body { padding-top: 80px; } } </style>
    
    <!-- PRIMARY SEO META TAGS -->
 
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website"> <meta property="og:url" content="<?php echo htmlspecialchars($page_url); ?>"> <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>"> <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>"> <meta property="og:image" content="<?php echo $page_image; ?>"> <meta property="og:image:width" content="1200"> <meta property="og:image:height" content="630"> <meta property="og:image:alt" content="Muyovozi High School Campus"> <meta property="og:site_name" content="Muyovozi High School"> <meta property="og:locale" content="en_TZ"> <meta property="og:locale:alternate" content="sw_TZ">
    
    <!-- Favicon Icons -->
    <link rel="icon" type="image/x-icon" href="favicon.ico"> <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png"> <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png"> <link rel="icon" type="image/png" sizes="48x48" href="favicon.png"> <link rel="icon" type="image/png" sizes="96x96" href="favicon-96x96.png"> <link rel="icon" type="image/png" sizes="192x192" href="android-chrome-192x192.png">
    
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="57x57" href="apple-touch-icon-57x57.png"> <link rel="apple-touch-icon" sizes="60x60" href="apple-touch-icon-60x60.png"> <link rel="apple-touch-icon" sizes="72x72" href="/pple-touch-icon-72x72.png"> <link rel="apple-touch-icon" sizes="76x76" href="apple-touch-icon-76x76.png"> <link rel="apple-touch-icon" sizes="114x114" href="apple-touch-icon-114x114.png"> <link rel="apple-touch-icon" sizes="120x120" href="apple-touch-icon-120x120.png"> <link rel="apple-touch-icon" sizes="144x144" href="apple-touch-icon-144x144.png"> <link rel="apple-touch-icon" sizes="152x152" href="apple-touch-icon-152x152.png"> <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    
    <!-- Android Chrome -->
    <link rel="manifest" href="site.webmanifest"> <meta name="msapplication-TileColor" content="#3B9DB3"> <meta name="msapplication-TileImage" content="mstile-144x144.png">
    
    <!-- Preload Critical CSS Files -->
    <link rel="preload" href="style.css" as="style"> <link rel="preload" href="mhs/css/main.css" as="style"> <link rel="preload" href="home.css" as="style">
    
    <!-- Schema.org markup for Google -->
    <script type="application/ld+json"> {"@context": "https://schema.org", "@type": "EducationalOrganization", "name": "Muyovozi High School", "alternateName": "Muyovozi Secondary School", "url": "https://muyovozi.sc.tz", "logo": "https://muyovozi.sc.tz/muyovozi.png", "image": "https://muyovozi.sc.tz/images/image1.png", "description": "<?php echo htmlspecialchars($page_description); ?>", "address": {"@type": "PostalAddress", "streetAddress": "Kambi ya Mtabila, Kasulu District", "addressLocality": "Kasulu", "addressRegion": "Kigoma", "postalCode": "47300", "addressCountry": "Tanzania" }, "geo": {"@type": "GeoCoordinates", "latitude": "-4.41209", "longitude": "30.26763" }, "telephone": "+255622032538", "email": "info@muyovozihigh.ac.tz", "foundingDate": "2013", "numberOfStudents": "1200", "numberOfTeachers": "85", "educationalLevel": "Advanced Level Secondary School (Form Five - Form Six)" } </script>
    
    <!-- CSS Files (loaded normally after preload) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"> <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet"> <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="mhs/css/main.css">
    
    <!-- Page-specific CSS -->
    <?php if (isset($page_css) && file_exists($page_css)): ?>
        <link rel="stylesheet" href="<?php echo $page_css; ?>">
    <?php endif; ?>
    
    <!-- Preload critical images -->
    <link rel="preload" href="../images/image1.png" as="image">
    <link rel="preload" href="../images/image4.png" as="image">
    
    <!-- Additional header styles -->
    <style> :root {--primary-color: #3B9DB3; --primary-dark: #2d7c8f; --primary-light: #6bb5c7; --accent-color: #ffc107; --dark-color: #1a2f3a; } @media (max-width: 992px) {.desktop-nav { display: none; } } @media (min-width: 993px) {.mobile-nav-toggle { display: none !important; } } .mobile-dropdown-menu {max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; } .mobile-dropdown-menu.show {max-height: 500px; display: block; } body.sidebar-open {overflow: hidden; position: fixed; width: 100%; } </style>
</head>
<body>
    <!-- CRITICAL PAGE LOADER - Inaonekana mara moja -->
    <div class="page-loader-critical" id="criticalPageLoader">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <div class="loader-title">School Management System</div>
            <div class="loader-subtitle">Loading...</div>
        </div>
    </div>
    
    <!-- Main Header -->
    <div class="main-header" id="mainHeader">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12">
                    <div class="logo-container">
                        <div class="logo-left">
                            <div class="shield-logo">
                                <img src="images/image4.png" alt="Muyovozi High School Official Logo - Shield Emblem" width="60" height="60" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%233B9DB3%22/><text x=%2250%22 y=%2265%22 font-size=%2250%22 text-anchor=%22middle%22 fill=%22%23ffffff%22>⚔️</text></svg>'">
                            </div>
                        </div>
                        
                        <div class="school-title">
                            <span class="school-main-name">SCHOOL MANAGEMENT SYSTEM</span>
                            <span class="school-motto">"Education For Life"</span>
                        </div>
                        
                        <div class="logo-right">
                            <div class="logo-img">
                                <img src="images/muyovozi.jpg" alt="Muyovozi Secondary School Logo - Excellence in Education" width="60" height="60" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%233B9DB3%22/><text x=%2250%22 y=%2265%22 font-size=%2250%22 text-anchor=%22middle%22 fill=%22%23ffffff%22>M</text></svg>'">
                            </div>
                        </div>
                        
                        <button class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Menu">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Desktop Navigation -->
    <div class="desktop-nav">
        <div class="container">
            <ul class="nav-menu">
                <li><a href="mhs/muyovozi_home"><i class="fas fa-home"></i> HOME</a></li>
                <li class="dropdown">
                    <a href="mhs/about"><i class="fas fa-info-circle"></i> ABOUT US</a>
                    <div class="dropdown-menu-custom">
                        <a href="mhs/about"><i class="fas fa-school"></i> About School</a>
                        <a href="mhs/history"><i class="fas fa-history"></i> History & Heritage</a>
                        <a href="mhs/mission"><i class="fas fa-bullseye"></i> Mission & Vision</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="mhs/academics"><i class="fas fa-book-open"></i> ACADEMICS</a>
                    <div class="dropdown-menu-custom">
                        <a href="mhs/academic_subjects"><i class="fas fa-globe"></i> Arts Subjects</a>
                        <a href="mhs/calendar"><i class="fas fa-calendar-alt"></i> Academic Calendar</a>
                        <a href="mhs/results"><i class="fas fa-clipboard-list"></i> School Result</a>
                        <a href="mhs/nectaresult"><i class="fas fa-file-signature"></i> Necta Result</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="mhs/student-life"><i class="fas fa-users"></i> STUDENT LIFE</a>
                    <div class="dropdown-menu-custom">
                        <a href="mhs/clubs"><i class="fas fa-users-cog"></i> Clubs & Societies</a>
                        <a href="mhs/sports"><i class="fas fa-futbol"></i> Sports</a>
                        <a href="mhs/muyo_salama"><i class="fas fa-heart"></i> Shule salama</a>
                        <a href="mhs/gallery"><i class="fas fa-images"></i> Gallery</a>
                    </div>
                </li>
                <li><a href="mhs/news"><i class="fas fa-newspaper"></i> NEWS & EVENTS</a></li>
                <li><a href="mhs/contact"><i class="fas fa-envelope"></i> CONTACT</a></li>
                <li><a href="mhs/login"><i class="fas fa-sign-in-alt"></i> LOGIN</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <!-- Mobile Sidebar -->
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="mobile-sidebar-header">
            <div class="mobile-sidebar-logo">
                <img src="images/muyovozi.jpg" alt="Muyovozi Logo">
            </div>
            <div class="mobile-sidebar-title">
                <span class="school-name">School Management System</span>
                <span class="school-motto-side">"Education For Life"</span>
            </div>
        </div>
        
        <div class="mobile-sidebar-content">
            <ul class="mobile-nav-menu">
                <li class="mobile-nav-item">
                    <a href="mhs/muyovozi_home" class="mobile-nav-link">
                        <i class="fas fa-home"></i> HOME
                    </a>
                </li>
                
                <li class="mobile-nav-item">
                    <button class="mobile-dropdown-trigger" data-dropdown="aboutDropdown">
                        <i class="fas fa-info-circle"></i> ABOUT US
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <div class="mobile-dropdown-menu" id="aboutDropdown">
                        <a href="mhs/about" class="mobile-dropdown-link">
                            <i class="fas fa-school"></i> About School
                        </a>
                        <a href="mhs/history" class="mobile-dropdown-link">
                            <i class="fas fa-history"></i> History & Heritage
                        </a>
                        <a href="mhs/mission" class="mobile-dropdown-link">
                            <i class="fas fa-bullseye"></i> Mission & Vision
                        </a>
                    </div>
                </li>
                
                <li class="mobile-nav-item">
                    <button class="mobile-dropdown-trigger" data-dropdown="academicsDropdown">
                        <i class="fas fa-book-open"></i> ACADEMICS
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <div class="mobile-dropdown-menu" id="academicsDropdown">
                        <a href="mhs/academic_subjects" class="mobile-dropdown-link">
                            <i class="fas fa-globe"></i> Arts Subjects
                        </a>
                        <a href="mhs/calendar" class="mobile-dropdown-link">
                            <i class="fas fa-calendar-alt"></i> Academic Calendar
                        </a>
                        <a href="mhs/results" class="mobile-dropdown-link">
                            <i class="fas fa-clipboard-list"></i> School Result
                        </a>
                        <a href="mhs/nectaresult" class="mobile-dropdown-link">
                            <i class="fas fa-file-signature"></i> Necta Result
                        </a>
                    </div>
                </li>
                
                <li class="mobile-nav-item">
                    <button class="mobile-dropdown-trigger" data-dropdown="studentLifeDropdown">
                        <i class="fas fa-users"></i> STUDENT LIFE
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <div class="mobile-dropdown-menu" id="studentLifeDropdown">
                        <a href="mhs/clubs" class="mobile-dropdown-link">
                            <i class="fas fa-users-cog"></i> Clubs & Societies
                        </a>
                        <a href="mhs/sports" class="mobile-dropdown-link">
                            <i class="fas fa-futbol"></i> Sports
                        </a>
                        <a href="mhs/muyo_salama" class="mobile-dropdown-link">
                            <i class="fas fa-heart"></i> Shule Salama
                        </a>
                        <a href="mhs/gallery" class="mobile-dropdown-link">
                            <i class="fas fa-images"></i> Gallery
                        </a>
                    </div>
                </li>
                
                <li class="mobile-nav-item">
                    <a href="mhs/news" class="mobile-nav-link">
                        <i class="fas fa-newspaper"></i> NEWS & EVENTS
                    </a>
                </li>
                
                <li class="mobile-nav-item">
                    <a href="mhs/contact" class="mobile-nav-link">
                        <i class="fas fa-envelope"></i> CONTACT
                    </a>
                </li>
                
                <li class="mobile-nav-item">
                    <a href="mhs/login" class="mobile-nav-link">
                        <i class="fas fa-sign-in-alt"></i> LOGIN
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="mobile-sidebar-footer">
            <div class="sidebar-social-links">
                <a href="https://www.facebook.com/Muyovozi2014?mibextid=rS40aB7S9Ucbxw6v" class="social-link"><i class="fab fa-facebook-f"></i></a>
                <a href="https://www.tiktok.com/@frankkatabazi2025?_r=1&_t=ZS-95FzY4H40Zh" class="social-link"><i class="fab fa-tiktok"></i></a>
                <a href="https://youtu.be/-PuMDkImYF0?si=ttBkI-kox_XvJM3G" class="social-link"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </div>
    
    <script src="mhs/js/main.js"></script>
    
    <!-- HIDE LOADER SCRIPT - Runs immediately -->
    <script>
    (function() {
        // Hide critical loader function
        function hideCriticalLoader() {
            var loader = document.getElementById('criticalPageLoader');
            if (loader) {
                loader.classList.add('hide');
                setTimeout(function() {
                    if (loader && loader.parentNode) {
                        loader.style.display = 'none';
                    }
                }, 500);
            }
        }
        
        // Hide loader as soon as DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hideCriticalLoader);
        } else {
            hideCriticalLoader();
        }
        
        // Fallback: Force hide after 2 seconds maximum
        setTimeout(hideCriticalLoader, 2000);
    })();
    </script>
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();
?>