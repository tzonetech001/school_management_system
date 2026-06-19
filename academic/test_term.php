<?php
// test_term.php - Test if term is being passed correctly
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Term Passing</title>
</head>
<body>
    <h2>Test Term Passing</h2>
    
    <form method="post" action="test_term_receive.php">
        <label>Select Term:</label>
        <select name="term">
            <option value="Term 01">Term 01</option>
            <option value="Term 02" selected>Term 02</option>
        </select>
        <br><br>
        <input type="submit" value="Submit">
    </form>
    
    <?php
    if (isset($_POST['term'])) {
        echo "<p>Term received: <strong>" . $_POST['term'] . "</strong></p>";
    }
    ?>
</body>
</html>