// script.js - Muyovozi High School Main JavaScript

(function() {
    'use strict';
    
    // ==================== VISIT COUNT & DYNAMIC TIMING LOGIC ====================
    let storageKey = 'muyovozi_visit_count';
    let currentVisitCount = 0;
    
    try {
        let stored = localStorage.getItem(storageKey);
        if (stored !== null) {
            currentVisitCount = parseInt(stored, 10);
            if (isNaN(currentVisitCount)) currentVisitCount = 0;
        }
        // Increment for this visit
        let newCount = currentVisitCount + 1;
        localStorage.setItem(storageKey, newCount);
    } catch(e) {
        currentVisitCount = 0;
    }
    
    let previousCount = currentVisitCount;
    let loadDuration = 1000; // default 1 second
    
    // Dynamic timing based on visit count
    if (previousCount === 0) {
        loadDuration = 6000;   // First time: 6 seconds
    } else if (previousCount === 1) {
        loadDuration = 4000;   // Second time: 4 seconds
    } else if (previousCount === 2) {
        loadDuration = 2000;   // Third time: 2 seconds
    } else {
        loadDuration = 1000;   // Always 1 second after 3+ visits
    }
    
    // ==================== UPDATE LOADER MESSAGE ====================
    const loaderText = document.getElementById('loaderText');
    if (loaderText) {
        if (previousCount === 0) {
            loaderText.innerText = '✨ Welcome first time visitor! ✨';
        } else if (previousCount === 1) {
            loaderText.innerText = '🌟 Welcome back! 🌟';
        } else if (previousCount === 2) {
            loaderText.innerText = '⚡ Fast loading for you! ⚡';
        } else {
            loaderText.innerText = '🚀 Instant access! 🚀';
        }
    }
    
    // ==================== UPDATE LOADER DISPLAY WITH TIMER ====================
    const loaderTitle = document.querySelector('.loader-title');
    if (loaderTitle) {
        console.log(`Visit #${previousCount + 1} - Loading for ${loadDuration / 1000} seconds`);
    }
    
    // ==================== REDIRECT FUNCTION ====================
    const redirectTarget = "mhs/";
    
    function performRedirect() {
        const loader = document.getElementById('loaderScreen');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => {
                window.location.href = redirectTarget;
            }, 400);
        } else {
            window.location.href = redirectTarget;
        }
    }
    
    // ==================== REDIRECT AFTER DYNAMIC DURATION ====================
    let redirectTimer = setTimeout(performRedirect, loadDuration);
    
    // ==================== MANUAL LINK HANDLER ====================
    const manualLink = document.getElementById('manualLink');
    if (manualLink) {
        manualLink.addEventListener('click', function(e) {
            e.preventDefault();
            clearTimeout(redirectTimer);
            performRedirect();
        });
    }
    
    // ==================== EMERGENCY FALLBACK REDIRECT ====================
    setTimeout(function() {
        if (document.getElementById('loaderScreen') && window.location.pathname === '/') {
            window.location.href = redirectTarget;
        }
    }, loadDuration + 2000);
    
    // ==================== SCHEMA.ORG STRUCTURED DATA ====================
    const scriptLd = document.createElement('script');
    scriptLd.type = 'application/ld+json';
    scriptLd.textContent = JSON.stringify({
        "@context": "https://schema.org",
        "@type": "HighSchool",
        "name": "Muyovozi High School",
        "alternateName": "Muyovozi Secondary School",
        "url": "https://muyovozi.sc.tz/",
        "logo": "https://muyovozi.sc.tz/mhs/logo.png",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Kambi ya Mtabila, Kasulu District",
            "addressLocality": "Kasulu",
            "addressRegion": "Kigoma",
            "addressCountry": "Tanzania"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": "-4.41209",
            "longitude": "30.26763"
        },
        "telephone": "+255622032538",
        "email": "info@muyovozihigh.sc.tz",
        "description": "Advanced Level Secondary School in Kasulu, Kigoma, Tanzania. Offers Form Five and Form Six education with excellent NECTA results.",
        "foundingDate": "2013",
        "numberOfStudents": "1200",
        "numberOfTeachers": "85",
        "educationalLevel": "Advanced Level Secondary School (Form Five - Form Six)"
    });
    document.head.appendChild(scriptLd);
    
    // ==================== BREADCRUMB LIST SCHEMA ====================
    const breadcrumbLd = document.createElement('script');
    breadcrumbLd.type = 'application/ld+json';
    breadcrumbLd.textContent = JSON.stringify({
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [{
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://muyovozi.sc.tz/"
        }]
    });
    document.head.appendChild(breadcrumbLd);
    
    // ==================== CONSOLE LOG FOR DEBUGGING ====================
    console.log(`Muyovozi High School - Visit #${previousCount + 1} | Loading: ${loadDuration / 1000}s | Redirecting to: ${redirectTarget}`);
    
    // ==================== SMOOTH SCROLL FOR LINKS ====================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== "#" && href !== "#seoContent") {
                return;
            }
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    
})();