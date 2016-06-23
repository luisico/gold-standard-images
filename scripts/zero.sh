# Zero out space on all partitions

swap_devices=$(/sbin/swapon --show=NAME --noheadings --ifexists)
/sbin/swapoff -a
for device in $swap_devices; do
  dd if=/dev/zero of=$device bs=1M
  /sbin/mkswap $device
done

for mount in $(findmnt --fstab --types ext4,ext3 --noheadings --raw | cut -f 1 -d' '); do
  dd if=/dev/zero of=$mount/EMPTY bs=1M
  rm -f $mount/EMPTY
done
