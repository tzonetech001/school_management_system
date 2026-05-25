<?php
// sports_timetable.php - Sports Timetable with Full Knockout Stage Generation
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$current_year = date('Y');

// Get user role information
$user_roles_sql = "SELECT role_id FROM admin_role_assignments WHERE admin_id = ?";
$stmt = $conn->prepare($user_roles_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$user_roles_result = $stmt->get_result();
$user_role_ids = [];
while ($row = $user_roles_result->fetch_assoc()) {
    $user_role_ids[] = $row['role_id'];
}

// Check if user has edit permissions (Admins with role 1, 2, or Sports role 6)
$authorized_roles = [1, 2, 6];
$can_edit = false;
foreach ($user_role_ids as $role_id) {
    if (in_array($role_id, $authorized_roles)) {
        $can_edit = true;
        break;
    }
}

// All logged-in users can view the timetable
$can_view = true;

// Handle timetable actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $can_edit) {
    if ($_POST['action'] == 'generate_timetable') {
        $tournament_id = intval($_POST['tournament_id']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $match_time = mysqli_real_escape_string($conn, $_POST['match_time']);
        
        generateGroupStage($conn, $tournament_id, $start_date, $match_time, $admin_id);
        header("Location: sports_timetable.php");
        exit();
    }
    
    if ($_POST['action'] == 'generate_knockout_stages') {
        $tournament_id = intval($_POST['tournament_id']);
        $quarter_start_date = mysqli_real_escape_string($conn, $_POST['quarter_start_date']);
        $semi_start_date = mysqli_real_escape_string($conn, $_POST['semi_start_date']);
        $final_date = mysqli_real_escape_string($conn, $_POST['final_date']);
        $match_time = mysqli_real_escape_string($conn, $_POST['match_time']);
        
        generateKnockoutStages($conn, $tournament_id, $quarter_start_date, $semi_start_date, $final_date, $match_time, $admin_id);
        header("Location: sports_timetable.php");
        exit();
    }
    
    if ($_POST['action'] == 'update_match_time') {
        $match_id = intval($_POST['match_id']);
        $match_date = mysqli_real_escape_string($conn, $_POST['match_date']);
        $match_time = mysqli_real_escape_string($conn, $_POST['match_time']);
        
        $update_sql = "UPDATE matches SET match_date = ?, match_time = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssi", $match_date, $match_time, $match_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Match time updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating match time: " . $conn->error;
        }
        header("Location: sports_timetable.php");
        exit();
    }
    
    if ($_POST['action'] == 'delete_match') {
        $match_id = intval($_POST['match_id']);
        
        $delete_sql = "DELETE FROM matches WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $match_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Match deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting match: " . $conn->error;
        }
        header("Location: sports_timetable.php");
        exit();
    }
}

// Function to generate group stage matches
function generateGroupStage($conn, $tournament_id, $start_date, $match_time, $admin_id) {
    // Delete existing matches for this tournament
    $delete_sql = "DELETE FROM matches WHERE tournament_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    
    // Get tournament teams grouped by group
    $teams_sql = "SELECT tt.*, t.team_name 
                  FROM tournament_teams tt
                  JOIN teams t ON tt.team_id = t.id
                  WHERE tt.tournament_id = ? 
                  ORDER BY tt.group_name, t.team_name";
    $stmt = $conn->prepare($teams_sql);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $teams_result = $stmt->get_result();
    
    $groups = [];
    while ($team = $teams_result->fetch_assoc()) {
        $group = $team['group_name'] ?: 'A';
        $groups[$group][] = $team;
    }
    
    // Get game type from tournament
    $game_sql = "SELECT game_type_id FROM tournaments WHERE id = ?";
    $stmt = $conn->prepare($game_sql);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $game_result = $stmt->get_result();
    $tournament = $game_result->fetch_assoc();
    $game_type_id = $tournament['game_type_id'];
    
    // Get stage ID for group stage
    $stage_sql = "SELECT id FROM tournament_stages WHERE stage_name = 'Group Stage'";
    $stage_result = mysqli_query($conn, $stage_sql);
    $stage = $stage_result->fetch_assoc();
    $group_stage_id = $stage['id'];
    
    $current_date = $start_date;
    $current_time = $match_time;
    
    // Generate round-robin matches for each group
    foreach ($groups as $group_name => $teams) {
        $num_teams = count($teams);
        for ($i = 0; $i < $num_teams; $i++) {
            for ($j = $i + 1; $j < $num_teams; $j++) {
                $insert_sql = "INSERT INTO matches (tournament_id, game_type_id, stage_id, group_name, team1_id, team2_id, match_date, match_time, created_by, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled')";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("iiisiiissi", $tournament_id, $game_type_id, $group_stage_id, $group_name, 
                                  $teams[$i]['team_id'], $teams[$j]['team_id'], 
                                  $current_date, $current_time, $admin_id);
                $stmt->execute();
                
                // Update time for next match (2 hours gap)
                $current_time = date('H:i:s', strtotime($current_time . ' +2 hours'));
                if (date('H', strtotime($current_time)) >= 20) {
                    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                    $current_time = '08:00:00';
                }
            }
        }
    }
    
    $_SESSION['success'] = "Group stage matches generated successfully! After entering results, use 'Generate Knockout Stages' to create Quarter Finals, Semi Finals, and Finals.";
}

// Function to generate knockout stages (Quarter Finals, Semi Finals, Finals)
function generateKnockoutStages($conn, $tournament_id, $quarter_start_date, $semi_start_date, $final_date, $match_time, $admin_id) {
    // Get all teams with their group stage standings
    $teams_sql = "SELECT tt.*, t.team_name 
                  FROM tournament_teams tt
                  JOIN teams t ON tt.team_id = t.id
                  WHERE tt.tournament_id = ? 
                  ORDER BY tt.group_name, tt.points DESC, tt.goal_difference DESC, tt.goals_for DESC";
    $stmt = $conn->prepare($teams_sql);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $teams_result = $stmt->get_result();
    
    $groups = [];
    while ($team = $teams_result->fetch_assoc()) {
        $group = $team['group_name'] ?: 'A';
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }
        $groups[$group][] = $team;
    }
    
    // Get top 2 teams from each group for quarter finals
    $qualified_teams = [];
    foreach ($groups as $group_name => $teams) {
        // Take top 2 from each group
        if (isset($teams[0])) {
            $qualified_teams[] = $teams[0]; // Group winner
        }
        if (isset($teams[1])) {
            $qualified_teams[] = $teams[1]; // Group runner-up
        }
    }
    
    $num_qualified = count($qualified_teams);
    
    if ($num_qualified < 8) {
        $_SESSION['error'] = "Need at least 8 teams (top 2 from each group) to generate quarter finals. Current qualified: $num_qualified";
        return;
    }
    
    // Get stage IDs
    $stage_names = ['Quarter Finals', 'Semi Finals', 'Final', '3rd Place Playoff'];
    $stages = [];
    foreach ($stage_names as $stage_name) {
        $stage_sql = "SELECT id FROM tournament_stages WHERE stage_name = ?";
        $stmt = $conn->prepare($stage_sql);
        $stmt->bind_param("s", $stage_name);
        $stmt->execute();
        $stage_result = $stmt->get_result();
        $stage = $stage_result->fetch_assoc();
        $stages[$stage_name] = $stage['id'];
    }
    
    // Get game type
    $game_sql = "SELECT game_type_id FROM tournaments WHERE id = ?";
    $stmt = $conn->prepare($game_sql);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $game_result = $stmt->get_result();
    $tournament = $game_result->fetch_assoc();
    $game_type_id = $tournament['game_type_id'];
    
    // Delete existing knockout matches
    $delete_sql = "DELETE FROM matches WHERE tournament_id = ? AND stage_id IN (?, ?, ?, ?)";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("iiii", $tournament_id, $stages['Quarter Finals'], $stages['Semi Finals'], $stages['Final'], $stages['3rd Place Playoff']);
    $stmt->execute();
    
    // Generate Quarter Finals
    $current_date = $quarter_start_date;
    $current_time = $match_time;
    $quarter_matches = [];
    
    // Standard quarter final pairing: Winner A vs Runner-up B, Winner B vs Runner-up A, etc.
    $group_names = array_keys($groups);
    for ($i = 0; $i < count($group_names); $i += 2) {
        if ($i + 1 < count($group_names)) {
            $group1 = $group_names[$i];
            $group2 = $group_names[$i + 1];
            
            // Get winner of group1 and runner-up of group2
            $winner1 = $groups[$group1][0] ?? null;
            $runner2 = $groups[$group2][1] ?? null;
            
            // Get winner of group2 and runner-up of group1
            $winner2 = $groups[$group2][0] ?? null;
            $runner1 = $groups[$group1][1] ?? null;
            
            if ($winner1 && $runner2) {
                $quarter_matches[] = createMatch($conn, $tournament_id, $game_type_id, $stages['Quarter Finals'], 
                    $winner1['team_id'], $runner2['team_id'], $current_date, $current_time, $admin_id);
                
                $current_time = date('H:i:s', strtotime($current_time . ' +2 hours'));
                if (date('H', strtotime($current_time)) >= 20) {
                    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                    $current_time = '08:00:00';
                }
            }
            
            if ($winner2 && $runner1) {
                $quarter_matches[] = createMatch($conn, $tournament_id, $game_type_id, $stages['Quarter Finals'], 
                    $winner2['team_id'], $runner1['team_id'], $current_date, $current_time, $admin_id);
                
                $current_time = date('H:i:s', strtotime($current_time . ' +2 hours'));
                if (date('H', strtotime($current_time)) >= 20) {
                    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                    $current_time = '08:00:00';
                }
            }
        }
    }
    
    $_SESSION['success'] = "Quarter finals generated successfully! After quarter final results are entered, click again to generate semi finals and finals.";
    $_SESSION['knockout_generated'] = true;
    $_SESSION['semi_start_date'] = $semi_start_date;
    $_SESSION['final_date'] = $final_date;
    $_SESSION['match_time'] = $match_time;
}

// Helper function to create a match
function createMatch($conn, $tournament_id, $game_type_id, $stage_id, $team1_id, $team2_id, $match_date, $match_time, $admin_id) {
    $insert_sql = "INSERT INTO matches (tournament_id, game_type_id, stage_id, team1_id, team2_id, match_date, match_time, created_by, status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled')";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iiiiissi", $tournament_id, $game_type_id, $stage_id, $team1_id, $team2_id, $match_date, $match_time, $admin_id);
    $stmt->execute();
    return $stmt->insert_id;
}

// Function to update tournament standings when a match result is entered
function updateTournamentStandings($conn, $match_id) {
    $match_sql = "SELECT tournament_id, team1_id, team2_id, team1_score, team2_score, stage_id FROM matches WHERE id = ?";
    $stmt = $conn->prepare($match_sql);
    $stmt->bind_param("i", $match_id);
    $stmt->execute();
    $match = $stmt->get_result()->fetch_assoc();
    
    if (!$match) return;
    
    // Update team stats
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

// Get all tournaments
$tournaments_sql = "SELECT t.*, 
                    (SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = t.id) as team_count
                    FROM tournaments t 
                    WHERE t.is_archived = FALSE 
                    ORDER BY t.created_at DESC";
$tournaments_result = mysqli_query($conn, $tournaments_sql);

// Get all matches with details for timetable display
$matches_sql = "SELECT m.*, 
                s.stage_name, s.color_code as stage_color, s.bg_color as stage_bg,
                t1.team_name as team1_name,
                t2.team_name as team2_name,
                wt.team_name as winner_name,
                tr.tournament_name,
                tr.year as tournament_year,
                tr.status as tournament_status
                FROM matches m
                LEFT JOIN tournament_stages s ON m.stage_id = s.id
                LEFT JOIN teams t1 ON m.team1_id = t1.id
                LEFT JOIN teams t2 ON m.team2_id = t2.id
                LEFT JOIN teams wt ON m.winner_team_id = wt.id
                LEFT JOIN tournaments tr ON m.tournament_id = tr.id
                WHERE tr.is_archived = FALSE
                ORDER BY 
                    CASE s.stage_name
                        WHEN 'Group Stage' THEN 1
                        WHEN 'Quarter Finals' THEN 2
                        WHEN 'Semi Finals' THEN 3
                        WHEN '3rd Place Playoff' THEN 4
                        WHEN 'Final' THEN 5
                        ELSE 6
                    END,
                    m.match_date ASC, 
                    m.match_time ASC";
$matches_result = mysqli_query($conn, $matches_sql);

// Group matches by tournament and stage
$tournament_matches = [];
$tournament_standings = [];

while ($match = mysqli_fetch_assoc($matches_result)) {
    $tournament_id = $match['tournament_id'];
    $stage_name = $match['stage_name'];
    $group_name = $match['group_name'] ?: 'Main';
    
    if (!isset($tournament_matches[$tournament_id])) {
        $tournament_matches[$tournament_id] = [
            'name' => $match['tournament_name'],
            'year' => $match['tournament_year'],
            'status' => $match['tournament_status'],
            'stages' => []
        ];
    }
    
    if (!isset($tournament_matches[$tournament_id]['stages'][$stage_name])) {
        $tournament_matches[$tournament_id]['stages'][$stage_name] = [
            'groups' => [],
            'stage_id' => $match['stage_id'],
            'color' => $match['stage_color'],
            'bg' => $match['stage_bg']
        ];
    }
    
    if (!isset($tournament_matches[$tournament_id]['stages'][$stage_name]['groups'][$group_name])) {
        $tournament_matches[$tournament_id]['stages'][$stage_name]['groups'][$group_name] = [];
    }
    
    $tournament_matches[$tournament_id]['stages'][$stage_name]['groups'][$group_name][] = $match;
}

// Calculate group standings for each tournament
foreach ($tournament_matches as $tournament_id => $tournament) {
    if (isset($tournament['stages']['Group Stage'])) {
        $standings = [];
        foreach ($tournament['stages']['Group Stage']['groups'] as $group_name => $matches) {
            $standings[$group_name] = [];
            foreach ($matches as $match) {
                // Initialize teams if not exists
                if (!isset($standings[$group_name][$match['team1_id']])) {
                    $standings[$group_name][$match['team1_id']] = [
                        'team_name' => $match['team1_name'],
                        'team_id' => $match['team1_id'],
                        'played' => 0,
                        'wins' => 0,
                        'draws' => 0,
                        'losses' => 0,
                        'goals_for' => 0,
                        'goals_against' => 0,
                        'points' => 0
                    ];
                }
                if (!isset($standings[$group_name][$match['team2_id']])) {
                    $standings[$group_name][$match['team2_id']] = [
                        'team_name' => $match['team2_name'],
                        'team_id' => $match['team2_id'],
                        'played' => 0,
                        'wins' => 0,
                        'draws' => 0,
                        'losses' => 0,
                        'goals_for' => 0,
                        'goals_against' => 0,
                        'points' => 0
                    ];
                }
                
                // Update stats if match is completed
                if ($match['status'] == 'Completed') {
                    $standings[$group_name][$match['team1_id']]['played']++;
                    $standings[$group_name][$match['team2_id']]['played']++;
                    $standings[$group_name][$match['team1_id']]['goals_for'] += $match['team1_score'];
                    $standings[$group_name][$match['team1_id']]['goals_against'] += $match['team2_score'];
                    $standings[$group_name][$match['team2_id']]['goals_for'] += $match['team2_score'];
                    $standings[$group_name][$match['team2_id']]['goals_against'] += $match['team1_score'];
                    
                    if ($match['team1_score'] > $match['team2_score']) {
                        $standings[$group_name][$match['team1_id']]['wins']++;
                        $standings[$group_name][$match['team1_id']]['points'] += 3;
                        $standings[$group_name][$match['team2_id']]['losses']++;
                    } elseif ($match['team2_score'] > $match['team1_score']) {
                        $standings[$group_name][$match['team2_id']]['wins']++;
                        $standings[$group_name][$match['team2_id']]['points'] += 3;
                        $standings[$group_name][$match['team1_id']]['losses']++;
                    } else {
                        $standings[$group_name][$match['team1_id']]['draws']++;
                        $standings[$group_name][$match['team1_id']]['points'] += 1;
                        $standings[$group_name][$match['team2_id']]['draws']++;
                        $standings[$group_name][$match['team2_id']]['points'] += 1;
                    }
                }
            }
            
            // Convert to array and sort
            $standings[$group_name] = array_values($standings[$group_name]);
            usort($standings[$group_name], function($a, $b) {
                if ($a['points'] != $b['points']) return $b['points'] - $a['points'];
                $gd_a = $a['goals_for'] - $a['goals_against'];
                $gd_b = $b['goals_for'] - $b['goals_against'];
                if ($gd_a != $gd_b) return $gd_b - $gd_a;
                return $b['goals_for'] - $a['goals_for'];
            });
        }
        $tournament_standings[$tournament_id] = $standings;
    }
}
?>

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="page-title">
                <i class="fas fa-calendar-alt me-2"></i>Sports Timetable
            </h2>
            <div>
                <?php if ($can_edit): ?>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#generateTimetableModal">
                    <i class="fas fa-magic me-2"></i>Generate Group Stage
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateKnockoutModal">
                    <i class="fas fa-trophy me-2"></i>Generate Knockout Stages
                </button>
                <?php endif; ?>
                <button class="btn btn-outline-secondary ms-2" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Timetable
                </button>
                <a href="sports.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Tournament Progression:</strong>
            <ul class="mb-0 mt-2">
                <li><strong>Step 1:</strong> Generate Group Stage matches using "Generate Group Stage" button</li>
                <li><strong>Step 2:</strong> Enter group stage results in Sports Management page</li>
                <li><strong>Step 3:</strong> Once all group stage matches are completed, use "Generate Knockout Stages" to create Quarter Finals, Semi Finals, and Final matches</li>
                <li><strong>Step 4:</strong> Enter knockout stage results to determine tournament champion</li>
            </ul>
        </div>

        <!-- Tournament Filter -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Timetable</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter by Tournament</label>
                        <select id="tournamentFilter" class="form-select">
                            <option value="all">All Tournaments</option>
                            <?php 
                            mysqli_data_seek($tournaments_result, 0);
                            while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                            ?>
                                <option value="<?php echo htmlspecialchars($tournament['tournament_name']); ?>">
                                    <?php echo htmlspecialchars($tournament['tournament_name']); ?> (<?php echo $tournament['year']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Filter by Stage</label>
                        <select id="stageFilter" class="form-select">
                            <option value="all">All Stages</option>
                            <option value="Group Stage">Group Stage</option>
                            <option value="Quarter Finals">Quarter Finals</option>
                            <option value="Semi Finals">Semi Finals</option>
                            <option value="Final">Final</option>
                            <option value="3rd Place Playoff">3rd Place Playoff</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Filter by Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="all">All Status</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="In Progress">In Progress</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                            <i class="fas fa-undo-alt me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timetable Display -->
        <?php if (empty($tournament_matches)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h5>No Timetable Available</h5>
                    <p class="text-muted">Please generate group stage matches for tournaments.</p>
                    <?php if ($can_edit): ?>
                    <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#generateTimetableModal">
                        <i class="fas fa-magic me-2"></i>Generate Group Stage
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($tournament_matches as $tournament_id => $tournament): ?>
                <div class="card mb-4 tournament-card" data-tournament-name="<?php echo htmlspecialchars($tournament['name']); ?>">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>
                                <?php echo htmlspecialchars($tournament['name']); ?> (<?php echo $tournament['year']; ?>)
                            </h4>
                            <span class="badge bg-light text-dark">
                                <?php echo $tournament['status']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php 
                        $stage_order = ['Group Stage', 'Quarter Finals', 'Semi Finals', '3rd Place Playoff', 'Final'];
                        foreach ($stage_order as $stage_name): 
                            if (isset($tournament['stages'][$stage_name])):
                                $stage = $tournament['stages'][$stage_name];
                        ?>
                            <div class="stage-section mb-4" data-stage-name="<?php echo htmlspecialchars($stage_name); ?>">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">
                                        <span class="badge" style="background-color: <?php echo $stage['color'] ?? '#6c757d'; ?>; background: <?php echo $stage['bg'] ?? '#e9ecef'; ?>; color: <?php echo $stage['color'] ?? '#6c757d'; ?>; padding: 8px 15px;">
                                            <i class="fas <?php echo $stage_name == 'Group Stage' ? 'fa-layer-group' : ($stage_name == 'Final' ? 'fa-crown' : 'fa-list-ol'); ?> me-2"></i>
                                            <?php echo $stage_name; ?>
                                        </span>
                                    </h5>
                                </div>
                                
                                <?php foreach ($stage['groups'] as $group_name => $matches): ?>
                                    <div class="group-section mb-4" data-group-name="<?php echo htmlspecialchars($group_name); ?>">
                                        <?php if ($group_name != 'Main' && $stage_name == 'Group Stage'): ?>
                                            <h6 class="text-muted mb-2">
                                                <i class="fas fa-layer-group me-1"></i>Group <?php echo $group_name; ?>
                                            </h6>
                                        <?php endif; ?>
                                        
                                        <?php if ($stage_name == 'Group Stage' && isset($tournament_standings[$tournament_id][$group_name])): ?>
                                            <!-- Group Standings Table -->
                                            <div class="table-responsive mb-3">
                                                <table class="table table-sm table-bordered standings-table">
                                                    <thead class="table-dark">
                                                        <tr style="text-align: center;">
                                                            <th>Pos</th>
                                                            <th>Team</th>
                                                            <th>P</th>
                                                            <th>W</th>
                                                            <th>D</th>
                                                            <th>L</th>
                                                            <th>GF</th>
                                                            <th>GA</th>
                                                            <th>GD</th>
                                                            <th>Pts</th>
                                                            <th>Qualification</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $pos = 1; foreach ($tournament_standings[$tournament_id][$group_name] as $team): ?>
                                                        <tr style="text-align: center;">
                                                            <td><strong><?php echo $pos++; ?></strong></td>
                                                            <td style="text-align: left;"><?php echo htmlspecialchars($team['team_name']); ?></td>
                                                            <td><?php echo $team['played']; ?></td>
                                                            <td><?php echo $team['wins']; ?></td>
                                                            <td><?php echo $team['draws']; ?></td>
                                                            <td><?php echo $team['losses']; ?></td>
                                                            <td><?php echo $team['goals_for']; ?></td>
                                                            <td><?php echo $team['goals_against']; ?></td>
                                                            <td><?php echo $team['goals_for'] - $team['goals_against']; ?></td>
                                                            <td><strong><?php echo $team['points']; ?></strong></td>
                                                            <td>
                                                                <?php if ($pos <= 3): ?>
                                                                    <?php if ($pos == 2): ?>
                                                                        <span class="badge bg-success">Qualified (Winner)</span>
                                                                    <?php elseif ($pos == 3): ?>
                                                                        <span class="badge bg-info">Qualified (Runner-up)</span>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Eliminated</span>
                                                                <?php endif; ?>
                                                             </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Matches Table -->
                                        <div class="table-responsive">
                                            <table class="table table-hover timetable-table">
                                                <thead class="table-light">
                                                    <tr style="text-align: center;">
                                                        <th width="8%">#</th>
                                                        <th width="35%">Teams</th>
                                                        <th width="18%">Date</th>
                                                        <th width="12%">Time</th>
                                                        <th width="12%">Status</th>
                                                        <th width="15%">Result</th>
                                                        <?php if ($can_edit && $stage_name != 'Group Stage'): ?>
                                                        <th width="10%">Actions</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $match_num = 1; foreach ($matches as $match): ?>
                                                        <tr data-match-status="<?php echo $match['status']; ?>">
                                                            <td class="text-center"><?php echo $match_num++; ?></td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($match['team1_name']); ?></strong>
                                                                <span class="text-muted mx-2">vs</span>
                                                                <strong><?php echo htmlspecialchars($match['team2_name']); ?></strong>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php 
                                                                if (!empty($match['match_date']) && $match['match_date'] != '0000-00-00') {
                                                                    echo date('M d, Y', strtotime($match['match_date']));
                                                                } else {
                                                                    echo '<span class="text-muted">TBD</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php 
                                                                if (!empty($match['match_time']) && $match['match_time'] != '00:00:00') {
                                                                    echo date('h:i A', strtotime($match['match_time']));
                                                                } else {
                                                                    echo '<span class="text-muted">TBD</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php
                                                                $status_class = [
                                                                    'Scheduled' => 'warning',
                                                                    'In Progress' => 'info',
                                                                    'Completed' => 'success',
                                                                    'Postponed' => 'danger',
                                                                    'Cancelled' => 'secondary'
                                                                ];
                                                                $class = $status_class[$match['status']] ?? 'secondary';
                                                                ?>
                                                                <span class="badge bg-<?php echo $class; ?>">
                                                                    <?php echo $match['status']; ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if ($match['status'] == 'Completed' && isset($match['team1_score'])): ?>
                                                                    <span class="badge bg-success" style="font-size: 1rem;">
                                                                        <?php echo $match['team1_score']; ?> - <?php echo $match['team2_score']; ?>
                                                                    </span>
                                                                    <?php if ($match['winner_name']): ?>
                                                                        <br><small class="text-warning">🏆 Winner: <?php echo htmlspecialchars($match['winner_name']); ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <?php if ($can_edit && $stage_name != 'Group Stage'): ?>
                                                            <td class="text-center">
                                                                <button class="btn btn-sm btn-outline-primary edit-match-time" 
                                                                        data-id="<?php echo $match['id']; ?>"
                                                                        data-date="<?php echo $match['match_date']; ?>"
                                                                        data-time="<?php echo $match['match_time']; ?>"
                                                                        title="Edit Date/Time">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger delete-match" 
                                                                        data-id="<?php echo $match['id']; ?>"
                                                                        data-teams="<?php echo htmlspecialchars($match['team1_name'] . ' vs ' . $match['team2_name']); ?>"
                                                                        title="Delete Match">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Legend Section -->
        <div class="card mt-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Timetable Legend</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <span class="badge bg-warning me-2">Scheduled</span> - Match scheduled but not started
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-info me-2">In Progress</span> - Match currently ongoing
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-success me-2">Completed</span> - Match finished with result
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-secondary me-2">TBD</span> - Date/Time to be determined
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <i class="fas fa-trophy text-warning me-2"></i>
                        <strong>Tournament Progression:</strong>
                        <ul class="mt-2">
                            <li>Group Stage: Top 2 teams from each group qualify for Quarter Finals</li>
                            <li>Quarter Finals: Winners advance to Semi Finals</li>
                            <li>Semi Finals: Winners advance to Final, Losers go to 3rd Place Playoff</li>
                            <li>Final: Tournament Champion determined</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <i class="fas fa-chart-line text-info me-2"></i>
                        <strong>Group Stage Ranking:</strong>
                        <ul class="mt-2">
                            <li>Teams ranked by: Points → Goal Difference → Goals Scored</li>
                            <li>Win = 3 points, Draw = 1 point, Loss = 0 points</li>
                            <li>Top 2 teams from each group advance to Quarter Finals</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generate Group Stage Modal -->
<?php if ($can_edit): ?>
<div class="modal fade" id="generateTimetableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-magic me-2"></i>Generate Group Stage Matches</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate_timetable">
                    <div class="mb-3">
                        <label class="form-label">Select Tournament *</label>
                        <select name="tournament_id" class="form-select" required>
                            <option value="">Choose tournament...</option>
                            <?php 
                            mysqli_data_seek($tournaments_result, 0);
                            while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                                if ($tournament['team_count'] > 0):
                            ?>
                                <option value="<?php echo $tournament['id']; ?>">
                                    <?php echo htmlspecialchars($tournament['tournament_name']); ?> 
                                    (<?php echo $tournament['team_count']; ?> teams)
                                </option>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Time *</label>
                        <input type="time" name="match_time" class="form-control" value="08:00" required>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> This will delete all existing matches for the selected tournament and generate new group stage matches in round-robin format.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Group Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generate Knockout Stages Modal -->
<div class="modal fade" id="generateKnockoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-trophy me-2"></i>Generate Knockout Stages</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate_knockout_stages">
                    <div class="mb-3">
                        <label class="form-label">Select Tournament *</label>
                        <select name="tournament_id" class="form-select" required>
                            <option value="">Choose tournament...</option>
                            <?php 
                            mysqli_data_seek($tournaments_result, 0);
                            while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                                if ($tournament['team_count'] > 0):
                            ?>
                                <option value="<?php echo $tournament['id']; ?>">
                                    <?php echo htmlspecialchars($tournament['tournament_name']); ?> 
                                    (<?php echo $tournament['team_count']; ?> teams)
                                </option>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quarter Finals Start Date *</label>
                        <input type="date" name="quarter_start_date" class="form-control" required>
                        <small class="text-muted">Date for quarter final matches</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semi Finals Start Date *</label>
                        <input type="date" name="semi_start_date" class="form-control" required>
                        <small class="text-muted">Date for semi final matches</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Final Date *</label>
                        <input type="date" name="final_date" class="form-control" required>
                        <small class="text-muted">Date for final match and 3rd place playoff</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Match Time *</label>
                        <input type="time" name="match_time" class="form-control" value="14:00" required>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Requirements:</strong>
                        <ul class="mt-2 mb-0">
                            <li>All group stage matches must be completed</li>
                            <li>Top 2 teams from each group will qualify for quarter finals</li>
                            <li>Quarter final winners advance to semi finals</li>
                            <li>Semi final winners advance to final</li>
                            <li>Semi final losers play for 3rd place</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Generate Knockout Stages</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Match Time Modal -->
<div class="modal fade" id="editMatchTimeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Match Date & Time</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_match_time">
                    <input type="hidden" name="match_id" id="edit_match_id">
                    <div class="mb-3">
                        <label class="form-label">Match Date *</label>
                        <input type="date" name="match_date" id="edit_match_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Match Time *</label>
                        <input type="time" name="match_time" id="edit_match_time" class="form-control" required>
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
<?php endif; ?>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    const tournamentFilter = document.getElementById('tournamentFilter');
    const stageFilter = document.getElementById('stageFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    function filterTimetable() {
        const tournamentValue = tournamentFilter ? tournamentFilter.value : 'all';
        const stageValue = stageFilter ? stageFilter.value : 'all';
        const statusValue = statusFilter ? statusFilter.value : 'all';
        
        const tournamentCards = document.querySelectorAll('.tournament-card');
        
        tournamentCards.forEach(card => {
            const tournamentName = card.getAttribute('data-tournament-name');
            let showTournament = tournamentValue === 'all' || tournamentName === tournamentValue;
            
            if (showTournament) {
                const stageSections = card.querySelectorAll('.stage-section');
                let hasVisibleStage = false;
                
                stageSections.forEach(section => {
                    const stageName = section.getAttribute('data-stage-name');
                    let showStage = stageValue === 'all' || stageName === stageValue;
                    
                    if (showStage) {
                        const matchRows = section.querySelectorAll('tbody tr');
                        let hasVisibleMatch = false;
                        
                        matchRows.forEach(row => {
                            const matchStatus = row.getAttribute('data-match-status');
                            let showMatch = statusValue === 'all' || matchStatus === statusValue;
                            
                            if (showMatch) {
                                row.style.display = '';
                                hasVisibleMatch = true;
                            } else {
                                row.style.display = 'none';
                            }
                        });
                        
                        if (hasVisibleMatch) {
                            section.style.display = '';
                            hasVisibleStage = true;
                        } else {
                            section.style.display = 'none';
                        }
                    } else {
                        section.style.display = 'none';
                    }
                });
                
                if (hasVisibleStage) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    if (tournamentFilter) tournamentFilter.addEventListener('change', filterTimetable);
    if (stageFilter) stageFilter.addEventListener('change', filterTimetable);
    if (statusFilter) statusFilter.addEventListener('change', filterTimetable);
    
    <?php if ($can_edit): ?>
    // Edit match time modal
    const editButtons = document.querySelectorAll('.edit-match-time');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const matchId = this.getAttribute('data-id');
            const matchDate = this.getAttribute('data-date');
            const matchTime = this.getAttribute('data-time');
            
            document.getElementById('edit_match_id').value = matchId;
            document.getElementById('edit_match_date').value = matchDate;
            // Format time for input (HH:MM)
            const timeParts = matchTime.split(':');
            const formattedTime = timeParts[0] + ':' + timeParts[1];
            document.getElementById('edit_match_time').value = formattedTime;
            
            const modal = new bootstrap.Modal(document.getElementById('editMatchTimeModal'));
            modal.show();
        });
    });
    
    // Delete match confirmation
    const deleteButtons = document.querySelectorAll('.delete-match');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const matchId = this.getAttribute('data-id');
            const teams = this.getAttribute('data-teams');
            
            Swal.fire({
                title: 'Delete Match?',
                text: `Are you sure you want to delete the match: ${teams}?`,
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
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
    <?php endif; ?>
    
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
    
    <?php if (isset($_SESSION['info'])): ?>
        Swal.fire({
            title: 'Info',
            text: '<?php echo htmlspecialchars($_SESSION['info']); ?>',
            icon: 'info',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6'
        });
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>
});

function resetFilters() {
    const tournamentFilter = document.getElementById('tournamentFilter');
    const stageFilter = document.getElementById('stageFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    if (tournamentFilter) tournamentFilter.value = 'all';
    if (stageFilter) stageFilter.value = 'all';
    if (statusFilter) statusFilter.value = 'all';
    
    const tournamentCards = document.querySelectorAll('.tournament-card');
    tournamentCards.forEach(card => card.style.display = '');
    
    const stageSections = document.querySelectorAll('.stage-section');
    stageSections.forEach(section => section.style.display = '');
    
    const matchRows = document.querySelectorAll('tbody tr');
    matchRows.forEach(row => row.style.display = '');
}
</script>

<style>
/* Timetable Styles */
.timetable-table {
    border-radius: 10px;
    overflow: hidden;
}

.timetable-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid var(--primary-color);
}

.timetable-table td {
    vertical-align: middle;
    padding: 12px;
}

.standings-table {
    font-size: 0.9rem;
}

.standings-table th {
    background-color: #2c3e50;
    color: white;
    font-weight: 600;
}

.standings-table td {
    padding: 8px;
}

.stage-section {
    border-left: 3px solid var(--primary-color);
    padding-left: 20px;
}

.group-section {
    background: #fefefe;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Alert styles */
.alert-info {
    background-color: #e7f3ff;
    border-color: #b8daff;
    color: #004085;
    border-radius: 10px;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
    border-radius: 10px;
}

/* Card header gradient */
.card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
}

/* Responsive styles */
@media (max-width: 768px) {
    .timetable-table {
        font-size: 0.85rem;
    }
    
    .stage-section {
        padding-left: 10px;
    }
    
    .group-section {
        padding: 10px;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
    }
    
    .standings-table {
        font-size: 0.75rem;
    }
}

/* Badge styles */
.badge {
    font-weight: 500;
    padding: 6px 12px;
}

/* Table hover effect */
.table-hover tbody tr:hover {
    background-color: rgba(59, 157, 179, 0.08);
    transition: all 0.2s ease;
}

/* Print styles */
@media print {
    .sidebar, .header, .btn, .modal, .card-header .btn, .no-print, .alert-info {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }
    
    .timetable-table, .standings-table {
        font-size: 10pt !important;
    }
    
    .stage-section {
        break-inside: avoid;
    }
    
    .group-section {
        break-inside: avoid;
    }
}
</style>

<?php include '../controller/footer.php'; ?>