# Clean packages
yum -y clean all

# Remove kickstart scripts
rm -f /tmp/ks-script-*

# Remove ssh sessions
rm -rf /tmp/ssh-*
