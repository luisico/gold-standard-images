#!/usr/bin/env bash

set -e

# Following env variables are set in the main packer template before calling this script:
# - VENDOR
# - PRODUCT
# - VERSION
# - OUTDIR
# - OUTNAME

TMPDIR=$OUTDIR/tmp

mkdir -p $TMPDIR

ovfopts="--quiet --name=$OUTNAME --diskMode=thin --vCloudTemplate --compress=1 --shaAlgorithm=sha256"

echo "Converting VMX to OVF"
ovftool $OUTDIR/$OUTNAME.vmx $TMPDIR/$OUTNAME.ovf

echo "Injecting OVF Envelop properties for Cloud Init"
ovfenv=$(sed -e '0,/__OVFENV__$/d' \
             -e 's/^/    /' \
             -e "s/{{VENDOR}}/${VENDOR}/" \
             -e "s/{{PRODUCT}}/${PRODUCT} GSI/" \
             -e "s/{{VERSION}}/${VERSION}/" \
             -e "s/{{FULLVERSION}}/${VERSION}/" \
             "$0")

sed -i \
    -e 's/<VirtualHardwareSection>/<VirtualHardwareSection ovf:transport="com.vmware.guestInfo">/' \
    -e "/<\/OperatingSystemSection>/r /dev/stdin" \
    $TMPDIR/$OUTNAME.ovf <<< "$ovfenv"

echo "Converting OVF to OVA"
rm $TMPDIR/$OUTNAME.mf
ovftool $ovfopts $TMPDIR/$OUTNAME.ovf $OUTDIR/$OUTNAME.ova

rm -rf $TMPDIR

exit

# Reference: https://www.dmtf.org/sites/default/files/standards/documents/DSP0243_2.1.1.pdf

__OVFENV__
<ProductSection ovf:required="true">
  <Info>Cloud-Init customization</Info>
  <Product>{{PRODUCT}}</Product>
  <Vendor>{{VENDOR}}</Vendor>
  <Version>{{VERSION}}</Version>
  <FullVersion>{{FULLVERSION}}</FullVersion>
  <ProductUrl/>
  <VendorUrl/>
  <AppUrl/>
  <Category>Cloud Init</Category>
  <Property ovf:key="instance-id" ovf:type="string" ovf:userConfigurable="true" ovf:value="id-ovf">
    <Label>Unique Instance ID *</Label>
    <Description>Specifies the instance ID (REQUIRED). Used to determine if the machine should take "first boot" actions.</Description>
  </Property>
  <Property ovf:key="hostname" ovf:type="string" ovf:userConfigurable="true">
    <Label>Hostname (FQDN)</Label>
    <Description>Fully Qualified Domain Name of system (OPTIONAL).</Description>
  </Property>
  <Property ovf:key="seedfrom" ovf:type="string" ovf:userConfigurable="true">
    <Label>URL to seed instance data from</Label>
    <Description>The instance should "seed" user-data and meta-data from the given URL (OPTIONAL). User- and meta-data will be pulled from given URL suffixed with "user-data" and "meta-data", respectively.</Description>
  </Property>
  <Property ovf:key="public-keys" ovf:type="string" ovf:userConfigurable="true">
    <Label>SSH public key</Label>
    <Description>Add public key to the "authorized_keys" of the default user (OPTIONAL).</Description>
  </Property>
  <Property ovf:key="user-data" ovf:type="string" ovf:userConfigurable="true">
    <Label>User-data (base64)</Label>
    <Description>Cloud Init user data (OPTIONAL). This value must be base64 encoded to fit in a single XML string. See https://cloudinit.readthedocs.io/en/latest/topics/format.html for available formats.</Description>
  </Property>
  <Property ovf:key="password" ovf:type="string" ovf:userConfigurable="true" ovf:password="true">
    <Label>Password for default user</Label>
    <Description>Password of the default user to allow password based login (OPTIONAL). It will be good for only a single login. If set to "RANDOM", then a random password will be generated, and written to the console.</Description>
  </Property>
</ProductSection>
