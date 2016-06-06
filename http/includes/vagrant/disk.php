zerombr
volgroup VG_localhost_sys --pesize 4096 pv.01
logvol swap --fstype swap --name LV_swap --vgname VG_localhost_sys --size 1024
logvol / --fstype ext4 --name LV_localhost_sys --vgname VG_localhost_sys --size 1 --grow
