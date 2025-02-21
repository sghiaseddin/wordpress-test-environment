<?php
/*
 * Get env variables in the Docker container
 * 
**/
if (!function_exists('getenv_docker')) {
	function getenv_docker($env, $default) {
		if ($fileEnv = getenv($env . '_FILE')) {
			return rtrim(file_get_contents($fileEnv), "\r\n");
		}
		else if (($val = getenv($env)) !== false) {
			return $val;
		}
		else {
			return $default;
		}
	}
}

// Fetch environment variables
$wordpress_port = getenv_docker('WORDPRESS_PORT', '8050');
$admin_user = getenv_docker('WORDPRESS_ADMIN_USERNAME', 'admin');
$admin_password = getenv_docker('WORDPRESS_ADMIN_PASSWORD', 'password');
$admin_email = getenv_docker('WORDPRESS_ADMIN_EMAIL', 'admin@example.com');

// Add some styling
echo '<h1 style="
    text-align: center;
    margin-bottom: 0;
    margin-top: 8vh;
">Wordpress Installation Log</h1>';
echo '<pre style="
    background-color: gainsboro;
    padding: 20px;
    border: 2px solid darkgray;
    width: 80%;
    max-width: 550px;
    margin: 2vh auto 10vh;
    height: 60vh;
    overflow-y: scroll;
    text-wrap: auto;">';

// Inline Bash script
$temp_script = tempnam(sys_get_temp_dir(), 'wp_init_');
file_put_contents($temp_script, <<<BASH
#!/bin/bash
echo "WordPress is ready. Proceeding with installation."

# Change directory to WordPress installation
cd /var/www/html

# Ensure WordPress is installed
if ! wp core is-installed --allow-root; then
  echo "Installing WordPress..."
  wp core install --url="http://localhost:$wordpress_port" \\
    --title="My WordPress Site" \\
    --admin_user="$admin_user" \\
    --admin_password="$admin_password" \\
    --admin_email="$admin_email" \\
    --allow-root

  echo "Database initialized."
  echo " "
  echo "Administrator user created."
  echo "Admin Area: http://localhost:$wordpress_port/wp-admin"
  echo "Username: $admin_user"
  echo "Password: $admin_password"
  echo " "
  
  echo "Installing plugins..."
  # put plugin slug in the list below
  wp plugin install classic-editor wordpress-seo --activate --allow-root
  echo "Plugins installed."
else
  echo "WordPress is already installed."
fi
BASH);

// Make script executable
chmod($temp_script, 0755);

// Run the script with live output
$descriptors = [
    1 => ['pipe', 'w'], // Standard output
    2 => ['pipe', 'w']  // Standard error
];

$process = proc_open("bash $temp_script", $descriptors, $pipes);

if (is_resource($process)) {
    // Display output line by line
    while ($line = fgets($pipes[1])) {
        echo nl2br(htmlspecialchars($line));
        ob_flush();
        flush();
    }
    
    // Capture errors (if any)
    while ($line = fgets($pipes[2])) {
        echo nl2br(htmlspecialchars("ERROR: " . $line));
        ob_flush();
        flush();
    }
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    proc_close($process);
}

// Close element
echo '</pre>';

// Cleanup: Delete temp script and this PHP file
unlink($temp_script);
unlink(__FILE__);
?>