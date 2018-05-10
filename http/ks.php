<?php include "includes/header.php"; ?>
install
cmdline

<?php include "includes/repo.php"; ?>

<?php include "includes/locale.php"; ?>

<?php include "includes/$hardware/network.php"; ?>

<?php include "includes/rootpw.php"; ?>

authconfig --enableshadow
firewall --enabled --port 22:tcp
selinux --disabled
firstboot --disabled

bootloader --location mbr --driveorder sda --append "rdblacklist=nouveau nouveau.modeset=0"

<?php include "includes/$hardware/disk.php"; ?>

reboot

%packages --nobase
@core
eject
man
openssh-clients
system-config-firewall-base
%end

%post
<?php include "includes/$hardware/post.php" ?>
%end
