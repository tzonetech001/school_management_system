<?php
// team_analysis.php - Comprehensive Team Analysis Page
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get filter parameters
$team_type_filter = isset($_GET['team_type']) ? $_GET['team_type'] : '';
$tournament_id_filter = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Get all teams with their overall statistics
$teams_sql = "SELECT t.*, 
              COUNT(DISTINCT tt.tournament_id) as tournaments_played,
              SUM(tt.matches_played) as total_matches,
              SUM(tt.wins) as total_wins,
              SUM(tt.draws) as total_draws,
              SUM(tt.losses) as total_losses,
              SUM(tt.goals_for) as total_goals_for,
              SUM(tt.goals_against) as total_goals_against,
              SUM(tt.goal_difference) as total_goal_difference,
              SUM(tt.points) as total_points
              FROM teams t
              LEFT JOIN tournament_teams tt ON t.id = tt.team_id
              WHERE t.is_active = TRUE";

// Add filters
if ($team_type_filter) {
    $teams_sql .= " AND t.team_type = '$team_type_filter'";
}
if ($search_term) {
    $teams_sql .= " AND t.team_name LIKE '%$search_term%'";
}

$teams_sql .= " GROUP BY t.id 
                ORDER BY total_points DESC, total_goal_difference DESC, total_goals_for DESC";

$teams_result = mysqli_query($conn, $teams_sql);

// Get all tournaments for filter
$tournaments_sql = "SELECT id, tournament_name, season FROM tournaments ORDER BY start_date DESC";
$tournaments_result = mysqli_query($conn, $tournaments_sql);

// Get tournament details if selected
$selected_tournament = null;
if ($tournament_id_filter > 0) {
    $tournament_sql = "SELECT * FROM tournaments WHERE id = ?";
    $stmt = $conn->prepare($tournament_sql);
    $stmt->bind_param("i", $tournament_id_filter);
    $stmt->execute();
    $selected_tournament = $stmt->get_result()->fetch_assoc();
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
                    <i class="fas fa-chart-line me-2"></i>
                    Team Analysis & Statistics
                </h2>
                <p class="text-muted">Comprehensive analysis of all teams across tournaments</p>
            </div>
            <div>
                <a href="sports.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Sports
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Teams</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Team Type</label>
                        <select name="team_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="Form Five Combination" <?php echo $team_type_filter == 'Form Five Combination' ? 'selected' : ''; ?>>Form V</option>
                            <option value="Form Six Combination" <?php echo $team_type_filter == 'Form Six Combination' ? 'selected' : ''; ?>>Form VI</option>
                            <option value="Staff" <?php echo $team_type_filter == 'Staff' ? 'selected' : ''; ?>>Staff</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tournament</label>
                        <select name="tournament_id" class="form-select">
                            <option value="0">All Tournaments</option>
                            <?php while ($tournament = mysqli_fetch_assoc($tournaments_result)): ?>
                                <option value="<?php echo $tournament['id']; ?>" <?php echo $tournament_id_filter == $tournament['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tournament['tournament_name']); ?> (<?php echo $tournament['season']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search Team</label>
                        <input type="text" name="search" class="form-control" placeholder="Enter team name..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Statistics Cards -->
        <?php
        // Calculate summary statistics
        $total_teams = mysqli_num_rows($teams_result);
        
        // Reset pointer for summary calculation
        mysqli_data_seek($teams_result, 0);
        $summary = [
            'total_matches' => 0,
            'total_goals' => 0,
            'total_points' => 0,
            'total_wins' => 0,
            'total_draws' => 0,
            'total_losses' => 0
        ];
        
        while ($team = mysqli_fetch_assoc($teams_result)) {
            $summary['total_matches'] += $team['total_matches'];
            $summary['total_goals'] += $team['total_goals_for'];
            $summary['total_points'] += $team['total_points'];
            $summary['total_wins'] += $team['total_wins'];
            $summary['total_draws'] += $team['total_draws'];
            $summary['total_losses'] += $team['total_losses'];
        }
        
        // Reset pointer again for main display
        mysqli_data_seek($teams_result, 0);
        ?>
        
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-users" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $total_teams; ?></h3>
                    <p>Total Teams</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-futbol" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $summary['total_matches']; ?></h3>
                    <p>Total Matches Played</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-chart-line" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $summary['total_points']; ?></h3>
                    <p>Total Points</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-trophy" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $summary['total_wins']; ?></h3>
                    <p>Total Wins</p>
                </div>
            </div>
        </div>

        <!-- Teams Analysis Table -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    <?php if ($selected_tournament): ?>
                        Team Analysis - <?php echo htmlspecialchars($selected_tournament['tournament_name']); ?>
                    <?php else: ?>
                        All Teams Performance Analysis
                    <?php endif; ?>
                </h4>
            </div>
            <div class="card-body">
                <?php if ($total_teams > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="teamsTable">
                            <thead class="table-light">
                                <tr style="text-align: center;">
                                    <th>#</th>
                                    <th>Team</th>
                                    <th>Type</th>
                                    <th>Tournaments</th>
                                    <th>P</th>
                                    <th>W</th>
                                    <th>D</th>
                                    <th>L</th>
                                    <th>GF</th>
                                    <th>GA</th>
                                    <th>GD</th>
                                    <th>Points</th>
                                    <th>Win %</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; while ($team = mysqli_fetch_assoc($teams_result)): 
                                    $win_percentage = $team['total_matches'] > 0 ? round(($team['total_wins'] / $team['total_matches']) * 100, 1) : 0;
                                    $avg_goals_per_match = $team['total_matches'] > 0 ? round($team['total_goals_for'] / $team['total_matches'], 2) : 0;
                                ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: bold;"><?php echo $rank++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($team['team_name']); ?></strong>
                                            <?php if ($team['combination_code']): ?>
                                                <br><small class="text-muted">(<?php echo $team['combination_code']; ?>)</small>
                                            <?php endif; ?>
                                        </td>
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
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge bg-secondary"><?php echo $team['tournaments_played']; ?></span>
                                        </td>
                                        <td style="text-align: center;"><?php echo $team['total_matches']; ?></td>
                                        <td style="text-align: center; color: green;"><?php echo $team['total_wins']; ?></td>
                                        <td style="text-align: center; color: orange;"><?php echo $team['total_draws']; ?></td>
                                        <td style="text-align: center; color: red;"><?php echo $team['total_losses']; ?></td>
                                        <td style="text-align: center;"><?php echo $team['total_goals_for']; ?></td>
                                        <td style="text-align: center;"><?php echo $team['total_goals_against']; ?></td>
                                        <td style="text-align: center; font-weight: bold; <?php echo $team['total_goal_difference'] >= 0 ? 'color: green;' : 'color: red;'; ?>">
                                            <?php echo $team['total_goal_difference'] >= 0 ? '+' : ''; ?><?php echo $team['total_goal_difference']; ?>
                                        </td>
                                        <td style="text-align: center; font-weight: bold; color: var(--primary-color);"><?php echo $team['total_points']; ?></td>
                                        <td style="text-align: center;">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $win_percentage; ?>%;" 
                                                     aria-valuenow="<?php echo $win_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $win_percentage; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <button class="btn btn-sm btn-info" onclick="showTeamDetails(<?php echo $team['id']; ?>, '<?php echo addslashes($team['team_name']); ?>')">
                                                <i class="fas fa-chart-line"></i> Analysis
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>No teams found matching your criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Performance Indicators Section -->
        <div class="row mt-4">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Performance Metrics Explanation</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Key Statistics:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-chart-line text-primary me-2"></i> <strong>Points:</strong> Total points accumulated</li>
                                    <li><i class="fas fa-futbol text-success me-2"></i> <strong>GF:</strong> Goals For (Goals scored)</li>
                                    <li><i class="fas fa-shield-alt text-danger me-2"></i> <strong>GA:</strong> Goals Against (Goals conceded)</li>
                                    <li><i class="fas fa-balance-scale me-2"></i> <strong>GD:</strong> Goal Difference (GF - GA)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Performance Indicators:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-trophy text-warning me-2"></i> <strong>Win %:</strong> Percentage of matches won</li>
                                    <li><i class="fas fa-chart-bar text-info me-2"></i> <strong>Tournaments:</strong> Number of tournaments participated</li>
                                    <li><i class="fas fa-chart-line text-success me-2"></i> <strong>Form:</strong> Current performance trend</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Analysis Insights</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-chart-line me-2"></i>
                            <strong>Top Performers:</strong> Teams with highest points and win percentage
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-chart-line me-2"></i>
                            <strong>Improvement Areas:</strong> Teams with negative goal difference need defensive improvement
                        </div>
                        <div class="alert alert-success">
                            <i class="fas fa-chart-line me-2"></i>
                            <strong>Consistent Teams:</strong> Teams with high number of tournaments and positive records
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Team Details Modal -->
<div class="modal fade" id="teamDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <h5 class="modal-title"><i class="fas fa-chart-line me-2"></i>Team Performance Analysis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="teamDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading team analysis...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 and DataTables -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#teamsTable').DataTable({
        pageLength: 25,
        order: [[11, 'desc']], // Sort by points by default
        language: {
            search: "Search teams:",
            lengthMenu: "Show _MENU_ teams per page",
            info: "Showing _START_ to _END_ of _TOTAL_ teams",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
});

function showTeamDetails(teamId, teamName) {
    $('#teamDetailsModal').modal('show');
    $('#teamDetailsModal .modal-title').html('<i class="fas fa-chart-line me-2"></i>' + teamName + ' - Performance Analysis');
    
    // Fetch team details via AJAX
    $.ajax({
        url: 'get_team_analysis.php',
        method: 'POST',
        data: { team_id: teamId },
        success: function(response) {
            $('#teamDetailsContent').html(response);
        },
        error: function() {
            $('#teamDetailsContent').html('<div class="alert alert-danger">Error loading team analysis. Please try again.</div>');
        }
    });
}

// Print functionality
window.print = function() {
    var printContent = document.querySelector('.main-content .container-fluid').cloneNode(true);
    var originalTitle = document.title;
    document.title = 'Team Analysis Report';
    
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Team Analysis Report</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">');
    printWindow.document.write('<style>@media print { .btn, .no-print { display: none; } body { padding: 20px; } }</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<div class="container-fluid">' + printContent.innerHTML + '</div>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
    document.title = originalTitle;
}
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

.progress {
    background-color: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.3s ease;
}

@media (max-width: 768px) {
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
}

.dataTables_filter {
    margin-bottom: 20px;
}

.dataTables_filter input {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 5px 10px;
    margin-left: 10px;
}

.paginate_button {
    padding: 5px 10px;
    margin: 0 2px;
    border-radius: 5px;
}

.paginate_button.current {
    background: #3B9DB3;
    color: white !important;
}
</style>

<?php include '../controller/footer.php'; ?>