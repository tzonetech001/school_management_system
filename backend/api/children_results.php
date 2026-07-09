<?php
// API: share/children_results
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$input = $_GET;
$raw = file_get_contents('php://input');
if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) $input = array_merge($input, $json);
}

$phone = trim($input['phone'] ?? '');
$exam_type_id = isset($input['exam_type_id']) ? intval($input['exam_type_id']) : 0;
$form = isset($input['form']) ? $input['form'] : '';

// Token support
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

    if (!$phoneFromToken && empty($phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'phone or token required']);
        exit;
    }

    if ($phoneFromToken) $phone = $phoneFromToken;

    // normalize phone variants
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

    // fetch children
    $placeholders = rtrim(str_repeat('?,', count($phoneVariants)), ',');
    $stmt = $pdo->prepare("SELECT s.id, CONCAT(s.first_name, ' ', COALESCE(s.second_name,''), ' ', s.last_name) AS name, s.class, s.combination, s.index_number, s.school_id, sc.school_name FROM students s LEFT JOIN schools sc ON s.school_id = sc.id WHERE s.parent_phone IN ($placeholders) AND s.status = 1 AND s.is_leaver = 0");
    $stmt->execute($phoneVariants);
    $children = $stmt->fetchAll();

    // optionally include results for each child
    $includeResults = ($exam_type_id && $form);
    $fields = ['ac','htm','his','geo','kisw','eng','b_math','adv_m','eco','fren'];

    $resultList = [];
    foreach ($children as $c) {
        $entry = [
            'id' => $c['id'],
            'name' => $c['name'],
            'class' => $c['class'],
            'combination' => $c['combination'],
            'index_number' => $c['index_number'],
            'school_id' => $c['school_id'] ?? null,
            'school_name' => $c['school_name'] ?? null
        ];
        if ($includeResults) {
            $results_table = (stripos($form, 'six') !== false) ? 'form_six_results' : 'form_five_results';
            $sstmt = $pdo->prepare("SELECT total_points, average, division FROM $results_table WHERE student_id = ? AND exam_type_id = ? LIMIT 1");
            $sstmt->execute([$c['id'], $exam_type_id]);
            $summary = $sstmt->fetch();

            $fieldList = implode(', ', $fields);
            $sstmt = $pdo->prepare("SELECT $fieldList FROM $results_table WHERE student_id = ? AND exam_type_id = ? LIMIT 1");
            $sstmt->execute([$c['id'], $exam_type_id]);
            $subs = $sstmt->fetch();

            $subjects = [];
            foreach ($fields as $f) {
                $marks = $subs[$f] ?? null;
                $subjects[$f] = $marks;
            }

            $entry['results'] = [
                'total_points' => $summary['total_points'] ?? null,
                'average' => isset($summary['average']) ? floatval($summary['average']) : null,
                'division' => $summary['division'] ?? null,
                'subjects' => $subjects
            ];
        }
        $resultList[] = $entry;
    }

    echo json_encode(['success' => true, 'children' => $resultList]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

?>