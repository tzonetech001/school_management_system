<?php
// get_rooms.php - Enhanced version with data validation
session_start();
require_once '../controller/db_connect.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users, but log them

if (!isset($_GET['dormitory_id'])) {
    echo json_encode(['error' => 'Dormitory ID is required']);
    exit();
}

$dormitory_id = intval($_GET['dormitory_id']);

if ($dormitory_id <= 0) {
    echo json_encode(['error' => 'Invalid dormitory ID']);
    exit();
}

// 1. First, clean up any data inconsistencies
cleanupDormitoryData($conn, $dormitory_id);

// 2. Get dormitory details
$dormitory = getDormitoryDetails($conn, $dormitory_id);

if (!$dormitory) {
    echo json_encode(['error' => 'Dormitory not found or inactive']);
    exit();
}

// 3. Get available rooms
$rooms = getAvailableRooms($conn, $dormitory_id);

// 4. Get statistics
$statistics = getDormitoryStatistics($conn, $dormitory_id, $dormitory);

echo json_encode([
    'dormitory' => $dormitory,
    'rooms' => $rooms,
    'statistics' => $statistics,
    'timestamp' => date('Y-m-d H:i:s')
]);

// ==================== FUNCTIONS ====================

/**
 * Clean up dormitory data inconsistencies
 */
function cleanupDormitoryData($conn, $dormitory_id) {
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Find and remove assignments for leavers/graduated students
        $cleanup_leavers_sql = "UPDATE student_dormitory sd
                               JOIN students s ON sd.student_id = s.id
                               SET sd.status = 'Left',
                                   sd.notes = CONCAT(COALESCE(sd.notes, ''), ' | Auto-removed at cleanup'),
                                   sd.updated_at = CURRENT_TIMESTAMP
                               WHERE sd.dormitory_id = $dormitory_id
                               AND sd.status = 'Active'
                               AND (s.is_leaver = TRUE 
                                    OR s.class IN ('Leavers', 'Graduated') 
                                    OR s.graduation_status IN ('Graduated', 'Left'))";
        
        if (!mysqli_query($conn, $cleanup_leavers_sql)) {
            throw new Exception("Failed to cleanup leavers: " . mysqli_error($conn));
        }
        
        // 2. Recalculate room occupancy based on active assignments
        $recalculate_sql = "UPDATE dormitory_rooms dr
                           SET dr.current_occupancy = (
                               SELECT COUNT(*) 
                               FROM student_dormitory sd2 
                               WHERE sd2.room_id = dr.id 
                               AND sd2.status = 'Active'
                               AND sd2.dormitory_id = $dormitory_id
                           )
                           WHERE dr.dormitory_id = $dormitory_id";
        
        if (!mysqli_query($conn, $recalculate_sql)) {
            throw new Exception("Failed to recalculate occupancy: " . mysqli_error($conn));
        }
        
        // 3. Recalculate dormitory occupancy
        $recalculate_dorm_sql = "UPDATE dormitories d
                                SET d.current_occupancy = (
                                    SELECT SUM(dr2.current_occupancy)
                                    FROM dormitory_rooms dr2
                                    WHERE dr2.dormitory_id = d.id
                                )
                                WHERE d.id = $dormitory_id";
        
        if (!mysqli_query($conn, $recalculate_dorm_sql)) {
            throw new Exception("Failed to recalculate dormitory occupancy: " . mysqli_error($conn));
        }
        
        // 4. Update room statuses
        $update_status_sql = "UPDATE dormitory_rooms dr
                             SET dr.status = CASE 
                                 WHEN dr.current_occupancy >= dr.capacity THEN 'Full'
                                 ELSE 'Available'
                             END
                             WHERE dr.dormitory_id = $dormitory_id";
        
        if (!mysqli_query($conn, $update_status_sql)) {
            throw new Exception("Failed to update room statuses: " . mysqli_error($conn));
        }
        
        mysqli_commit($conn);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Data cleanup error for dormitory $dormitory_id: " . $e->getMessage());
    }
}

/**
 * Get dormitory details
 */
function getDormitoryDetails($conn, $dormitory_id) {
    $dorm_sql = "SELECT * FROM dormitories 
                 WHERE id = $dormitory_id 
                 AND status IN ('Active', 'Full')";
    
    $dorm_result = mysqli_query($conn, $dorm_sql);
    
    if (!$dorm_result || mysqli_num_rows($dorm_result) == 0) {
        return null;
    }
    
    return mysqli_fetch_assoc($dorm_result);
}

/**
 * Get available rooms
 */
function getAvailableRooms($conn, $dormitory_id) {
    $rooms_sql = "SELECT 
                    dr.id,
                    dr.room_number,
                    dr.room_label,
                    dr.capacity,
                    dr.current_occupancy,
                    (dr.capacity - dr.current_occupancy) as available_beds,
                    dr.status,
                    dr.created_at,
                    dr.updated_at,
                    -- Count active, non-leaver students in this room
                    (SELECT COUNT(*) 
                     FROM student_dormitory sd 
                     JOIN students s ON sd.student_id = s.id
                     WHERE sd.room_id = dr.id 
                     AND sd.status = 'Active'
                     AND s.is_leaver = FALSE
                     AND s.class IN ('Form Five', 'Form Six')
                     AND s.graduation_status NOT IN ('Graduated', 'Left')) as eligible_students
                  FROM dormitory_rooms dr 
                  WHERE dr.dormitory_id = $dormitory_id 
                  AND dr.status = 'Available'
                  AND dr.current_occupancy < dr.capacity
                  ORDER BY CAST(SUBSTRING(room_number FROM 2) AS UNSIGNED), room_number";
    
    $rooms_result = mysqli_query($conn, $rooms_sql);
    $rooms = [];
    
    if ($rooms_result && mysqli_num_rows($rooms_result) > 0) {
        while ($row = mysqli_fetch_assoc($rooms_result)) {
            // Verify data consistency
            if ($row['current_occupancy'] != $row['eligible_students']) {
                // Data inconsistency - log it
                error_log(sprintf(
                    "Room %s (%d) has occupancy %d but only %d eligible students",
                    $row['room_number'],
                    $row['id'],
                    $row['current_occupancy'],
                    $row['eligible_students']
                ));
            }
            
            // Add room to results
            $rooms[] = [
                'id' => $row['id'],
                'room_number' => $row['room_number'],
                'room_label' => $row['room_label'],
                'capacity' => $row['capacity'],
                'current_occupancy' => $row['current_occupancy'],
                'available_beds' => $row['available_beds'],
                'status' => $row['status'],
                'data_quality' => [
                    'occupancy_matches_eligibility' => ($row['current_occupancy'] == $row['eligible_students']),
                    'eligible_students_count' => $row['eligible_students']
                ]
            ];
        }
    }
    
    return $rooms;
}

/**
 * Get dormitory statistics
 */
function getDormitoryStatistics($conn, $dormitory_id, $dormitory) {
    // Total active, eligible students in dormitory
    $active_students_sql = "SELECT COUNT(DISTINCT sd.id) as active_students_count
                           FROM student_dormitory sd
                           JOIN students s ON sd.student_id = s.id
                           WHERE sd.dormitory_id = $dormitory_id
                           AND sd.status = 'Active'
                           AND s.is_leaver = FALSE
                           AND s.class IN ('Form Five', 'Form Six')
                           AND s.graduation_status NOT IN ('Graduated', 'Left')";
    
    $active_result = mysqli_query($conn, $active_students_sql);
    $active_data = mysqli_fetch_assoc($active_result);
    $active_students_count = $active_data['active_students_count'] ?? 0;
    
    // Get room statistics
    $room_stats_sql = "SELECT 
                        COUNT(*) as total_rooms,
                        COUNT(CASE WHEN status = 'Available' THEN 1 END) as available_rooms,
                        COUNT(CASE WHEN status = 'Full' THEN 1 END) as full_rooms,
                        COUNT(CASE WHEN status = 'Maintenance' THEN 1 END) as maintenance_rooms,
                        SUM(capacity) as total_capacity,
                        SUM(current_occupancy) as total_occupancy,
                        SUM(capacity - current_occupancy) as total_available_beds
                       FROM dormitory_rooms 
                       WHERE dormitory_id = $dormitory_id";
    
    $room_result = mysqli_query($conn, $room_stats_sql);
    $room_stats = mysqli_fetch_assoc($room_result);
    
    // Calculate occupancy rates
    $total_capacity = $room_stats['total_capacity'] ?? 0;
    $total_occupancy = $room_stats['total_occupancy'] ?? 0;
    $occupancy_rate = $total_capacity > 0 ? round(($total_occupancy / $total_capacity) * 100, 2) : 0;
    
    // Real available beds (considering only eligible students)
    $real_available_beds = $total_capacity - $active_students_count;
    
    return [
        'rooms' => [
            'total' => $room_stats['total_rooms'] ?? 0,
            'available' => $room_stats['available_rooms'] ?? 0,
            'full' => $room_stats['full_rooms'] ?? 0,
            'maintenance' => $room_stats['maintenance_rooms'] ?? 0
        ],
        'capacity' => [
            'total' => $total_capacity,
            'occupied' => $total_occupancy,
            'available' => $room_stats['total_available_beds'] ?? 0,
            'real_available' => max(0, $real_available_beds)
        ],
        'students' => [
            'active_eligible' => $active_students_count,
            'occupancy_rate' => $occupancy_rate
        ],
        'data_quality' => [
            'occupancy_mismatch' => ($total_occupancy != $active_students_count),
            'mismatch_count' => abs($total_occupancy - $active_students_count)
        ]
    ];
}
?>