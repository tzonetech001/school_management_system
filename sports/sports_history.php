<?php
// sports_history.php - View Completed Games with Year and Tournament Filtering
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_tournament = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$selected_game_type = isset($_GET['game_type']) ? intval($_GET['game_type']) : 0;
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Get all available years from tournaments
$years_sql = "SELECT DISTINCT year FROM tournaments WHERE year IS NOT NULL AND year > 0 ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_sql);

// Get all game types
$game_types_sql = "SELECT id, game_name, color_code FROM game_types WHERE status = 'Active' ORDER BY game_name";
$game_types_result = mysqli_query($conn, $game_types_sql);

// Get tournaments for selected year and game type
$tournaments_sql = "SELECT id, tournament_name, game_type_id FROM tournaments WHERE year = ?";
$params = [$selected_year];
$types = "i";

if ($selected_game_type > 0) {
    $tournaments_sql .= " AND game_type_id = ?";
    $params[] = $selected_game_type;
    $types .= "i";
}
$tournaments_sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($tournaments_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tournaments_result = $stmt->get_result();

// Get completed matches based on filters
$matches_sql = "SELECT m.*, 
                s.stage_name, s.color_code as stage_color, s.bg_color as stage_bg,
                t1.team_name as team1_name,
                t2.team_name as team2_name,
                wt.team_name as winner_name,
                tr.tournament_name,
                tr.year as tournament_year,
                tr.season,
                gt.game_name as game_name,
                gt.color_code as game_color
                FROM matches m
                LEFT JOIN tournament_stages s ON m.stage_id = s.id
                LEFT JOIN teams t1 ON m.team1_id = t1.id
                LEFT JOIN teams t2 ON m.team2_id = t2.id
                LEFT JOIN teams wt ON m.winner_team_id = wt.id
                LEFT JOIN tournaments tr ON m.tournament_id = tr.id
                LEFT JOIN game_types gt ON m.game_type_id = gt.id
                WHERE m.status = 'Completed'";

$params = [];
$types = "";

if ($selected_tournament > 0) {
    $matches_sql .= " AND m.tournament_id = ?";
    $params[] = $selected_tournament;
    $types .= "i";
} else {
    if ($selected_year > 0) {
        $matches_sql .= " AND tr.year = ?";
        $params[] = $selected_year;
        $types .= "i";
    }
    if ($selected_game_type > 0) {
        $matches_sql .= " AND m.game_type_id = ?";
        $params[] = $selected_game_type;
        $types .= "i";
    }
}

if ($search_term) {
    $matches_sql .= " AND (t1.team_name LIKE ? OR t2.team_name LIKE ? OR tr.tournament_name LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$matches_sql .= " ORDER BY m.match_date DESC, m.match_time DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($matches_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $matches_result = $stmt->get_result();
} else {
    $matches_result = mysqli_query($conn, $matches_sql);
}

// Get statistics
if ($selected_tournament > 0) {
    $stats_sql = "SELECT 
        COUNT(*) as total_matches,
        COUNT(DISTINCT m.team1_id) + COUNT(DISTINCT m.team2_id) as total_teams,
        (SELECT COUNT(DISTINCT winner_team_id) FROM matches WHERE status = 'Completed' AND tournament_id = ?) as unique_winners,
        SUM(m.team1_score + m.team2_score) as total_goals,
        AVG(m.team1_score + m.team2_score) as avg_goals_per_match
        FROM matches m
        WHERE m.status = 'Completed' AND m.tournament_id = ?";
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param("ii", $selected_tournament, $selected_tournament);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
} else {
    $stats_sql = "SELECT 
        COUNT(*) as total_matches,
        COUNT(DISTINCT m.team1_id) + COUNT(DISTINCT m.team2_id) as total_teams,
        (SELECT COUNT(DISTINCT winner_team_id) FROM matches WHERE status = 'Completed') as unique_winners,
        SUM(m.team1_score + m.team2_score) as total_goals,
        AVG(m.team1_score + m.team2_score) as avg_goals_per_match
        FROM matches m
        WHERE m.status = 'Completed'";
    
    if ($selected_year > 0) {
        $stats_sql .= " AND YEAR(m.match_date) = $selected_year";
    }
    if ($selected_game_type > 0) {
        $stats_sql .= " AND m.game_type_id = $selected_game_type";
    }
    $stats_result = mysqli_query($conn, $stats_sql);
    $stats = mysqli_fetch_assoc($stats_result);
}

// Get tournament champions (top performing teams)
$champions_sql = "SELECT 
    t.team_name,
    t.team_type,
    t.combination_code,
    COUNT(m.winner_team_id) as wins,
    SUM(m.team1_score + m.team2_score) as total_goals,
    COUNT(DISTINCT m.tournament_id) as tournaments_won
    FROM matches m
    JOIN teams t ON m.winner_team_id = t.id
    WHERE m.status = 'Completed' AND m.winner_team_id IS NOT NULL";
    
$params = [];
$types = "";

if ($selected_tournament > 0) {
    $champions_sql .= " AND m.tournament_id = ?";
    $params[] = $selected_tournament;
    $types .= "i";
} else {
    if ($selected_year > 0) {
        $champions_sql .= " AND YEAR(m.match_date) = ?";
        $params[] = $selected_year;
        $types .= "i";
    }
    if ($selected_game_type > 0) {
        $champions_sql .= " AND m.game_type_id = ?";
        $params[] = $selected_game_type;
        $types .= "i";
    }
}

$champions_sql .= " GROUP BY t.id ORDER BY wins DESC, total_goals DESC LIMIT 10";

if (!empty($params)) {
    $stmt = $conn->prepare($champions_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $champions_result = $stmt->get_result();
} else {
    $champions_result = mysqli_query($conn, $champions_sql);
}

// Get tournament winners by year
// Get tournament winners by year - FIXED GROUP BY issue
$tournament_winners_sql = "SELECT 
    tr.id,
    tr.tournament_name,
    tr.year,
    tr.season,
    gt.game_name,
    gt.color_code as game_color,
    t.team_name as champion_name,
    t.team_type as champion_type,
    COUNT(m.id) as total_matches
    FROM tournaments tr
    LEFT JOIN matches m ON m.tournament_id = tr.id AND m.status = 'Completed' AND m.winner_team_id IS NOT NULL
    LEFT JOIN teams t ON m.winner_team_id = t.id
    LEFT JOIN game_types gt ON tr.game_type_id = gt.id
    WHERE tr.is_archived = FALSE AND tr.status = 'Completed'";

if ($selected_game_type > 0) {
    $tournament_winners_sql .= " AND tr.game_type_id = $selected_game_type";
}
if ($selected_year > 0) {
    $tournament_winners_sql .= " AND tr.year = $selected_year";
}

$tournament_winners_sql .= " GROUP BY tr.id, tr.tournament_name, tr.year, tr.season, gt.game_name, gt.color_code, t.team_name, t.team_type
    ORDER BY tr.year DESC, tr.start_date DESC LIMIT 20";
$tournament_winners_result = mysqli_query($conn, $tournament_winners_sql);

// Get all completed matches count
$all_matches_count = 0;
if ($matches_result) {
    $all_matches_count = mysqli_num_rows($matches_result);
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
                    <i class="fas fa-history me-2"></i>Sports History - Completed Games
                </h2>
                <p class="text-muted">View all completed matches, tournament winners, and historical statistics</p>
            </div>
            <div>
                <a href="sports.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Current Sports
                </a>
                <button class="btn btn-success ms-2" onclick="exportToCSV()">
                    <i class="fas fa-file-excel me-2"></i>Export to Excel
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter History</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Game Type</label>
                        <select name="game_type" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Games</option>
                            <?php 
                            // Reset game types result pointer
                            mysqli_data_seek($game_types_result, 0);
                            while ($game = mysqli_fetch_assoc($game_types_result)): 
                            ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo $selected_game_type == $game['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['game_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select name="year" id="yearSelect" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Years</option>
                            <?php 
                            // Reset years result pointer
                            mysqli_data_seek($years_result, 0);
                            while ($year = mysqli_fetch_assoc($years_result)): 
                            ?>
                                <option value="<?php echo $year['year']; ?>" <?php echo $selected_year == $year['year'] ? 'selected' : ''; ?>>
                                    <?php echo $year['year']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tournament</label>
                        <select name="tournament_id" id="tournamentSelect" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Tournaments</option>
                            <?php 
                            // Reset tournaments result pointer
                            if (isset($tournaments_result) && $tournaments_result) {
                                mysqli_data_seek($tournaments_result, 0);
                            }
                            if ($tournaments_result && mysqli_num_rows($tournaments_result) > 0):
                                while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                            ?>
                                <option value="<?php echo $tournament['id']; ?>" <?php echo $selected_tournament == $tournament['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tournament['tournament_name']); ?>
                                </option>
                            <?php 
                                endwhile;
                            endif;
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search Team</label>
                        <input type="text" name="search" class="form-control" placeholder="Enter team name..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-info">Total Records: <?php echo $all_matches_count; ?></span>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-2"></i>Apply Filters
                                </button>
                                <a href="sports_history.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo-alt me-2"></i>Reset All
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-futbol" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($stats['total_matches'] ?? 0); ?></h3>
                    <p>Total Completed Matches</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-users" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($stats['total_teams'] ?? 0); ?></h3>
                    <p>Participating Teams</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-crown" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($stats['unique_winners'] ?? 0); ?></h3>
                    <p>Unique Winners</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-chart-line" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($stats['total_goals'] ?? 0); ?></h3>
                    <p>Total Goals Scored</p>
                </div>
            </div>
        </div>

        <!-- Tournament Winners Section -->
        <?php if ($tournament_winners_result && mysqli_num_rows($tournament_winners_result) > 0): ?>
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-trophy me-2"></i>Tournament Champions</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>Year</th>
                                <th>Tournament</th>
                                <th>Game Type</th>
                                <th>Champion</th>
                                <th>Team Type</th>
                                <th>Total Matches</th>
                                <th>Actions</th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset tournament winners result pointer
                            mysqli_data_seek($tournament_winners_result, 0);
                            while ($winner = mysqli_fetch_assoc($tournament_winners_result)): 
                            ?>
                                <tr>
                                    <td style="text-align: center;"><strong><?php echo $winner['year']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($winner['tournament_name']); ?></td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="background-color: <?php echo $winner['game_color'] ?? '#3B9DB3'; ?>; color: white;">
                                            <i class="fas <?php echo $winner['game_name'] == 'Football' ? 'fa-futbol' : ($winner['game_name'] == 'Netball' ? 'fa-basketball-ball' : ($winner['game_name'] == 'Handball' ? 'fa-hand-peace' : 'fa-volleyball-ball')); ?> me-1"></i>
                                            <?php echo htmlspecialchars($winner['game_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($winner['champion_name']); ?></strong>
                                        <?php if ($winner['champion_type']): ?>
                                            <br><small class="text-muted">(<?php echo $winner['champion_type'] == 'Form Five Combination' ? 'Form V' : ($winner['champion_type'] == 'Form Six Combination' ? 'Form VI' : 'Staff'); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php
                                        $type_badge = [
                                            'Form Five Combination' => 'primary',
                                            'Form Six Combination' => 'info',
                                            'Staff' => 'warning'
                                        ];
                                        $badge = $type_badge[$winner['champion_type']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php echo $winner['champion_type'] == 'Form Five Combination' ? 'Form V' : ($winner['champion_type'] == 'Form Six Combination' ? 'Form VI' : 'Staff'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;"><?php echo $winner['total_matches']; ?> matches</td>
                                    <td style="text-align: center;">
                                        <button class="btn btn-sm btn-outline-info view-tournament" data-id="<?php echo $winner['id']; ?>" data-name="<?php echo htmlspecialchars($winner['tournament_name']); ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Performing Teams Section -->
        <?php if ($champions_result && mysqli_num_rows($champions_result) > 0): ?>
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-chart-line me-2"></i>Top Performing Teams</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    $rank = 1;
                    // Reset champions result pointer
                    mysqli_data_seek($champions_result, 0);
                    while ($champion = mysqli_fetch_assoc($champions_result)): 
                    ?>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="text-center p-3 border rounded champion-card" style="background: <?php echo $rank == 1 ? 'linear-gradient(135deg, #ffd700, #ffed4e)' : ($rank == 2 ? 'linear-gradient(135deg, #c0c0c0, #e0e0e0)' : ($rank == 3 ? 'linear-gradient(135deg, #cd7f32, #e0a878)' : '#f8f9fa')); ?>;">
                                <?php if ($rank == 1): ?>
                                    <i class="fas fa-crown fa-2x mb-2" style="color: #ff6b35;"></i>
                                <?php elseif ($rank == 2): ?>
                                    <i class="fas fa-medal fa-2x mb-2" style="color: #c0c0c0;"></i>
                                <?php elseif ($rank == 3): ?>
                                    <i class="fas fa-medal fa-2x mb-2" style="color: #cd7f32;"></i>
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
                                        <?php echo $rank; ?>
                                    </div>
                                <?php endif; ?>
                                <h6 class="mb-1"><?php echo htmlspecialchars($champion['team_name']); ?></h6>
                                <?php if ($champion['combination_code']): ?>
                                    <small class="text-muted">(<?php echo $champion['combination_code']; ?>)</small>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-trophy me-1"></i><?php echo $champion['wins']; ?> Wins
                                    </span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-futbol me-1"></i><?php echo $champion['total_goals']; ?> Goals
                                </small>
                                <small class="text-muted d-block">
                                    <i class="fas fa-trophy me-1"></i><?php echo $champion['tournaments_won']; ?> Tournaments
                                </small>
                            </div>
                        </div>
                    <?php $rank++; endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Completed Games Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i>Completed Games</h4>
                <div>
                    <button class="btn btn-light btn-sm" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <button class="btn btn-light btn-sm ms-2" onclick="exportToCSV()">
                        <i class="fas fa-file-excel me-1"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Search and Additional Filters -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-2">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search teams, tournament, stage...">
                    </div>
                    <div class="col-md-3 mb-2">
                        <select id="stageFilter" class="form-select">
                            <option value="">All Stages</option>
                            <option value="Group Stage">Group Stage</option>
                            <option value="Quarter Finals">Quarter Finals</option>
                            <option value="Semi Finals">Semi Finals</option>
                            <option value="Final">Final</option>
                            <option value="3rd Place Playoff">3rd Place Playoff</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <select id="groupFilter" class="form-select">
                            <option value="">All Groups</option>
                            <option value="A">Group A</option>
                            <option value="B">Group B</option>
                            <option value="C">Group C</option>
                            <option value="D">Group D</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <select id="sortOrder" class="form-select" onchange="sortTable()">
                            <option value="date_desc">Latest First</option>
                            <option value="date_asc">Oldest First</option>
                            <option value="score_desc">Highest Score</option>
                            <option value="score_asc">Lowest Score</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="gamesTable">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>Game</th>
                                <th>Tournament</th>
                                <th>Season</th>
                                <th>Stage</th>
                                <th>Group</th>
                                <th>Match</th>
                                <th>Score</th>
                                <th>Winner</th>
                                <th>Date</th>
                                <th>Actions</th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php if ($matches_result && mysqli_num_rows($matches_result) > 0): ?>
                                <?php 
                                // Reset matches result pointer
                                mysqli_data_seek($matches_result, 0);
                                while ($match = mysqli_fetch_assoc($matches_result)): 
                                    $match_date_display = (!empty($match['match_date']) && $match['match_date'] != '0000-00-00') ? date('M d, Y', strtotime($match['match_date'])) : 'Date TBD';
                                    $match_time_display = (!empty($match['match_time']) && $match['match_time'] != '00:00:00') ? date('h:i A', strtotime($match['match_time'])) : 'Time TBD';
                                ?>
                                    <tr data-date="<?php echo $match['match_date']; ?>"
                                        data-score="<?php echo ($match['team1_score'] ?? 0) + ($match['team2_score'] ?? 0); ?>"
                                        data-tournament="<?php echo htmlspecialchars($match['tournament_name']); ?>"
                                        data-stage="<?php echo htmlspecialchars($match['stage_name']); ?>"
                                        data-group="<?php echo htmlspecialchars($match['group_name']); ?>"
                                        data-status="<?php echo $match['status']; ?>">
                                        <td style="text-align: center;">
                                            <span class="badge" style="background-color: <?php echo $match['game_color'] ?? '#3B9DB3'; ?>; color: white;">
                                                <i class="fas <?php echo $match['game_name'] == 'Football' ? 'fa-futbol' : ($match['game_name'] == 'Netball' ? 'fa-basketball-ball' : ($match['game_name'] == 'Handball' ? 'fa-hand-peace' : 'fa-volleyball-ball')); ?> me-1"></i>
                                                <?php echo htmlspecialchars($match['game_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($match['tournament_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('Y', strtotime($match['match_date'])); ?></small>
                                        </td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($match['season']); ?></td>
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
                                            <span class="badge bg-success" style="font-size: 1rem;">
                                                <?php echo $match['team1_score']; ?> - <?php echo $match['team2_score']; ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($match['winner_name']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-trophy me-1"></i>
                                                    <?php echo htmlspecialchars($match['winner_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Draw</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php echo $match_date_display; ?><br>
                                            <small class="text-muted"><?php echo $match_time_display; ?></small>
                                        </td>
                                        <td style="text-align: center;">
                                            <button class="btn btn-sm btn-outline-info view-match" data-id="<?php echo $match['id']; ?>" title="View Match Details">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-futbol fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No completed games found for the selected filters.</p>
                                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="resetFilters()">
                                            <i class="fas fa-undo-alt me-1"></i> Reset Filters
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div>
                        <span class="text-muted">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span> games</span>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                    </nav>
                </div>
            </div>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printMatchDetails()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printTournamentDetails()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
let currentPage = 1;
const rowsPerPage = 15;
let allRows = [];

function resetFilters() {
    window.location.href = 'sports_history.php';
}

function filterTable() {
    const searchValue = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const stageValue = document.getElementById('stageFilter')?.value.toLowerCase() || '';
    const groupValue = document.getElementById('groupFilter')?.value.toLowerCase() || '';
    
    const rows = document.querySelectorAll('#gamesTable tbody tr');
    let visibleRows = [];
    
    rows.forEach(row => {
        if (row.cells.length > 1) { // Skip "no data" row
            const text = row.textContent.toLowerCase();
            const stage = row.getAttribute('data-stage')?.toLowerCase() || '';
            const group = row.getAttribute('data-group')?.toLowerCase() || '';
            
            const matchesSearch = !searchValue || text.includes(searchValue);
            const matchesStage = !stageValue || stage.includes(stageValue);
            const matchesGroup = !groupValue || group.includes(groupValue);
            
            if (matchesSearch && matchesStage && matchesGroup) {
                row.style.display = '';
                visibleRows.push(row);
            } else {
                row.style.display = 'none';
            }
        }
    });
    
    allRows = visibleRows;
    updatePagination();
    return visibleRows.length;
}

function sortTable() {
    const sortOrder = document.getElementById('sortOrder').value;
    const tbody = document.querySelector('#gamesTable tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Filter out "no data" row
    const dataRows = rows.filter(row => row.cells.length > 1);
    
    dataRows.sort((a, b) => {
        if (sortOrder === 'date_desc') {
            return new Date(b.getAttribute('data-date')) - new Date(a.getAttribute('data-date'));
        } else if (sortOrder === 'date_asc') {
            return new Date(a.getAttribute('data-date')) - new Date(b.getAttribute('data-date'));
        } else if (sortOrder === 'score_desc') {
            return (b.getAttribute('data-score') || 0) - (a.getAttribute('data-score') || 0);
        } else if (sortOrder === 'score_asc') {
            return (a.getAttribute('data-score') || 0) - (b.getAttribute('data-score') || 0);
        }
        return 0;
    });
    
    dataRows.forEach(row => tbody.appendChild(row));
    filterTable(); // Re-apply filters after sorting
}

function updatePagination() {
    const totalRows = allRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    
    // Hide all rows first
    allRows.forEach(row => row.style.display = 'none');
    
    // Show only current page rows
    for (let i = start; i < end && i < totalRows; i++) {
        allRows[i].style.display = '';
    }
    
    // Update count display
    document.getElementById('showingCount').textContent = Math.min(end, totalRows);
    document.getElementById('totalCount').textContent = totalRows;
    
    // Update pagination buttons
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    if (totalPages <= 1) return;
    
    // Previous button
    pagination.innerHTML += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">«</a>
        </li>
    `;
    
    // Page numbers
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        pagination.innerHTML += `
            <li class="page-item ${currentPage === i ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>
        `;
    }
    
    // Next button
    pagination.innerHTML += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">»</a>
        </li>
    `;
}

function changePage(page) {
    currentPage = page;
    updatePagination();
}

function exportToCSV() {
    const rows = document.querySelectorAll('#gamesTable tbody tr');
    let csvContent = "Game,Tournament,Season,Stage,Group,Team 1,Team 2,Score,Winner,Date\n";
    
    rows.forEach(row => {
        if (row.style.display !== 'none' && row.cells.length > 1) {
            const cells = row.cells;
            const game = cells[0]?.innerText.trim() || '';
            const tournament = cells[1]?.innerText.trim() || '';
            const season = cells[2]?.innerText.trim() || '';
            const stage = cells[3]?.innerText.trim() || '';
            const group = cells[4]?.innerText.trim() || '';
            const teams = cells[5]?.innerText.replace(/\n/g, ' ').replace(/vs/g, 'vs').trim() || '';
            const score = cells[6]?.innerText.trim() || '';
            const winner = cells[7]?.innerText.trim() || '';
            const date = cells[8]?.innerText.trim() || '';
            
            csvContent += `"${game}","${tournament}","${season}","${stage}","${group}","${teams}","${score}","${winner}","${date}"\n`;
        }
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'sports_history_' + new Date().toISOString().slice(0,10) + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Swal.fire({
        title: 'Export Complete!',
        text: 'CSV file has been downloaded successfully.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

function printMatchDetails() {
    const printContent = document.getElementById('matchDetails').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Match Details</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="container">${printContent}</div>
            <div class="text-center mt-4 no-print">
                <button onclick="window.print()" class="btn btn-primary">Print</button>
                <button onclick="window.close()" class="btn btn-secondary">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function printTournamentDetails() {
    const printContent = document.getElementById('tournamentDetails').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Tournament Details</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="container">${printContent}</div>
            <div class="text-center mt-4 no-print">
                <button onclick="window.print()" class="btn btn-primary">Print</button>
                <button onclick="window.close()" class="btn btn-secondary">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// View Match Modal
document.addEventListener('DOMContentLoaded', function() {
    // Initialize table
    const allTableRows = document.querySelectorAll('#gamesTable tbody tr');
    allRows = Array.from(allTableRows).filter(row => row.cells.length > 1);
    updatePagination();
    
    // Add event listeners
    const searchInput = document.getElementById('searchInput');
    const stageFilter = document.getElementById('stageFilter');
    const groupFilter = document.getElementById('groupFilter');
    const sortOrder = document.getElementById('sortOrder');
    
    if (searchInput) searchInput.addEventListener('keyup', () => { currentPage = 1; filterTable(); });
    if (stageFilter) stageFilter.addEventListener('change', () => { currentPage = 1; filterTable(); });
    if (groupFilter) groupFilter.addEventListener('change', () => { currentPage = 1; filterTable(); });
    if (sortOrder) sortOrder.addEventListener('change', sortTable);
    
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
            const tournamentName = this.getAttribute('data-name');
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

function printPage() {
    window.print();
}
</script>

<style>
/* Sports History Page Styles */
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

.champion-card {
    transition: all 0.3s ease;
    border-radius: 12px;
}

.champion-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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

/* Print styles */
@media print {
    .sidebar, .header, .btn, .pagination, .filter-section, .card-header .btn, .no-print {
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
    
    .table {
        font-size: 10pt !important;
    }
}

/* Hover effects */
.table tbody tr:hover {
    background-color: rgba(59, 157, 179, 0.05);
    cursor: pointer;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .champion-card {
        margin-bottom: 15px;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
        margin: 2px;
    }
}

/* Pagination styles */
.pagination {
    margin-bottom: 0;
}

.page-item.active .page-link {
    background-color: #3B9DB3;
    border-color: #3B9DB3;
}

.page-link {
    color: #3B9DB3;
}

.page-link:hover {
    color: #2c7a8c;
}
</style>

<?php include '../controller/footer.php'; ?>