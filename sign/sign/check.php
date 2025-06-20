<?php
if (file_exists('build/index.html')) {
    $html = file_get_contents('build/index.html');
    
    // Show the first 1000 characters of the HTML
    echo "<h2>Content of build/index.html:</h2>";
    echo "<pre>" . htmlspecialchars(substr($html, 0, 1000)) . "</pre>";
    
    // Check if static files exist
    echo "<h2>Static files check:</h2>";
    echo "build/static/ exists: " . (is_dir('build/static') ? 'YES' : 'NO') . "<br>";
    
    if (is_dir('build/static')) {
        echo "<h3>Contents of build/static/:</h3>";
        $dirs = ['css', 'js'];
        foreach ($dirs as $dir) {
            if (is_dir("build/static/$dir")) {
                echo "$dir folder contents:<br>";
                $files = scandir("build/static/$dir");
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        echo "- $file<br>";
                    }
                }
            }
        }
    }
} else {
    echo "build/index.html not found!";
}
?>