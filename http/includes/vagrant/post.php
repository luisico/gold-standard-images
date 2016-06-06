# Vagrant user
groupadd vagrant
useradd vagrant -g vagrant -G wheel
echo "vagrant" | passwd --stdin vagrant

# Add vagrant user to sudoers
yum -y install sudo
echo "Defaults:vagrant !requiretty" > /etc/sudoers.d/vagrant
echo "vagrant        ALL=(ALL)       NOPASSWD: ALL" >> /etc/sudoers.d/vagrant
chmod 0440 /etc/sudoers.d/vagrant
