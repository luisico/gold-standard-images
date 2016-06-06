# Vagrant keys
mkdir /home/vagrant/.ssh
cat /tmp/vagrant.pub >> /home/vagrant/.ssh/authorized_keys
chown -R vagrant /home/vagrant/.ssh
chmod -R go-rwsx /home/vagrant/.ssh
rm /tmp/vagrant.pub
