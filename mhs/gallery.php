<?php
// gallery.php - Photo Gallery for Muyovozi High School
session_start();
require_once '../controller/db_connect.php';

// Set page-specific CSS
$page_css = "css/gallery.css";

include 'header.php';

// ==================== DATABASE QUERIES ====================

// Get all images from notifications table
$notifications_sql = "SELECT 
                        n.id,
                        n.title,
                        n.description,
                        n.file_path,
                        n.file_name,
                        n.file_type,
                        n.file_size,
                        n.created_at,
                        'notification' as source_type,
                        CONCAT(a.first_name, ' ', a.last_name) as uploaded_by
                      FROM notifications n
                      LEFT JOIN admins a ON n.admin_id = a.id
                      WHERE n.file_type IN ('image', 'video')
                      AND n.file_path IS NOT NULL
                      AND n.file_path != ''
                      AND n.status = 'active'
                      ORDER BY n.created_at DESC";

// Get all images from shule_salama_posts table
$shule_salama_sql = "SELECT 
                        sp.id,
                        sp.title,
                        sp.description,
                        sp.file_path,
                        sp.file_name,
                        sp.file_type,
                        sp.file_size,
                        sp.created_at,
                        'shule_salama' as source_type,
                        CONCAT(a.first_name, ' ', a.last_name) as uploaded_by
                      FROM shule_salama_posts sp
                      LEFT JOIN admins a ON sp.admin_id = a.id
                      WHERE sp.file_type IN ('image', 'video')
                      AND sp.file_path IS NOT NULL
                      AND sp.file_path != ''
                      AND sp.status = 'active'
                      AND (sp.visibility = 'public' OR sp.visibility IS NULL)
                      ORDER BY sp.created_at DESC";

// Get images from ps_documents table
$ps_documents_sql = "SELECT 
                        pd.id,
                        pd.title,
                        pd.short_note as description,
                        pd.file_path,
                        pd.file_name,
                        pd.file_type,
                        pd.file_size,
                        pd.created_at,
                        'ps_document' as source_type,
                        CONCAT(a.first_name, ' ', a.last_name) as uploaded_by
                      FROM ps_documents pd
                      LEFT JOIN admins a ON pd.uploaded_by = a.id
                      WHERE pd.file_type IN ('image')
                      AND pd.file_path IS NOT NULL
                      AND pd.file_path != ''
                      AND pd.status = 'active'
                      AND pd.visibility = 'public'
                      ORDER BY pd.created_at DESC";

// Execute queries
$notifications_result = mysqli_query($conn, $notifications_sql);
$shule_salama_result = mysqli_query($conn, $shule_salama_sql);
$ps_documents_result = mysqli_query($conn, $ps_documents_sql);

// Collect all images into one array
$all_images = [];

if ($notifications_result && mysqli_num_rows($notifications_result) > 0) {
    while ($row = mysqli_fetch_assoc($notifications_result)) {
        $all_images[] = $row;
    }
}

if ($shule_salama_result && mysqli_num_rows($shule_salama_result) > 0) {
    while ($row = mysqli_fetch_assoc($shule_salama_result)) {
        $all_images[] = $row;
    }
}

if ($ps_documents_result && mysqli_num_rows($ps_documents_result) > 0) {
    while ($row = mysqli_fetch_assoc($ps_documents_result)) {
        $all_images[] = $row;
    }
}

// Sort by created date (newest first)
usort($all_images, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Get categories for filter
$categories = [];
foreach ($all_images as $image) {
    if (!in_array($image['source_type'], $categories)) {
        $categories[] = $image['source_type'];
    }
}

// Format file size function
function format_file_size($bytes) {
    if ($bytes === null || $bytes === 0 || $bytes === '') return '';
    $bytes = (int)$bytes;
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

// Get source type label
function get_source_label($source_type) {
    switch($source_type) {
        case 'notification':
            return '<span class="badge-source notification"><i class="fas fa-bell"></i> Announcement</span>';
        case 'shule_salama':
            return '<span class="badge-source safety"><i class="fas fa-shield-alt"></i> Safety Post</span>';
        case 'ps_document':
            return '<span class="badge-source document"><i class="fas fa-file-alt"></i> Document</span>';
        default:
            return '<span class="badge-source general"><i class="fas fa-image"></i> Gallery</span>';
    }
}

// Get file icon
function get_file_icon($file_type, $file_path) {
    if (!empty($file_path)) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
            return 'fa-image';
        } elseif (in_array($ext, ['mp4', 'webm', 'ogg', 'mov'])) {
            return 'fa-video';
        } elseif (in_array($ext, ['mp3', 'wav', 'ogg'])) {
            return 'fa-headphones';
        } elseif (in_array($ext, ['pdf'])) {
            return 'fa-file-pdf';
        } elseif (in_array($ext, ['doc', 'docx'])) {
            return 'fa-file-word';
        } elseif (in_array($ext, ['xls', 'xlsx'])) {
            return 'fa-file-excel';
        } elseif (in_array($ext, ['ppt', 'pptx'])) {
            return 'fa-file-powerpoint';
        } elseif (in_array($ext, ['zip', 'rar', '7z'])) {
            return 'fa-file-archive';
        }
    }
    return 'fa-file-image';
}
?>

<main class="main-content">
    
    <!-- HERO SECTION -->
    <section class="gallery-hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-images"></i>
                <span>Official Photo Gallery</span>
            </div>
            <h1>Moments at Muyovozi</h1>
            <p>Explore memorable moments, events, and campus life through our official photo gallery.</p>
        </div>
    </section>

    <div class="container">
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-images"></i></div>
                <div class="stat-number"><?php echo count($all_images); ?></div>
                <div class="stat-label">Total Media</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info-color), #138496);">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo date('Y'); ?></div>
                <div class="stat-label">Current Year</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--accent-color), #e0a800);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number">2014</div>
                <div class="stat-label">Since Founded</div>
            </div>
        </div>
        
        <!-- Category Filters -->
        <div class="category-filters">
            <button class="cat-btn active" data-category="all">📸 All Media</button>
            <?php foreach ($categories as $category): 
                $category_label = '';
                $category_icon = '';
                switch($category) {
                    case 'notification':
                        $category_label = 'Announcements';
                        $category_icon = '🔔';
                        break;
                    case 'shule_salama':
                        $category_label = 'Safety Posts';
                        $category_icon = '🛡️';
                        break;
                    case 'ps_document':
                        $category_label = 'Documents';
                        $category_icon = '📄';
                        break;
                    default:
                        $category_label = ucfirst($category);
                        $category_icon = '📷';
                }
            ?>
                <button class="cat-btn" data-category="<?php echo $category; ?>"><?php echo $category_icon; ?> <?php echo $category_label; ?></button>
            <?php endforeach; ?>
        </div>
        
        <!-- Search and Filter Bar -->
        <div class="filter-bar">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search gallery by title or description...">
            </div>
            <select id="sortFilter" class="filter-select">
                <option value="newest">📅 Newest First</option>
                <option value="oldest">📅 Oldest First</option>
                <option value="az">🔤 A to Z</option>
                <option value="za">🔤 Z to A</option>
            </select>
        </div>
        
        <!-- Gallery Grid -->
        <div class="gallery-grid" id="galleryGrid">
            <?php if (count($all_images) > 0): ?>
                <?php foreach ($all_images as $index => $image): 
                    $file_path = '../' . $image['file_path'];
                    $file_exists = file_exists($file_path);
                    $is_video = in_array(strtolower(pathinfo($image['file_path'], PATHINFO_EXTENSION)), ['mp4', 'webm', 'ogg', 'mov']);
                    $file_icon = get_file_icon($image['file_type'], $image['file_path']);
                    $source_badge = get_source_label($image['source_type']);
                    $uploaded_by = !empty($image['uploaded_by']) ? htmlspecialchars($image['uploaded_by']) : 'Administration';
                    $description = !empty($image['description']) ? strip_tags($image['description']) : '';
                    $description = strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                ?>
                <div class="gallery-card" 
                     data-category="<?php echo $image['source_type']; ?>" 
                     data-title="<?php echo strtolower(htmlspecialchars($image['title'])); ?>"
                     data-description="<?php echo strtolower($description); ?>"
                     data-date="<?php echo $image['created_at']; ?>"
                     data-title-original="<?php echo htmlspecialchars($image['title']); ?>">
                    
                    <div class="image-container" onclick="openLightbox(<?php echo $index; ?>)">
                        <div class="image-source-badge">
                            <?php echo $source_badge; ?>
                        </div>
                        
                        <?php if ($file_exists && !$is_video): ?>
                            <img src="<?php echo $file_path; ?>" alt="<?php echo htmlspecialchars($image['title']); ?>" class="gallery-image" loading="lazy">
                        <?php elseif ($file_exists && $is_video): ?>
                            <video class="gallery-image" style="object-fit: cover;">
                                <source src="<?php echo $file_path; ?>" type="video/mp4">
                            </video>
                            <div class="video-indicator">
                                <i class="fas fa-play"></i> Video
                            </div>
                        <?php else: ?>
                            <div class="gallery-image-placeholder">
                                <i class="fas <?php echo $file_icon; ?> fa-4x"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="image-overlay">
                            <div class="view-icon">
                                <i class="fas fa-search-plus"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-content">
                        <h5 class="card-title"><?php echo htmlspecialchars($image['title']); ?></h5>
                        <?php if ($description): ?>
                            <div class="card-description"><?php echo nl2br(htmlspecialchars($description)); ?></div>
                        <?php endif; ?>
                        <div class="card-meta">
                            <span class="meta-date"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($image['created_at'])); ?></span>
                            <span class="meta-uploader"><i class="fas fa-user"></i> <?php echo $uploaded_by; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-images"></i></div>
                    <h4>No Images Yet</h4>
                    <p class="text-muted">The gallery is currently being populated with images. Check back soon for memorable moments from Muyovozi High School!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Lightbox Modal -->
<div id="lightboxOverlay" class="lightbox-overlay" style="display: none;">
    <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
    <button class="lightbox-prev" onclick="prevImage()"><i class="fas fa-chevron-left"></i></button>
    <button class="lightbox-next" onclick="nextImage()"><i class="fas fa-chevron-right"></i></button>
    <div class="lightbox-content">
        <img id="lightboxImg" src="" alt="">
        <div id="lightboxCaption" class="lightbox-caption"></div>
    </div>
</div>

<script>
// Gallery data for lightbox
const galleryItems = <?php 
    $items = [];
    foreach ($all_images as $image) {
        $file_path = '../' . $image['file_path'];
        $is_video = in_array(strtolower(pathinfo($image['file_path'], PATHINFO_EXTENSION)), ['mp4', 'webm', 'ogg', 'mov']);
        $items[] = [
            'src' => $file_path,
            'title' => htmlspecialchars($image['title']),
            'description' => !empty($image['description']) ? strip_tags($image['description']) : '',
            'is_video' => $is_video,
            'source_type' => $image['source_type']
        ];
    }
    echo json_encode($items);
?>;

let currentLightboxIndex = 0;

function openLightbox(index) {
    currentLightboxIndex = index;
    const item = galleryItems[currentLightboxIndex];
    const overlay = document.getElementById('lightboxOverlay');
    const img = document.getElementById('lightboxImg');
    const caption = document.getElementById('lightboxCaption');
    
    img.style.display = 'block';
    img.src = item.src;
    caption.innerHTML = `<strong>${item.title}</strong><br>${item.description}`;
    
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightboxOverlay').style.display = 'none';
    document.body.style.overflow = '';
}

function prevImage() {
    currentLightboxIndex = (currentLightboxIndex - 1 + galleryItems.length) % galleryItems.length;
    const item = galleryItems[currentLightboxIndex];
    const img = document.getElementById('lightboxImg');
    const caption = document.getElementById('lightboxCaption');
    
    img.src = item.src;
    caption.innerHTML = `<strong>${item.title}</strong><br>${item.description}`;
}

function nextImage() {
    currentLightboxIndex = (currentLightboxIndex + 1) % galleryItems.length;
    const item = galleryItems[currentLightboxIndex];
    const img = document.getElementById('lightboxImg');
    const caption = document.getElementById('lightboxCaption');
    
    img.src = item.src;
    caption.innerHTML = `<strong>${item.title}</strong><br>${item.description}`;
}

// Close lightbox with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightbox();
    } else if (e.key === 'ArrowLeft') {
        if (document.getElementById('lightboxOverlay').style.display === 'flex') {
            prevImage();
        }
    } else if (e.key === 'ArrowRight') {
        if (document.getElementById('lightboxOverlay').style.display === 'flex') {
            nextImage();
        }
    }
});

// Search and Filter Functionality
const searchInput = document.getElementById('searchInput');
const sortFilter = document.getElementById('sortFilter');
const categoryBtns = document.querySelectorAll('.cat-btn');
const galleryCards = document.querySelectorAll('.gallery-card');
let currentCategory = 'all';

function filterAndSortGallery() {
    const searchTerm = searchInput.value.toLowerCase();
    const sortBy = sortFilter.value;
    
    let visibleCards = [];
    
    galleryCards.forEach(card => {
        const category = card.dataset.category;
        const title = card.dataset.title || '';
        const description = card.dataset.description || '';
        
        const matchesCategory = currentCategory === 'all' || category === currentCategory;
        const matchesSearch = searchTerm === '' || title.includes(searchTerm) || description.includes(searchTerm);
        
        if (matchesCategory && matchesSearch) {
            card.style.display = 'block';
            visibleCards.push(card);
        } else {
            card.style.display = 'none';
        }
    });
    
    // Sort visible cards
    const grid = document.getElementById('galleryGrid');
    const cardsArray = Array.from(visibleCards);
    
    cardsArray.sort((a, b) => {
        if (sortBy === 'newest') {
            return new Date(b.dataset.date) - new Date(a.dataset.date);
        } else if (sortBy === 'oldest') {
            return new Date(a.dataset.date) - new Date(b.dataset.date);
        } else if (sortBy === 'az') {
            return (a.dataset.title || '').localeCompare(b.dataset.title || '');
        } else if (sortBy === 'za') {
            return (b.dataset.title || '').localeCompare(a.dataset.title || '');
        }
        return 0;
    });
    
    // Re-append sorted cards
    cardsArray.forEach(card => {
        grid.appendChild(card);
    });
}

function resetFilters() {
    searchInput.value = '';
    sortFilter.value = 'newest';
    currentCategory = 'all';
    categoryBtns.forEach(btn => {
        if (btn.dataset.category === 'all') {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    filterAndSortGallery();
}

// Event listeners
searchInput.addEventListener('input', filterAndSortGallery);
sortFilter.addEventListener('change', filterAndSortGallery);

categoryBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        categoryBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentCategory = btn.dataset.category;
        filterAndSortGallery();
    });
});

// Scroll to Top
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include 'footer.php'; ?>