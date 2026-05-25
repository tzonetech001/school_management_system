<?php
// header - COMPLETE SEO OPTIMIZED VERSION with FULL Browser & Search Engine Support
// Version 2.0 - Fixed FOUC and CSS loading issues

// Start output buffering to prevent partial rendering
ob_start();

// Detect current page for dynamic title
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "Muyovozi High School";
$page_description = "Muyovozi High School is a leading advanced level secondary school in Kasulu District, Kigoma Region, Tanzania. Offering quality education for Form Five and Form Six students.";
$page_keywords = "Muyovozi, Muyovozi High School, Muyovozi Secondary School, schools in Tanzania, Mtabila, Kasulu schools, Kigoma schools, advanced level school, shule za upili Tanzania, NECTA results, matokeo ya NECTA, shule salama, maisha ya shule, michezo shule, form five, form six, A-Level education Tanzania";
$page_author = "Muyovozi High School";
$page_url = "https://muyovozi.sc.tz" . $_SERVER['REQUEST_URI'];
$page_image = "https://muyovozi.sc.tz/images/image1.png";

// Remove spaces from page names for proper matching
$current_page_clean = trim($current_page);

// Set specific page titles, descriptions, and keywords for better SEO
switch($current_page_clean) {
    case 'muyovozi_home':
        $page_title = "Muyovozi High School - Advanced Level School in Kasulu, Kigoma, Tanzania";
        $page_description = "Welcome to Muyovozi High School, the leading advanced level secondary school in Kasulu District, Kigoma. Quality Form Five and Form Six education with excellent NECTA results. Enroll today for a brighter future!";
        $page_keywords = "Muyovozi High School, advanced level school Kasulu, Form Five Kigoma, Form Six Tanzania, best secondary school Kigoma, A-Level education Tanzania, shule ya upili Kasulu";
        break;
    case 'about':
        $page_title = "About Muyovozi High School - History from Mtabila Refugee Camp to Academic Excellence";
        $page_description = "Discover the inspiring history of Muyovozi High School - from Mtabila refugee camp to becoming a center of academic excellence in Kasulu, Kigoma. Learn our mission, vision, and values.";
        $page_keywords = "Muyovozi history, Mtabila refugee camp school, Kasulu education history, Kigoma secondary school, school mission vision Tanzania";
        break;
    case 'academics':
        $page_title = "Academics - Form Five & Six Subject Combinations | Muyovozi High School Kigoma";
        $page_description = "Explore our advanced level subject combinations including PCM, PCB, HGE, HKL, CBG, ECA. Quality A-Level education with experienced teachers and modern facilities in Kigoma, Tanzania.";
        $page_keywords = "Form Five subjects Tanzania, Form Six combinations, PCM PCB HGE HKL, A-Level subjects Kigoma, advanced level curriculum Tanzania, NECTA syllabus";
        break;
    case 'contact':
        $page_title = "Contact Muyovozi High School - Admissions, Inquiries & Location | Kasulu, Kigoma";
        $page_description = "Get in touch with Muyovozi High School. Contact our Head Master, Academic Master, or admissions office for Form Five and Six enrollment. Visit us in Kambi ya Mtabila, Kasulu District, Kigoma.";
        $page_keywords = "Muyovozi contact, school admission Kigoma, Kasulu school phone number, Form Five enrollment Tanzania, contact secondary school Tanzania";
        break;
    case 'gallery':
        $page_title = "Photo Gallery - Campus Life, Events & Activities | Muyovozi High School";
        $page_description = "View our photo gallery showcasing campus life, academic activities, sports events, cultural programs, and memorable moments at Muyovozi High School in Kigoma, Tanzania.";
        $page_keywords = "Muyovozi gallery, school photos Kigoma, campus life Tanzania, secondary school events, Form Five and Six activities, shule picha Kasulu";
        break;
    case 'news':
        $page_title = "News & Updates - Latest Announcements | Muyovozi High School Tanzania";
        $page_description = "Stay updated with the latest news, announcements, NECTA results, school events, academic calendar updates, and important notices from Muyovozi High School.";
        $page_keywords = "Muyovozi news, school announcements Tanzania, NECTA results news, Kasulu school updates, Kigoma education news, shule habari";
        break;
    case 'calendar':
        $page_title = "Academic Calendar - Term Dates & Important Events | Muyovozi High School";
        $page_description = "View our academic calendar including term dates, examination schedules, holidays, and important events for Form Five and Six students at Muyovozi High School.";
        $page_keywords = "school calendar Tanzania, Form Five term dates, NECTA exam schedule, Kigoma school holidays, academic year Tanzania, shule kalenda";
        break;
    case 'student-life':
        $page_title = "Student Life - Daily Schedule, Sports & Clubs | Muyovozi High School";
        $page_description = "Discover vibrant student life at Muyovozi High School. Daily routines, sports activities, clubs and societies, leadership opportunities, and boarding life for advanced level students.";
        $page_keywords = "student life Tanzania, boarding school Kigoma, school clubs and sports, Form Five experience, secondary school activities Tanzania";
        break;
    case 'results':
        $page_title = "NECTA Results - Form Six Examination Performance | Muyovozi High School";
        $page_description = "Check Muyovozi High School NECTA results, Form Six examination performance, academic achievement statistics, and national ranking. See our excellent pass rates!";
        $page_keywords = "Muyovozi NECTA results, Form Six results Tanzania, advanced level exam results, Kigoma school performance, matokeo ya kidato cha sita";
        break;
    case 'muyo_salama':
        $page_title = "Shule Salama - Safe School Program | Muyovozi High School Kigoma";
        $page_description = "Muyovozi High School's Shule Salama initiative promoting student safety, health, well-being, and protection. Creating a secure learning environment in Kigoma, Tanzania.";
        $page_keywords = "Shule Salama Tanzania, safe school program Kigoma, student protection Tanzania, school safety initiative, healthy school environment";
        break;
    case 'history':
        $page_title = "History & Heritage - From Refugee Camp to Excellence | Muyovozi High School";
        $page_description = "Explore the remarkable journey of Muyovozi High School from Mtabila Refugee Camp to becoming a premier advanced level secondary school in Kasulu, Kigoma Region.";
        $page_keywords = "Muyovozi history, Mtabila refugee camp, Kasulu school heritage, Kigoma education history, refugee to excellence Tanzania";
        break;
    case 'mission':
        $page_title = "Mission, Vision & Core Values | Muyovozi High School Tanzania";
        $page_description = "Learn about Muyovozi High School's mission to provide quality advanced level education, vision for excellence, and core values that guide our community.";
        $page_keywords = "school mission vision Tanzania, Muyovozi values, educational philosophy Kigoma, secondary school goals Tanzania";
        break;
    case 'academic_subjects':
        $page_title = "Academic Subjects - All Form Five & Six Combinations | Muyovozi High School";
        $page_description = "Complete list of academic subjects offered at Muyovozi High School including Sciences, Arts, and Commercial subjects for advanced level students.";
        $page_keywords = "Form Five subjects, advanced level combinations, PCM PCB HGE HKL CBG ECA, Kigoma school subjects, Tanzania A-Level curriculum";
        break;
    case 'nectaresult':
        $page_title = "NECTA Results Portal - Form Six Examination Results | Muyovozi High School";
        $page_description = "Access detailed NECTA results for Muyovozi High School. View Form Six examination results, subject performance, and student achievements.";
        $page_keywords = "NECTA results portal, Form Six results 2024, matokeo ya kidato cha sita, advanced level exam results Tanzania, Muyovozi performance";
        break;
    case 'clubs':
        $page_title = "Clubs & Societies - Co-Curricular Activities | Muyovozi High School";
        $page_description = "Explore diverse clubs and societies at Muyovozi High School including debate, science, journalism, environment, and leadership clubs for student development.";
        $page_keywords = "school clubs Tanzania, student societies Kigoma, debate club Kasulu, science club, leadership opportunities secondary school";
        break;
    case 'sports':
        $page_title = "Sports & Athletics - Football, Basketball, Athletics | Muyovozi High School";
        $page_description = "Discover sports programs at Muyovozi High School including football, basketball, athletics, volleyball, and other sporting activities for students.";
        $page_keywords = "school sports Tanzania, football Kigoma, basketball Kasulu, athletics secondary school, student sports programs";
        break;
    case 'login':
        $page_title = "Student Portal Login - Access Your Account | Muyovozi High School";
        $page_description = "Login to Muyovozi High School student portal to access results, academic resources, announcements, and personal information.";
        $page_keywords = "student login Tanzania, school portal Kigoma, Muyovozi student account, parent portal access";
        break;
}

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
    <title><?php echo htmlspecialchars($page_title); ?></title> <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>"> <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>"> <meta name="author" content="<?php echo $page_author; ?>"> <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1"> <meta name="googlebot" content="index, follow"> <meta name="bingbot" content="index, follow"> <meta name="yandex" content="index, follow"> <meta name="rating" content="General"> <meta name="language" content="English"> <meta name="revisit-after" content="7 days"> <meta name="distribution" content="global"> <meta name="theme-color" content="#3B9DB3">
    
    <!-- Google Search Console Verification -->
    <meta name="google-site-verification" content="o7arwc_iyjUL-p6ia2-Ov0prfz68ZFRG33iLGRdSJvA" />
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($page_url); ?>">
    
    <!-- Alternate language versions -->
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($page_url); ?>">
    <link rel="alternate" hreflang="sw" href="https://muyovozi.sc.tz/sw<?php echo $_SERVER['REQUEST_URI']; ?>">
    
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
            <div class="loader-title">Muyovozi High School</div>
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
                            <span class="school-main-name">MUYOVOZI HIGH SCHOOL</span>
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
                <span class="school-name">Muyovozi High School</span>
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
                <a href="https://muyovozi.sc.tz/mhs/" class="web-link"><i class="fab fa-google"></i></a>
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