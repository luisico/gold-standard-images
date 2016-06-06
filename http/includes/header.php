<?php
header('Content-type: text/plain');

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

list($hostname, $foo) = explode('.', $fqdn);
$networkip = substr($ipaddr, 0, strrpos($ipaddr, '.'));

if ($hostname == 'vagrant') {
  $hardware = 'vagrant';
  $build_server = 'mirror.ox.ac.uk/sites/mirror.centos.org';
} else {
  $hardware = 'metal';
  // $build_server = ''; // TODO
}
?>
