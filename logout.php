<?php
session_start();
session_destroy();
header("Location: main.php?msg=" . urlencode("You have been logged out."));
exit();
?>