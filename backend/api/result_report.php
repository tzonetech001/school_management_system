<?php
// API: share/result_report
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Accept GET or POST JSON
$input = $_GET;
$raw = file_get_contents('php://input');
if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) $input = array_merge($input, $json);
}

$phone = trim($input['phone'] ?? '');
$student_id = isset($input['student_id']) ? intval($input['student_id']) : 0;
$exam_type_id = isset($input['exam_type_id']) ? intval($input['exam_type_id']) : 0;
$form = isset($input['form']) ? $input['form'] : '';

// Allow token-based auth via Authorization header: Bearer <token>
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$tokenFromHeader = null;
if ($authHeader) {
    if (stripos($authHeader, 'bearer ') === 0) {
        $tokenFromHeader = trim(substr($authHeader, 7));
    }
}

try {
    $pdo = getDBConnection();
    $phoneFromToken = null;
    if ($tokenFromHeader) {
        // validate token
        $tstmt = $pdo->prepare("SELECT token, phone, expires_at FROM api_tokens WHERE token = ? LIMIT 1");
        $tstmt->execute([$tokenFromHeader]);
        $trow = $tstmt->fetch();
        if ($trow) {
            if (strtotime($trow['expires_at']) < time()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Token expired']);
                exit;
            }
            $phoneFromToken = $trow['phone'];
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        }
    }

    if (!$phoneFromToken && (empty($phone) || !$student_id || !$exam_type_id || empty($form))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'phone (or token), student_id, exam_type_id and form are required']);
        exit;
    }

    if ($phoneFromToken) {
        $phone = $phoneFromToken;
    }

// normalize phone similar to auth/login
$phone = preg_replace('/\s+/', '', $phone);
$phoneWithoutPlus = ltrim($phone, '+');
$phoneVariants = [$phone, $phoneWithoutPlus];
if (strpos($phone, '0') === 0) {
    $phoneVariants[] = '+255' . substr($phone, 1);
    $phoneVariants[] = '255' . substr($phone, 1);
}
if (strpos($phone, '+255') === 0) {
    $phoneVariants[] = ltrim($phone, '+');
}


    // Verify that the student belongs to this parent phone
    $placeholders = rtrim(str_repeat('?,', count($phoneVariants)), ',');
    $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.index_number, s.combination, s.class, s.school_id, sc.school_name FROM students s LEFT JOIN schools sc ON s.school_id = sc.id WHERE s.id = ? AND (s.parent_phone IN ($placeholders)) AND s.status = 1 AND s.is_leaver = 0 LIMIT 1");
    $params = array_merge([$student_id], $phoneVariants);
    $stmt->execute($params);
    $student = $stmt->fetch();
    if (!$student) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Student not found for this phone number']);
        exit;
    }

    // Determine results table
    $results_table = (stripos($form, 'six') !== false) ? 'form_six_results' : 'form_five_results';

    // Fetch student summary
    $stmt = $pdo->prepare("SELECT total_points, average, division FROM $results_table WHERE student_id = ? AND exam_type_id = ? LIMIT 1");
    $stmt->execute([$student_id, $exam_type_id]);
    $summary = $stmt->fetch();

    // Fetch subjects
    $fields = ['ac','htm','his','geo','kisw','eng','b_math','adv_m','eco','fren'];
    $fieldList = implode(', ', $fields);
    $stmt = $pdo->prepare("SELECT $fieldList FROM $results_table WHERE student_id = ? AND exam_type_id = ? LIMIT 1");
    $stmt->execute([$student_id, $exam_type_id]);
    $subs = $stmt->fetch();

    // helper
    function getGradeLetter($marks) {
        if ($marks === null) return null;
        if ($marks >= 80) return 'A';
        if ($marks >= 70) return 'B';
        if ($marks >= 60) return 'C';
        if ($marks >= 50) return 'D';
        if ($marks >= 40) return 'E';
        if ($marks >= 35) return 'S';
        return 'F';
    }

    $subject_display = [
        'ac' => ['name' => 'AC', 'code' => 'AC'],
        'htm' => ['name' => 'HTM', 'code' => 'HTM'],
        'his' => ['name' => 'History', 'code' => 'HIST'],
        'geo' => ['name' => 'Geography', 'code' => 'GEO'],
        'kisw' => ['name' => 'Kiswahili', 'code' => 'KISW'],
        'eng' => ['name' => 'English', 'code' => 'ENG'],
        'b_math' => ['name' => 'Basic Math', 'code' => 'B/MATH'],
        'adv_m' => ['name' => 'Advanced Math', 'code' => 'ADV/M'],
        'eco' => ['name' => 'Economics', 'code' => 'ECO'],
        'fren' => ['name' => 'French', 'code' => 'FREN']
    ];

    $subjects = [];
    foreach ($fields as $f) {
        $marks = isset($subs[$f]) ? $subs[$f] : null;
        $subjects[] = [
            'code' => $subject_display[$f]['code'],
            'name' => $subject_display[$f]['name'],
            'marks' => $marks,
            'grade' => ($marks === null) ? null : getGradeLetter($marks)
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'student' => [
                'id' => $student['id'],
                'index_number' => $student['index_number'],
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'combination' => $student['combination'],
                'class' => $student['class'],
                'school_id' => $student['school_id'] ?? null,
                'school_name' => $student['school_name'] ?? null,
                'total_points' => $summary['total_points'] ?? null,
                'average' => isset($summary['average']) ? floatval($summary['average']) : null,
                'division' => $summary['division'] ?? null
            ],
            'subjects' => $subjects
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

?>