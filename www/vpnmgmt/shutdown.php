<?php
echo "Shutting down server...";
$result = shell_exec('sudo shutdown -h now');
?>
