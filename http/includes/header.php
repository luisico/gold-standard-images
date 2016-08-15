<?php
header('Content-type: text/plain');

// Defaults hardware
$hardware = 'metal';

// Parse machine
$machine = $_GET['machine'];

if (!$machine) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
  echo '400 Bad Request';
  echo 'No machine provided';
  exit;
}

$machinefile = "machines/$machine.php";
if (!file_exists($machinefile)) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
  echo '404 Not Found';
  echo "Machine '$machine' not found";
  exit;
}

include $machinefile;

// Parse OS (takes precedence over definitions in machinefile)
if ($_GET['os']) {
  list($os_name, $os_version) = explode('-', $_GET['os'], 2);
}

// Set hostname and domain
list($hostname, $domain) = explode('.', $fqdn, 2);
?>
