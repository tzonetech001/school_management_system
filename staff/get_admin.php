<?php
// get_admin.php
session_start();
require_once '../controller/db_connect.php';

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $sql = "SELECT a.*, 
            GROUP_CONCAT(DISTINCT CONCAT(ar.role_name, '(', IF(ara.is_primary=1, 'Primary', 'Secondary'), ')') 
            ORDER BY ara.is_primary DESC, ar.role_name SEPARATOR ', ') as roles_with_type
            FROM admins a
            LEFT JOIN admin_role_assignments ara ON a.id = ara.admin_id
            LEFT JOIN admin_roles ar ON ara.role_id = ar.id
            WHERE a.id = $id
            GROUP BY a.id";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $admin = mysqli_fetch_assoc($result);
        
        // Get profile image
        $profile_image = '../uploads/profiles/' . ($admin['profile_image'] ?: 'default.jpg');
        if (!file_exists($profile_image) || empty($admin['profile_image'])) {
            $profile_image_url = 'https://ui-avatars.com/api/?name=' . urlencode($admin['first_name'] . '+' . $admin['last_name']) . '&size=150&background=3B9DB3&color=fff&bold=true';
        } else {
            $profile_image_url = $profile_image;
        }
        
        echo '<div class="row">';
        echo '<div class="col-md-4 text-center mb-4">';
        echo '<div class="mb-3">';
        echo '<img src="' . $profile_image_url . '" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;" onerror="this.src=\'https://ui-avatars.com/api/?name=' . urlencode($admin['first_name'] . '+' . $admin['last_name']) . '&size=150&background=3B9DB3&color=fff&bold=true\'">';
        echo '</div>';
        echo '<h4>' . htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) . '</h4>';
        echo '<span class="badge ' . ($admin['status'] ? 'bg-success' : 'bg-danger') . '">';
        echo $admin['status'] ? 'Active' : 'Inactive';
        echo '</span>';
        echo '</div>';
        
        echo '<div class="col-md-8">';
        echo '<h5 class="mb-3">Personal Information</h5>';
        echo '<table class="table table-bordered">';
        echo '<tr><th>Full Name</th><td>' . htmlspecialchars($admin['first_name'] . ' ' . $admin['middle_name'] . ' ' . $admin['last_name']) . '</td></tr>';
        echo '<tr><th>Sex</th><td>' . htmlspecialchars($admin['sex']) . '</td></tr>';
        echo '<tr><th>Email</th><td>' . htmlspecialchars($admin['email']) . '</td></tr>';
        echo '<tr><th>Phone Number</th><td>' . htmlspecialchars($admin['phone_number']) . '</td></tr>';
        echo '<tr><th>Check Number</th><td>' . (empty($admin['check_number']) ? 'N/A' : htmlspecialchars($admin['check_number'])) . '</td></tr>';
        echo '<tr><th>NIDA Number</th><td>' . (empty($admin['nida']) ? 'N/A' : htmlspecialchars($admin['nida'])) . '</td></tr>';
        echo '<tr><th>Assigned Roles</th><td>' . (empty($admin['roles_with_type']) ? 'No roles assigned' : htmlspecialchars($admin['roles_with_type'])) . '</td></tr>';
        echo '<tr><th>Registration Date</th><td>' . date('F j, Y', strtotime($admin['created_at'])) . '</td></tr>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">Admin not found.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid request.</div>';
}
?>