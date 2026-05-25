<?php
/**
 * functions.php
 * Core functions for the Student Management System
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../mhs/login.php");
        exit();
    }
}

/**
 * Get current admin ID
 * @return int|null
 */
function getCurrentAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Get current admin name
 * @return string|null
 */
function getCurrentAdminName() {
    return $_SESSION['admin_name'] ?? null;
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a random password
 * @param int $length
 * @return string
 */
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Format date to readable format
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format datetime to readable format
 * @param string $datetime
 * @param string $format
 * @return string
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i:s') {
    if (empty($datetime)) {
        return '-';
    }
    return date($format, strtotime($datetime));
}

/**
 * Get grade and points from score
 * @param mysqli $conn
 * @param float $score
 * @return array|null
 */
function getGradeInfo($conn, $score) {
    if ($score === null || $score === '' || !is_numeric($score)) {
        return null;
    }
    
    $score = floatval($score);
    $sql = "SELECT grade, points, description FROM grade_mapping 
            WHERE $score BETWEEN min_score AND max_score 
            ORDER BY min_score DESC LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Calculate total points from scores
 * @param mysqli $conn
 * @param array $scores
 * @return int
 */
function calculateTotalPoints($conn, $scores) {
    $total = 0;
    foreach ($scores as $score) {
        if ($score !== null && is_numeric($score)) {
            $gradeInfo = getGradeInfo($conn, $score);
            if ($gradeInfo) {
                $total += intval($gradeInfo['points']);
            }
        }
    }
    return $total;
}

/**
 * Calculate average score
 * @param array $scores
 * @return float|null
 */
function calculateAverage($scores) {
    $validScores = array_filter($scores, function($score) {
        return $score !== null && is_numeric($score) && $score >= 0;
    });
    
    if (count($validScores) > 0) {
        return array_sum($validScores) / count($validScores);
    }
    
    return null;
}

/**
 * Get division from total points
 * @param mysqli $conn
 * @param int $totalPoints
 * @return string|null
 */
function getDivision($conn, $totalPoints) {
    if ($totalPoints === null) {
        return null;
    }
    
    $sql = "SELECT division_name FROM division_rules 
            WHERE $totalPoints BETWEEN min_points AND max_points 
            LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['division_name'];
    }
    
    return 'Not Classified';
}

/**
 * Update student positions for an exam
 * @param mysqli $conn
 * @param int $exam_type_id
 * @param int $academic_year_id
 * @return array
 */
function updateStudentPositions($conn, $exam_type_id, $academic_year_id) {
    $positions = [];
    
    // Get all results ordered by total_points (ascending - lower points is better)
    $sql = "SELECT id, student_id, total_points FROM student_results 
           WHERE exam_type_id = $exam_type_id AND academic_year_id = $academic_year_id
           AND total_points IS NOT NULL
           ORDER BY total_points ASC, average_score DESC";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $position = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            $update_sql = "UPDATE student_results SET position = $position 
                          WHERE id = {$row['id']}";
            mysqli_query($conn, $update_sql);
            $positions[$row['student_id']] = $position;
            $position++;
        }
    }
    
    return $positions;
}

/**
 * Get ordinal number suffix
 * @param int $number
 * @return string
 */
function ordinal($number) {
    $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return $number . 'th';
    }
    return $number . $ends[$number % 10];
}

/**
 * Get CSS class for grade
 * @param string $grade
 * @return string
 */
function getGradeClass($grade) {
    switch ($grade) {
        case 'A':
            return 'text-success fw-bold';
        case 'B':
            return 'text-info fw-bold';
        case 'C':
            return 'text-warning fw-bold';
        case 'D':
            return 'text-secondary fw-bold';
        case 'E':
            return 'text-secondary';
        case 'S':
            return 'text-dark';
        case 'F':
            return 'text-danger';
        default:
            return 'text-muted';
    }
}

/**
 * Get CSS class for division
 * @param string $division
 * @return string
 */
function getDivisionClass($division) {
    switch ($division) {
        case 'Division I':
            return 'bg-success';
        case 'Division II':
            return 'bg-info';
        case 'Division III':
            return 'bg-warning text-dark';
        case 'Division IV':
            return 'bg-secondary';
        case 'Division 0':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

/**
 * Get student full name
 * @param array $student
 * @return string
 */
function getStudentFullName($student) {
    $name = $student['first_name'] . ' ' . $student['last_name'];
    if (!empty($student['second_name'])) {
        $name .= ' ' . $student['second_name'];
    }
    return $name;
}

/**
 * Get student status badge
 * @param bool $status
 * @param bool $isLeaver
 * @return string
 */
function getStudentStatusBadge($status, $isLeaver = false) {
    if ($isLeaver) {
        return '<span class="badge bg-secondary">Leaver</span>';
    }
    
    if ($status) {
        return '<span class="badge bg-success">Active</span>';
    }
    
    return '<span class="badge bg-danger">Inactive</span>';
}

/**
 * Get gender badge
 * @param string $sex
 * @return string
 */
function getGenderBadge($sex) {
    if ($sex == 'Male') {
        return '<span class="badge bg-primary"><i class="fas fa-male me-1"></i>Male</span>';
    }
    
    return '<span class="badge" style="background-color: #e83e8c;"><i class="fas fa-female me-1"></i>Female</span>';
}

/**
 * Log user activity
 * @param mysqli $conn
 * @param int $admin_id
 * @param string $action
 * @param string $description
 * @return bool
 */
function logActivity($conn, $admin_id, $action, $description) {
    $admin_id = mysqli_real_escape_string($conn, $admin_id);
    $action = mysqli_real_escape_string($conn, $action);
    $description = mysqli_real_escape_string($conn, $description);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $sql = "INSERT INTO activity_logs (admin_id, action, description, ip_address, created_at) 
            VALUES ($admin_id, '$action', '$description', '$ip_address', NOW())";
    
    return mysqli_query($conn, $sql);
}

/**
 * Get system settings
 * @param mysqli $conn
 * @param string $key
 * @return string|null
 */
function getSetting($conn, $key) {
    $key = mysqli_real_escape_string($conn, $key);
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = '$key' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['setting_value'];
    }
    
    return null;
}

/**
 * Update system setting
 * @param mysqli $conn
 * @param string $key
 * @param string $value
 * @return bool
 */
function updateSetting($conn, $key, $value) {
    $key = mysqli_real_escape_string($conn, $key);
    $value = mysqli_real_escape_string($conn, $value);
    
    $sql = "INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('$key', '$value')
            ON DUPLICATE KEY UPDATE setting_value = '$value'";
    
    return mysqli_query($conn, $sql);
}

/**
 * Get current academic year
 * @param mysqli $conn
 * @return array|null
 */
function getCurrentAcademicYear($conn) {
    $sql = "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Format file size
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Generate unique filename
 * @param string $original_name
 * @return string
 */
function generateUniqueFilename($original_name) {
    $ext = pathinfo($original_name, PATHINFO_EXTENSION);
    return time() . '_' . uniqid() . '.' . $ext;
}

/**
 * Validate email
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Tanzania format)
 * @param string $phone
 * @return bool
 */
function validatePhone($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Tanzanian number (starts with 0, length 10)
    if (strlen($phone) == 10 && $phone[0] == '0') {
        return true;
    }
    
    // Check if it's a valid international format (+255)
    if (strlen($phone) == 12 && substr($phone, 0, 3) == '255') {
        return true;
    }
    
    return false;
}

/**
 * Format phone number to international format
 * @param string $phone
 * @return string
 */
function formatPhoneInternational($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If starts with 0, replace with 255
    if (strlen($phone) == 10 && $phone[0] == '0') {
        return '255' . substr($phone, 1);
    }
    
    // If already in international format, return as is
    if (strlen($phone) == 12 && substr($phone, 0, 3) == '255') {
        return $phone;
    }
    
    return $phone;
}

/**
 * Get month name in Swahili
 * @param int $month
 * @return string
 */
function getMonthNameSwahili($month) {
    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Machi',
        4 => 'Aprili',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Julai',
        8 => 'Agosti',
        9 => 'Septemba',
        10 => 'Oktoba',
        11 => 'Novemba',
        12 => 'Desemba'
    ];
    
    return $months[$month] ?? '';
}

/**
 * Get day name in Swahili
 * @param int $day (0 = Sunday, 1 = Monday, etc.)
 * @return string
 */
function getDayNameSwahili($day) {
    $days = [
        0 => 'Jumapili',
        1 => 'Jumatatu',
        2 => 'Jumanne',
        3 => 'Jumatano',
        4 => 'Alhamisi',
        5 => 'Ijumaa',
        6 => 'Jumamosi'
    ];
    
    return $days[$day] ?? '';
}

/**
 * Convert number to words (Swahili)
 * @param int $number
 * @return string
 */
function numberToWordsSwahili($number) {
    $words = [
        0 => 'sifuri',
        1 => 'moja',
        2 => 'mbili',
        3 => 'tatu',
        4 => 'nne',
        5 => 'tano',
        6 => 'sita',
        7 => 'saba',
        8 => 'nane',
        9 => 'tisa',
        10 => 'kumi',
        11 => 'kumi na moja',
        12 => 'kumi na mbili',
        13 => 'kumi na tatu',
        14 => 'kumi na nne',
        15 => 'kumi na tano',
        16 => 'kumi na sita',
        17 => 'kumi na saba',
        18 => 'kumi na nane',
        19 => 'kumi na tisa',
        20 => 'ishirini',
        21 => 'ishirini na moja',
        22 => 'ishirini na mbili',
        23 => 'ishirini na tatu',
        24 => 'ishirini na nne',
        25 => 'ishirini na tano',
        26 => 'ishirini na sita',
        27 => 'ishirini na saba',
        28 => 'ishirini na nane',
        29 => 'ishirini na tisa',
        30 => 'thelathini',
        31 => 'thelathini na moja',
        32 => 'thelathini na mbili',
        33 => 'thelathini na tatu',
        34 => 'thelathini na nne',
        35 => 'thelathini na tano',
        36 => 'thelathini na sita',
        37 => 'thelathini na saba',
        38 => 'thelathini na nane',
        39 => 'thelathini na tisa',
        40 => 'arobaini',
        41 => 'arobaini na moja',
        42 => 'arobaini na mbili',
        43 => 'arobaini na tatu',
        44 => 'arobaini na nne',
        45 => 'arobaini na tano',
        46 => 'arobaini na sita',
        47 => 'arobaini na saba',
        48 => 'arobaini na nane',
        49 => 'arobaini na tisa',
        50 => 'hamsini'
    ];
    
    if ($number <= 50) {
        return $words[$number];
    }
    
    return $number; // Return number if beyond our word list
}

/**
 * Generate a unique admission number
 * @param mysqli $conn
 * @return string
 */
function generateAdmissionNumber($conn) {
    $year = date('Y');
    $prefix = 'ADM';
    
    // Get the last admission number
    $sql = "SELECT admission_number FROM students 
            WHERE admission_number LIKE '$prefix$year%' 
            ORDER BY id DESC LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_num = intval(substr($row['admission_number'], -4));
        $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_num = '0001';
    }
    
    return $prefix . $year . $new_num;
}

/**
 * Generate a unique index number
 * @param mysqli $conn
 * @param string $class
 * @param string $combination
 * @return string
 */
function generateIndexNumber($conn, $class, $combination) {
    $school_code = 'S5098';
    $class_code = ($class == 'Form Five') ? '5' : '6';
    
    // Get count of students in this class and combination
    $sql = "SELECT COUNT(*) as count FROM students 
            WHERE class = '$class' AND combination = '$combination' 
            AND is_leaver = FALSE";
    
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $count = $row['count'] + 1;
    
    return $school_code . $class_code . str_pad($count, 3, '0', STR_PAD_LEFT);
}

/**
 * Send SMS notification
 * @param string $phone
 * @param string $message
 * @return bool
 */
function sendSMS($phone, $message) {
    // This is a placeholder - implement actual SMS gateway here
    // You can integrate with services like Africa's Talking, Twilio, etc.
    
    $phone = formatPhoneInternational($phone);
    
    // Example using Africa's Talking API
    /*
    $username = 'your_username';
    $apiKey = 'your_api_key';
    
    $gateway = new AfricasTalkingGateway($username, $apiKey);
    
    try {
        $results = $gateway->sendMessage($phone, $message);
        return true;
    } catch (Exception $e) {
        error_log("SMS Error: " . $e->getMessage());
        return false;
    }
    */
    
    // For now, just log it
    error_log("SMS to $phone: $message");
    return true;
}

/**
 * Send email notification
 * @param string $email
 * @param string $subject
 * @param string $message
 * @return bool
 */
function sendEmail($email, $subject, $message) {
    // This is a placeholder - implement actual email sending here
    // You can use PHPMailer or mail()
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@schoolsystem.com" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Get array of combinations
 * @return array
 */
function getCombinations() {
    return [
        'HGE' => 'History, Geography, English',
        'HGL' => 'History, Geography, Kiswahili',
        'HGK' => 'History, Geography, Communication Skills',
        'HKL' => 'History, Kiswahili, English',
        'KLF' => 'Kiswahili, English, French',
        'EGM' => 'Economics, Geography, Advanced Maths',
        'HLF' => 'History, English, French',
        'HGF' => 'History, Geography, French'
    ];
}

/**
 * Get array of subjects
 * @return array
 */
function getSubjects() {
    return [
        'ac' => 'Academic Communication',
        'htm' => 'Historia Tanzania na Maadili',
        'basic_maths' => 'Basic Applied Mathematics',
        'history' => 'History',
        'geography' => 'Geography',
        'kiswahili' => 'Kiswahili',
        'english' => 'English Language',
        'advanced_maths' => 'Advanced Mathematics',
        'economics' => 'Economics',
        'french' => 'French'
    ];
}

/**
 * Get subject short codes
 * @return array
 */
function getSubjectShortCodes() {
    return [
        'ac' => 'AC',
        'htm' => 'HTM',
        'basic_maths' => 'B/MATH',
        'history' => 'HIST',
        'geography' => 'GEO',
        'kiswahili' => 'KISW',
        'english' => 'ENG',
        'advanced_maths' => 'ADV/M',
        'economics' => 'ECO',
        'french' => 'FREN'
    ];
}

/**
 * Validate date range
 * @param string $start_date
 * @param string $end_date
 * @return bool
 */
function validateDateRange($start_date, $end_date) {
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    
    return $start !== false && $end !== false && $start <= $end;
}

/**
 * Calculate age from date of birth
 * @param string $dob
 * @return int
 */
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}

/**
 * Get user's IP address
 * @return string
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Generate a random token
 * @param int $length
 * @return string
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if a string is JSON
 * @param string $string
 * @return bool
 */
function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * Pretty print array for debugging
 * @param mixed $data
 */
function debugPrint($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

/**
 * Get time ago string
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Truncate text
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Convert string to slug
 * @param string $string
 * @return string
 */
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Check if a table exists
 * @param mysqli $conn
 * @param string $table
 * @return bool
 */
function tableExists($conn, $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return mysqli_num_rows($result) > 0;
}

/**
 * Get database size
 * @param mysqli $conn
 * @return int
 */
function getDatabaseSize($conn) {
    $sql = "SELECT SUM(data_length + index_length) as size 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()";
    
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    return $row['size'] ?? 0;
}

/**
 * Backup database
 * @param mysqli $conn
 * @return bool|string
 */
function backupDatabase($conn) {
    $tables = [];
    $result = mysqli_query($conn, "SHOW TABLES");
    
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    
    $backup = "";
    
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SELECT * FROM $table");
        $numFields = mysqli_num_fields($result);
        
        $backup .= "DROP TABLE IF EXISTS $table;\n";
        $row2 = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE $table"));
        $backup .= $row2[1] . ";\n\n";
        
        for ($i = 0; $i < mysqli_num_rows($result); $i++) {
            $backup .= "INSERT INTO $table VALUES(";
            $row = mysqli_fetch_row($result);
            for ($j = 0; $j < $numFields; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                if (isset($row[$j])) {
                    $backup .= '"' . $row[$j] . '"';
                } else {
                    $backup .= '""';
                }
                if ($j < ($numFields - 1)) {
                    $backup .= ',';
                }
            }
            $backup .= ");\n";
        }
        $backup .= "\n\n";
    }
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = '../backups/' . $filename;
    
    if (!is_dir('../backups')) {
        mkdir('../backups', 0755, true);
    }
    
    if (file_put_contents($filepath, $backup)) {
        return $filepath;
    }
    
    return false;
}

/**
 * Generate random color
 * @return string
 */
function getRandomColor() {
    $colors = ['#3B9DB3', '#28a745', '#dc3545', '#ffc107', '#17a2b8', 
               '#6f42c1', '#fd7e14', '#e83e8c', '#20c997', '#007bff'];
    
    return $colors[array_rand($colors)];
}

/**
 * Get theme color
 * @return string
 */
function getThemeColor() {
    return '#3B9DB3';
}

/**
 * Get system version
 * @return string
 */
function getSystemVersion() {
    return '1.0.0';
}

/**
 * Check if a function is called via AJAX
 * @return bool
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Return JSON response
 * @param bool $success
 * @param string $message
 * @param mixed $data
 */
function jsonResponse($success, $message = '', $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/**
 * Redirect with message
 * @param string $url
 * @param string $message
 * @param string $type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit();
}

/**
 * Get message from session
 * @return array|null
 */
function getSessionMessage() {
    $types = ['success', 'error', 'warning', 'info'];
    
    foreach ($types as $type) {
        if (isset($_SESSION[$type])) {
            $message = $_SESSION[$type];
            unset($_SESSION[$type]);
            return ['type' => $type, 'message' => $message];
        }
    }
    
    return null;
}

/**
 * Display message from session
 */
function displaySessionMessage() {
    $message = getSessionMessage();
    
    if ($message) {
        $icons = [
            'success' => 'check-circle',
            'error' => 'exclamation-circle',
            'warning' => 'exclamation-triangle',
            'info' => 'info-circle'
        ];
        
        $icon = $icons[$message['type']] ?? 'info-circle';
        
        echo '<div class="alert alert-' . $message['type'] . ' alert-dismissible fade show" role="alert">';
        echo '<i class="fas fa-' . $icon . ' me-2"></i>';
        echo htmlspecialchars($message['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Get school information
 * @param mysqli $conn
 * @return array
 */
function getSchoolInfo($conn) {
    $info = [
        'name' => getSetting($conn, 'school_name') ?: 'School Management System',
        'address' => getSetting($conn, 'school_address') ?: '',
        'phone' => getSetting($conn, 'school_phone') ?: '',
        'email' => getSetting($conn, 'school_email') ?: '',
        'website' => getSetting($conn, 'school_website') ?: '',
        'logo' => getSetting($conn, 'school_logo') ?: ''
    ];
    
    return $info;
}

/**
 * Check user permissions
 * @param mysqli $conn
 * @param int $admin_id
 * @param string $permission
 * @return bool
 */
function hasPermission($conn, $admin_id, $permission) {
    // This is a placeholder - implement your permission system here
    // You might have a roles and permissions table
    
    // For now, return true for admin_id = 1 (super admin)
    if ($admin_id == 1) {
        return true;
    }
    
    // Check permissions from database
    $sql = "SELECT COUNT(*) as has_perm FROM admin_permissions ap
            JOIN permissions p ON ap.permission_id = p.id
            WHERE ap.admin_id = $admin_id AND p.permission_key = '$permission'";
    
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    return $row['has_perm'] > 0;
}

/**
 * Get all permissions for an admin
 * @param mysqli $conn
 * @param int $admin_id
 * @return array
 */
function getAdminPermissions($conn, $admin_id) {
    $permissions = [];
    
    $sql = "SELECT p.permission_key FROM admin_permissions ap
            JOIN permissions p ON ap.permission_id = p.id
            WHERE ap.admin_id = $admin_id";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[] = $row['permission_key'];
    }
    
    return $permissions;
}

/**
 * Check if a student exists
 * @param mysqli $conn
 * @param int $student_id
 * @return bool
 */
function studentExists($conn, $student_id) {
    $student_id = mysqli_real_escape_string($conn, $student_id);
    $sql = "SELECT id FROM students WHERE id = $student_id LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    return mysqli_num_rows($result) > 0;
}

/**
 * Get student details
 * @param mysqli $conn
 * @param int $student_id
 * @return array|null
 */
function getStudentDetails($conn, $student_id) {
    $student_id = mysqli_real_escape_string($conn, $student_id);
    $sql = "SELECT * FROM students WHERE id = $student_id LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Get exam types
 * @param mysqli $conn
 * @param bool $active_only
 * @return array
 */
function getExamTypes($conn, $active_only = true) {
    $exam_types = [];
    $sql = "SELECT * FROM exam_types";
    
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    
    $sql .= " ORDER BY exam_order";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $exam_types[] = $row;
    }
    
    return $exam_types;
}

/**
 * Get academic years
 * @param mysqli $conn
 * @param bool $current_only
 * @return array
 */
function getAcademicYears($conn, $current_only = false) {
    $years = [];
    $sql = "SELECT * FROM academic_years";
    
    if ($current_only) {
        $sql .= " WHERE is_current = 1";
    }
    
    $sql .= " ORDER BY year_name DESC";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $years[] = $row;
    }
    
    return $years;
}

/**
 * Get exam sessions
 * @param mysqli $conn
 * @param string $class
 * @return array
 */
function getExamSessions($conn, $class = null) {
    $sessions = [];
    $sql = "SELECT es.*, et.exam_name, ay.year_name 
            FROM exam_sessions es
            JOIN exam_types et ON es.exam_type_id = et.id
            JOIN academic_years ay ON es.academic_year_id = ay.id";
    
    if ($class) {
        $class = mysqli_real_escape_string($conn, $class);
        $sql .= " WHERE es.class = '$class'";
    }
    
    $sql .= " ORDER BY es.exam_date DESC, es.created_at DESC";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $sessions[] = $row;
    }
    
    return $sessions;
}

/**
 * Get exam session details
 * @param mysqli $conn
 * @param int $session_id
 * @return array|null
 */
function getExamSessionDetails($conn, $session_id) {
    $session_id = mysqli_real_escape_string($conn, $session_id);
    $sql = "SELECT es.*, et.exam_name, ay.year_name 
            FROM exam_sessions es
            JOIN exam_types et ON es.exam_type_id = et.id
            JOIN academic_years ay ON es.academic_year_id = ay.id
            WHERE es.id = $session_id LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Get grade mapping
 * @param mysqli $conn
 * @return array
 */
function getGradeMapping($conn) {
    $grades = [];
    $sql = "SELECT * FROM grade_mapping ORDER BY min_score DESC";
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $grades[] = $row;
    }
    
    return $grades;
}

/**
 * Get division rules
 * @param mysqli $conn
 * @return array
 */
function getDivisionRules($conn) {
    $divisions = [];
    $sql = "SELECT * FROM division_rules ORDER BY min_points";
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $divisions[] = $row;
    }
    
    return $divisions;
}

/**
 * Calculate exam summary statistics
 * @param mysqli $conn
 * @param int $session_id
 * @return array
 */
function getExamStatistics($conn, $session_id) {
    $stats = [
        'total_students' => 0,
        'with_results' => 0,
        'class_average' => null,
        'best_points' => null,
        'worst_points' => null,
        'division_counts' => [
            'Division I' => 0,
            'Division II' => 0,
            'Division III' => 0,
            'Division IV' => 0,
            'Division 0' => 0
        ]
    ];
    
    $session_id = mysqli_real_escape_string($conn, $session_id);
    
    // Get session details first
    $session = getExamSessionDetails($conn, $session_id);
    
    if (!$session) {
        return $stats;
    }
    
    // Get statistics
    $sql = "SELECT 
            COUNT(DISTINCT s.id) as total_students,
            SUM(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) as with_results,
            AVG(r.average_score) as class_average,
            MIN(r.total_points) as best_points,
            MAX(r.total_points) as worst_points
            FROM students s
            LEFT JOIN student_results r ON s.id = r.student_id AND r.session_id = $session_id
            WHERE s.class = '{$session['class']}' AND s.is_leaver = FALSE";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        $stats['total_students'] = $data['total_students'];
        $stats['with_results'] = $data['with_results'];
        $stats['class_average'] = $data['class_average'];
        $stats['best_points'] = $data['best_points'];
        $stats['worst_points'] = $data['worst_points'];
    }
    
    // Get division counts
    $sql = "SELECT division, COUNT(*) as count 
            FROM student_results 
            WHERE session_id = $session_id AND division IS NOT NULL
            GROUP BY division";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($stats['division_counts'][$row['division']])) {
            $stats['division_counts'][$row['division']] = $row['count'];
        }
    }
    
    return $stats;
}

/**
 * Get student results for a session
 * @param mysqli $conn
 * @param int $session_id
 * @return array
 */
function getStudentResults($conn, $session_id) {
    $results = [];
    
    $sql = "SELECT s.*, 
            r.ac_score, r.htm_score, r.basic_maths_score, r.history_score, r.geography_score,
            r.kiswahili_score, r.english_score, r.advanced_maths_score, r.economics_score, r.french_score,
            r.ac_grade, r.htm_grade, r.basic_maths_grade, r.history_grade, r.geography_grade,
            r.kiswahili_grade, r.english_grade, r.advanced_maths_grade, r.economics_grade, r.french_grade,
            r.ac_points, r.htm_points, r.basic_maths_points, r.history_points, r.geography_points,
            r.kiswahili_points, r.english_points, r.advanced_maths_points, r.economics_points, r.french_points,
            r.total_points, r.average_score, r.division, r.position
            FROM students s
            LEFT JOIN student_results r ON s.id = r.student_id AND r.session_id = $session_id
            WHERE s.is_leaver = FALSE
            ORDER BY s.index_number ASC";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $results[] = $row;
    }
    
    return $results;
}

/**
 * Export results to CSV
 * @param mysqli $conn
 * @param int $session_id
 * @return string
 */
function exportResultsToCSV($conn, $session_id) {
    $session = getExamSessionDetails($conn, $session_id);
    $results = getStudentResults($conn, $session_id);
    
    $filename = 'results_' . $session['class'] . '_' . $session['exam_name'] . '_' . date('Y-m-d') . '.csv';
    
    $output = fopen('php://temp', 'w');
    
    // Headers
    fputcsv($output, [
        'Index No',
        'First Name',
        'Last Name',
        'Sex',
        'Combination',
        'AC Score',
        'AC Grade',
        'HTM Score',
        'HTM Grade',
        'Basic Maths Score',
        'Basic Maths Grade',
        'History Score',
        'History Grade',
        'Geography Score',
        'Geography Grade',
        'Kiswahili Score',
        'Kiswahili Grade',
        'English Score',
        'English Grade',
        'Advanced Maths Score',
        'Advanced Maths Grade',
        'Economics Score',
        'Economics Grade',
        'French Score',
        'French Grade',
        'Total Points',
        'Average',
        'Division',
        'Position'
    ]);
    
    // Data
    foreach ($results as $student) {
        fputcsv($output, [
            $student['index_number'],
            $student['first_name'],
            $student['last_name'],
            $student['sex'],
            $student['combination'],
            $student['ac_score'],
            $student['ac_grade'],
            $student['htm_score'],
            $student['htm_grade'],
            $student['basic_maths_score'],
            $student['basic_maths_grade'],
            $student['history_score'],
            $student['history_grade'],
            $student['geography_score'],
            $student['geography_grade'],
            $student['kiswahili_score'],
            $student['kiswahili_grade'],
            $student['english_score'],
            $student['english_grade'],
            $student['advanced_maths_score'],
            $student['advanced_maths_grade'],
            $student['economics_score'],
            $student['economics_grade'],
            $student['french_score'],
            $student['french_grade'],
            $student['total_points'],
            $student['average_score'],
            $student['division'],
            $student['position']
        ]);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}