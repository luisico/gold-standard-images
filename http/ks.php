<?php include "includes/header.php"; ?>
install
cmdline

<?php echo "url --url http://${build_server}/${os_version}/os/x86_64\n"; ?>

lang en_GB.UTF-8
keyboard uk
timezone Europe/London

<?php include "includes/$hardware/network.php"; ?>

<?php include "includes/$hardware/rootpw.php"; ?>

authconfig --enableshadow
firewall --enabled --port 22:tcp
selinux --disabled
firstboot --disabled

bootloader --location mbr --driveorder sda --append "rdblacklist=nouveau nouveau.modeset=0"

clearpart --drives sda --all --initlabel
part /boot --fstype ext4 --size 150 --ondisk sda
part pv.01 --size 1 --grow --ondisk sda

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
