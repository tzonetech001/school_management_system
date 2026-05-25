<?php
// clear_news_cache.php - Clear news cache and fetch fresh data
header('Content-Type: application/json');

$cache_file = 'news_cache.json';

if (file_exists($cache_file)) {
    unlink($cache_file);
}

echo json_encode(['success' => true]);
?>