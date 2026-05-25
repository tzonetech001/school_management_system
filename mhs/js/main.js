
    // Mobile Navigation and Dropdown Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mainHeader = document.getElementById('mainHeader');
        const mobileToggle = document.getElementById('mobileNavToggle');
        const mobileSidebar = document.getElementById('mobileSidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        // Handle mobile dropdown triggers
        const dropdownTriggers = document.querySelectorAll('.mobile-dropdown-trigger');
        
        dropdownTriggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const dropdownId = this.getAttribute('data-dropdown');
                const dropdownMenu = document.getElementById(dropdownId);
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                
                // Close all other dropdowns
                dropdownTriggers.forEach(otherTrigger => {
                    if (otherTrigger !== this) {
                        const otherId = otherTrigger.getAttribute('data-dropdown');
                        const otherMenu = document.getElementById(otherId);
                        if (otherMenu && otherMenu.classList.contains('show')) {
                            otherMenu.classList.remove('show');
                            otherTrigger.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
                
                // Toggle current dropdown
                if (dropdownMenu) {
                    if (!isExpanded) {
                        dropdownMenu.classList.add('show');
                        this.setAttribute('aria-expanded', 'true');
                    } else {
                        dropdownMenu.classList.remove('show');
                        this.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.mobile-dropdown-trigger') && !e.target.closest('.mobile-dropdown-menu')) {
                dropdownTriggers.forEach(trigger => {
                    const dropdownId = trigger.getAttribute('data-dropdown');
                    const dropdownMenu = document.getElementById(dropdownId);
                    if (dropdownMenu && dropdownMenu.classList.contains('show')) {
                        dropdownMenu.classList.remove('show');
                        trigger.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });
        
        // Update body padding based on header height
        function updateBodyPadding() {
            const headerHeight = mainHeader ? mainHeader.offsetHeight : 0;
            const desktopNav = document.querySelector('.desktop-nav');
            const navHeight = (window.innerWidth > 992 && desktopNav) ? desktopNav.offsetHeight : 0;
            document.body.style.paddingTop = (headerHeight + navHeight) + 'px';
        }
        
        // Handle scroll effect on header
        window.addEventListener('scroll', function() {
            if (mainHeader) {
                mainHeader.classList.toggle('scrolled', window.scrollY > 50);
            }
        });
        
        // Mobile sidebar toggle functionality
        if (mobileToggle && mobileSidebar) {
            mobileToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const isActive = mobileSidebar.classList.contains('active');
                
                if (!isActive) {
                    // Open sidebar
                    mobileSidebar.classList.add('active');
                    if (mobileOverlay) mobileOverlay.classList.add('active');
                    document.body.classList.add('sidebar-open');
                    this.classList.add('active');
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    }
                } else {
                    // Close sidebar
                    mobileSidebar.classList.remove('active');
                    if (mobileOverlay) mobileOverlay.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                    this.classList.remove('active');
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                    
                    // Also close any open dropdowns
                    dropdownTriggers.forEach(trigger => {
                        const dropdownId = trigger.getAttribute('data-dropdown');
                        const dropdownMenu = document.getElementById(dropdownId);
                        if (dropdownMenu && dropdownMenu.classList.contains('show')) {
                            dropdownMenu.classList.remove('show');
                            trigger.setAttribute('aria-expanded', 'false');
                        }
                    });
                }
            });
            
            // Close sidebar when clicking overlay
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function() {
                    mobileSidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                    if (mobileToggle) {
                        mobileToggle.classList.remove('active');
                        const icon = mobileToggle.querySelector('i');
                        if (icon) {
                            icon.classList.remove('fa-times');
                            icon.classList.add('fa-bars');
                        }
                    }
                });
            }
        }
        
        // Close sidebar on window resize if screen becomes desktop size
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && mobileSidebar && mobileSidebar.classList.contains('active')) {
                mobileSidebar.classList.remove('active');
                if (mobileOverlay) mobileOverlay.classList.remove('active');
                document.body.classList.remove('sidebar-open');
                if (mobileToggle) {
                    mobileToggle.classList.remove('active');
                    const icon = mobileToggle.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            }
            updateBodyPadding();
        });
        
        // Initialize
        updateBodyPadding();
        
        // Set active navigation item based on current page
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.mobile-nav-link, .desktop-nav .nav-menu > li > a, .dropdown-menu-custom a');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.classList.add('active');
            }
        });
    });
    