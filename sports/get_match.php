<?php
session_start();
require_once '../controller/db_connect.php';

if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit();
}

$match_id = intval($_GET['id']);

$sql = "SELECT m.*, 
        gt.game_name,
        s.stage_name,
        t1.team_name as team1_name,
        t2.team_name as team2_name,
        wt.team_name as winner_name
        FROM matches m
        LEFT JOIN game_types gt ON m.game_type_id = gt.id
        LEFT JOIN tournament_stages s ON m.stage_id = s.id
        LEFT JOIN teams t1 ON m.team1_id = t1.id
        LEFT JOIN teams t2 ON m.team2_id = t2.id
        LEFT JOIN teams wt ON m.winner_team_id = wt.id
        WHERE m.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $match_id);
$stmt->execute();
$result = $stmt->get_result();
$match = $result->fetch_assoc();

if (!$match) {
    echo '<div class="alert alert-danger">Match not found.</div>';
    exit();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <strong>Match Information</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="35%">Tournament:</th>
                            <td><?php echo htmlspecialchars($match['tournament_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Game Type:</th>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($match['game_name']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Stage:</th>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($match['stage_name']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Match Date:</th>
                            <td><?php echo date('l, F j, Y', strtotime($match['match_date'])); ?></td>
                        </tr>
                        <tr>
                            <th>Match Time:</th>
                            <td><?php echo date('h:i A', strtotime($match['match_time'])); ?></td>
                        </tr>
                        <tr>
                            <th>Venue:</th>
                            <td><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($match['venue']); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
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
                                <span class="badge bg-<?php echo $class; ?>"><?php echo $match['status']; ?></span>
                            </td>
                        </tr>
                        <?php if ($match['description']): ?>
                        <tr>
                            <th>Description:</th>
                            <td><?php echo htmlspecialchars($match['description']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <strong>Match Result</strong>
                </div>
                <div class="card-body text-center">
                    <?php if ($match['status'] == 'Completed'): ?>
                        <div class="row">
                            <div class="col-5">
                                <h4><?php echo htmlspecialchars($match['team1_name']); ?></h4>
                                <div class="display-4 text-primary"><?php echo $match['team1_score']; ?></div>
                            </div>
                            <div class="col-2">
                                <h2 class="mt-4">VS</h2>
                            </div>
                            <div class="col-5">
                                <h4><?php echo htmlspecialchars($match['team2_name']); ?></h4>
                                <div class="display-4 text-primary"><?php echo $match['team2_score']; ?></div>
                            </div>
                        </div>
                        <?php if ($match['winner_name']): ?>
                            <div class="mt-3">
                                <span class="badge bg-warning text-dark" style="font-size: 1rem;">
                                    🏆 Winner: <?php echo htmlspecialchars($match['winner_name']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="py-4">
                            <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Match result not yet available.</p>
                            <p class="small text-muted">Scheduled for <?php echo date('M d, Y h:i A', strtotime($match['match_date'] . ' ' . $match['match_time'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($match['status'] == 'Completed'): ?>
    <div class="card">
        <div class="card-header bg-info text-white">
            <strong>Match Statistics</strong>
        </div>
        <div class="card-body">
            <?php
            // Get match statistics
            $stats_sql = "SELECT * FROM match_statistics WHERE match_id = ? ORDER BY event_time";
            $stmt = $conn->prepare($stats_sql);
            $stmt->bind_param("i", $match_id);
            $stmt->execute();
            $stats_result = $stmt->get_result();
            ?>
            <?php if ($stats_result && mysqli_num_rows($stats_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Team</th>
                                <th>Event</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($stat = mysqli_fetch_assoc($stats_result)): ?>
                                <tr>
                                    <td><?php echo date('i:s', strtotime($stat['event_time'])); ?></td>
                                    <td>
                                        <?php
                                        $team_sql = "SELECT team_name FROM teams WHERE id = ?";
                                        $team_stmt = $conn->prepare($team_sql);
                                        $team_stmt->bind_param("i", $stat['team_id']);
                                        $team_stmt->execute();
                                        $team_result = $team_stmt->get_result();
                                        $team = $team_result->fetch_assoc();
                                        echo htmlspecialchars($team['team_name'] ?? 'N/A');
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $event_icons = [
                                            'Goal' => '⚽',
                                            'Yellow Card' => '🟨',
                                            'Red Card' => '🟥',
                                            'Substitution' => '🔄',
                                            'Injury' => '⚠️'
                                        ];
                                        $icon = $event_icons[$stat['event_type']] ?? '📋';
                                        echo $icon . ' ' . $stat['event_type'];
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($stat['description'] ?: '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center">No match statistics available.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>