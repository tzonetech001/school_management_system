<?php
// calendar.php - Academic Calendar Page
session_start();
require_once '../controller/db_connect.php';

// Set page-specific CSS
$page_css = "css/calendar.css";

include 'header.php';

// Get current month and year
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Adjust for previous/next navigation
if (isset($_GET['prev'])) {
    $current_month--;
    if ($current_month < 1) {
        $current_month = 12;
        $current_year--;
    }
}
if (isset($_GET['next'])) {
    $current_month++;
    if ($current_month > 12) {
        $current_month = 1;
        $current_year++;
    }
}

// Get month name
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Get first day of month
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$first_day_week = date('w', $first_day_of_month); // 0 = Sunday, 6 = Saturday

// Get number of days in month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);

// Get current day for highlighting
$today_day = date('j');
$today_month = date('n');
$today_year = date('Y');

// Array of events (in real app, fetch from database)
$events = [];

// Sample events - in production, fetch from database
$sample_events = [
    ['date' => '2024-01-10', 'title' => 'School Opens', 'type' => 'academic'],
    ['date' => '2024-01-15', 'title' => 'Parents Meeting', 'type' => 'meeting'],
    ['date' => '2024-02-14', 'title' => 'Mid-Term Exams', 'type' => 'exam'],
    ['date' => '2024-03-20', 'title' => 'Sports Day', 'type' => 'event'],
    ['date' => '2024-04-07', 'title' => 'Public Holiday', 'type' => 'holiday'],
    ['date' => '2024-05-01', 'title' => 'Labour Day', 'type' => 'holiday'],
    ['date' => '2024-06-10', 'title' => 'Final Exams', 'type' => 'exam'],
    ['date' => '2024-07-07', 'title' => 'Saba Saba Day', 'type' => 'holiday'],
    ['date' => '2024-08-08', 'title' => 'Nane Nane Day', 'type' => 'holiday'],
    ['date' => '2024-09-15', 'title' => 'Open Day', 'type' => 'event'],
    ['date' => '2024-10-14', 'title' => 'Nyerere Day', 'type' => 'holiday'],
    ['date' => '2024-11-10', 'title' => 'Form Four Exams', 'type' => 'exam'],
    ['date' => '2024-12-09', 'title' => 'Independence Day', 'type' => 'holiday'],
    ['date' => '2024-12-15', 'title' => 'School Closes', 'type' => 'academic'],
];

// Add sample events if year matches
foreach ($sample_events as $event) {
    $event_year = (int)substr($event['date'], 0, 4);
    if ($event_year == $current_year) {
        $events[$event['date']][] = $event;
    }
}

// SQL to fetch events from database (uncomment when database table is ready)
/*
$events_sql = "SELECT * FROM calendar_events 
               WHERE YEAR(event_date) = $current_year 
               AND MONTH(event_date) = $current_month
               AND status = 'active'
               ORDER BY event_date ASC";
$events_result = mysqli_query($conn, $events_sql);
if ($events_result && mysqli_num_rows($events_result) > 0) {
    while ($row = mysqli_fetch_assoc($events_result)) {
        $events[$row['event_date']][] = $row;
    }
}
*/

// Get upcoming events (next 30 days)
$upcoming_events = [];
$today_date = date('Y-m-d');
$next_month_date = date('Y-m-d', strtotime('+30 days'));

foreach ($sample_events as $event) {
    if ($event['date'] >= $today_date && $event['date'] <= $next_month_date) {
        $upcoming_events[] = $event;
    }
}
sort($upcoming_events);
?>

<main class="main-content">
    
    <!-- HERO SECTION -->
    <section class="calendar-hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-calendar-alt"></i>
                <span>Academic Calendar</span>
            </div>
            <h1>School Calendar</h1>
            <p>Stay updated with important academic dates, events, and holidays.</p>
        </div>
    </section>

    <div class="container">
        
        <!-- Calendar Navigation -->
        <div class="calendar-navigation">
            <a href="?prev=1&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" class="nav-btn">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <div class="current-month">
                <i class="fas fa-calendar-day"></i>
                <?php echo $month_names[$current_month] . ' ' . $current_year; ?>
            </div>
            <a href="?next=1&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" class="nav-btn">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        
        <!-- Calendar Grid -->
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
            </div>
            
            <div class="calendar-grid">
                <?php
                // Fill empty days before first day of month
                for ($i = 0; $i < $first_day_week; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                
                // Fill days of month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date_str = sprintf("%04d-%02d-%02d", $current_year, $current_month, $day);
                    $is_today = ($day == $today_day && $current_month == $today_month && $current_year == $today_year);
                    $has_events = isset($events[$date_str]) && count($events[$date_str]) > 0;
                    
                    $day_class = 'calendar-day';
                    if ($is_today) $day_class .= ' today';
                    if ($has_events) $day_class .= ' has-event';
                    
                    echo '<div class="' . $day_class . '">';
                    echo '<span class="day-number">' . $day . '</span>';
                    
                    if ($has_events) {
                        echo '<div class="event-dot">';
                        foreach ($events[$date_str] as $event) {
                            $event_class = '';
                            switch($event['type']) {
                                case 'holiday': $event_class = 'event-holiday'; break;
                                case 'exam': $event_class = 'event-exam'; break;
                                case 'meeting': $event_class = 'event-meeting'; break;
                                case 'event': $event_class = 'event-event'; break;
                                default: $event_class = 'event-academic';
                            }
                            echo '<span class="event-badge ' . $event_class . '" title="' . htmlspecialchars($event['title']) . '">';
                            echo '<i class="fas fa-circle"></i>';
                            echo '</span>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <!-- Event Legend -->
        <div class="legend-section">
            <h3><i class="fas fa-tag"></i> Event Legend</h3>
            <div class="legend-items">
                <div class="legend-item">
                    <span class="legend-color academic"></span>
                    <span>Academic Event</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color exam"></span>
                    <span>Examination</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color meeting"></span>
                    <span>Meeting</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color holiday"></span>
                    <span>Holiday</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color event"></span>
                    <span>Special Event</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color today"></span>
                    <span>Today</span>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Events Section -->
        <div class="upcoming-section">
            <h3><i class="fas fa-clock"></i> Upcoming Events (Next 30 Days)</h3>
            <div class="upcoming-list">
                <?php if (count($upcoming_events) > 0): ?>
                    <?php foreach ($upcoming_events as $event): 
                        $event_class = '';
                        switch($event['type']) {
                            case 'holiday': $event_class = 'upcoming-holiday'; break;
                            case 'exam': $event_class = 'upcoming-exam'; break;
                            case 'meeting': $event_class = 'upcoming-meeting'; break;
                            case 'event': $event_class = 'upcoming-event'; break;
                            default: $event_class = 'upcoming-academic';
                        }
                    ?>
                        <div class="upcoming-item <?php echo $event_class; ?>">
                            <div class="upcoming-date">
                                <span class="upcoming-day"><?php echo date('d', strtotime($event['date'])); ?></span>
                                <span class="upcoming-month"><?php echo date('M', strtotime($event['date'])); ?></span>
                            </div>
                            <div class="upcoming-info">
                                <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                <p><?php echo date('l, F j, Y', strtotime($event['date'])); ?></p>
                            </div>
                            <div class="upcoming-type">
                                <span class="type-badge <?php echo $event['type']; ?>">
                                    <?php 
                                        switch($event['type']) {
                                            case 'holiday': echo 'Holiday'; break;
                                            case 'exam': echo 'Exam'; break;
                                            case 'meeting': echo 'Meeting'; break;
                                            case 'event': echo 'Event'; break;
                                            default: echo 'Academic';
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-upcoming">
                        <i class="fas fa-calendar-check"></i>
                        <p>No upcoming events in the next 30 days.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        
        <!-- Download Calendar -->
        <div class="download-section">
            <a href="#" class="download-btn" onclick="downloadCalendar()">
                <i class="fas fa-download"></i> Download Calendar (PDF)
            </a>
            <a href="#" class="download-btn secondary" onclick="addToGoogleCalendar()">
                <i class="fab fa-google"></i> Add to Google Calendar
            </a>
        </div>
        
    </div>
</main>

<script>
// Download Calendar function
function downloadCalendar() {
    alert("Calendar PDF download will be available soon.");
}

// Add to Google Calendar
function addToGoogleCalendar() {
    alert("Google Calendar integration coming soon.");
}

// Print Calendar
function printCalendar() {
    window.print();
}
</script>

<?php include 'footer.php'; ?>