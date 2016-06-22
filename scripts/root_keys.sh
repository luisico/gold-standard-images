#!/bin/sh -x
# Root keys
mkdir /root/.ssh
ls -lart /tmp
cat /tmp/root.pub >> /root/.ssh/authorized_keys
chown -R root /root/.ssh
chmod -R go-rwsx /root/.ssh
#rm /tmp/root.pub
