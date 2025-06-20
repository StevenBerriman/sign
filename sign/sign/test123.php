<?php
echo "PHP file access is working!";
echo "<br>Current file: " . __FILE__;
echo "<br>Request URI: " . $_SERVER['REQUEST_URI'];
echo "<br>Script name: " . $_SERVER['SCRIPT_NAME'];
?>