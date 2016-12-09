<?php
echo "volgroup VG_${hostname}_sys --pesize 32768 pv.01\n";
echo "logvol swap --fstype swap --name LogVolSwap --vgname VG_${hostname}_sys --size 2048\n";
echo "logvol / --fstype $fstype --name LV_${hostname}_sys --vgname VG_${hostname}_sys --size 61440\n";
?>
