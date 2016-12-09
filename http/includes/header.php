<?php
header('Content-type: text/plain');

// Defaults hardware
$hardware = 'metal';

// Parse build type
$build = $_GET['build'];

if (!$build) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
  echo '400 Bad Request';
  echo 'No build type provided';
  exit;
}

$buildfile = "builds/$build.php";
if (!file_exists($buildfile)) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
  echo '404 Not Found';
  echo "Machine '$build' not found";
  exit;
}

include $buildfile;

// Parse OS (takes precedence over definitions in buildfile)
if ($_GET['os']) {
  list($os_name, $os_version) = explode('-', $_GET['os'], 2);
}

// Set hostname and domain
list($hostname, $domain) = explode('.', $fqdn, 2);
?>
