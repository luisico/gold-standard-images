# Introduction

Gold Standard Images is a pillar of DevOps best practices. Images are repeatable, self-documented, and portable across multiple platforms and ensure production/staging/development parity. Other benefits include:
- Fast deployment, benefiting not only production, but development as well.
- Early test-ability.
- End-to-end control.
- Known and predictable state.
- Policy enforcement.

The system proposed here relies on [Packer.io](https://www.packer.io), [Vagrant](https://www.vagrantup.com), [VirtualBox](https://www.virtualbox.org) and [Ansible](https://www.ansible.com) technologies to build minimal images in a repeatable and portable manner. Bare metal is also supported in addition to multiple cloud providers. This is accomplished by providing kickstarts and Ansible playbooks that are similar between bare metal and VMs.

The images produced are minimal by design, containing only the minimum number of packages and configuration to make them workable, secure and general enough to be reused in multiple projects.

The system depends on three inputs:
- OS ISO image.
- Kickstart.
- Ansible playbooks.

# Supported images

### OS
- Centos 7.2.1511
- RedHat 7.2

### Providers
- Virtualbox
- VMware
- AWS
- OpenStack

# Building artifacts

First select the artifact name and OS template. Supported operating system are:
``` sh
vm_name=centos72 ; template=CentOS-7-x86_64-Minimal-1511
vm_name=rhel72   ; template=rhel-server-7.2-x86_64
```

Then select the version to generate and other variables used by packer (a complete list can be found at the top of `template/main.json`), ie:
``` sh
version=0.0.0
aws_s3_bucket=mys3bucket
vmware_host=myhost
gcloud_bucket=mygcpbucket
```

Other options of interest are:
``` sh
opts="-var headless=false"        # helps debugging kickstarts
opts="-force"                     # forces overwriting of artifacts
```

Run packer in parallel to generate the all artifacts and automatically upload them to their cloud providers (when available):
``` sh
packer build $opts -var-file=templates/$template.json -var version=$version -var s3_bucket=$s3_bucket templates/main.json
```

## Provider specifics

### VMware

Manually upload from a converted OVA artifact:
``` sh
ovftool --name=${vm_name}-${version}-packer -dm=thin --vCloudTemplate --compress=1 artifacts/$version/${vm_name}/vmware/${vm_name}-${version}_vmware.vmx artifacts/$version/${vm_name}/vmware/${vm_name}.ova
```

### OpenStack

To manage an OpenStack cloud from command line requires the [OpenStack command-line](http://docs.openstack.org/user-guide/common/cli-install-openstack-command-line-clients.html) client. Instructions to install and use the client can be found in its webpage. Briefly, being a python tool it is best to install it in a virtualenv:
``` sh
virtualenv python-openstack
. python-openstack/bin/activate
pip install python-openstackclient
```

We also need to configure our system to access the OpenStack cloud. The easies route is to set environment variables using the OpenStack RC file. Download this file from your cloud at `Access & Security > API Access > Download OpenStack RC file` and save locally. Source it before issuing OpenStack client commands and enter your password:
``` sh
. openstack.rc
```

Upload:
``` sh
openstack image create --disk-format qcow2 --file artifacts/$version/${vm_name}/openstack/${vm_name}-${version}_openstack.qcow2 --tag packer --protected --public ${vm_name}-${version}-packer
```

# Azure

Manually upload the VHD image:

``` sh
key=$(azure storage account keys list storage_account -g resource_group --json | jq -r '.[] | select(.keyName == "key1") | .value')
azure storage blob upload -t page -a storage_account -k $key --container images_container -f artifacts/$version/${vm_name}/azure/${vm_name}-${version}-packer.vhd
```

# Google Compute Cloud

Manually upload the image:

``` sh
gsutil cp artifacts/$version/${vm_name}/gcloud/${vm_name}-${version}_gcloud.tar.gz gs://${gcloud_bucket}
gcloud compute images create ${vm_name}-${version}-packer --source-uri gs://${gcloud_bucket}/${vm_name}-${version}_gcloud.tar.gz
```

# Docker

Manually import image:

``` sh
docker import artifacts/$version/${vm_name}/docker/{{user `version`}}_{{build_name}}.tar.gz {{user `vm_name`}}-{{user `version`}}-packer
```

# Components

The build of an artifact for use in a cloud environment start with a Packer.io template, which in turn will first use a kickstart to bootstrap the machine and then provision if with Ansible playbooks and shell scripts. The end result will produce an artifact that can be deployed to the target cloud environment.

## Packer.io templates

A standard Packer.io JSON template for parallel generation of artifacts for different cloud providers is located in the `templates/main.json` alongside with OS specific templates (ie `CentOS-7-x86_64-Minimal-1511.json`).

This standard template declares a set of variables (`user variables` as per Packer.io), which can generally be divided in three types:
- Artifact specific variables (ie `namespace`, `vm_name` and `version`).
- OS specific variables (ie `os_name`, `os_version`, `iso` and `iso_checksum`). These are unset in the templates and instead should be defined for each supported OS in a OS specific template (ie `CentOS-7-x86_64-Minimal-1511.json`). These might not be declared if the template depends on another template's artifacts.
- Specific template variables needed by the builders and post-processors (these have a prefix corresponding to the builder name).

Default values are provider either on the template itself or on the OS specific template. Those variables with a default value of `null` will need to get their value from the OS specific template or the command line. Variables defined in the OS specific template will always override values from the template, and variables defined in the command line will override any other value.

This setup provides flexibility in image building process, like separating building process from OS, and allowing for versioning of artifacts.

The main templates will bootstrap artifacts for multiple providers, usually providing an additional VirtualBox artifact for development/testing purposes.

## Kickstarts

Kickstart files are located in the `http/` directory. The main kickstart file is `ks.php`, and requires a server running php. On a local computer the easiest is to install apache and symlink this directory to `~/public_html`. The kickstart system is divided in multiple files that get included depending on the variables defined the machine file for the specified server (found in `machines/`). All Packer.io templates will use definitions found `packer.php` while bare metal servers will look for definition files with their hostname. Different kickstart sections get included for metal or packer appropriately. URLs to target different servers are in the form of `http://server.domain:port/ks.php?machine=packer` (substitute `packer` with any machine defined under `machines/`).

### First boot options

Builders using a kickstart pass the following boot options:
- `selinux=0` will disable selinux.

The customized kickstart also understands the following options:
- `EJECT` will eject the first cd/dvd drive. This is mainly use in bare metal outside of packer.

## Ansible playbooks

Each builder will provision the image by calling an Ansbile playbook of the same name, ie `virtualbox.yml`. These playbooks start by running base tasks from `base.yml` common to all builders, and then running the builder specific tasks.

The structure of these playbooks does not conform to best practices due to the specificity of this projects. In particular, tasks are used directly instead of roles. Tasks (as well as templates and files) are shared among all playbooks to better maintain consistency among generated artifacts.

Note that idempotency is not key when running Ansible in this project because a playbook is only run once.

## Finishing shell scripts

Several shell scripts located in `scripts/` are run by each template to clean up the images:
- `cleanup.up` remove caches and sessions.
- `zero.sh` compresses the image.

## Directory Structure

```
|-- ansible/                                      Ansible playbooks
|   |-- base.yml                                  Base tasks included in all playbooks
|   |-- virtualbox.yml                            One playbook per builder
|   |-- vmware.yml
|   |-- ...
|   |-- tasks/                                    Tasks used in playbooks
|   |-- files/                                    Files used by Ansible tasks
|   `-- templates/                                Templates used by Ansible tasks
|-- artifacts/                                    Artifacts (intermediate/end images)
|   |-- 0.0.0/                                    Ordered by version
|   |   `-- rhel72/                               and OS
|   |       |-- aws/                              Each template generates at least 2 artifacts
|   |       |   `-- rhel72-aws-0.0.0.ova            A template specific artifact
|   |       |   `-- rhel72-aws-0.0.0.box            A vagrant box for debugging purposes
|   |       .                                       Other files might be generated by packer
|   |       `-- vmware/                             Directory structure is in VC, but
|   |           |-- rhel72-aws-0.0.0.box            not the artifacts [!VC]
|   |           |-- rhel72-aws-0.0.0.ova
|   |           |-- ...
|   |           `-- rhel72-aws-0.0.0.vmdk
|   `-- RedHat-7.2.json                           Vagrant boxes catalog metadata (one per OS)
|-- http/                                         HTTP server directory for Packer.io and metal PXE
|   |-- ks.php                                    Kickstart entry point
|   |-- includes/                                 Kickstart include files
|   |   |-- header.php
|   |   |-- metal/                                Metal specific Kickstart settings
|   |   |   |-- disk.php
|   |   |   |-- network.php
|   |   |   |-- post.php
|   |   |   `-- rootpw.php
|   |   |-- packer/                               Packer specific Kickstart settings
|   |   |   |-- disk.php
|   |   |   |-- network.php
|   |   |   |-- post.php
|   |   |   `-- rootpw.php
|   |   `-- repos/                                Repositoy URLs for OSs
|   |       |-- CentOS.php
|   |       `-- RedHat.php
|   |-- machines/                                 Per machine definition (used mostly for bare metal)
|   |   `-- packer.php                            Definitions for machines bootstrap by Packer.io
|   ` -- isos/                                    Locally downloaded isos
|       |-- RedHat/                               Ordered by OS
|       |   `-- 7.2/                                and version
|       |     `-- rhel-server-7.2-x86_64-boot.iso
|       `-- CentOS/
|-- keys/                                         SSH keys [!VC]
|   |-- packer
|   |-- packer.pub
|   |-- vagrant
|   `-- vagrant.pub
|-- README.md                                     Yeah, readme please!
|-- scripts/                                      Provisioning helper scripts
|   |-- cleanup.sh                                Cleanup boxes
|   `-- zero.sh                                   Compress boxes
|-- templates/                                    Packer.io templates
|   |-- main.json                                 Parallel template
|   |-- rhel-server-7.2-x86_64.json               OS specific variables
|   `-- CentOS-7-x86_64-Minimal-1511.json
|-- test/                                         Vagrant tests
|   `-- Vagrantfile
`-- .gitignore                                    Files to ignore in VC
```

# TODO

- User vagrant? Different users might be needed for each artifact
- Use EPM for all repositories
- Document generation of vagrant and packer keys
- Bigger disk size and allow to grow?
- guest_os_type variables for vbox and VMware should go in OS template
- Move vm_name out of OS templates?
- Change vm_name to be similar to OS (ie CentOS-7.2.1511)
