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


// Parse OS (takes precedence over definitions in buildfile)
if ($_GET['os']) {
  list($os_name, $os_version) = explode('-', $_GET['os'], 2);
}

// Set hostname and domain
list($hostname, $domain) = explode('.', $fqdn, 2);
?>
