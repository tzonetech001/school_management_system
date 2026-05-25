<?php
// sports.php - Complete Sports Management System with Multiple Game Types
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$current_year = date('Y');

// Check user roles for permissions
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has Sports & Game role (role_id 6)
$has_sports_role = false;
$is_admin_role = false;
foreach ($user_role_ids as $role_id) {
    if ($role_id == 1 || $role_id == 2) {
        $is_admin_role = true;
        $has_sports_role = true;
    }
    if ($role_id == 6) {
        $has_sports_role = true;
    }
}

$can_manage = $is_admin_role || $has_sports_role;
$can_edit = $can_manage;
$can_delete = $is_admin_role;

// Get selected game type filter
$selected_game_type = isset($_GET['game_type']) ? intval($_GET['game_type']) : 0;

// Handle tournament creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_tournament' && $can_manage) {
        $tournament_name = mysqli_real_escape_string($conn, $_POST['tournament_name']);
        $game_type_id = intval($_POST['game_type_id']);
        $season = mysqli_real_escape_string($conn, $_POST['season']);
        $year = intval($_POST['year']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $insert_sql = "INSERT INTO tournaments (tournament_name, game_type_id, season, year, start_date, end_date, description, created_by, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Upcoming')";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sisisssi", $tournament_name, $game_type_id, $season, $year, $start_date, $end_date, $description, $admin_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Tournament created successfully!";
        } else {
            $_SESSION['error'] = "Error creating tournament: " . $conn->error;
        }
        header("Location: sports.php?game_type=" . $game_type_id);
        exit();
    }
    
    if ($_POST['action'] == 'add_match' && $can_manage) {
    $tournament_id = intval($_POST['tournament_id']);
    $game_type_id = intval($_POST['game_type_id']);
    $stage_id = intval($_POST['stage_id']);
    $group_name = isset($_POST['group_name']) && !empty($_POST['group_name']) ? mysqli_real_escape_string($conn, $_POST['group_name']) : null;
    $team1_id = intval($_POST['team1_id']);
    $team2_id = intval($_POST['team2_id']);
    
    // Fix: Ensure date is properly formatted
    $match_date = !empty($_POST['match_date']) ? $_POST['match_date'] : date('Y-m-d');
    $match_time = !empty($_POST['match_time']) ? $_POST['match_time'] : '14:00:00';
    
    // Add debug to see what's being submitted (remove after testing)
    error_log("Match Date from POST: " . $_POST['match_date']);
    error_log("Match Time from POST: " . $_POST['match_time']);
    
    $venue = mysqli_real_escape_string($conn, $_POST['venue']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $insert_sql = "INSERT INTO matches (tournament_id, game_type_id, stage_id, group_name, team1_id, team2_id, match_date, match_time, venue, description, created_by, status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled')";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iiisiiisssi", $tournament_id, $game_type_id, $stage_id, $group_name, $team1_id, $team2_id, $match_date, $match_time, $venue, $description, $admin_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Match scheduled successfully!";
    } else {
        $_SESSION['error'] = "Error scheduling match: " . $conn->error;
    }
    header("Location: sports.php?game_type=" . $game_type_id);
    exit();
}
    
    if ($_POST['action'] == 'update_result' && $can_edit) {
        $match_id = intval($_POST['match_id']);
        $team1_score = intval($_POST['team1_score']);
        $team2_score = intval($_POST['team2_score']);
        $game_type_id = intval($_POST['game_type_id']);
        
        $match_sql = "SELECT tournament_id, team1_id, team2_id FROM matches WHERE id = ?";
        $stmt = $conn->prepare($match_sql);
        $stmt->bind_param("i", $match_id);
        $stmt->execute();
        $match = $stmt->get_result()->fetch_assoc();
        
        if ($match) {
            $winner_team_id = null;
            if ($team1_score > $team2_score) {
                $winner_team_id = $match['team1_id'];
            } elseif ($team2_score > $team1_score) {
                $winner_team_id = $match['team2_id'];
            }
            
            $update_sql = "UPDATE matches SET team1_score = ?, team2_score = ?, winner_team_id = ?, status = 'Completed' WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("iiii", $team1_score, $team2_score, $winner_team_id, $match_id);
            
            if ($stmt->execute()) {
                updateTournamentStandings($conn, $match_id);
                $_SESSION['success'] = "Match result updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating result: " . $conn->error;
            }
        }
        header("Location: sports.php?game_type=" . $game_type_id);
        exit();
    }
    
    if ($_POST['action'] == 'edit_match' && $can_edit) {
        $match_id = intval($_POST['match_id']);
        $tournament_id = intval($_POST['tournament_id']);
        $game_type_id = intval($_POST['game_type_id']);
        $stage_id = intval($_POST['stage_id']);
        $group_name = isset($_POST['group_name']) && !empty($_POST['group_name']) ? mysqli_real_escape_string($conn, $_POST['group_name']) : null;
        $team1_id = intval($_POST['team1_id']);
        $team2_id = intval($_POST['team2_id']);
        $match_date = mysqli_real_escape_string($conn, $_POST['match_date']);
        $match_time = !empty($_POST['match_time']) ? $_POST['match_time'] : '14:00:00';
        $venue = mysqli_real_escape_string($conn, $_POST['venue']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        $update_sql = "UPDATE matches SET tournament_id = ?, stage_id = ?, group_name = ?,
                       team1_id = ?, team2_id = ?, match_date = ?, match_time = ?, venue = ?, description = ? 
                       WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iisiissssi", $tournament_id, $stage_id, $group_name, $team1_id, $team2_id, $match_date, $match_time, $venue, $description, $match_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Match updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating match: " . $conn->error;
        }
        header("Location: sports.php?game_type=" . $game_type_id);
        exit();
    }
    
    if ($_POST['action'] == 'delete_match' && $can_delete) {
        $match_id = intval($_POST['match_id']);
        $game_type_id = intval($_POST['game_type_id']);
        
        $delete_sql = "DELETE FROM matches WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $match_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Match deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting match: " . $conn->error;
        }
        header("Location: sports.php?game_type=" . $game_type_id);
        exit();
    }
    
    if ($_POST['action'] == 'delete_tournament' && $can_delete) {
        $tournament_id = intval($_POST['tournament_id']);
        $game_type_id = intval($_POST['game_type_id']);
        
        $delete_matches_sql = "DELETE FROM matches WHERE tournament_id = ?";
        $stmt = $conn->prepare($delete_matches_sql);
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        
        $delete_teams_sql = "DELETE FROM tournament_teams WHERE tournament_id = ?";
        $stmt = $conn->prepare($delete_teams_sql);
        $stmt->bind_param("i", $tournament_id);
        $stmt->execute();
        
        $delete_sql = "DELETE FROM tournaments WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $tournament_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Tournament deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting tournament: " . $conn->error;
        }
        header("Location: sports.php?game_type=" . $game_type_id);
        exit();
    }
}

function updateTournamentStandings($conn, $match_id) {
    $match_sql = "SELECT tournament_id, team1_id, team2_id, team1_score, team2_score FROM matches WHERE id = ?";
    $stmt = $conn->prepare($match_sql);
    $stmt->bind_param("i", $match_id);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    
    if (!$match) return;
    
    updateTeamStats($conn, $match['tournament_id'], $match['team1_id'], $match['team1_score'], $match['team2_score']);
    updateTeamStats($conn, $match['tournament_id'], $match['team2_id'], $match['team2_score'], $match['team1_score']);
}

function updateTeamStats($conn, $tournament_id, $team_id, $goals_for, $goals_against) {
    $stats_sql = "SELECT * FROM tournament_teams WHERE tournament_id = ? AND team_id = ?";
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param("ii", $tournament_id, $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        $matches_played = $stats['matches_played'] + 1;
        $goals_for_total = $stats['goals_for'] + $goals_for;
        $goals_against_total = $stats['goals_against'] + $goals_against;
        $goal_diff = $goals_for_total - $goals_against_total;
        
        $wins = $stats['wins'];
        $draws = $stats['draws'];
        $losses = $stats['losses'];
        $points = $stats['points'];
        
        if ($goals_for > $goals_against) {
            $wins++;
            $points += 3;
        } elseif ($goals_for == $goals_against) {
            $draws++;
            $points += 1;
        } else {
            $losses++;
        }
        
        $update_sql = "UPDATE tournament_teams SET matches_played = ?, wins = ?, draws = ?, losses = ?,
                       goals_for = ?, goals_against = ?, goal_difference = ?, points = ?
                       WHERE tournament_id = ? AND team_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iiiiiiiiii", $matches_played, $wins, $draws, $losses, 
                          $goals_for_total, $goals_against_total, $goal_diff, $points,
                          $tournament_id, $team_id);
        $stmt->execute();
    } else {
        $points = ($goals_for > $goals_against) ? 3 : (($goals_for == $goals_against) ? 1 : 0);
        $wins = ($goals_for > $goals_against) ? 1 : 0;
        $draws = ($goals_for == $goals_against) ? 1 : 0;
        $losses = ($goals_for < $goals_against) ? 1 : 0;
        $goal_diff = $goals_for - $goals_against;
        
        $insert_sql = "INSERT INTO tournament_teams (tournament_id, team_id, matches_played, wins, draws, losses, 
                       goals_for, goals_against, goal_difference, points) 
                       VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiiiiiiii", $tournament_id, $team_id, $wins, $draws, $losses, 
                          $goals_for, $goals_against, $goal_diff, $points);
        $stmt->execute();
    }
}

// Get game types
$game_types_sql = "SELECT * FROM game_types WHERE status = 'Active' ORDER BY id";
$game_types_result = mysqli_query($conn, $game_types_sql);

// Get all tournament stages
$stages_sql = "SELECT * FROM tournament_stages ORDER BY stage_order";
$stages_result = mysqli_query($conn, $stages_sql);

// Get all teams
$teams_sql = "SELECT * FROM teams WHERE is_active = TRUE ORDER BY team_type, team_name";
$teams_result = mysqli_query($conn, $teams_sql);

// Get tournaments filtered by game type
$tournaments_sql = "SELECT t.*, gt.game_name, gt.color_code 
                    FROM tournaments t 
                    LEFT JOIN game_types gt ON t.game_type_id = gt.id 
                    WHERE t.is_archived = FALSE";
if ($selected_game_type > 0) {
    $tournaments_sql .= " AND t.game_type_id = $selected_game_type";
}
$tournaments_sql .= " ORDER BY t.created_at DESC";
$tournaments_result = mysqli_query($conn, $tournaments_sql);

// Get matches filtered by game type
$matches_sql = "SELECT m.*, 
                s.stage_name, s.color_code as stage_color, s.bg_color as stage_bg,
                t1.team_name as team1_name,
                t2.team_name as team2_name,
                wt.team_name as winner_name,
                tr.tournament_name,
                tr.year as tournament_year,
                gt.game_name as game_name,
                gt.color_code as game_color
                FROM matches m
                LEFT JOIN tournament_stages s ON m.stage_id = s.id
                LEFT JOIN teams t1 ON m.team1_id = t1.id
                LEFT JOIN teams t2 ON m.team2_id = t2.id
                LEFT JOIN teams wt ON m.winner_team_id = wt.id
                LEFT JOIN tournaments tr ON m.tournament_id = tr.id
                LEFT JOIN game_types gt ON m.game_type_id = gt.id
                WHERE tr.is_archived = FALSE";
if ($selected_game_type > 0) {
    $matches_sql .= " AND m.game_type_id = $selected_game_type";
}
$matches_sql .= " ORDER BY m.match_date DESC, m.match_time DESC";
$matches_result = mysqli_query($conn, $matches_sql);

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM tournaments WHERE is_archived = FALSE " . ($selected_game_type > 0 ? "AND game_type_id = $selected_game_type" : "") . ") as total_tournaments,
    (SELECT COUNT(*) FROM matches m LEFT JOIN tournaments tr ON m.tournament_id = tr.id WHERE tr.is_archived = FALSE AND m.status = 'Scheduled' " . ($selected_game_type > 0 ? "AND m.game_type_id = $selected_game_type" : "") . ") as upcoming_matches,
    (SELECT COUNT(*) FROM matches m LEFT JOIN tournaments tr ON m.tournament_id = tr.id WHERE tr.is_archived = FALSE AND m.status = 'Completed' " . ($selected_game_type > 0 ? "AND m.game_type_id = $selected_game_type" : "") . ") as completed_matches";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title">
                <i class="fas fa-futbol me-2"></i>Sports & Games Management
            </h2>
            <div>
                <?php if ($can_manage): ?>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createTournamentModal">
                    <i class="fas fa-trophy me-2"></i>Create Tournament
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scheduleMatchModal">
                    <i class="fas fa-calendar-plus me-2"></i>Schedule Match
                </button>
                <?php endif; ?>
                <a href="report_sports.php" class="btn btn-info">
                    <i class="fas fa-chart-line me-2"></i>Reports
                </a>
            </div>
        </div>

        <!-- Game Type Navigation -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <a href="?game_type=0" class="btn <?php echo $selected_game_type == 0 ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                            <i class="fas fa-trophy me-2"></i>All Games
                        </a>
                    </div>
                    <?php 
                    $game_icons = [
                        1 => 'fa-futbol',
                        2 => 'fa-basketball-ball',
                        3 => 'fa-hand-peace',
                        4 => 'fa-volleyball-ball'
                    ];
                    mysqli_data_seek($game_types_result, 0);
                    while ($game = mysqli_fetch_assoc($game_types_result)): 
                        $icon = $game_icons[$game['id']] ?? 'fa-sports';
                    ?>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <a href="?game_type=<?php echo $game['id']; ?>" class="btn <?php echo $selected_game_type == $game['id'] ? 'btn-primary' : 'btn-outline-primary'; ?> w-100" style="border-color: <?php echo $game['color_code'] ?? '#3B9DB3'; ?>;">
                            <i class="fas <?php echo $icon; ?> me-2"></i><?php echo htmlspecialchars($game['game_name']); ?>
                        </a>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-trophy" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $stats['total_tournaments'] ?? 0; ?></h3>
                    <p>Active Tournaments</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-calendar-alt" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $stats['upcoming_matches'] ?? 0; ?></h3>
                    <p>Upcoming Matches</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-check-circle" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $stats['completed_matches'] ?? 0; ?></h3>
                    <p>Completed Matches</p>
                </div>
            </div>
        </div>

        <!-- Active Tournaments Section -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-trophy me-2"></i>Active Tournaments</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Game</th>
                                <th>Tournament Name</th>
                                <th>Season</th>
                                <th>Year</th>
                                <th>Duration</th>
                                <th>Matches</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($tournaments_result && mysqli_num_rows($tournaments_result) > 0): ?>
                                <?php while ($tournament = mysqli_fetch_assoc($tournaments_result)): ?>
                                    <tr>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo $tournament['color_code'] ?? '#3B9DB3'; ?>; color: white;">
                                                <i class="fas <?php echo $tournament['game_name'] == 'Football' ? 'fa-futbol' : ($tournament['game_name'] == 'Netball' ? 'fa-basketball-ball' : ($tournament['game_name'] == 'Handball' ? 'fa-hand-peace' : 'fa-volleyball-ball')); ?> me-1"></i>
                                                <?php echo htmlspecialchars($tournament['game_name']); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($tournament['tournament_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($tournament['season']); ?></td>
                                        <td><?php echo $tournament['year']; ?></td>
                                        <td>
                                            <?php 
                                            if (!empty($tournament['start_date']) && $tournament['start_date'] != '0000-00-00') {
                                                echo date('M d', strtotime($tournament['start_date'])) . ' - ';
                                            }
                                            if (!empty($tournament['end_date']) && $tournament['end_date'] != '0000-00-00') {
                                                echo date('M d, Y', strtotime($tournament['end_date']));
                                            } else {
                                                echo 'TBD';
                                            }
                                            ?>
                                        </td>
                                        <td><span class="badge bg-info"><?php echo $tournament['total_matches'] ?? 0; ?> matches</span></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'Upcoming' => 'warning',
                                                'Ongoing' => 'success',
                                                'Completed' => 'secondary',
                                                'Cancelled' => 'danger'
                                            ];
                                            $status_class = $status_class[$tournament['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo $tournament['status']; ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info view-tournament" data-id="<?php echo $tournament['id']; ?>" data-name="<?php echo htmlspecialchars($tournament['tournament_name']); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($can_manage): ?>
                                                <a href="tournament_teams.php?id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-users"></i> Teams
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($can_delete): ?>
                                                <button class="btn btn-sm btn-outline-danger delete-tournament" 
                                                        data-id="<?php echo $tournament['id']; ?>"
                                                        data-game-type="<?php echo $tournament['game_type_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($tournament['tournament_name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No active tournaments found. Create your first tournament!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Matches Schedule & Results Section -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Matches Schedule & Results</h4>
                <div>
                    <button class="btn btn-light btn-sm" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Section -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-2">
                        <select id="tournamentFilter" class="form-select">
                            <option value="">All Tournaments</option>
                            <?php 
                            mysqli_data_seek($tournaments_result, 0);
                            while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                            ?>
                                <option value="<?php echo $tournament['tournament_name']; ?>"><?php echo htmlspecialchars($tournament['tournament_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <select id="stageFilter" class="form-select">
                            <option value="">All Stages</option>
                            <?php 
                            mysqli_data_seek($stages_result, 0);
                            while ($stage = mysqli_fetch_assoc($stages_result)): 
                            ?>
                                <option value="<?php echo $stage['stage_name']; ?>"><?php echo $stage['stage_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <select id="groupFilter" class="form-select">
                            <option value="">All Groups</option>
                            <option value="A">Group A</option>
                            <option value="B">Group B</option>
                            <option value="C">Group C</option>
                            <option value="D">Group D</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search teams...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="matchesTable">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>Game</th>
                                <th>Tournament</th>
                                <th>Stage</th>
                                <th>Group</th>
                                <th>Participants</th>
                                <th>Score</th>
                                <th>Winner</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($matches_result && mysqli_num_rows($matches_result) > 0): ?>
                                <?php while ($match = mysqli_fetch_assoc($matches_result)): 
                                    $match_date_display = (!empty($match['match_date']) && $match['match_date'] != '0000-00-00') ? date('M d, Y', strtotime($match['match_date'])) : 'Date TBD';
                                    $match_time_display = (!empty($match['match_time']) && $match['match_time'] != '00:00:00') ? date('h:i A', strtotime($match['match_time'])) : 'Time TBD';
                                ?>
                                    <tr data-tournament="<?php echo htmlspecialchars($match['tournament_name']); ?>"
                                        data-stage="<?php echo htmlspecialchars($match['stage_name']); ?>"
                                        data-group="<?php echo htmlspecialchars($match['group_name']); ?>"
                                        data-status="<?php echo $match['status']; ?>">
                                        <td style="text-align: center;">
                                            <span class="badge" style="background-color: <?php echo $match['game_color'] ?? '#3B9DB3'; ?>; color: white;">
                                                <i class="fas <?php echo $match['game_name'] == 'Football' ? 'fa-futbol' : ($match['game_name'] == 'Netball' ? 'fa-basketball-ball' : ($match['game_name'] == 'Handball' ? 'fa-hand-peace' : 'fa-volleyball-ball')); ?> me-1"></i>
                                                <?php echo htmlspecialchars($match['game_name']); ?>
                                            </span>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($match['tournament_name'] ?? 'N/A'); ?></small></td>
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
                                            <div class="d-flex flex-column text-center">
                                                <strong><?php echo htmlspecialchars($match['team1_name']); ?></strong>
                                                <span class="text-muted">vs</span>
                                                <strong><?php echo htmlspecialchars($match['team2_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td style="text-align: center; font-size: 1.2rem; font-weight: bold;">
                                            <?php if ($match['status'] == 'Completed'): ?>
                                                <span class="badge bg-success" style="font-size: 1rem;"><?php echo $match['team1_score']; ?> - <?php echo $match['team2_score']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($match['winner_name']): ?>
                                                <span class="badge bg-warning text-dark">🏆 <?php echo htmlspecialchars($match['winner_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php echo $match_date_display; ?><br>
                                            <small class="text-muted"><?php echo $match_time_display; ?></small>
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
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-info view-match" data-id="<?php echo $match['id']; ?>" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($can_edit): ?>
                                                    <?php if ($match['status'] != 'Completed'): ?>
                                                        <button class="btn btn-outline-success update-result" 
                                                                data-id="<?php echo $match['id']; ?>"
                                                                data-game-type="<?php echo $match['game_type_id']; ?>"
                                                                data-team1="<?php echo htmlspecialchars($match['team1_name']); ?>"
                                                                data-team2="<?php echo htmlspecialchars($match['team2_name']); ?>"
                                                                data-team1-id="<?php echo $match['team1_id']; ?>"
                                                                data-team2-id="<?php echo $match['team2_id']; ?>"
                                                                title="Update Result">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-warning edit-match" 
                                                                data-id="<?php echo $match['id']; ?>"
                                                                data-game-type="<?php echo $match['game_type_id']; ?>"
                                                                data-tournament-id="<?php echo $match['tournament_id']; ?>"
                                                                data-stage-id="<?php echo $match['stage_id']; ?>"
                                                                data-group-name="<?php echo htmlspecialchars($match['group_name']); ?>"
                                                                data-team1-id="<?php echo $match['team1_id']; ?>"
                                                                data-team2-id="<?php echo $match['team2_id']; ?>"
                                                                data-date="<?php echo $match['match_date']; ?>"
                                                                data-time="<?php echo $match['match_time']; ?>"
                                                                data-venue="<?php echo htmlspecialchars($match['venue']); ?>"
                                                                data-description="<?php echo htmlspecialchars($match['description']); ?>"
                                                                title="Edit Match">
                                                            <i class="fas fa-pencil-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($can_delete): ?>
                                                    <button class="btn btn-outline-danger delete-match" 
                                                            data-id="<?php echo $match['id']; ?>"
                                                            data-game-type="<?php echo $match['game_type_id']; ?>"
                                                            data-teams="<?php echo htmlspecialchars($match['team1_name'] . ' vs ' . $match['team2_name']); ?>"
                                                            title="Delete Match">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                          </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No matches scheduled. Create your first match!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Tournament Modal -->
<div class="modal fade" id="createTournamentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-trophy me-2"></i>Create New Tournament</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_tournament">
                    <div class="mb-3">
                        <label class="form-label">Game Type *</label>
                        <select name="game_type_id" class="form-select" required>
                            <option value="">Select Game Type</option>
                            <?php 
                            mysqli_data_seek($game_types_result, 0);
                            while ($game = mysqli_fetch_assoc($game_types_result)): 
                            ?>
                                <option value="<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['game_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tournament Name *</label>
                        <input type="text" name="tournament_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Season *</label>
                            <input type="text" name="season" class="form-control" placeholder="e.g., 2024 Season" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year *</label>
                            <input type="number" name="year" class="form-control" value="<?php echo $current_year; ?>" min="2000" max="2100" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Tournament</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Schedule Match Modal -->
<div class="modal fade" id="scheduleMatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Schedule New Match</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="scheduleMatchForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_match">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Game Type *</label>
                            <select name="game_type_id" id="gameTypeSelect" class="form-select" required onchange="loadTournamentsByGameType()">
                                <option value="">Select Game Type</option>
                                <?php 
                                mysqli_data_seek($game_types_result, 0);
                                while ($game = mysqli_fetch_assoc($game_types_result)): 
                                ?>
                                    <option value="<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['game_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tournament *</label>
                            <select name="tournament_id" id="tournamentSelect" class="form-select" required>
                                <option value="">Select Tournament</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stage *</label>
                            <select name="stage_id" class="form-select" required>
                                <option value="">Select Stage</option>
                                <?php 
                                mysqli_data_seek($stages_result, 0);
                                while ($stage = mysqli_fetch_assoc($stages_result)): 
                                ?>
                                    <option value="<?php echo $stage['id']; ?>"><?php echo $stage['stage_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Group (Optional)</label>
                            <select name="group_name" class="form-select">
                                <option value="">No Group</option>
                                <option value="A">Group A</option>
                                <option value="B">Group B</option>
                                <option value="C">Group C</option>
                                <option value="D">Group D</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Team 1 *</label>
                            <select name="team1_id" id="team1Select" class="form-select" required>
                                <option value="">Select Team</option>
                                <?php 
                                mysqli_data_seek($teams_result, 0);
                                while ($team = mysqli_fetch_assoc($teams_result)): 
                                ?>
                                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Team 2 *</label>
                            <select name="team2_id" id="team2Select" class="form-select" required>
                                <option value="">Select Team</option>
                                <?php 
                                mysqli_data_seek($teams_result, 0);
                                while ($team = mysqli_fetch_assoc($teams_result)): 
                                ?>
                                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Match Date *</label>
                            <input type="date" name="match_date" id="match_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Match Time *</label>
                            <input type="time" name="match_time" id="match_time" class="form-control" required value="14:00">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="venue" class="form-control" placeholder="e.g., School Grounds, Sports Hall">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Schedule Match</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Match Modal -->
<div class="modal fade" id="editMatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Match</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editMatchForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_match">
                    <input type="hidden" name="match_id" id="edit_match_id">
                    <input type="hidden" name="game_type_id" id="edit_game_type_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tournament *</label>
                            <select name="tournament_id" id="edit_tournament_id" class="form-select" required>
                                <option value="">Select Tournament</option>
                                <?php 
                                mysqli_data_seek($tournaments_result, 0);
                                while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                                ?>
                                    <option value="<?php echo $tournament['id']; ?>"><?php echo htmlspecialchars($tournament['tournament_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stage *</label>
                            <select name="stage_id" id="edit_stage_id" class="form-select" required>
                                <option value="">Select Stage</option>
                                <?php 
                                mysqli_data_seek($stages_result, 0);
                                while ($stage = mysqli_fetch_assoc($stages_result)): 
                                ?>
                                    <option value="<?php echo $stage['id']; ?>"><?php echo $stage['stage_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Group</label>
                            <select name="group_name" id="edit_group_name" class="form-select">
                                <option value="">No Group</option>
                                <option value="A">Group A</option>
                                <option value="B">Group B</option>
                                <option value="C">Group C</option>
                                <option value="D">Group D</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Team 1 *</label>
                            <select name="team1_id" id="edit_team1_id" class="form-select" required>
                                <option value="">Select Team</option>
                                <?php 
                                mysqli_data_seek($teams_result, 0);
                                while ($team = mysqli_fetch_assoc($teams_result)): 
                                ?>
                                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Team 2 *</label>
                            <select name="team2_id" id="edit_team2_id" class="form-select" required>
                                <option value="">Select Team</option>
                                <?php 
                                mysqli_data_seek($teams_result, 0);
                                while ($team = mysqli_fetch_assoc($teams_result)): 
                                ?>
                                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Match Date *</label>
                            <input type="date" name="match_date" id="edit_match_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Match Time *</label>
                            <input type="time" name="match_time" id="edit_match_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="venue" id="edit_venue" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Match</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Result Modal -->
<div class="modal fade" id="updateResultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Match Result</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_result">
                    <input type="hidden" name="match_id" id="result_match_id">
                    <input type="hidden" name="game_type_id" id="result_game_type_id">
                    <input type="hidden" name="team1_id" id="result_team1_id">
                    <input type="hidden" name="team2_id" id="result_team2_id">
                    <div class="row">
                        <div class="col-md-6 text-center">
                            <div class="p-3 border rounded" style="background: #f8f9fa;">
                                <h5 id="result_team1_name" class="mb-3"></h5>
                                <input type="number" name="team1_score" id="team1_score" class="form-control text-center" style="font-size: 2rem;" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6 text-center">
                            <div class="p-3 border rounded" style="background: #f8f9fa;">
                                <h5 id="result_team2_name" class="mb-3"></h5>
                                <input type="number" name="team2_score" id="team2_score" class="form-control text-center" style="font-size: 2rem;" min="0" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Result</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Match Modal -->
<div class="modal fade" id="viewMatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Match Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="matchDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading match details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Tournament Modal -->
<div class="modal fade" id="viewTournamentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-trophy me-2"></i>Tournament Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="tournamentDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading tournament details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// Add this inside your DOMContentLoaded event listener
const scheduleModal = document.getElementById('scheduleMatchModal');
if (scheduleModal) {
    scheduleModal.addEventListener('show.bs.modal', function() {
        // Set default date if not already set
        const dateInput = document.getElementById('match_date');
        if (dateInput && !dateInput.value) {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            dateInput.value = `${year}-${month}-${day}`;
        }
        
        // Set default time if not already set
        const timeInput = document.getElementById('match_time');
        if (timeInput && !timeInput.value) {
            timeInput.value = '14:00';
        }
    });
}

    
    function loadTournamentsByGameType() {
        var gameTypeId = document.getElementById('gameTypeSelect').value;
        var tournamentSelect = document.getElementById('tournamentSelect');
        
        if (!gameTypeId) {
            tournamentSelect.innerHTML = '<option value="">Select Tournament</option>';
            return;
        }
        
        tournamentSelect.innerHTML = '<option value="">Loading tournaments...</option>';
        
        fetch('get_tournaments_by_game.php?game_type_id=' + gameTypeId)
            .then(response => response.json())
            .then(data => {
                tournamentSelect.innerHTML = '<option value="">Select Tournament</option>';
                data.forEach(function(tournament) {
                    var option = document.createElement('option');
                    option.value = tournament.id;
                    option.textContent = tournament.tournament_name + ' (' + tournament.season + ')';
                    tournamentSelect.appendChild(option);
                });
                if (data.length === 0) {
                    tournamentSelect.innerHTML = '<option value="">No tournaments found</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tournamentSelect.innerHTML = '<option value="">Error loading tournaments</option>';
            });
    }

    // Store tournaments data for filtering
    let tournamentsData = [];

    // Fetch tournaments on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch tournaments data
        fetch('get_tournaments_json.php')
            .then(response => response.json())
            .then(data => {
                tournamentsData = data;
            })
            .catch(error => console.error('Error loading tournaments:', error));
        
        // Filter functionality
        const tournamentFilter = document.getElementById('tournamentFilter');
        const stageFilter = document.getElementById('stageFilter');
        const groupFilter = document.getElementById('groupFilter');
        const statusFilter = document.getElementById('statusFilter');
        const searchInput = document.getElementById('searchInput');
        
        function filterTable() {
            const rows = document.querySelectorAll('#matchesTable tbody tr');
            const tournamentValue = tournamentFilter ? tournamentFilter.value.toLowerCase() : '';
            const stageValue = stageFilter ? stageFilter.value.toLowerCase() : '';
            const groupValue = groupFilter ? groupFilter.value.toLowerCase() : '';
            const statusValue = statusFilter ? statusFilter.value.toLowerCase() : '';
            const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
            
            rows.forEach(row => {
                const tournament = row.getAttribute('data-tournament')?.toLowerCase() || '';
                const stage = row.getAttribute('data-stage')?.toLowerCase() || '';
                const group = row.getAttribute('data-group')?.toLowerCase() || '';
                const status = row.getAttribute('data-status')?.toLowerCase() || '';
                const text = row.textContent.toLowerCase();
                
                const matchesTournament = !tournamentValue || tournament === tournamentValue;
                const matchesStage = !stageValue || stage === stageValue;
                const matchesGroup = !groupValue || group === groupValue;
                const matchesStatus = !statusValue || status === statusValue;
                const matchesSearch = !searchValue || text.includes(searchValue);
                
                row.style.display = (matchesTournament && matchesStage && matchesGroup && matchesStatus && matchesSearch) ? '' : 'none';
            });
        }
        
        if (tournamentFilter) tournamentFilter.addEventListener('change', filterTable);
        if (stageFilter) stageFilter.addEventListener('change', filterTable);
        if (groupFilter) groupFilter.addEventListener('change', filterTable);
        if (statusFilter) statusFilter.addEventListener('change', filterTable);
        if (searchInput) searchInput.addEventListener('keyup', filterTable);
        
        // Prevent duplicate matches (same combination)
        const team1Select = document.getElementById('team1Select');
        const team2Select = document.getElementById('team2Select');
        
        function checkDuplicateMatch() {
            if (team1Select && team2Select && team1Select.value && team2Select.value && team1Select.value === team2Select.value) {
                Swal.fire({
                    title: 'Invalid Match',
                    text: 'A team cannot play against itself!',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                team2Select.value = '';
                return false;
            }
            return true;
        }
        
        if (team1Select && team2Select) {
            team1Select.addEventListener('change', function() {
                if (team2Select.value && team1Select.value === team2Select.value) {
                    Swal.fire({
                        title: 'Invalid Match',
                        text: 'A team cannot play against itself!',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    team2Select.value = '';
                }
            });
            
            team2Select.addEventListener('change', function() {
                if (team1Select.value && team2Select.value === team1Select.value) {
                    Swal.fire({
                        title: 'Invalid Match',
                        text: 'A team cannot play against itself!',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    this.value = '';
                }
            });
        }
        
        // Update Result Modal
        const updateButtons = document.querySelectorAll('.update-result');
        updateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const matchId = this.getAttribute('data-id');
                const gameTypeId = this.getAttribute('data-game-type');
                const team1Name = this.getAttribute('data-team1');
                const team2Name = this.getAttribute('data-team2');
                const team1Id = this.getAttribute('data-team1-id');
                const team2Id = this.getAttribute('data-team2-id');
                
                document.getElementById('result_match_id').value = matchId;
                document.getElementById('result_game_type_id').value = gameTypeId;
                document.getElementById('result_team1_name').textContent = team1Name;
                document.getElementById('result_team2_name').textContent = team2Name;
                document.getElementById('result_team1_id').value = team1Id;
                document.getElementById('result_team2_id').value = team2Id;
                document.getElementById('team1_score').value = '';
                document.getElementById('team2_score').value = '';
                
                const modal = new bootstrap.Modal(document.getElementById('updateResultModal'));
                modal.show();
            });
        });
        
        // Edit Match Modal - Clear and populate with fresh data
        const editButtons = document.querySelectorAll('.edit-match');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Get match data from button attributes
                const matchId = this.getAttribute('data-id');
                const gameTypeId = this.getAttribute('data-game-type');
                const tournamentId = this.getAttribute('data-tournament-id');
                const stageId = this.getAttribute('data-stage-id');
                const groupName = this.getAttribute('data-group-name');
                const team1Id = this.getAttribute('data-team1-id');
                const team2Id = this.getAttribute('data-team2-id');
                const matchDate = this.getAttribute('data-date');
                const matchTime = this.getAttribute('data-time');
                const venue = this.getAttribute('data-venue') || '';
                const description = this.getAttribute('data-description') || '';
                
                // Set form values
                document.getElementById('edit_match_id').value = matchId;
                document.getElementById('edit_game_type_id').value = gameTypeId;
                document.getElementById('edit_tournament_id').value = tournamentId;
                document.getElementById('edit_stage_id').value = stageId;
                document.getElementById('edit_group_name').value = groupName;
                document.getElementById('edit_team1_id').value = team1Id;
                document.getElementById('edit_team2_id').value = team2Id;
                
                // Set date and time - ensure they are not empty or 0000-00-00
                if (matchDate && matchDate !== '0000-00-00' && matchDate !== '') {
                    document.getElementById('edit_match_date').value = matchDate;
                } else {
                    document.getElementById('edit_match_date').value = '<?php echo date('Y-m-d'); ?>';
                }
                
                if (matchTime && matchTime !== '00:00:00' && matchTime !== '') {
                    // Format time for input (HH:MM)
                    const timeParts = matchTime.split(':');
                    const formattedTime = timeParts[0] + ':' + timeParts[1];
                    document.getElementById('edit_match_time').value = formattedTime;
                } else {
                    document.getElementById('edit_match_time').value = '14:00';
                }
                
                document.getElementById('edit_venue').value = venue;
                document.getElementById('edit_description').value = description;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('editMatchModal'));
                modal.show();
            });
        });
        
        // Delete Match Confirmation
        const deleteButtons = document.querySelectorAll('.delete-match');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const matchId = this.getAttribute('data-id');
                const gameTypeId = this.getAttribute('data-game-type');
                const teams = this.getAttribute('data-teams');
                
                Swal.fire({
                    title: 'Delete Match?',
                    text: `Are you sure you want to delete the match: ${teams}? This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete_match">
                            <input type="hidden" name="match_id" value="${matchId}">
                            <input type="hidden" name="game_type_id" value="${gameTypeId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
        
        // Delete Tournament Confirmation
        const deleteTournamentButtons = document.querySelectorAll('.delete-tournament');
        deleteTournamentButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tournamentId = this.getAttribute('data-id');
                const gameTypeId = this.getAttribute('data-game-type');
                const tournamentName = this.getAttribute('data-name');
                
                Swal.fire({
                    title: 'Delete Tournament?',
                    html: `Are you sure you want to delete the tournament: <strong>${tournamentName}</strong>?<br><br>This will also delete:<br>- All matches in this tournament<br>- All team assignments<br>This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete_tournament">
                            <input type="hidden" name="tournament_id" value="${tournamentId}">
                            <input type="hidden" name="game_type_id" value="${gameTypeId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
        
        // View Match Modal
        const viewButtons = document.querySelectorAll('.view-match');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const matchId = this.getAttribute('data-id');
                const modal = new bootstrap.Modal(document.getElementById('viewMatchModal'));
                
                document.getElementById('matchDetails').innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading match details...</p>
                    </div>
                `;
                
                fetch(`get_match.php?id=${matchId}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('matchDetails').innerHTML = data;
                    })
                    .catch(error => {
                        document.getElementById('matchDetails').innerHTML = '<div class="alert alert-danger">Error loading match details.</div>';
                    });
                
                modal.show();
            });
        });
        
        // View Tournament Modal
        const tournamentViewButtons = document.querySelectorAll('.view-tournament');
        tournamentViewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tournamentId = this.getAttribute('data-id');
                const modal = new bootstrap.Modal(document.getElementById('viewTournamentModal'));
                
                document.getElementById('tournamentDetails').innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading tournament details...</p>
                    </div>
                `;
                
                fetch(`get_tournament.php?id=${tournamentId}`)
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('tournamentDetails').innerHTML = data;
                    })
                    .catch(error => {
                        document.getElementById('tournamentDetails').innerHTML = '<div class="alert alert-danger">Error loading tournament details.</div>';
                    });
                
                modal.show();
            });
        });
        
        // SweetAlert notifications
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
/* Sports Page Styles */
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
    padding: 12px 8px;
}

.badge {
    font-weight: 500;
    padding: 5px 10px;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    margin: 0 2px;
}

.table tbody tr:hover {
    background-color: rgba(59, 157, 179, 0.05);
}

@media (max-width: 768px) {
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
        margin: 2px;
    }
    
    .btn-group {
        flex-wrap: wrap;
    }
}
</style>

<?php include '../controller/footer.php'; ?>