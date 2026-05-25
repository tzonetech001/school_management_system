<?php
// header - COMPLETE SEO OPTIMIZED VERSION with FULL Browser & Search Engine Support

// Detect current page for dynamic title
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = "School Management System";
$page_description = "School Management System is a leading advanced level secondary school in Kasulu District, Kigoma Region, Tanzania. Offering quality education for Form Five and Form Six students.";
$page_keywords = "Muyovozi, School Management System, Muyovozi Secondary School, schools in Tanzania, Mtabila, Kasulu schools, Kigoma schools, advanced level school, shule za upili Tanzania, NECTA results, matokeo ya NECTA, shule salama, maisha ya shule, michezo shule, form five, form six, A-Level education Tanzania";
$page_author = "School Management System";
$page_url = "https://muyovozi.sc.tz" . $_SERVER['REQUEST_URI'];
$page_image = "https://muyovozi.sc.tz/images/image1.png";

// Remove spaces from page names for proper matching
$current_page_clean = trim($current_page);

// Set specific page titles, descriptions, and keywords for better SEO
switch($current_page_clean) {
    case 'muyovozi_home':
        $page_title = "School Management System - Advanced Level School in Kasulu, Kigoma, Tanzania";
        $page_description = "Welcome to School Management System, the leading advanced level secondary school in Kasulu District, Kigoma. Quality Form Five and Form Six education with excellent NECTA results. Enroll today for a brighter future!";
        $page_keywords = "School Management System, advanced level school Kasulu, Form Five Kigoma, Form Six Tanzania, best secondary school Kigoma, A-Level education Tanzania, shule ya upili Kasulu";
        break;
    case 'about':
        $page_title = "About School Management System - History from Mtabila Refugee Camp to Academic Excellence";
        $page_description = "Discover the inspiring history of School Management System - from Mtabila refugee camp to becoming a center of academic excellence in Kasulu, Kigoma. Learn our mission, vision, and values.";
        $page_keywords = "Muyovozi history, Mtabila refugee camp school, Kasulu education history, Kigoma secondary school, school mission vision Tanzania";
        break;
    case 'academics':
        $page_title = "Academics - Form Five & Six Subject Combinations | School Management System Kigoma";
        $page_description = "Explore our advanced level subject combinations including PCM, PCB, HGE, HKL, CBG, ECA. Quality A-Level education with experienced teachers and modern facilities in Kigoma, Tanzania.";
        $page_keywords = "Form Five subjects Tanzania, Form Six combinations, PCM PCB HGE HKL, A-Level subjects Kigoma, advanced level curriculum Tanzania, NECTA syllabus";
        break;
    case 'contact':
        $page_title = "Contact School Management System - Admissions, Inquiries & Location | Kasulu, Kigoma";
        $page_description = "Get in touch with School Management System. Contact our Head Master, Academic Master, or admissions office for Form Five and Six enrollment. Visit us in Kambi ya Mtabila, Kasulu District, Kigoma.";
        $page_keywords = "Muyovozi contact, school admission Kigoma, Kasulu school phone number, Form Five enrollment Tanzania, contact secondary school Tanzania";
        break;
    case 'gallery':
        $page_title = "Photo Gallery - Campus Life, Events & Activities | School Management System";
        $page_description = "View our photo gallery showcasing campus life, academic activities, sports events, cultural programs, and memorable moments at School Management System in Kigoma, Tanzania.";
        $page_keywords = "Muyovozi gallery, school photos Kigoma, campus life Tanzania, secondary school events, Form Five and Six activities, shule picha Kasulu";
        break;
    case 'news':
        $page_title = "News & Updates - Latest Announcements | School Management System Tanzania";
        $page_description = "Stay updated with the latest news, announcements, NECTA results, school events, academic calendar updates, and important notices from School Management System.";
        $page_keywords = "Muyovozi news, school announcements Tanzania, NECTA results news, Kasulu school updates, Kigoma education news, shule habari";
        break;
    case 'calendar':
        $page_title = "Academic Calendar - Term Dates & Important Events | School Management System";
        $page_description = "View our academic calendar including term dates, examination schedules, holidays, and important events for Form Five and Six students at School Management System.";
        $page_keywords = "school calendar Tanzania, Form Five term dates, NECTA exam schedule, Kigoma school holidays, academic year Tanzania, shule kalenda";
        break;
    case 'student-life':
        $page_title = "Student Life - Daily Schedule, Sports & Clubs | School Management System";
        $page_description = "Discover vibrant student life at School Management System. Daily routines, sports activities, clubs and societies, leadership opportunities, and boarding life for advanced level students.";
        $page_keywords = "student life Tanzania, boarding school Kigoma, school clubs and sports, Form Five experience, secondary school activities Tanzania";
        break;
    case 'results':
        $page_title = "NECTA Results - Form Six Examination Performance | School Management System";
        $page_description = "Check School Management System NECTA results, Form Six examination performance, academic achievement statistics, and national ranking. See our excellent pass rates!";
        $page_keywords = "Muyovozi NECTA results, Form Six results Tanzania, advanced level exam results, Kigoma school performance, matokeo ya kidato cha sita";
        break;
    case 'muyo_salama':
        $page_title = "Shule Salama - Safe School Program | School Management System Kigoma";
        $page_description = "School Management System's Shule Salama initiative promoting student safety, health, well-being, and protection. Creating a secure learning environment in Kigoma, Tanzania.";
        $page_keywords = "Shule Salama Tanzania, safe school program Kigoma, student protection Tanzania, school safety initiative, healthy school environment";
        break;
    case 'history':
        $page_title = "History & Heritage - From Refugee Camp to Excellence | School Management System";
        $page_description = "Explore the remarkable journey of School Management System from Mtabila Refugee Camp to becoming a premier advanced level secondary school in Kasulu, Kigoma Region.";
        $page_keywords = "Muyovozi history, Mtabila refugee camp, Kasulu school heritage, Kigoma education history, refugee to excellence Tanzania";
        break;
    case 'mission':
        $page_title = "Mission, Vision & Core Values | School Management System Tanzania";
        $page_description = "Learn about School Management System's mission to provide quality advanced level education, vision for excellence, and core values that guide our community.";
        $page_keywords = "school mission vision Tanzania, Muyovozi values, educational philosophy Kigoma, secondary school goals Tanzania";
        break;
    case 'academic_subjects':
        $page_title = "Academic Subjects - All Form Five & Six Combinations | School Management System";
        $page_description = "Complete list of academic subjects offered at School Management System including Sciences, Arts, and Commercial subjects for advanced level students.";
        $page_keywords = "Form Five subjects, advanced level combinations, PCM PCB HGE HKL CBG ECA, Kigoma school subjects, Tanzania A-Level curriculum";
        break;
    case 'nectaresult':
        $page_title = "NECTA Results Portal - Form Six Examination Results | School Management System";
        $page_description = "Access detailed NECTA results for School Management System. View Form Six examination results, subject performance, and student achievements.";
        $page_keywords = "NECTA results portal, Form Six results 2024, matokeo ya kidato cha sita, advanced level exam results Tanzania, Muyovozi performance";
        break;
    case 'clubs':
        $page_title = "Clubs & Societies - Co-Curricular Activities | School Management System";
        $page_description = "Explore diverse clubs and societies at School Management System including debate, science, journalism, environment, and leadership clubs for student development.";
        $page_keywords = "school clubs Tanzania, student societies Kigoma, debate club Kasulu, science club, leadership opportunities secondary school";
        break;
    case 'sports':
        $page_title = "Sports & Athletics - Football, Basketball, Athletics | School Management System";
        $page_description = "Discover sports programs at School Management System including football, basketball, athletics, volleyball, and other sporting activities for students.";
        $page_keywords = "school sports Tanzania, football Kigoma, basketball Kasulu, athletics secondary school, student sports programs";
        break;
    case 'login':
        $page_title = "Student Portal Login - Access Your Account | School Management System";
        $page_description = "Login to School Management System student portal to access results, academic resources, announcements, and personal information.";
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
    
    <!-- PRIMARY SEO META TAGS -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
    <meta name="author" content="<?php echo $page_author; ?>">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    <meta name="yandex" content="index, follow">
    <meta name="rating" content="General">
    <meta name="language" content="English">
    <meta name="revisit-after" content="7 days">
    <meta name="distribution" content="global">
    <meta name="theme-color" content="#3B9DB3">
    
    <!-- Google Search Console Verification -->
    <meta name="google-site-verification" content="o7arwc_iyjUL-p6ia2-Ov0prfz68ZFRG33iLGRdSJvA" />
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($page_url); ?>">
    
    <!-- Alternate language versions (if needed) -->
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($page_url); ?>">
    <link rel="alternate" hreflang="sw" href="https://muyovozi.sc.tz/sw<?php echo $_SERVER['REQUEST_URI']; ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:image" content="<?php echo $page_image; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="School Management System Campus">
    <meta property="og:site_name" content="School Management System">
    <meta property="og:locale" content="en_TZ">
    <meta property="og:locale:alternate" content="sw_TZ">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@muyovozihigh">
    <meta name="twitter:creator" content="@muyovozihigh">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($page_url); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="twitter:image" content="<?php echo $page_image; ?>">
    <meta name="twitter:image:alt" content="School Management System">
    
    <!-- Favicon Icons - Complete set for all browsers and devices -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
    
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="57x57" href="/apple-touch-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="/apple-touch-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/apple-touch-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="/apple-touch-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/apple-touch-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/apple-touch-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/apple-touch-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    
    <!-- Android Chrome -->
    <link rel="manifest" href="/site.webmanifest">
    <meta name="msapplication-TileColor" content="#3B9DB3">
    <meta name="msapplication-TileImage" content="/mstile-144x144.png">
    <meta name="theme-color" content="#3B9DB3">
    
    <!-- Additional image references for browsers -->
    <link rel="image_src" href="<?php echo $page_image; ?>">
    <meta name="thumbnail" content="<?php echo $page_image; ?>">
    
    <!-- Shortcut icon -->
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/svg+xml" href="/muyovozi.svg">
    
    <!-- Schema.org markup for Google - Enhanced -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "School Management System",
        "alternateName": "Muyovozi Secondary School",
        "url": "https://muyovozi.sc.tz",
        "logo": "https://muyovozi.sc.tz/muyovozi.png",
        "image": "https://muyovozi.sc.tz/images/image1.png",
        "description": "<?php echo htmlspecialchars($page_description); ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Kambi ya Mtabila, Kasulu District",
            "addressLocality": "Kasulu",
            "addressRegion": "Kigoma",
            "postalCode": "47300",
            "addressCountry": "Tanzania"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": "-4.41209",
            "longitude": "30.26763"
        },
        "telephone": "+255622032538",
        "email": "info@muyovozihigh.ac.tz",
        "foundingDate": "2013",
        "numberOfStudents": "1200",
        "numberOfTeachers": "85",
        "educationalLevel": "Advanced Level Secondary School (Form Five - Form Six)",
        "openingHours": "Mo-Fr 08:00-16:30",
        "openingHoursSpecification": [
            {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                "opens": "08:00",
                "closes": "16:30"
            }
        ],
        "sameAs": [
            "https://www.facebook.com/muyovozihigh",
            "https://twitter.com/muyovozihigh",
            "https://www.instagram.com/muyovozihigh"
        ]
    }
    </script>
    
    <!-- BreadcrumbList Schema - Dynamic based on current page -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [{
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://muyovozi.sc.tz/muyovozi_home"
        },{
            "@type": "ListItem",
            "position": 2,
            "name": "<?php echo str_replace('School Management System - ', '', htmlspecialchars($page_title)); ?>",
            "item": "<?php echo htmlspecialchars($page_url); ?>"
        }]
    }
    </script>
    
    <!-- WebSite Schema -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "School Management System",
        "url": "https://muyovozi.sc.tz",
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "https://muyovozi.sc.tz/search?q={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="css/main.css">
    
    <!-- Page-specific CSS -->
    <?php if (isset($page_css) && file_exists($page_css)): ?>
        <link rel="stylesheet" href="<?php echo $page_css; ?>">
    <?php endif; ?>
    
    <!-- Preload critical images -->
    <link rel="preload" href="../images/image1.png" as="image">
    <link rel="preload" href="../images/image4.png" as="image">
    
    <style>
        /* Additional header styles */
        :root {
            --primary-color: #3B9DB3;
            --primary-dark: #2d7c8f;
            --primary-light: #6bb5c7;
            --accent-color: #ffc107;
            --dark-color: #1a2f3a;
        }
        
        /* Ensure favicon displays properly */
        link[rel="icon"] {
            display: inline-block;
        }
        
        /* Additional responsive fixes */
        @media (max-width: 992px) {
            .desktop-nav {
                display: none;
            }
        }
        
        @media (min-width: 993px) {
            .mobile-nav-toggle {
                display: none !important;
            }
        }
        
        /* Fix for dropdown animations */
        .mobile-dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .mobile-dropdown-menu.show {
            max-height: 500px;
            display: block;
        }
        
        /* Prevent body scroll when sidebar is open */
        body.sidebar-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Main Header -->
    <div class="main-header" id="mainHeader">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12">
                    <div class="logo-container">
                        <div class="logo-left">
                            <div class="shield-logo">
                                <img src="../images/image4.png" alt="School Management System Official Logo - Shield Emblem" width="60" height="60" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%233B9DB3%22/><text x=%2250%22 y=%2265%22 font-size=%2250%22 text-anchor=%22middle%22 fill=%22%23ffffff%22>⚔️</text></svg>'">
                            </div>
                        </div>
                        
                        <div class="school-title">
                            <span class="school-main-name">School Management System</span>
                            <span class="school-motto">"Education For Life"</span>
                        </div>
                        
                        <div class="logo-right">
                            <div class="logo-img">
                                <img src="../images/muyovozi.jpg" alt="Muyovozi Secondary School Logo - Excellence in Education" width="60" height="60" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%233B9DB3%22/><text x=%2250%22 y=%2265%22 font-size=%2250%22 text-anchor=%22middle%22 fill=%22%23ffffff%22>M</text></svg>'">
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
                <li><a href="muyovozi_home"><i class="fas fa-home"></i> HOME</a></li>
                <li class="dropdown">
                    <a href="about"><i class="fas fa-info-circle"></i> ABOUT US</a>
                    <div class="dropdown-menu-custom">
                        <a href="about"><i class="fas fa-school"></i> About School</a>
                        <a href="history"><i class="fas fa-history"></i> History & Heritage</a>
                        <a href="mission"><i class="fas fa-bullseye"></i> Mission & Vision</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="academics"><i class="fas fa-book-open"></i> ACADEMICS</a>
                    <div class="dropdown-menu-custom">
                        <a href="academic_subjects"><i class="fas fa-globe"></i> Arts Subjects</a>
                        <a href="calendar"><i class="fas fa-calendar-alt"></i> Academic Calendar</a>
                        <a href="results"><i class="fas fa-clipboard-list"></i> School Result</a>
                        <a href="nectaresult"><i class="fas fa-file-signature"></i> Necta Result</a>
                    </div>
                </li>
                <li class="dropdown">
                    <a href="student-life"><i class="fas fa-users"></i> STUDENT LIFE</a>
                    <div class="dropdown-menu-custom">
                        <a href="clubs"><i class="fas fa-users-cog"></i> Clubs & Societies</a>
                        <a href="sports"><i class="fas fa-futbol"></i> Sports</a>
                        <a href="muyo_salama"><i class="fas fa-heart"></i> Shule salama</a>
                        <a href="gallery"><i class="fas fa-images"></i> Gallery</a>
                    </div>
                </li>
                <li><a href="news"><i class="fas fa-newspaper"></i> NEWS & EVENTS</a></li>
                <li><a href="contact"><i class="fas fa-envelope"></i> CONTACT</a></li>
                <li><a href="login"><i class="fas fa-sign-in-alt"></i> LOGIN</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <!-- Mobile Sidebar -->
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="mobile-sidebar-header">
            <div class="mobile-sidebar-logo">
                <img src="../images/muyovozi.jpg" alt="Muyovozi Logo">
            </div>
            <div class="mobile-sidebar-title">
                <span class="school-name">School Management System</span>
                <span class="school-motto-side">"Education For Life"</span>
            </div>
        </div>
        
        <div class="mobile-sidebar-content">
            <ul class="mobile-nav-menu">
                <li class="mobile-nav-item">
                    <a href="muyovozi_home" class="mobile-nav-link">
                        <i class="fas fa-home"></i> HOME
                    </a>
                </li>
                
                <li class="mobile-nav-item">
                    <button class="mobile-dropdown-trigger" data-dropdown="aboutDropdown">
                        <i class="fas fa-info-circle"></i> ABOUT US
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <div class="mobile-dropdown-menu" id="aboutDropdown">
                        <a href="about" class="mobile-dropdown-link">
                            <i class="fas fa-school"></i> About School
                        </a>
                        <a href="history" class="mobile-dropdown-link">
                            <i class="fas fa-history"></i> History & Heritage
                        </a>
                        <a href="mission" class="mobile-dropdown-link">
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
                        <a href="academic_subjects" class="mobile-dropdown-link">
                            <i class="fas fa-globe"></i> Arts Subjects
                        </a>
                        <a href="calendar" class="mobile-dropdown-link">
                            <i class="fas fa-calendar-alt"></i> Academic Calendar
                        </a>
                        <a href="results" class="mobile-dropdown-link">
                            <i class="fas fa-clipboard-list"></i> School Result
                        </a>
                        <a href="nectaresult" class="mobile-dropdown-link">
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
                        <a href="clubs" class="mobile-dropdown-link">
                            <i class="fas fa-users-cog"></i> Clubs & Societies
                        </a>
                        <a href="sports" class="mobile-dropdown-link">
                            <i class="fas fa-futbol"></i> Sports
                        </a>
                        <a href="muyo_salama" class="mobile-dropdown-link">
                            <i class="fas fa-heart"></i> Shule Salama
                        </a>
                        <a href="gallery" class="mobile-dropdown-link">
                            <i class="fas fa-images"></i> Gallery
                        </a>
                    </div>
                </li>
                
                <li class="mobile-nav-item">
                    <a href="news" class="mobile-nav-link">
                        <i class="fas fa-newspaper"></i> NEWS & EVENTS
                    </a>
                </li>
                
                <li class="mobile-nav-item">
                    <a href="contact" class="mobile-nav-link">
                        <i class="fas fa-envelope"></i> CONTACT
                    </a>
                </li>
                
                <li class="mobile-nav-item">
                    <a href="login" class="mobile-nav-link">
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
    
  <script src="js/main.js"></script>
</body>
</html>