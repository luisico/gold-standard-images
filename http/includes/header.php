<?php
header('Content-type: text/plain');

// Read defaults
include "builds/defaults.php";

// Build options (overwrite defaults)
if ($build = $_GET['build']) {
  $buildfile = "builds/$build.php";
  if (!file_exists($buildfile)) {
    echo "# Build '$build' not found... continuing with defaults\n\n";
  }
  include $buildfile;
}

// OS (overwrite defaults)
if ($_GET['os']) {
  list($os_name, $os_version) = explode('-', $_GET['os'], 2);
}

// Set hostname and domain
if ($fqdn) {
  list($hostname, $domain) = explode('.', $fqdn, 2);
}
?>
