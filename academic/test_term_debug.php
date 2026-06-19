<?php
// test_term_debug.php - Debug term passing
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Term Passing</title>
</head>
<body>
    <h2>Debug: Check What Term is Being Sent</h2>
    
    <form method="post" action="test_term_debug.php">
        <label>Select Term:</label>
        <select name="term" id="termSelect">
            <option value="Term 01">Term 01</option>
            <option value="Term 02" selected>Term 02</option>
        </select>
        <br><br>
        <input type="submit" value="Submit">
    </form>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h3>POST Data Received:</h3>";
        echo "<pre>";
        print_r($_POST);
        echo "</pre>";
        
        $term = isset($_POST['term']) ? $_POST['term'] : 'Not set';
        echo "<p><strong>Term value:</strong> '" . $term . "'</p>";
        echo "<p><strong>Term length:</strong> " . strlen($term) . " characters</p>";
    }
    ?>
</body>
</html>