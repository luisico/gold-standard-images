# Clean packages
yum -y clean all

# Zero out space
/sbin/swapoff -a
/sbin/mkswap /dev/mapper/VG_localhost_sys-LV_swap
dd if=/dev/zero of=/boot/EMPTY bs=1M
rm -f /boot/EMPTY
dd if=/dev/zero of=/EMPTY bs=1M
rm -f /EMPTY
