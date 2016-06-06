<?php
$netmask      = '255.255.0.0';
$nameserver   = '172.16.1.100';
$gateway      = '172.22.1.1';
echo "network --bootproto static --ip $ipaddr --hostname $fqdn --netmask $netmask --gateway $gateway --nameserver $nameserver\n";
?>
