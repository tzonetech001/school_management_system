<?php
// report_sports.php - Complete Sports Reports Dashboard
session_start();
require_once '../controller/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get report type from request
$report_type = isset($_GET['type']) ? $_GET['type'] : 'overview';
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_game_type = isset($_GET['game_type']) ? intval($_GET['game_type']) : 0;
$selected_tournament = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
$selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get all game types
$game_types_sql = "SELECT * FROM game_types WHERE status = 'Active' ORDER BY game_name";
$game_types_result = mysqli_query($conn, $game_types_sql);

// Get all years from tournaments
$years_sql = "SELECT DISTINCT year FROM tournaments WHERE year IS NOT NULL ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_sql);

// Get all tournaments
$tournaments_sql = "SELECT id, tournament_name, year FROM tournaments WHERE is_archived = FALSE ORDER BY year DESC";
$tournaments_result = mysqli_query($conn, $tournaments_sql);

// Get all teams
$teams_sql = "SELECT id, team_name, team_type FROM teams WHERE is_active = TRUE ORDER BY team_name";
$teams_result = mysqli_query($conn, $teams_sql);

// Initialize data arrays based on report type
$report_data = [];
$chart_data = [];
$summary_stats = [];

// Include TCPDF library
require_once('../tcpdf/tcpdf.php');

// Handle export
if (isset($_GET['export']) && in_array($_GET['export'], ['pdf', 'excel'])) {
    exportReport($report_type, $conn, $_GET);
    exit();
}

function exportReport($report_type, $conn, $params) {
    $format = $params['export'];
    
    if ($format == 'excel') {
        $filename = "sports_report_" . $report_type . "_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        
        if ($report_type == 'overview') {
            fputcsv($output, ['Sports Report - Overview']);
            fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Tournament Winners']);
            fputcsv($output, ['Year', 'Tournament', 'Game Type', 'Champion', 'Start Date', 'End Date']);
            
            $winners_sql = "SELECT tr.year, tr.tournament_name, gt.game_name, t.team_name as champion_name, tr.start_date, tr.end_date
                FROM tournaments tr
                LEFT JOIN matches m ON m.tournament_id = tr.id AND m.status = 'Completed' AND m.winner_team_id IS NOT NULL
                LEFT JOIN teams t ON m.winner_team_id = t.id
                LEFT JOIN game_types gt ON tr.game_type_id = gt.id
                WHERE tr.status = 'Completed'
                GROUP BY tr.id, tr.year, tr.tournament_name, gt.game_name, t.team_name, tr.start_date, tr.end_date
                ORDER BY tr.year DESC LIMIT 10";
            $result = mysqli_query($conn, $winners_sql);
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, $row);
            }
        } elseif ($report_type == 'team_performance') {
            fputcsv($output, ['Team Performance Report']);
            fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Team Name', 'Team Type', 'Tournaments', 'Matches', 'Wins', 'Draws', 'Losses', 'GF', 'GA', 'GD', 'Points', 'Win %']);
            
            $team_stats_sql = "SELECT t.team_name, t.team_type, COUNT(DISTINCT tt.tournament_id) as tournaments_played,
                   SUM(tt.matches_played) as total_matches, SUM(tt.wins) as total_wins,
                   SUM(tt.draws) as total_draws, SUM(tt.losses) as total_losses,
                   SUM(tt.goals_for) as total_goals_for, SUM(tt.goals_against) as total_goals_against,
                   SUM(tt.goal_difference) as total_gd, SUM(tt.points) as total_points,
                   ROUND(AVG(CASE WHEN tt.matches_played > 0 THEN (tt.wins / tt.matches_played) * 100 ELSE 0 END), 1) as win_percentage
                   FROM teams t
                   LEFT JOIN tournament_teams tt ON t.id = tt.team_id
                   WHERE t.is_active = TRUE
                   GROUP BY t.id, t.team_name, t.team_type
                   ORDER BY total_points DESC";
            $result = mysqli_query($conn, $team_stats_sql);
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['team_name'], $row['team_type'], $row['tournaments_played'],
                    $row['total_matches'], $row['total_wins'], $row['total_draws'], $row['total_losses'],
                    $row['total_goals_for'], $row['total_goals_against'], $row['total_gd'],
                    $row['total_points'], $row['win_percentage'] . '%'
                ]);
            }
        } elseif ($report_type == 'match_analysis') {
            fputcsv($output, ['Match Analysis Report']);
            fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Date', 'Tournament', 'Stage', 'Game', 'Team 1', 'Score', 'Team 2', 'Winner']);
            
            $match_sql = "SELECT m.match_date, tr.tournament_name, s.stage_name, gt.game_name,
                          t1.team_name as team1_name, m.team1_score, m.team2_score, t2.team_name as team2_name, 
                          wt.team_name as winner_name
                          FROM matches m
                          LEFT JOIN teams t1 ON m.team1_id = t1.id
                          LEFT JOIN teams t2 ON m.team2_id = t2.id
                          LEFT JOIN teams wt ON m.winner_team_id = wt.id
                          LEFT JOIN tournaments tr ON m.tournament_id = tr.id
                          LEFT JOIN tournament_stages s ON m.stage_id = s.id
                          LEFT JOIN game_types gt ON m.game_type_id = gt.id
                          WHERE m.status = 'Completed'
                          ORDER BY m.match_date DESC";
            $result = mysqli_query($conn, $match_sql);
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['match_date'], $row['tournament_name'], $row['stage_name'], $row['game_name'],
                    $row['team1_name'], $row['team1_score'] . ' - ' . $row['team2_score'], $row['team2_name'], 
                    $row['winner_name'] ?? 'Draw'
                ]);
            }
        } elseif ($report_type == 'tournament_analysis') {
            fputcsv($output, ['Tournament Analysis Report']);
            fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Tournament', 'Game', 'Season', 'Year', 'Teams', 'Matches', 'Completed', 'Scheduled', 'Goals', 'Avg Goals', 'Status']);
            
            $tournament_sql = "SELECT t.tournament_name, gt.game_name, t.season, t.year, 
                   COUNT(DISTINCT tt.team_id) as total_teams, COUNT(DISTINCT m.id) as total_matches,
                   SUM(CASE WHEN m.status = 'Completed' THEN 1 ELSE 0 END) as completed_matches,
                   SUM(CASE WHEN m.status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled_matches,
                   SUM(m.team1_score + m.team2_score) as total_goals,
                   AVG(m.team1_score + m.team2_score) as avg_goals, t.status
                   FROM tournaments t
                   LEFT JOIN game_types gt ON t.game_type_id = gt.id
                   LEFT JOIN tournament_teams tt ON t.id = tt.tournament_id
                   LEFT JOIN matches m ON t.id = m.tournament_id
                   WHERE t.is_archived = FALSE
                   GROUP BY t.id, t.tournament_name, gt.game_name, t.season, t.year, t.status
                   ORDER BY t.year DESC";
            $result = mysqli_query($conn, $tournament_sql);
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['tournament_name'], $row['game_name'], $row['season'], $row['year'],
                    $row['total_teams'], $row['total_matches'], $row['completed_matches'], 
                    $row['scheduled_matches'], $row['total_goals'], round($row['avg_goals'], 2), $row['status']
                ]);
            }
        } elseif ($report_type == 'player_stats') {
            fputcsv($output, ['Player Statistics Report']);
            fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Player Name', 'Type', 'Team', 'Position', 'Jersey', 'Goals', 'Yellow Cards', 'Red Cards', 'Matches', 'Goals/Match']);
            
            $player_sql = "SELECT 
                            CASE 
                                WHEN tp.participant_type = 'Student' THEN CONCAT(s.first_name, ' ', s.last_name)
                                WHEN tp.participant_type = 'Staff' THEN CONCAT(a.first_name, ' ', a.last_name)
                                ELSE 'Unknown'
                            END as player_name,
                            tp.participant_type, tp.position, tp.jersey_number, t.team_name,
                            COUNT(ms.id) as total_events,
                            SUM(CASE WHEN ms.event_type = 'Goal' THEN 1 ELSE 0 END) as goals,
                            SUM(CASE WHEN ms.event_type = 'Yellow Card' THEN 1 ELSE 0 END) as yellow_cards,
                            SUM(CASE WHEN ms.event_type = 'Red Card' THEN 1 ELSE 0 END) as red_cards,
                            COUNT(DISTINCT ms.match_id) as matches_played
                            FROM team_participants tp
                            LEFT JOIN teams t ON tp.team_id = t.id
                            LEFT JOIN match_statistics ms ON tp.participant_id = ms.participant_id AND tp.participant_type = ms.participant_type
                            LEFT JOIN students s ON tp.participant_type = 'Student' AND tp.participant_id = s.id
                            LEFT JOIN admins a ON tp.participant_type = 'Staff' AND tp.participant_id = a.id
                            WHERE tp.status = 'Active'
                            GROUP BY tp.id
                            HAVING total_events > 0 OR goals > 0
                            ORDER BY goals DESC LIMIT 50";
            $result = mysqli_query($conn, $player_sql);
            while ($row = mysqli_fetch_assoc($result)) {
                $goals_per_match = $row['matches_played'] > 0 ? round($row['goals'] / $row['matches_played'], 2) : 0;
                fputcsv($output, [
                    $row['player_name'], $row['participant_type'], $row['team_name'],
                    $row['position'] ?: '-', $row['jersey_number'] ?: '-',
                    $row['goals'], $row['yellow_cards'], $row['red_cards'],
                    $row['matches_played'], $goals_per_match
                ]);
            }
        } elseif ($report_type == 'inventory') {
            fputcsv($output, ['Inventory Report']);
            fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Category', 'Total Items', 'Total Quantity', 'Low Stock', 'Out of Stock', 'Total Value (TZS)']);
            
            $inventory_sql = "SELECT category, COUNT(*) as total_items, SUM(quantity) as total_quantity,
                              SUM(CASE WHEN quantity <= min_quantity AND quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                              SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                              SUM(purchase_price * quantity) as total_value
                              FROM sports_equipment WHERE is_archived = FALSE
                              GROUP BY category ORDER BY total_quantity DESC";
            $result = mysqli_query($conn, $inventory_sql);
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['category'], $row['total_items'], $row['total_quantity'],
                    $row['low_stock'], $row['out_of_stock'], number_format($row['total_value'])
                ]);
            }
        }
        
        fclose($output);
    } elseif ($format == 'pdf') {
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Sports Management System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Sports Report - ' . ucfirst($report_type));
        $pdf->SetSubject('Sports Report');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Title
        $html = '<h1 style="text-align: center; color: #3B9DB3;">Sports Management System</h1>';
        $html .= '<h2 style="text-align: center;">' . ucfirst(str_replace('_', ' ', $report_type)) . ' Report</h2>';
        $html .= '<p style="text-align: center;">Generated on: ' . date('Y-m-d H:i:s') . '</p>';
        $html .= '<hr>';
        
        // Add content based on report type
        if ($report_type == 'overview') {
            // Get statistics
            $stats_sql = "SELECT 
                (SELECT COUNT(*) FROM tournaments WHERE is_archived = FALSE) as total_tournaments,
                (SELECT COUNT(*) FROM matches WHERE status = 'Completed') as total_matches,
                (SELECT COUNT(*) FROM teams WHERE is_active = TRUE) as total_teams,
                (SELECT SUM(team1_score + team2_score) FROM matches WHERE status = 'Completed') as total_goals";
            $stats_result = mysqli_query($conn, $stats_sql);
            $stats = mysqli_fetch_assoc($stats_result);
            
            $html .= '<h3>Summary Statistics</h3>';
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><td><strong>Total Tournaments</strong></td><td>' . ($stats['total_tournaments'] ?? 0) . '</td></tr>';
            $html .= '<tr><td><strong>Total Completed Matches</strong></td><td>' . ($stats['total_matches'] ?? 0) . '</td></tr>';
            $html .= '<tr><td><strong>Total Active Teams</strong></td><td>' . ($stats['total_teams'] ?? 0) . '</td></tr>';
            $html .= '<tr><td><strong>Total Goals Scored</strong></td><td>' . ($stats['total_goals'] ?? 0) . '</td></tr>';
            $html .= '</table><br>';
            
            // Tournament Winners - FIXED GROUP BY
            $html .= '<h3>Recent Tournament Champions</h3>';
            $winners_sql = "SELECT tr.year, tr.tournament_name, gt.game_name, t.team_name as champion_name
                            FROM tournaments tr
                            LEFT JOIN matches m ON m.tournament_id = tr.id AND m.status = 'Completed' AND m.winner_team_id IS NOT NULL
                            LEFT JOIN teams t ON m.winner_team_id = t.id
                            LEFT JOIN game_types gt ON tr.game_type_id = gt.id
                            WHERE tr.status = 'Completed'
                            GROUP BY tr.id, tr.year, tr.tournament_name, gt.game_name, t.team_name
                            ORDER BY tr.year DESC LIMIT 10";
            $result = mysqli_query($conn, $winners_sql);
            
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><th>Year</th><th>Tournament</th><th>Game Type</th><th>Champion</th></tr>';
            while ($row = mysqli_fetch_assoc($result)) {
                $html .= '<tr>';
                $html .= '<td>' . $row['year'] . '</td>';
                $html .= '<td>' . htmlspecialchars($row['tournament_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['game_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['champion_name'] ?? 'Pending') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            
        } elseif ($report_type == 'team_performance') {
            // FIXED GROUP BY
            $team_stats_sql = "SELECT t.team_name, t.team_type, COUNT(DISTINCT tt.tournament_id) as tournaments_played,
                               SUM(tt.matches_played) as total_matches, SUM(tt.wins) as total_wins,
                               SUM(tt.draws) as total_draws, SUM(tt.losses) as total_losses,
                               SUM(tt.goals_for) as total_goals_for, SUM(tt.goals_against) as total_goals_against,
                               SUM(tt.goal_difference) as total_gd, SUM(tt.points) as total_points,
                               ROUND(AVG(CASE WHEN tt.matches_played > 0 THEN (tt.wins / tt.matches_played) * 100 ELSE 0 END), 1) as win_percentage
                               FROM teams t
                               LEFT JOIN tournament_teams tt ON t.id = tt.team_id
                               WHERE t.is_active = TRUE
                               GROUP BY t.id, t.team_name, t.team_type
                               ORDER BY total_points DESC LIMIT 30";
            $result = mysqli_query($conn, $team_stats_sql);
            
            $html .= '<h3>Team Performance Rankings</h3>';
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><th>Rank</th><th>Team</th><th>Type</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GF</th><th>GA</th><th>GD</th><th>Pts</th><th>Win%</th></tr>';
            $rank = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                $html .= '<tr>';
                $html .= '<td>' . $rank++ . '</td>';
                $html .= '<td>' . htmlspecialchars($row['team_name']) . '</td>';
                $html .= '<td>' . $row['team_type'] . '</td>';
                $html .= '<td>' . $row['total_matches'] . '</td>';
                $html .= '<td>' . $row['total_wins'] . '</td>';
                $html .= '<td>' . $row['total_draws'] . '</td>';
                $html .= '<td>' . $row['total_losses'] . '</td>';
                $html .= '<td>' . $row['total_goals_for'] . '</td>';
                $html .= '<td>' . $row['total_goals_against'] . '</td>';
                $html .= '<td>' . ($row['total_gd'] >= 0 ? '+' : '') . $row['total_gd'] . '</td>';
                $html .= '<td><strong>' . $row['total_points'] . '</strong></td>';
                $html .= '<td>' . $row['win_percentage'] . '%</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            
        } elseif ($report_type == 'match_analysis') {
            $match_sql = "SELECT m.match_date, tr.tournament_name, s.stage_name, gt.game_name,
                          t1.team_name as team1_name, m.team1_score, m.team2_score, t2.team_name as team2_name, 
                          wt.team_name as winner_name
                          FROM matches m
                          LEFT JOIN teams t1 ON m.team1_id = t1.id
                          LEFT JOIN teams t2 ON m.team2_id = t2.id
                          LEFT JOIN teams wt ON m.winner_team_id = wt.id
                          LEFT JOIN tournaments tr ON m.tournament_id = tr.id
                          LEFT JOIN tournament_stages s ON m.stage_id = s.id
                          LEFT JOIN game_types gt ON m.game_type_id = gt.id
                          WHERE m.status = 'Completed'
                          ORDER BY m.match_date DESC LIMIT 50";
            $result = mysqli_query($conn, $match_sql);
            
            $html .= '<h3>Match Results</h3>';
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><th>Date</th><th>Tournament</th><th>Stage</th><th>Game</th><th>Team 1</th><th>Score</th><th>Team 2</th><th>Winner</th></tr>';
            while ($row = mysqli_fetch_assoc($result)) {
                $html .= '<tr>';
                $html .= '<td>' . date('M d, Y', strtotime($row['match_date'])) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['tournament_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['stage_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['game_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['team1_name']) . '</td>';
                $html .= '<td>' . $row['team1_score'] . ' - ' . $row['team2_score'] . '</td>';
                $html .= '<td>' . htmlspecialchars($row['team2_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['winner_name'] ?? 'Draw') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            
        } elseif ($report_type == 'player_stats') {
            $player_sql = "SELECT 
                            CASE 
                                WHEN tp.participant_type = 'Student' THEN CONCAT(s.first_name, ' ', s.last_name)
                                WHEN tp.participant_type = 'Staff' THEN CONCAT(a.first_name, ' ', a.last_name)
                                ELSE 'Unknown'
                            END as player_name,
                            tp.participant_type, tp.position, tp.jersey_number, t.team_name,
                            SUM(CASE WHEN ms.event_type = 'Goal' THEN 1 ELSE 0 END) as goals,
                            SUM(CASE WHEN ms.event_type = 'Yellow Card' THEN 1 ELSE 0 END) as yellow_cards,
                            SUM(CASE WHEN ms.event_type = 'Red Card' THEN 1 ELSE 0 END) as red_cards,
                            COUNT(DISTINCT ms.match_id) as matches_played
                            FROM team_participants tp
                            LEFT JOIN teams t ON tp.team_id = t.id
                            LEFT JOIN match_statistics ms ON tp.participant_id = ms.participant_id AND tp.participant_type = ms.participant_type
                            LEFT JOIN students s ON tp.participant_type = 'Student' AND tp.participant_id = s.id
                            LEFT JOIN admins a ON tp.participant_type = 'Staff' AND tp.participant_id = a.id
                            WHERE tp.status = 'Active'
                            GROUP BY tp.id
                            HAVING goals > 0
                            ORDER BY goals DESC LIMIT 30";
            $result = mysqli_query($conn, $player_sql);
            
            $html .= '<h3>Top Scorers</h3>';
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><th>Rank</th><th>Player Name</th><th>Type</th><th>Team</th><th>Position</th><th>Jersey</th><th>Goals</th><th>YC</th><th>RC</th><th>Matches</th></tr>';
            $rank = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                $goals_per_match = $row['matches_played'] > 0 ? round($row['goals'] / $row['matches_played'], 2) : 0;
                $html .= '<tr>';
                $html .= '<td>' . $rank++ . '</td>';
                $html .= '<td>' . htmlspecialchars($row['player_name']) . '</td>';
                $html .= '<td>' . $row['participant_type'] . '</td>';
                $html .= '<td>' . htmlspecialchars($row['team_name']) . '</td>';
                $html .= '<td>' . ($row['position'] ?: '-') . '</td>';
                $html .= '<td>' . ($row['jersey_number'] ?: '-') . '</td>';
                $html .= '<td><strong>' . $row['goals'] . '</strong></td>';
                $html .= '<td>' . $row['yellow_cards'] . '</td>';
                $html .= '<td>' . $row['red_cards'] . '</td>';
                $html .= '<td>' . $row['matches_played'] . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            
        } elseif ($report_type == 'inventory') {
            $inventory_sql = "SELECT item_name, category, unit, quantity, min_quantity, purchase_price,
                              CASE 
                                  WHEN quantity <= 0 THEN 'Out of Stock'
                                  WHEN quantity <= min_quantity THEN 'Low Stock'
                                  ELSE 'In Stock'
                              END as stock_status
                              FROM sports_equipment 
                              WHERE is_archived = FALSE
                              ORDER BY 
                                  CASE 
                                      WHEN quantity <= 0 THEN 1
                                      WHEN quantity <= min_quantity THEN 2
                                      ELSE 3
                                  END,
                                  item_name";
            $result = mysqli_query($conn, $inventory_sql);
            
            $html .= '<h3>Equipment Inventory</h3>';
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><th>Item Name</th><th>Category</th><th>Unit</th><th>Quantity</th><th>Min Stock</th><th>Status</th><th>Price (TZS)</th></tr>';
            while ($row = mysqli_fetch_assoc($result)) {
                $status_class = $row['stock_status'] == 'Out of Stock' ? 'danger' : ($row['stock_status'] == 'Low Stock' ? 'warning' : 'success');
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($row['item_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['category']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['unit']) . '</td>';
                $html .= '<td>' . $row['quantity'] . '</td>';
                $html .= '<td>' . $row['min_quantity'] . '</td>';
                $html .= '<td>' . $row['stock_status'] . '</td>';
                $html .= '<td>' . number_format($row['purchase_price']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }
        
        $html .= '<hr>';
        $html .= '<p style="text-align: center; font-size: 8pt;">Report generated by Sports Management System</p>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Close and output PDF document
        $pdf->Output('sports_report_' . $report_type . '_' . date('Y-m-d') . '.pdf', 'D');
    }
}

// ==================== OVERVIEW REPORT DATA ====================
if ($report_type == 'overview') {
    $stats_sql = "SELECT 
        (SELECT COUNT(*) FROM tournaments WHERE is_archived = FALSE) as total_tournaments,
        (SELECT COUNT(*) FROM matches WHERE status = 'Completed') as total_matches,
        (SELECT COUNT(*) FROM matches WHERE status = 'Scheduled') as upcoming_matches,
        (SELECT COUNT(*) FROM teams WHERE is_active = TRUE) as total_teams,
        (SELECT SUM(team1_score + team2_score) FROM matches WHERE status = 'Completed') as total_goals,
        (SELECT COUNT(DISTINCT winner_team_id) FROM matches WHERE status = 'Completed' AND winner_team_id IS NOT NULL) as unique_winners,
        (SELECT AVG(team1_score + team2_score) FROM matches WHERE status = 'Completed') as avg_goals_per_match";
    $stats_result = mysqli_query($conn, $stats_sql);
    $overall_stats = mysqli_fetch_assoc($stats_result);
    
    // FIXED GROUP BY - Added all non-aggregated columns
    $winners_sql = "SELECT tr.year, tr.tournament_name, gt.game_name, t.team_name as champion_name, tr.start_date, tr.end_date
                    FROM tournaments tr
                    LEFT JOIN matches m ON m.tournament_id = tr.id AND m.status = 'Completed' AND m.winner_team_id IS NOT NULL
                    LEFT JOIN teams t ON m.winner_team_id = t.id
                    LEFT JOIN game_types gt ON tr.game_type_id = gt.id
                    WHERE tr.status = 'Completed'
                    GROUP BY tr.id, tr.year, tr.tournament_name, gt.game_name, t.team_name, tr.start_date, tr.end_date
                    ORDER BY tr.year DESC LIMIT 10";
    $winners_result = mysqli_query($conn, $winners_sql);
    
    $monthly_sql = "SELECT 
        MONTH(match_date) as month,
        YEAR(match_date) as year,
        COUNT(*) as match_count,
        SUM(team1_score + team2_score) as total_goals
        FROM matches 
        WHERE status = 'Completed'
        GROUP BY YEAR(match_date), MONTH(match_date)
        ORDER BY year DESC, month DESC LIMIT 12";
    $monthly_result = mysqli_query($conn, $monthly_sql);
    
    $game_dist_sql = "SELECT 
        gt.game_name,
        COUNT(DISTINCT m.id) as match_count,
        COUNT(DISTINCT tr.id) as tournament_count,
        SUM(m.team1_score + m.team2_score) as total_goals
        FROM game_types gt
        LEFT JOIN matches m ON m.game_type_id = gt.id AND m.status = 'Completed'
        LEFT JOIN tournaments tr ON tr.game_type_id = gt.id
        WHERE gt.status = 'Active'
        GROUP BY gt.id, gt.game_name
        ORDER BY match_count DESC";
    $game_dist_result = mysqli_query($conn, $game_dist_sql);
}

// ==================== TEAM PERFORMANCE REPORT DATA ====================
if ($report_type == 'team_performance') {
    $team_condition = "";
    $params = [];
    $types = "";
    
    if ($selected_team > 0) {
        $team_condition = " AND tt.team_id = ?";
        $params[] = $selected_team;
        $types .= "i";
    }
    if ($selected_tournament > 0) {
        $team_condition .= " AND tt.tournament_id = ?";
        $params[] = $selected_tournament;
        $types .= "i";
    }
    
    $team_stats_sql = "SELECT 
        t.id,
        t.team_name,
        t.team_type,
        t.combination_code,
        COUNT(DISTINCT tt.tournament_id) as tournaments_played,
        SUM(tt.matches_played) as total_matches,
        SUM(tt.wins) as total_wins,
        SUM(tt.draws) as total_draws,
        SUM(tt.losses) as total_losses,
        SUM(tt.goals_for) as total_goals_for,
        SUM(tt.goals_against) as total_goals_against,
        SUM(tt.goal_difference) as total_gd,
        SUM(tt.points) as total_points,
        ROUND(AVG(CASE WHEN tt.matches_played > 0 THEN (tt.wins / tt.matches_played) * 100 ELSE 0 END), 1) as win_percentage
        FROM teams t
        LEFT JOIN tournament_teams tt ON t.id = tt.team_id
        WHERE t.is_active = TRUE $team_condition
        GROUP BY t.id, t.team_name, t.team_type, t.combination_code
        ORDER BY total_points DESC, total_gd DESC, total_goals_for DESC";
    
    $stmt = $conn->prepare($team_stats_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $team_stats_result = $stmt->get_result();
    
    $top_scorers_sql = "SELECT 
        t.team_name,
        t.team_type,
        SUM(tt.goals_for) as total_goals,
        SUM(tt.matches_played) as matches_played,
        ROUND(AVG(tt.goals_for), 2) as avg_goals_per_match
        FROM tournament_teams tt
        JOIN teams t ON tt.team_id = t.id
        WHERE 1=1";
    
    if ($selected_tournament > 0) {
        $top_scorers_sql .= " AND tt.tournament_id = $selected_tournament";
    }
    $top_scorers_sql .= " GROUP BY t.id, t.team_name, t.team_type ORDER BY total_goals DESC LIMIT 10";
    $top_scorers_result = mysqli_query($conn, $top_scorers_sql);
}

// ==================== MATCH ANALYSIS REPORT DATA ====================
if ($report_type == 'match_analysis') {
    $match_conditions = "";
    $params = [];
    $types = "";
    
    if ($selected_tournament > 0) {
        $match_conditions .= " AND m.tournament_id = ?";
        $params[] = $selected_tournament;
        $types .= "i";
    }
    if ($selected_game_type > 0) {
        $match_conditions .= " AND m.game_type_id = ?";
        $params[] = $selected_game_type;
        $types .= "i";
    }
    if ($date_from && $date_to) {
        $match_conditions .= " AND m.match_date BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    }
    
    $match_stats_sql = "SELECT 
        m.id,
        m.match_date,
        m.match_time,
        m.team1_score,
        m.team2_score,
        t1.team_name as team1_name,
        t2.team_name as team2_name,
        wt.team_name as winner_name,
        tr.tournament_name,
        s.stage_name,
        gt.game_name
        FROM matches m
        LEFT JOIN teams t1 ON m.team1_id = t1.id
        LEFT JOIN teams t2 ON m.team2_id = t2.id
        LEFT JOIN teams wt ON m.winner_team_id = wt.id
        LEFT JOIN tournaments tr ON m.tournament_id = tr.id
        LEFT JOIN tournament_stages s ON m.stage_id = s.id
        LEFT JOIN game_types gt ON m.game_type_id = gt.id
        WHERE m.status = 'Completed' $match_conditions
        ORDER BY m.match_date DESC, m.match_time DESC";
    
    $stmt = $conn->prepare($match_stats_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $match_stats_result = $stmt->get_result();
    
    $total_matches = mysqli_num_rows($match_stats_result);
    $total_goals = 0;
    $avg_goals = 0;
    $highest_score = 0;
    $highest_score_match = null;
    $most_common_score = [];
    $score_frequency = [];
    
    mysqli_data_seek($match_stats_result, 0);
    while ($match = mysqli_fetch_assoc($match_stats_result)) {
        $total_score = $match['team1_score'] + $match['team2_score'];
        $total_goals += $total_score;
        
        if ($total_score > $highest_score) {
            $highest_score = $total_score;
            $highest_score_match = $match;
        }
        
        $score_key = $match['team1_score'] . '-' . $match['team2_score'];
        if (!isset($score_frequency[$score_key])) {
            $score_frequency[$score_key] = 0;
        }
        $score_frequency[$score_key]++;
    }
    
    if ($total_matches > 0) {
        $avg_goals = round($total_goals / $total_matches, 2);
        arsort($score_frequency);
        $most_common_score = key($score_frequency);
    }
    
    mysqli_data_seek($match_stats_result, 0);
}

// ==================== TOURNAMENT ANALYSIS REPORT DATA ====================
if ($report_type == 'tournament_analysis') {
    $tournament_condition = "";
    if ($selected_tournament > 0) {
        $tournament_condition = " AND t.id = $selected_tournament";
    }
    if ($selected_game_type > 0) {
        $tournament_condition .= " AND t.game_type_id = $selected_game_type";
    }
    
    // FIXED GROUP BY - Added all non-aggregated columns
    $tournament_stats_sql = "SELECT 
        t.id,
        t.tournament_name,
        t.season,
        t.year,
        t.start_date,
        t.end_date,
        t.status,
        gt.game_name,
        COUNT(DISTINCT tt.team_id) as total_teams,
        COUNT(DISTINCT m.id) as total_matches,
        SUM(CASE WHEN m.status = 'Completed' THEN 1 ELSE 0 END) as completed_matches,
        SUM(CASE WHEN m.status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled_matches,
        SUM(m.team1_score + m.team2_score) as total_goals,
        AVG(m.team1_score + m.team2_score) as avg_goals
        FROM tournaments t
        LEFT JOIN game_types gt ON t.game_type_id = gt.id
        LEFT JOIN tournament_teams tt ON t.id = tt.tournament_id
        LEFT JOIN matches m ON t.id = m.tournament_id
        WHERE t.is_archived = FALSE $tournament_condition
        GROUP BY t.id, t.tournament_name, t.season, t.year, t.start_date, t.end_date, t.status, gt.game_name
        ORDER BY t.year DESC, t.start_date DESC";
    
    $tournament_stats_result = mysqli_query($conn, $tournament_stats_sql);
    
    $trends_sql = "SELECT 
    year,
    COUNT(*) as tournament_count,
    SUM(team_count) as total_teams,
    SUM(match_count) as total_matches
    FROM (
        SELECT 
            t.year,
            t.id,
            COUNT(DISTINCT tt.team_id) as team_count,
            COUNT(DISTINCT m.id) as match_count
        FROM tournaments t
        LEFT JOIN tournament_teams tt ON t.id = tt.tournament_id
        LEFT JOIN matches m ON t.id = m.tournament_id
        WHERE t.is_archived = FALSE
        GROUP BY t.id, t.year
    ) as tournament_data
    GROUP BY year
    ORDER BY year DESC LIMIT 5";
    $trends_result = mysqli_query($conn, $trends_sql);
}

// ==================== PLAYER STATISTICS REPORT DATA ====================
if ($report_type == 'player_stats') {
    $player_stats_sql = "SELECT 
        CASE 
            WHEN tp.participant_type = 'Student' THEN CONCAT(s.first_name, ' ', s.last_name)
            WHEN tp.participant_type = 'Staff' THEN CONCAT(a.first_name, ' ', a.last_name)
            ELSE 'Unknown'
        END as player_name,
        tp.participant_type,
        tp.position,
        tp.jersey_number,
        t.team_name,
        COUNT(ms.id) as total_events,
        SUM(CASE WHEN ms.event_type = 'Goal' THEN 1 ELSE 0 END) as goals,
        SUM(CASE WHEN ms.event_type = 'Yellow Card' THEN 1 ELSE 0 END) as yellow_cards,
        SUM(CASE WHEN ms.event_type = 'Red Card' THEN 1 ELSE 0 END) as red_cards,
        COUNT(DISTINCT ms.match_id) as matches_played
        FROM team_participants tp
        LEFT JOIN teams t ON tp.team_id = t.id
        LEFT JOIN match_statistics ms ON tp.participant_id = ms.participant_id AND tp.participant_type = ms.participant_type
        LEFT JOIN students s ON tp.participant_type = 'Student' AND tp.participant_id = s.id
        LEFT JOIN admins a ON tp.participant_type = 'Staff' AND tp.participant_id = a.id
        WHERE tp.status = 'Active'
        GROUP BY tp.id, tp.participant_type, tp.position, tp.jersey_number, t.team_name
        HAVING total_events > 0 OR goals > 0
        ORDER BY goals DESC, total_events DESC
        LIMIT 50";
    $player_stats_result = mysqli_query($conn, $player_stats_sql);
}

// ==================== INVENTORY REPORT DATA ====================
if ($report_type == 'inventory') {
    $inventory_sql = "SELECT 
        category,
        COUNT(*) as total_items,
        SUM(quantity) as total_quantity,
        SUM(CASE WHEN quantity <= min_quantity AND quantity > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(purchase_price * quantity) as total_value
        FROM sports_equipment
        WHERE is_archived = FALSE
        GROUP BY category
        ORDER BY total_quantity DESC";
    $inventory_result = mysqli_query($conn, $inventory_sql);
    
    $recent_transactions_sql = "SELECT 
        t.*,
        e.item_name,
        e.category,
        CONCAT(a.first_name, ' ', a.last_name) as performed_by_name
        FROM equipment_transactions t
        LEFT JOIN sports_equipment e ON t.equipment_id = e.id
        LEFT JOIN admins a ON t.performed_by = a.id
        ORDER BY t.created_at DESC LIMIT 20";
    $recent_transactions = mysqli_query($conn, $recent_transactions_sql);
}
?>

<!-- Rest of your HTML remains the same from line 469 onwards -->

<?php include '../controller/header.php'; ?>
<?php include '../controller/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-chart-line me-2"></i>Sports Reports Dashboard
                </h2>
                <p class="text-muted">Comprehensive analytics and statistics for all sports activities</p>
            </div>
            <div>
                <a href="sports.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Sports
                </a>
                <button class="btn btn-success" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
            </div>
        </div>

        <!-- Report Type Navigation -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-2 col-sm-4">
                        <a href="?type=overview&year=<?php echo $selected_year; ?>" class="btn <?php echo $report_type == 'overview' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                            <i class="fas fa-chart-pie me-2"></i>Overview
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <a href="?type=team_performance&year=<?php echo $selected_year; ?>" class="btn <?php echo $report_type == 'team_performance' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                            <i class="fas fa-users me-2"></i>Team Performance
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <a href="?type=match_analysis&year=<?php echo $selected_year; ?>" class="btn <?php echo $report_type == 'match_analysis' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                            <i class="fas fa-futbol me-2"></i>Match Analysis
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <a href="?type=tournament_analysis&year=<?php echo $selected_year; ?>" class="btn <?php echo $report_type == 'tournament_analysis' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                            <i class="fas fa-trophy me-2"></i>Tournament Analysis
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <a href="?type=player_stats&year=<?php echo $selected_year; ?>" class="btn <?php echo $report_type == 'player_stats' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                            <i class="fas fa-user me-2"></i>Player Stats
                        </a>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <a href="?type=inventory&year=<?php echo $selected_year; ?>" class="btn <?php echo $report_type == 'inventory' ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                            <i class="fas fa-boxes me-2"></i>Inventory
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3" id="filterForm">
                    <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">Game Type</label>
                        <select name="game_type" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Games</option>
                            <?php 
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
                        <select name="year" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Years</option>
                            <?php 
                            mysqli_data_seek($years_result, 0);
                            while ($year = mysqli_fetch_assoc($years_result)): 
                            ?>
                                <option value="<?php echo $year['year']; ?>" <?php echo $selected_year == $year['year'] ? 'selected' : ''; ?>>
                                    <?php echo $year['year']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <?php if ($report_type == 'team_performance' || $report_type == 'tournament_analysis'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Tournament</label>
                        <select name="tournament_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Tournaments</option>
                            <?php 
                            mysqli_data_seek($tournaments_result, 0);
                            while ($tournament = mysqli_fetch_assoc($tournaments_result)): 
                            ?>
                                <option value="<?php echo $tournament['id']; ?>" <?php echo $selected_tournament == $tournament['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tournament['tournament_name']); ?> (<?php echo $tournament['year']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($report_type == 'team_performance'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Team</label>
                        <select name="team_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">All Teams</option>
                            <?php 
                            mysqli_data_seek($teams_result, 0);
                            while ($team = mysqli_fetch_assoc($teams_result)): 
                            ?>
                                <option value="<?php echo $team['id']; ?>" <?php echo $selected_team == $team['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['team_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($report_type == 'match_analysis'): ?>
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>" onchange="this.form.submit()">
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-<?php echo ($report_type == 'match_analysis') ? '3' : '6'; ?> d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                        <a href="?type=<?php echo $report_type; ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-undo-alt me-2"></i>Reset
                        </a>
                        <a href="?type=<?php echo $report_type; ?>&export=excel<?php echo isset($_GET['year']) ? '&year=' . $selected_year : ''; ?><?php echo isset($_GET['game_type']) ? '&game_type=' . $selected_game_type : ''; ?><?php echo isset($_GET['tournament_id']) ? '&tournament_id=' . $selected_tournament : ''; ?><?php echo isset($_GET['team_id']) ? '&team_id=' . $selected_team : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . $date_from . '&date_to=' . $date_to : ''; ?>" class="btn btn-success me-2">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </a>
                        <a href="?type=<?php echo $report_type; ?>&export=pdf<?php echo isset($_GET['year']) ? '&year=' . $selected_year : ''; ?><?php echo isset($_GET['game_type']) ? '&game_type=' . $selected_game_type : ''; ?><?php echo isset($_GET['tournament_id']) ? '&tournament_id=' . $selected_tournament : ''; ?><?php echo isset($_GET['team_id']) ? '&team_id=' . $selected_team : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . $date_from . '&date_to=' . $date_to : ''; ?>" class="btn btn-danger">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ==================== OVERVIEW REPORT ==================== -->
        <?php if ($report_type == 'overview'): ?>
        
        <!-- Summary Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-trophy" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($overall_stats['total_tournaments'] ?? 0); ?></h3>
                    <p>Total Tournaments</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-futbol" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($overall_stats['total_matches'] ?? 0); ?></h3>
                    <p>Completed Matches</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-users" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($overall_stats['total_teams'] ?? 0); ?></h3>
                    <p>Active Teams</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-chart-line" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($overall_stats['total_goals'] ?? 0); ?></h3>
                    <p>Total Goals Scored</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Tournament Winners -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Recent Tournament Champions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Tournament</th>
                                        <th>Game</th>
                                        <th>Champion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($winners_result && mysqli_num_rows($winners_result) > 0): ?>
                                        <?php while ($winner = mysqli_fetch_assoc($winners_result)): ?>
                                             <tr>
                                                <td><strong><?php echo $winner['year']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($winner['tournament_name']); ?></td>
                                                <td><?php echo htmlspecialchars($winner['game_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-crown me-1"></i>
                                                        <?php echo htmlspecialchars($winner['champion_name'] ?? 'Pending'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">No tournament winners recorded yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Match Distribution -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Monthly Match Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Game Type Distribution -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Game Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="gameTypeChart" height="250"></canvas>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Game Type</th>
                                        <th>Tournaments</th>
                                        <th>Matches</th>
                                        <th>Total Goals</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $game_labels = [];
                                    $game_match_counts = [];
                                    mysqli_data_seek($game_dist_result, 0);
                                    while ($game = mysqli_fetch_assoc($game_dist_result)): 
                                        $game_labels[] = $game['game_name'];
                                        $game_match_counts[] = $game['match_count'];
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($game['game_name']); ?></strong></td>
                                            <td><?php echo $game['tournament_count']; ?></td>
                                            <td><?php echo $game['match_count']; ?></td>
                                            <td><?php echo $game['total_goals']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Key Statistics -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Key Performance Indicators</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-4">
                                <div class="border rounded p-3">
                                    <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                                    <h2><?php echo round($overall_stats['avg_goals_per_match'] ?? 0, 2); ?></h2>
                                    <p class="text-muted mb-0">Average Goals Per Match</p>
                                </div>
                            </div>
                            <div class="col-6 mb-4">
                                <div class="border rounded p-3">
                                    <i class="fas fa-trophy fa-2x text-warning mb-2"></i>
                                    <h2><?php echo number_format($overall_stats['unique_winners'] ?? 0); ?></h2>
                                    <p class="text-muted mb-0">Unique Champions</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <i class="fas fa-calendar-alt fa-2x text-success mb-2"></i>
                                    <h2><?php echo number_format($overall_stats['upcoming_matches'] ?? 0); ?></h2>
                                    <p class="text-muted mb-0">Upcoming Matches</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                                    <h2><?php echo number_format($overall_stats['total_matches'] ?? 0); ?></h2>
                                    <p class="text-muted mb-0">Total Matches</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
        
        <!-- ==================== TEAM PERFORMANCE REPORT ==================== -->
        <?php if ($report_type == 'team_performance'): ?>
        
        <!-- Team Performance Table -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Team Performance Rankings</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="teamPerformanceTable">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>Rank</th>
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
                                <th>Pts</th>
                                <th>Win %</th>
                                <th>Avg GF</th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            if ($team_stats_result && mysqli_num_rows($team_stats_result) > 0):
                                while ($team = mysqli_fetch_assoc($team_stats_result)):
                                    $avg_gf = $team['total_matches'] > 0 ? round($team['total_goals_for'] / $team['total_matches'], 2) : 0;
                            ?>
                                <tr style="text-align: center;">
                                    <td><strong><?php echo $rank++; ?></strong></td>
                                    <td style="text-align: left;"><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                    <td>
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
                                    <td><?php echo $team['tournaments_played']; ?></td>
                                    <td><?php echo $team['total_matches']; ?></td>
                                    <td class="text-success"><?php echo $team['total_wins']; ?></td>
                                    <td class="text-warning"><?php echo $team['total_draws']; ?></td>
                                    <td class="text-danger"><?php echo $team['total_losses']; ?></td>
                                    <td><?php echo $team['total_goals_for']; ?></td>
                                    <td><?php echo $team['total_goals_against']; ?></td>
                                    <td class="<?php echo $team['total_gd'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $team['total_gd'] >= 0 ? '+' : ''; ?><?php echo $team['total_gd']; ?>
                                    </td>
                                    <td><strong><?php echo $team['total_points']; ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $team['win_percentage']; ?>%;">
                                                <?php echo $team['win_percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $avg_gf; ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="14" class="text-center">No team data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Top Scorers -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-futbol me-2"></i>Top Scoring Teams</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Team</th>
                                <th>Type</th>
                                <th>Total Goals</th>
                                <th>Matches</th>
                                <th>Avg Goals/Match</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            if ($top_scorers_result && mysqli_num_rows($top_scorers_result) > 0):
                                while ($scorer = mysqli_fetch_assoc($top_scorers_result)):
                                    $percentage = $scorer['matches_played'] > 0 ? ($scorer['total_goals'] / $scorer['matches_played'] / 5) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo $rank++; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($scorer['team_name']); ?></strong></td>
                                    <td><?php echo $scorer['team_type'] == 'Form Five Combination' ? 'Form V' : ($scorer['team_type'] == 'Form Six Combination' ? 'Form VI' : 'Staff'); ?></td>
                                    <td><span class="badge bg-success fs-6"><?php echo $scorer['total_goals']; ?></span></td>
                                    <td><?php echo $scorer['matches_played']; ?></td>
                                    <td><?php echo $scorer['avg_goals_per_match']; ?></td>
                                    <td style="width: 30%;">
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: <?php echo min($percentage, 100); ?>%;">
                                                <?php echo round($scorer['avg_goals_per_match'], 1); ?> goals/match
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="7" class="text-center">No scoring data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
        
        <!-- ==================== MATCH ANALYSIS REPORT ==================== -->
        <?php if ($report_type == 'match_analysis'): ?>
        
        <!-- Match Statistics Summary -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-futbol" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($total_matches); ?></h3>
                    <p>Total Matches</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-chart-line" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo number_format($total_goals); ?></h3>
                    <p>Total Goals</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-chart-line" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $avg_goals; ?></h3>
                    <p>Avg Goals/Match</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-chart-line" style="color: #3B9DB3;"></i></div>
                    <h3><?php echo $highest_score; ?></h3>
                    <p>Highest Scoring Match</p>
                </div>
            </div>
        </div>
        
        <!-- Most Common Score -->
        <div class="alert alert-info mb-4">
            <i class="fas fa-chart-line me-2"></i>
            <strong>Most Common Score:</strong> <?php echo $most_common_score ?: 'N/A'; ?> 
            (Occurred <?php echo $score_frequency[$most_common_score] ?? 0; ?> times)
        </div>
        
        <!-- Match Results Table -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-list me-2"></i>Match Results Details</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="matchAnalysisTable">
                        <thead class="table-light">
                            <tr style="text-align: center;">
                                <th>Date</th>
                                <th>Tournament</th>
                                <th>Stage</th>
                                <th>Game</th>
                                <th>Team 1</th>
                                <th>Score</th>
                                <th>Team 2</th>
                                <th>Winner</th>
                              </tr>
                        </thead>
                        <tbody>
                            <?php if ($match_stats_result && mysqli_num_rows($match_stats_result) > 0): ?>
                                <?php while ($match = mysqli_fetch_assoc($match_stats_result)): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($match['match_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($match['tournament_name']); ?></td>
                                        <td><?php echo htmlspecialchars($match['stage_name']); ?></td>
                                        <td><?php echo htmlspecialchars($match['game_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($match['team1_name']); ?></strong></td>
                                        <td class="text-center"><span class="badge bg-dark"><?php echo $match['team1_score']; ?> - <?php echo $match['team2_score']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($match['team2_name']); ?></strong></td>
                                        <td>
                                            <?php if ($match['winner_name']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-trophy me-1"></i><?php echo htmlspecialchars($match['winner_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Draw</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">No match data available for selected filters</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
        
        <!-- ==================== TOURNAMENT ANALYSIS REPORT ==================== -->
        <?php if ($report_type == 'tournament_analysis'): ?>
        
        <!-- Tournament Statistics Table -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-trophy me-2"></i>Tournament Statistics</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Tournament</th>
                                <th>Game</th>
                                <th>Season</th>
                                <th>Teams</th>
                                <th>Total Matches</th>
                                <th>Completed</th>
                                <th>Scheduled</th>
                                <th>Total Goals</th>
                                <th>Avg Goals</th>
                                <th>Status</th>
                              </tr>
                        </thead>
                        <tbody>
                            <?php if ($tournament_stats_result && mysqli_num_rows($tournament_stats_result) > 0): ?>
                                <?php while ($tournament = mysqli_fetch_assoc($tournament_stats_result)): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($tournament['tournament_name']); ?></strong><br><small class="text-muted"><?php echo $tournament['year']; ?></small></td>
                                        <td><?php echo htmlspecialchars($tournament['game_name']); ?></td>
                                        <td><?php echo htmlspecialchars($tournament['season']); ?></td>
                                        <td class="text-center"><?php echo $tournament['total_teams']; ?></td>
                                        <td class="text-center"><?php echo $tournament['total_matches']; ?></td>
                                        <td class="text-center text-success"><?php echo $tournament['completed_matches']; ?></td>
                                        <td class="text-center text-warning"><?php echo $tournament['scheduled_matches']; ?></td>
                                        <td class="text-center"><?php echo $tournament['total_goals']; ?></td>
                                        <td class="text-center"><?php echo round($tournament['avg_goals'], 2); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'Upcoming' => 'warning',
                                                'Ongoing' => 'info',
                                                'Completed' => 'success',
                                                'Cancelled' => 'danger'
                                            ];
                                            $class = $status_class[$tournament['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $class; ?>"><?php echo $tournament['status']; ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center">No tournament data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Tournament Trends -->
        <?php if ($trends_result && mysqli_num_rows($trends_result) > 0): ?>
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-chart-line me-2"></i>Tournament Participation Trends</h4>
            </div>
            <div class="card-body">
                <canvas id="trendsChart" height="200"></canvas>
            </div>
        </div>
        
        <script>
        var trendsCtx = document.getElementById('trendsChart')?.getContext('2d');
        if (trendsCtx) {
            var trendsData = <?php 
                $years = [];
                $teams = [];
                $matches = [];
                mysqli_data_seek($trends_result, 0);
                while ($trend = mysqli_fetch_assoc($trends_result)) {
                    $years[] = $trend['year'];
                    $teams[] = $trend['total_teams'];
                    $matches[] = $trend['total_matches'];
                }
                echo json_encode(['years' => array_reverse($years), 'teams' => array_reverse($teams), 'matches' => array_reverse($matches)]);
            ?>;
            
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: trendsData.years,
                    datasets: [
                        {
                            label: 'Participating Teams',
                            data: trendsData.teams,
                            borderColor: '#3B9DB3',
                            backgroundColor: 'rgba(59, 157, 179, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Total Matches',
                            data: trendsData.matches,
                            borderColor: '#FFC107',
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    }
                }
            });
        }
        </script>
        <?php endif; ?>
        
        <?php endif; ?>
        
        <!-- ==================== PLAYER STATISTICS REPORT ==================== -->
        <?php if ($report_type == 'player_stats'): ?>
        
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-user me-2"></i>Player Statistics</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Rank</th>
                                <th>Player Name</th>
                                <th>Type</th>
                                <th>Team</th>
                                <th>Position</th>
                                <th>Jersey</th>
                                <th>Goals</th>
                                <th>Yellow Cards</th>
                                <th>Red Cards</th>
                                <th>Matches</th>
                                <th>Goals/Match</th>
                              </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            if ($player_stats_result && mysqli_num_rows($player_stats_result) > 0):
                                while ($player = mysqli_fetch_assoc($player_stats_result)):
                                    $goals_per_match = $player['matches_played'] > 0 ? round($player['goals'] / $player['matches_played'], 2) : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo $rank++; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($player['player_name']); ?></strong></td>
                                    <td><?php echo $player['participant_type']; ?></td>
                                    <td><?php echo htmlspecialchars($player['team_name']); ?></td>
                                    <td><?php echo htmlspecialchars($player['position'] ?: '-'); ?></td>
                                    <td><?php echo $player['jersey_number'] ?: '-'; ?></td>
                                    <td><span class="badge bg-success"><?php echo $player['goals']; ?></span></td>
                                    <td><span class="badge bg-warning"><?php echo $player['yellow_cards']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $player['red_cards']; ?></span></td>
                                    <td><?php echo $player['matches_played']; ?></td>
                                    <td><?php echo $goals_per_match; ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="11" class="text-center">No player statistics available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
        
        <!-- ==================== INVENTORY REPORT ==================== -->
        <?php if ($report_type == 'inventory'): ?>
        
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-boxes" style="color: #3B9DB3;"></i></div>
                    <?php 
                    $total_categories = mysqli_num_rows($inventory_result);
                    ?>
                    <h3><?php echo $total_categories; ?></h3>
                    <p>Equipment Categories</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-cubes" style="color: #28a745;"></i></div>
                    <?php 
                    $total_value = 0;
                    mysqli_data_seek($inventory_result, 0);
                    while ($cat = mysqli_fetch_assoc($inventory_result)) {
                        $total_value += $cat['total_value'];
                    }
                    ?>
                    <h3><?php echo number_format($total_value); ?></h3>
                    <p>Total Inventory Value (TZS)</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card simple-card">
                    <div class="stats-icon"><i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i></div>
                    <?php 
                    $total_low_stock = 0;
                    mysqli_data_seek($inventory_result, 0);
                    while ($cat = mysqli_fetch_assoc($inventory_result)) {
                        $total_low_stock += $cat['low_stock'] + $cat['out_of_stock'];
                    }
                    ?>
                    <h3><?php echo $total_low_stock; ?></h3>
                    <p>Items Need Attention</p>
                </div>
            </div>
        </div>
        
        <!-- Inventory by Category -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Inventory by Category</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Items</th>
                                <th>Total Quantity</th>
                                <th>Low Stock</th>
                                <th>Out of Stock</th>
                                <th>Total Value (TZS)</th>
                                <th>Status</th>
                              </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($inventory_result, 0);
                            while ($category = mysqli_fetch_assoc($inventory_result)): 
                                $status_class = $category['out_of_stock'] > 0 ? 'danger' : ($category['low_stock'] > 0 ? 'warning' : 'success');
                                $status_text = $category['out_of_stock'] > 0 ? 'Critical' : ($category['low_stock'] > 0 ? 'Low Stock' : 'Good');
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($category['category']); ?></strong></td>
                                    <td><?php echo $category['total_items']; ?></td>
                                    <td><?php echo $category['total_quantity']; ?></td>
                                    <td class="text-warning"><?php echo $category['low_stock']; ?></td>
                                    <td class="text-danger"><?php echo $category['out_of_stock']; ?></td>
                                    <td><?php echo number_format($category['total_value']); ?></td>
                                    <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white);">
                <h4 class="mb-0"><i class="fas fa-history me-2"></i>Recent Stock Transactions</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Equipment</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Previous Qty</th>
                                <th>New Qty</th>
                                <th>Reason</th>
                                <th>Performed By</th>
                              </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_transactions && mysqli_num_rows($recent_transactions) > 0): ?>
                                <?php while ($txn = mysqli_fetch_assoc($recent_transactions)): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($txn['item_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($txn['category']); ?></td>
                                        <td>
                                            <?php if ($txn['transaction_type'] == 'IN'): ?>
                                                <span class="badge bg-success">+ Stock IN</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">- Stock OUT</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $txn['quantity']; ?></td>
                                        <td><?php echo $txn['previous_quantity']; ?></td>
                                        <td><?php echo $txn['new_quantity']; ?></td>
                                        <td><?php echo htmlspecialchars(substr($txn['reason'], 0, 50)); ?></td>
                                        <td><?php echo htmlspecialchars($txn['performed_by_name']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center">No transaction records found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
        
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($report_type == 'overview'): ?>
    // Monthly Match Chart
    var monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
    if (monthlyCtx) {
        var monthlyData = <?php 
            $months = [];
            $counts = [];
            $goals = [];
            mysqli_data_seek($monthly_result, 0);
            $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            while ($month = mysqli_fetch_assoc($monthly_result)) {
                $months[] = $month_names[$month['month'] - 1] . ' ' . $month['year'];
                $counts[] = $month['match_count'];
                $goals[] = $month['total_goals'];
            }
            echo json_encode(['months' => array_reverse($months), 'matches' => array_reverse($counts), 'goals' => array_reverse($goals)]);
        ?>;
        
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyData.months,
                datasets: [
                    {
                        label: 'Matches Played',
                        data: monthlyData.matches,
                        backgroundColor: 'rgba(59, 157, 179, 0.7)',
                        borderColor: '#3B9DB3',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Goals Scored',
                        data: monthlyData.goals,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: '#FFC107',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Matches' } },
                    y1: { position: 'right', beginAtZero: true, title: { display: true, text: 'Goals' }, grid: { drawOnChartArea: false } }
                }
            }
        });
    }
    
    // Game Type Chart
    var gameTypeCtx = document.getElementById('gameTypeChart')?.getContext('2d');
    if (gameTypeCtx) {
        var gameTypeData = <?php 
            $game_labels = [];
            $game_counts = [];
            mysqli_data_seek($game_dist_result, 0);
            while ($game = mysqli_fetch_assoc($game_dist_result)) {
                $game_labels[] = $game['game_name'];
                $game_counts[] = $game['match_count'];
            }
            echo json_encode(['labels' => $game_labels, 'counts' => $game_counts]);
        ?>;
        
        new Chart(gameTypeCtx, {
            type: 'pie',
            data: {
                labels: gameTypeData.labels,
                datasets: [{
                    data: gameTypeData.counts,
                    backgroundColor: ['#3B9DB3', '#FFC107', '#28A745', '#DC3545', '#6F42C1', '#FD7E14'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
    <?php endif; ?>
    
    // DataTables initialization for large tables
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#teamPerformanceTable, #matchAnalysisTable').DataTable({
            pageLength: 25,
            order: [[11, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries"
            }
        });
    }
});

// Print functionality
window.print = function() {
    var printContent = document.querySelector('.main-content .container-fluid').cloneNode(true);
    var originalTitle = document.title;
    document.title = 'Sports Report - <?php echo ucfirst($report_type); ?>';
    
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Sports Report</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">');
    printWindow.document.write('<style>@media print { .btn, .no-print, .card-header .btn { display: none; } body { padding: 20px; } .stats-card.simple-card { box-shadow: none; border: 1px solid #ddd; } }</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<div class="container-fluid">' + printContent.innerHTML + '</div>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
    document.title = originalTitle;
}
</script>

<style>
/* Report Page Styles */
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

.progress {
    background-color: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    line-height: 20px;
    font-size: 11px;
}

@media print {
    .sidebar, .header, .btn, .pagination, .card-header .btn, .no-print {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .stats-card.simple-card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }
    
    .table {
        font-size: 10pt !important;
    }
}

@media (max-width: 768px) {
    .stats-card.simple-card h3 {
        font-size: 1.5rem;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
}
</style>

<?php include '../controller/footer.php'; ?>