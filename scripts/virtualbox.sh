# Install Guest Additions
# TODO:  --disablerepo=\* --enablerepo=ebi
yum -y install kernel-devel-`uname -r` gcc perl bzip2

cd /tmp
mount -o loop /tmp/VBoxGuestAdditions.iso /mnt
sh /mnt/VBoxLinuxAdditions.run
umount /mnt
rm -rf /tmp/VBoxGuestAdditions.iso

yum -y history undo last
