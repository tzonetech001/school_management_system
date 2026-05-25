<?php
// tournament_teams.php - Manage teams for a tournament with full standings
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$tournament_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tournament_id <= 0) {
    header("Location: sports.php");
    exit();
}

// Get tournament details
$tournament_sql = "SELECT t.*, gt.game_name, gt.color_code FROM tournaments t 
                   LEFT JOIN game_types gt ON t.game_type_id = gt.id 
                   WHERE t.id = ?";
$stmt = $conn->prepare($tournament_sql);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tournament = $stmt->get_result()->fetch_assoc();

if (!$tournament) {
    header("Location: sports.php");
    exit();
}

// Check user permissions
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

$has_sports_role = false;
$is_admin_role = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2) {
        $is_admin_role = true;
        $has_sports_role = true;
    }
    if ($role_id == 10) {
        $has_sports_role = true;
    }
}

$can_manage = $is_admin_role || $has_sports_role;

// Handle adding team to tournament
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $can_manage) {
    if ($_POST['action'] == 'add_team') {
        $team_id = intval($_POST['team_id']);
        $group_name = mysqli_real_escape_string($conn, $_POST['group_name']);
        
        // Check if team already added
        $check_sql = "SELECT id FROM tournament_teams WHERE tournament_id = ? AND team_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $tournament_id, $team_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            $insert_sql = "INSERT INTO tournament_teams (tournament_id, team_id, group_name) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iis", $tournament_id, $team_id, $group_name);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Team added to tournament successfully!";
            } else {
                $_SESSION['error'] = "Error adding team: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Team is already added to this tournament!";
        }
        header("Location: tournament_teams.php?id=" . $tournament_id);
        exit();
    }
    
    if ($_POST['action'] == 'remove_team') {
        $team_id = intval($_POST['team_id']);
        
        $delete_sql = "DELETE FROM tournament_teams WHERE tournament_id = ? AND team_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("ii", $tournament_id, $team_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Team removed from tournament successfully!";
        } else {
            $_SESSION['error'] = "Error removing team: " . $conn->error;
        }
        header("Location: tournament_teams.php?id=" . $tournament_id);
        exit();
    }
}

// Get all teams
$teams_sql = "SELECT * FROM teams WHERE is_active = TRUE ORDER BY team_type, team_name";
$teams_result = mysqli_query($conn, $teams_sql);

// Get tournament teams with standings - Grouped by group
$tournament_teams_sql = "SELECT tt.*, t.team_name, t.team_type, t.combination_code,
                         tt.points, tt.matches_played, tt.wins, tt.draws, tt.losses,
                         tt.goals_for, tt.goals_against, tt.goal_difference
                         FROM tournament_teams tt
                         JOIN teams t ON tt.team_id = t.id
                         WHERE tt.tournament_id = ?
                         ORDER BY tt.group_name, tt.points DESC, tt.goal_difference DESC, tt.goals_for DESC";
$stmt = $conn->prepare($tournament_teams_sql);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$tournament_teams_result = $stmt->get_result();

// Group teams by group
$groups = [];
while ($team = $tournament_teams_result->fetch_assoc()) {
    $group = $team['group_name'] ?: 'All';
    $groups[$group][] = $team;
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-trophy me-2"></i>
                    <?php echo htmlspecialchars($tournament['tournament_name']); ?>
                </h2>
                <p class="text-muted">
                    <span class="badge" style="background-color: <?php echo $tournament['color_code'] ?? '#3B9DB3'; ?>; color: white;">
                        <?php echo htmlspecialchars($tournament['game_name']); ?>
                    </span>
                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($tournament['season']); ?></span>
                </p>
            </div>
            <div>
                <a href="sports.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Sports
                </a>
                <?php if ($can_manage): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                    <i class="fas fa-plus me-2"></i>Add Team
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tournament Info Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-calendar-alt" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo date('M d', strtotime($tournament['start_date'])); ?> - <?php echo date('M d, Y', strtotime($tournament['end_date'])); ?></h3>
                    <p>Tournament Duration</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-users" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo count($groups, COUNT_RECURSIVE) - count($groups); ?></h3>
                    <p>Participating Teams</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-trophy" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $tournament['status']; ?></h3>
                    <p>Tournament Status</p>
                </div>
            </div>
        </div>

        <!-- Tournament Description -->
        <?php if ($tournament['description']): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5><i class="fas fa-info-circle me-2"></i>Tournament Description</h5>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($tournament['description'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Teams Standings by Group -->
        <?php foreach ($groups as $group_name => $teams): ?>
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0">
                    <i class="fas fa-ranking-star me-2"></i>
                    <?php echo $group_name == 'All' ? 'All Teams' : 'Group ' . $group_name; ?> Standings
                </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>#</th>
                                <th>Team</th>
                                <th>Type</th>
                                <th>P</th>
                                <th>W</th>
                                <th>D</th>
                                <th>L</th>
                                <th>GF</th>
                                <th>GA</th>
                                <th>GD</th>
                                <th>Points</th>
                                <?php if ($can_manage): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($teams as $team): ?>
                                <tr>
                                    <td style="text-align: center; font-weight: bold;"><?php echo $rank++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                    <td style="text-align: center;">
                                        <?php
                                        $type_badge = [
                                            'Form Five Combination' => 'primary',
                                            'Form Six Combination' => 'info',
                                            'Staff' => 'warning'
                                        ];
                                        $badge = $type_badge[$team['team_type']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php echo $team['team_type'] == 'Form Five Combination' ? 'Form V' : ($team['team_type'] == 'Form Six Combination' ? 'Form VI' : 'Staff'); ?>
                                            <?php if ($team['combination_code']): ?>
                                                (<?php echo $team['combination_code']; ?>)
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;"><?php echo $team['matches_played']; ?></td>
                                    <td style="text-align: center;"><?php echo $team['wins']; ?></td>
                                    <td style="text-align: center;"><?php echo $team['draws']; ?></td>
                                    <td style="text-align: center;"><?php echo $team['losses']; ?></td>
                                    <td style="text-align: center;"><?php echo $team['goals_for']; ?></td>
                                    <td style="text-align: center;"><?php echo $team['goals_against']; ?></td>
                                    <td style="text-align: center;"><?php echo $team['goal_difference']; ?></td>
                                    <td style="text-align: center; font-weight: bold; color: var(--primary-color);"><?php echo $team['points']; ?></td>
                                    <?php if ($can_manage): ?>
                                    <td style="text-align: center;">
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Remove this team from tournament?');">
                                            <input type="hidden" name="action" value="remove_team">
                                            <input type="hidden" name="team_id" value="<?php echo $team['team_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Tournament Matches -->
        <div class="card mt-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Tournament Matches</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php
                    $matches_sql = "SELECT m.*, 
                                    t1.team_name as team1_name,
                                    t2.team_name as team2_name,
                                    s.stage_name,
                                    s.color_code as stage_color,
                                    s.bg_color as stage_bg
                                    FROM matches m
                                    LEFT JOIN teams t1 ON m.team1_id = t1.id
                                    LEFT JOIN teams t2 ON m.team2_id = t2.id
                                    LEFT JOIN tournament_stages s ON m.stage_id = s.id
                                    WHERE m.tournament_id = ?
                                    ORDER BY m.match_date DESC, m.match_time DESC";
                    $stmt = $conn->prepare($matches_sql);
                    $stmt->bind_param("i", $tournament_id);
                    $stmt->execute();
                    $matches_result = $stmt->get_result();
                    ?>
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>Stage</th>
                                <th>Group</th>
                                <th>Match</th>
                                <th>Score</th>
                                <th>Winner</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($matches_result && mysqli_num_rows($matches_result) > 0): ?>
                                <?php while ($match = mysqli_fetch_assoc($matches_result)): ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <span class="badge" style="background-color: <?php echo $match['stage_color'] ?? '#6c757d'; ?>; background: <?php echo $match['stage_bg'] ?? '#e9ecef'; ?>; color: <?php echo $match['stage_color'] ?? '#6c757d'; ?>;">
                                                <?php echo htmlspecialchars($match['stage_name']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($match['group_name']): ?>
                                                <span class="badge bg-info">Group <?php echo htmlspecialchars($match['group_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($match['team1_name']); ?></strong>
                                            <span class="text-muted"> vs </span>
                                            <strong><?php echo htmlspecialchars($match['team2_name']); ?></strong>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($match['status'] == 'Completed'): ?>
                                                <span class="badge bg-success"><?php echo $match['team1_score']; ?> - <?php echo $match['team2_score']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($match['winner_team_id']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo $match['team1_score'] > $match['team2_score'] ? $match['team1_name'] : $match['team2_name']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php echo date('M d, Y', strtotime($match['match_date'])); ?><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($match['match_time'])); ?></small>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php
                                            $status_badge = [
                                                'Scheduled' => 'warning',
                                                'Completed' => 'success'
                                            ];
                                            $badge_class = $status_badge[$match['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $match['status']; ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No matches scheduled for this tournament yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Team Modal -->
<div class="modal fade" id="addTeamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Team to Tournament</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_team">
                    <div class="mb-3">
                        <label class="form-label">Select Team *</label>
                        <select name="team_id" class="form-select" required>
                            <option value="">Choose a team...</option>
                            <?php 
                            mysqli_data_seek($teams_result, 0);
                            while ($team = mysqli_fetch_assoc($teams_result)): 
                                // Check if team already added
                                $check_sql = "SELECT id FROM tournament_teams WHERE tournament_id = ? AND team_id = ?";
                                $stmt = $conn->prepare($check_sql);
                                $stmt->bind_param("ii", $tournament_id, $team['id']);
                                $stmt->execute();
                                $check_result = $stmt->get_result();
                                $already_added = $check_result->num_rows > 0;
                                
                                if (!$already_added):
                            ?>
                                <option value="<?php echo $team['id']; ?>">
                                    <?php echo htmlspecialchars($team['team_name']); ?> 
                                    (<?php echo $team['team_type'] == 'Form Five Combination' ? 'Form V' : ($team['team_type'] == 'Form Six Combination' ? 'Form VI' : 'Staff'); ?>
                                    <?php if ($team['combination_code']): ?>- <?php echo $team['combination_code']; ?><?php endif; ?>)
                                </option>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Group Name</label>
                        <select name="group_name" class="form-select">
                            <option value="">No Group</option>
                            <option value="A">Group A</option>
                            <option value="B">Group B</option>
                            <option value="C">Group C</option>
                            <option value="D">Group D</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            title: 'Success!',
            text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
            timer: 3000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            title: 'Error!',
            text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#d33'
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>

<style>
.stats-card.simple-card {
    border: none;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stats-card.simple-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: #3B9DB3;
}

.stats-card.simple-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-card.simple-card .stats-icon i {
    font-size: 2.2rem;
    margin-bottom: 10px;
}

.stats-card.simple-card h3 {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 10px 0;
}

.table th {
    font-weight: 600;
    background-color: rgba(59, 157, 179, 0.05);
    border-bottom: 2px solid rgba(59, 157, 179, 0.2);
}

@media (max-width: 768px) {
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>