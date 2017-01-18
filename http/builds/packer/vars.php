<?php
// Read packer template variables
// Note: variables overriden in packer's command line are not picked up here

$packerfile = "builds/packer/site.json";
if (file_exists($packerfile)) {
  $packer_site = json_decode(file_get_contents($packerfile), true);
} else {
  echo "# Packer template '$packerfile' not found... continuing without site variables\n\n";
}

$packerfile = "builds/packer/os/${os_name}-${os_version}.json";
if (file_exists($packerfile)) {
  $packer_os = json_decode(file_get_contents($packerfile), true);
} else {
  echo "# Packer template '$packerfile' not found... continuing without OS variables\n\n";
}
?>
