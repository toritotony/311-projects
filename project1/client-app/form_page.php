<!--
    This section is part of our plan to create a callback form that utilizes the database
    to dynamically populate selectable choices within the form. The options presented to the
    Upon submission, we will leverage postback form techniques to update the page appearance,
    providing immediate feedback or additional options based on user input.
-->

<?php
    require_once '../conn.php';

    $sql = "SELECT * FROM choices";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['name']) . "</option>";
        }
    } else {
        echo "<option>No choices found</option>";
    }

    $conn->close();
?>
    

