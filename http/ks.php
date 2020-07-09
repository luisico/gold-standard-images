<?php
require "includes/header.php";

if (file_exists("includes/$hardware/bootline.php")) {
  include "includes/$hardware/bootline.php";
}
?>

cmdline
<?php
if ($os_version_major < 8) {
  echo "install\n";
  echo "unsupported_hardware\n";
}
?>

<?php require "includes/repo.php"; ?>

<?php require "includes/locale.php"; ?>

<?php require "includes/$hardware/network.php"; ?>

<?php require "includes/rootpw.php"; ?>

<?php
if ($os_version_major < 8) {
  echo "authconfig --enableshadow --passalgo sha512\n";
}
?>
firewall --enabled --port 22:tcp
selinux --disabled
firstboot --disabled

bootloader --location mbr --driveorder sda --append "rdblacklist=nouveau nouveau.modeset=0"

<?php require "includes/disk.php"; ?>

reboot

<?php
if ($os_version_major < 8) {
  echo "%packages --nobase\n";
  echo "@core\n";
  echo "man\n";
  echo "openssh-clients\n";
  echo "system-config-firewall-base\n";
  echo "%end\n";
} else {
  echo "%packages\n";
  echo "@^minimal-environment\n";
  echo "kexec-tools\n";
  echo "%end\n";
}
?>

%post
<?php require "includes/$hardware/post.php" ?>
%end
