<?php
echo "Step 1: PHP is working<br>";

// Test if config directory exists
if (is_dir('config')) {
    echo "Step 2: config directory exists<br>";
} else {
    echo "Step 2: ERROR - config directory NOT found<br>";
}

// Test if config.php exists
if (file_exists('config/config.php')) {
    echo "Step 3: config.php exists<br>";
    
    // Try to include it
    try {
        require_once 'config/config.php';
        echo "Step 4: config.php loaded successfully<br>";
    } catch (Exception $e) {
        echo "Step 4: ERROR loading config.php: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Step 3: ERROR - config.php NOT found<br>";
}

// Test redirect
echo "Step 5: If you see this, PHP is working fine!<br>";
echo "<a href='build/index.html'>Click here to go to the app</a>";
?>