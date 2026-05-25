<?php
// get_team_analysis.php - Fetch detailed team analysis for modal
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id']) || !isset($_POST['team_id'])) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit();
}

$team_id = intval($_POST['team_id']);

// Get team basic info
$team_sql = "SELECT * FROM teams WHERE id = ?";
$stmt = $conn->prepare($team_sql);
$stmt->bind_param("i", $team_id);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();

if (!$team) {
    echo '<div class="alert alert-danger">Team not found.</div>';
    exit();
}

// Get tournament participation details
$tournaments_sql = "SELECT t.*, tt.points, tt.matches_played, tt.wins, tt.draws, tt.losses,
                     tt.goals_for, tt.goals_against, tt.goal_difference,
                     gt.game_name, gt.color_code
                     FROM tournament_teams tt
                     JOIN tournaments t ON tt.tournament_id = t.id
                     LEFT JOIN game_types gt ON t.game_type_id = gt.id
                     WHERE tt.team_id = ?
                     ORDER BY t.start_date DESC";
$stmt = $conn->prepare($tournaments_sql);
$stmt->bind_param("i", $team_id);
$stmt->execute();
$tournaments_result = $stmt->get_result();

// Calculate overall statistics
$overall_stats = [
    'total_tournaments' => 0,
    'total_matches' => 0,
    'total_wins' => 0,
    'total_draws' => 0,
    'total_losses' => 0,
    'total_goals_for' => 0,
    'total_goals_against' => 0,
    'total_points' => 0
];

while ($tournament = mysqli_fetch_assoc($tournaments_result)) {
    $overall_stats['total_tournaments']++;
    $overall_stats['total_matches'] += $tournament['matches_played'];
    $overall_stats['total_wins'] += $tournament['wins'];
    $overall_stats['total_draws'] += $tournament['draws'];
    $overall_stats['total_losses'] += $tournament['losses'];
    $overall_stats['total_goals_for'] += $tournament['goals_for'];
    $overall_stats['total_goals_against'] += $tournament['goals_against'];
    $overall_stats['total_points'] += $tournament['points'];
}

// Reset pointer for display
mysqli_data_seek($tournaments_result, 0);

$win_percentage = $overall_stats['total_matches'] > 0 ? round(($overall_stats['total_wins'] / $overall_stats['total_matches']) * 100, 1) : 0;
$avg_goals_per_match = $overall_stats['total_matches'] > 0 ? round($overall_stats['total_goals_for'] / $overall_stats['total_matches'], 2) : 0;
$avg_goals_conceded = $overall_stats['total_matches'] > 0 ? round($overall_stats['total_goals_against'] / $overall_stats['total_matches'], 2) : 0;
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Team Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Team Name:</th>
                                <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Team Type:</th>
                                <td>
                                    <span class="badge bg-<?php echo $team['team_type'] == 'Form Five Combination' ? 'primary' : ($team['team_type'] == 'Form Six Combination' ? 'info' : 'warning'); ?>">
                                        <?php echo $team['team_type']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if ($team['combination_code']): ?>
                            <tr>
                                <th>Combination:</th>
                                <td><?php echo htmlspecialchars($team['combination_code']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo $team['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $team['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Registered:</th>
                                <td><?php echo date('F j, Y', strtotime($team['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Career Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="stats-mini-card">
                            <h3><?php echo $overall_stats['total_tournaments']; ?></h3>
                            <p>Tournaments</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-mini-card">
                            <h3><?php echo $overall_stats['total_matches']; ?></h3>
                            <p>Matches Played</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-mini-card">
                            <h3><?php echo $overall_stats['total_points']; ?></h3>
                            <p>Total Points</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-mini-card">
                            <h3><?php echo $win_percentage; ?>%</h3>
                            <p>Win Rate</p>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Match Record</h6>
                        <div class="progress mb-2" style="height: 25px;">
                            <?php 
                            $total = $overall_stats['total_matches'];
                            $wins_percent = $total > 0 ? ($overall_stats['total_wins'] / $total) * 100 : 0;
                            $draws_percent = $total > 0 ? ($overall_stats['total_draws'] / $total) * 100 : 0;
                            $losses_percent = $total > 0 ? ($overall_stats['total_losses'] / $total) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $wins_percent; ?>%">
                                Wins: <?php echo $overall_stats['total_wins']; ?>
                            </div>
                            <div class="progress-bar bg-warning" style="width: <?php echo $draws_percent; ?>%">
                                Draws: <?php echo $overall_stats['total_draws']; ?>
                            </div>
                            <div class="progress-bar bg-danger" style="width: <?php echo $losses_percent; ?>%">
                                Losses: <?php echo $overall_stats['total_losses']; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Goals Statistics</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <strong>GF</strong><br>
                                    <span class="h5 text-success"><?php echo $overall_stats['total_goals_for']; ?></span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <strong>GA</strong><br>
                                    <span class="h5 text-danger"><?php echo $overall_stats['total_goals_against']; ?></span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <strong>GD</strong><br>
                                    <span class="h5 <?php echo $overall_stats['total_goals_for'] - $overall_stats['total_goals_against'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $overall_stats['total_goals_for'] - $overall_stats['total_goals_against']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 text-center">
                            <small>Avg. <?php echo $avg_goals_per_match; ?> goals scored per match</small><br>
                            <small>Avg. <?php echo $avg_goals_conceded; ?> goals conceded per match</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Tournament Performance History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>Tournament</th>
                                <th>Game Type</th>
                                <th>Season</th>
                                <th>P</th>
                                <th>W</th>
                                <th>D</th>
                                <th>L</th>
                                <th>GF</th>
                                <th>GA</th>
                                <th>GD</th>
                                <th>Points</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($tournaments_result) > 0): ?>
                                <?php while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                                    $tournament_win_percentage = $tournament['matches_played'] > 0 ? round(($tournament['wins'] / $tournament['matches_played']) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($tournament['tournament_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo date('Y', strtotime($tournament['start_date'])); ?></small>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge" style="background-color: <?php echo $tournament['color_code'] ?? '#3B9DB3'; ?>; color: white;">
                                                <?php echo htmlspecialchars($tournament['game_name']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;"><?php echo $tournament['season']; ?></td>
                                        <td style="text-align: center;"><?php echo $tournament['matches_played']; ?></td>
                                        <td style="text-align: center; color: green;"><?php echo $tournament['wins']; ?></td>
                                        <td style="text-align: center; color: orange;"><?php echo $tournament['draws']; ?></td>
                                        <td style="text-align: center; color: red;"><?php echo $tournament['losses']; ?></td>
                                        <td style="text-align: center;"><?php echo $tournament['goals_for']; ?></td>
                                        <td style="text-align: center;"><?php echo $tournament['goals_against']; ?></td>
                                        <td style="text-align: center; <?php echo $tournament['goal_difference'] >= 0 ? 'color: green;' : 'color: red;'; ?>">
                                            <?php echo $tournament['goal_difference'] >= 0 ? '+' : ''; ?><?php echo $tournament['goal_difference']; ?>
                                        </td>
                                        <td style="text-align: center; font-weight: bold;"><?php echo $tournament['points']; ?></td>
                                        <td style="text-align: center;">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $tournament_win_percentage; ?>%;">
                                                    <?php echo $tournament_win_percentage; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="text-center">No tournament participation records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-mini-card {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
}

.stats-mini-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.stats-mini-card h3 {
    font-size: 2rem;
    font-weight: bold;
    margin: 0;
    color: #3B9DB3;
}

.stats-mini-card p {
    margin: 5px 0 0;
    color: #6c757d;
}

.progress {
    background-color: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    line-height: 25px;
    font-size: 12px;
    font-weight: bold;
}

.table th {
    font-weight: 600;
    background-color: rgba(59, 157, 179, 0.05);
}
</style>