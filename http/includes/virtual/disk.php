clearpart --drives sda --all --initlabel
zerombr

<?php
if ($boot) {
  echo "part /boot --fstype $fstype --size 150 --ondisk sda\n";
}

if ($lvm) {
  echo "part pv.01 --size 1 --grow --ondisk sda\n";
  echo "volgroup VG_localhost_sys --pesize 4096 pv.01\n";
  if ($swap) {
    echo "logvol swap --fstype swap --name LV_swap --vgname VG_localhost_sys --size 1024\n";
  }
  echo "logvol / --fstype $fstype --name LV_localhost_sys --vgname VG_localhost_sys --size 1 --grow\n";

} else {
  echo "part / --fstype $fstype --size 1 --grow --ondisk sda\n";
}
?>
