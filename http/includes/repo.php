<?php
if ($os_version_major < 8) {
  echo "url --url ${packer_os['repo_server']}/${os_version}/os/x86_64\n";
} else {
  echo "url --url ${packer_os['repo_server']}/${os_version}/BaseOS/x86_64/os\n";
}
?>
