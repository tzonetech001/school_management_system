<?php
// news.php - News Page with External RSS Feeds
session_start();
require_once '../controller/db_connect.php';

// Set page-specific CSS
$page_css = "css/news.css";

include 'header.php';

// ==================== RSS FEED CONFIGURATION ====================
// List of external RSS feeds to fetch news from
$rss_feeds = [
    [
        'name' => 'BBC News - Africa',
        'url' => 'https://feeds.bbci.co.uk/news/world/africa/rss.xml',
        'icon' => 'fab fa-bbc',
        'color' => '#BB1919'
    ],
    [
        'name' => 'Al Jazeera - Africa',
        'url' => 'https://www.aljazeera.com/xml/rss/africa.xml',
        'icon' => 'fas fa-newspaper',
        'color' => '#009A8D'
    ],
    [
        'name' => 'The Citizen Tanzania',
        'url' => 'https://www.thecitizen.co.tz/rss.xml',
        'icon' => 'fas fa-newspaper',
        'color' => '#1A5F7A'
    ],
    [
        'name' => 'Daily News Tanzania',
        'url' => 'https://dailynews.co.tz/feed/',
        'icon' => 'fas fa-newspaper',
        'color' => '#2C3E50'
    ],
    [
        'name' => 'TechCrunch',
        'url' => 'https://techcrunch.com/feed/',
        'icon' => 'fab fa-twitter',
        'color' => '#F97316'
    ],
    [
        'name' => 'GitHub Blog',
        'url' => 'https://github.blog/feed/',
        'icon' => 'fab fa-github',
        'color' => '#181717'
    ],
    [
        'name' => 'PHP Latest News',
        'url' => 'https://www.php.net/releases/feed.php',
        'icon' => 'fab fa-php',
        'color' => '#777BB4'
    ],
    [
        'name' => 'Google News - Tanzania',
        'url' => 'https://news.google.com/rss/search?q=Tanzania&hl=en&gl=TZ&ceid=TZ:en',
        'icon' => 'fab fa-google',
        'color' => '#4285F4'
    ]
];

// ==================== FUNCTION TO FETCH RSS FEED ====================
function fetch_rss_feed($url, $max_items = 10) {
    $all_items = [];
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MuyovoziNewsBot/1.0)');
    
    $xml_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || empty($xml_content)) {
        return [];
    }
    
    // Suppress warnings for malformed XML
    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml_content);
    
    if ($feed === false) {
        return [];
    }
    
    // Handle different RSS formats
    $items = [];
    
    // RSS 2.0 format
    if (isset($feed->channel->item)) {
        foreach ($feed->channel->item as $item) {
            $items[] = $item;
        }
    }
    // Atom format
    elseif (isset($feed->entry)) {
        foreach ($feed->entry as $entry) {
            $items[] = $entry;
        }
    }
    
    // Limit items
    $items = array_slice($items, 0, $max_items);
    
    foreach ($items as $item) {
        // Extract title
        $title = isset($item->title) ? (string)$item->title : '';
        
        // Extract link
        $link = isset($item->link) ? (string)$item->link : '';
        if (isset($item->link['href'])) {
            $link = (string)$item->link['href'];
        }
        
        // Extract description/summary
        $description = '';
        if (isset($item->description)) {
            $description = (string)$item->description;
        } elseif (isset($item->summary)) {
            $description = (string)$item->summary;
        } elseif (isset($item->content)) {
            $description = (string)$item->content;
        }
        
        // Strip HTML tags and limit length
        $description = strip_tags($description);
        $description = strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description;
        
        // Extract pub date
        $pub_date = '';
        if (isset($item->pubDate)) {
            $pub_date = (string)$item->pubDate;
        } elseif (isset($item->published)) {
            $pub_date = (string)$item->published;
        } elseif (isset($item->updated)) {
            $pub_date = (string)$item->updated;
        }
        
        $timestamp = strtotime($pub_date);
        if ($timestamp === false) {
            $timestamp = time();
        }
        
        // Extract image from description or enclosure
        $image_url = '';
        if (isset($item->enclosure) && isset($item->enclosure['url'])) {
            $image_url = (string)$item->enclosure['url'];
        } elseif (isset($item->children('media', true)->content)) {
            $media = $item->children('media', true)->content;
            if (isset($media['url'])) {
                $image_url = (string)$media['url'];
            }
        } else {
            // Try to extract first image from description
            preg_match('/<img[^>]+src="([^">]+)"/', (string)$item->description, $matches);
            if (isset($matches[1])) {
                $image_url = $matches[1];
            }
        }
        
        $all_items[] = [
            'title' => htmlspecialchars($title),
            'link' => $link,
            'description' => htmlspecialchars($description),
            'pub_date' => date('M d, Y H:i', $timestamp),
            'timestamp' => $timestamp,
            'image' => $image_url
        ];
    }
    
    return $all_items;
}

// ==================== CACHE SYSTEM ====================
$cache_file = 'news_cache.json';
$cache_time = 1800; // 30 minutes cache

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    // Load from cache
    $all_news = json_decode(file_get_contents($cache_file), true);
} else {
    // Fetch from all RSS feeds
    $all_news = [];
    
    foreach ($rss_feeds as $feed) {
        $feed_items = fetch_rss_feed($feed['url'], 8);
        foreach ($feed_items as &$item) {
            $item['source'] = $feed['name'];
            $item['source_icon'] = $feed['icon'];
            $item['source_color'] = $feed['color'];
        }
        $all_news = array_merge($all_news, $feed_items);
    }
    
    // Sort by date (newest first)
    usort($all_news, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // Save to cache
    file_put_contents($cache_file, json_encode($all_news));
}

// Pagination
$items_per_page = 12;
$total_items = count($all_news);
$total_pages = ceil($total_items / $items_per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;
$page_items = array_slice($all_news, $offset, $items_per_page);

// Get sources list for filter
$sources = array_unique(array_column($all_news, 'source'));
?>

<main class="main-content">
    
    <!-- HERO SECTION -->
    <section class="news-hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-rss"></i>
                <span>External News Feed</span>
            </div>
            <h1>Latest News</h1>
            <p>Stay updated with the latest news from Tanzania, Africa, and around the world</p>
        </div>
    </section>

    <div class="container">
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search news...">
            </div>
            <select id="sourceFilter" class="filter-select">
                <option value="all">📰 All Sources</option>
                <?php foreach ($sources as $source): ?>
                    <option value="<?php echo htmlspecialchars($source); ?>">
                        <?php echo htmlspecialchars($source); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select id="sortFilter" class="filter-select">
                <option value="newest">📅 Newest First</option>
                <option value="oldest">📅 Oldest First</option>
            </select>
        </div>
        
        <!-- News Grid -->
        <div class="news-grid" id="newsGrid">
            <?php if (count($page_items) > 0): ?>
                <?php foreach ($page_items as $item): ?>
                    <div class="news-card" 
                         data-title="<?php echo strtolower($item['title']); ?>"
                         data-description="<?php echo strtolower($item['description']); ?>"
                         data-source="<?php echo htmlspecialchars($item['source']); ?>"
                         data-date="<?php echo $item['timestamp']; ?>">
                        
                        <div class="news-image">
                            <?php if (!empty($item['image']) && filter_var($item['image'], FILTER_VALIDATE_URL)): ?>
                                <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['title']; ?>" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 200%22%3E%3Crect width=%22400%22 height=%22200%22 fill=%22%233B9DB3%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 fill=%22white%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            <?php endif; ?>
                            <div class="news-source" style="background: <?php echo $item['source_color']; ?>">
                                <i class="<?php echo $item['source_icon']; ?>"></i>
                                <?php echo htmlspecialchars($item['source']); ?>
                            </div>
                        </div>
                        
                        <div class="news-content">
                            <h3 class="news-title">
                                <a href="<?php echo $item['link']; ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo $item['title']; ?>
                                </a>
                            </h3>
                            <p class="news-description"><?php echo $item['description']; ?></p>
                            <div class="news-meta">
                                <span class="news-date">
                                    <i class="far fa-calendar-alt"></i> <?php echo $item['pub_date']; ?>
                                </span>
                                <a href="<?php echo $item['link']; ?>" class="read-more" target="_blank">
                                    Read More <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-news">
                    <i class="fas fa-newspaper"></i>
                    <h3>No News Available</h3>
                    <p>Unable to fetch news at this moment. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>" class="page-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <span class="page-info">
                    Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>" class="page-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- News Sources Section -->
        <div class="sources-section">
            <h3><i class="fas fa-rss"></i> News Sources</h3>
            <div class="sources-grid">
                <?php foreach ($rss_feeds as $feed): ?>
                    <div class="source-card" style="border-left-color: <?php echo $feed['color']; ?>">
                        <div class="source-icon" style="background: <?php echo $feed['color']; ?>">
                            <i class="<?php echo $feed['icon']; ?>"></i>
                        </div>
                        <div class="source-info">
                            <h4><?php echo $feed['name']; ?></h4>
                            <p>Latest news from <?php echo $feed['name']; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Refresh Button -->
        <div class="refresh-section">
            <button onclick="refreshNews()" class="refresh-btn">
                <i class="fas fa-sync-alt"></i> Refresh News (Clear Cache)
            </button>
            <p class="refresh-note">
                <i class="fas fa-info-circle"></i> News updates every 30 minutes
            </p>
        </div>
        
    </div>
</main>

<script>
// Filter and Search Functionality
const searchInput = document.getElementById('searchInput');
const sourceFilter = document.getElementById('sourceFilter');
const sortFilter = document.getElementById('sortFilter');
const newsCards = document.querySelectorAll('.news-card');

function filterAndSortNews() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedSource = sourceFilter.value;
    const sortBy = sortFilter.value;
    
    let visibleCards = [];
    
    newsCards.forEach(card => {
        const title = card.dataset.title || '';
        const description = card.dataset.description || '';
        const source = card.dataset.source || '';
        
        const matchesSearch = searchTerm === '' || title.includes(searchTerm) || description.includes(searchTerm);
        const matchesSource = selectedSource === 'all' || source === selectedSource;
        
        if (matchesSearch && matchesSource) {
            card.style.display = 'block';
            visibleCards.push(card);
        } else {
            card.style.display = 'none';
        }
    });
    
    // Sort visible cards
    const grid = document.getElementById('newsGrid');
    const cardsArray = Array.from(visibleCards);
    
    cardsArray.sort((a, b) => {
        const dateA = parseInt(a.dataset.date);
        const dateB = parseInt(b.dataset.date);
        
        if (sortBy === 'newest') {
            return dateB - dateA;
        } else {
            return dateA - dateB;
        }
    });
    
    // Re-append sorted cards
    cardsArray.forEach(card => {
        grid.appendChild(card);
    });
}

// Event listeners
searchInput.addEventListener('input', filterAndSortNews);
sourceFilter.addEventListener('change', filterAndSortNews);
sortFilter.addEventListener('change', filterAndSortNews);

// Refresh news (clear cache)
function refreshNews() {
    const refreshBtn = document.querySelector('.refresh-btn');
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    fetch('clear_news_cache.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Retry';
                refreshBtn.disabled = false;
                alert('Failed to refresh. Please try again.');
            }
        })
        .catch(error => {
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Retry';
            refreshBtn.disabled = false;
            alert('Network error. Please try again.');
        });
}
</script>

<?php include 'footer.php'; ?>