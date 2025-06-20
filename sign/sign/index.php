<?php
// Check if this is an API request
if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false && !strpos($_SERVER['REQUEST_URI'], '.php')) {
    require_once 'api/index.php';
    exit;
}
// Redirect to React app
header('Location: build/index.html');
exit;
?>