# Introduction

Gold Standard Images is a pillar of DevOps best practices. Images are repeatable, self-documented, and portable across multiple platforms and ensure production/staging/development parity. Other benefits include:
- Fast deployment, benefiting not only production, but development as well.
- Early test-ability.
- End-to-end control.
- Known and predictable state.
- Policy enforcement.

The system proposed here relies on [Packer.io](https://www.packer.io), [Vagrant](https://www.vagrantup.com), [VirtualBox](https://www.virtualbox.org) and [Ansible](https://www.ansible.com) technologies to build minimal images in a repeatable and portable manner. Bare metal is also supported in addition to multiple cloud providers. This is accomplished by providing kickstarts and Ansible playbooks that are similar between bare metal and VMs.

The images produced are minimal by design, containing only the minimum number of packages and configuration to make them workable, secure and general enough to be reused in multiple projects.

The system depends on 4 inputs:
- Packer.io template.
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

# Building artifacts

First select the OS, artifact name and version and a few other variables:
```
vm_name=centos72
version=0.2.0
s3_bucket=mys3bucket

vars="-var-file=templates/CentOS-7-x86_64-Minimal-1511.json -var version=$version -var s3_bucket=$s3_bucket"
opts=""
```

Other optional variables and options of interest are:
```
vars="$vars -var headless=false"        # helps debugging
opts="$opts -force"                     # forces overwriting of artifacts
```

Run packer (and ovftool helper for VMware) in turn to generate the different artifacts as needed:
```
packer build $opts $vars templates/base.json
packer build $opts $vars templates/aws.json
packer build $opts $vars templates/virtualbox.json
packer build $opts $vars templates/vmware.json
ovftool -dm=thin --compress=1 artifacts/$version/vmware/${vm_name}.vmx artifacts/$version/vmware/${vm_name}.ova
```

# Components

The build of an artifact for use in a cloud environment start with a Packer.io template, which in turn will first use a kickstart to bootstrap the machine and then provision if with Ansible playbooks and shell scripts. The end result will produce an artifact that can be deployed to the target cloud environment.

## Packer.io templates

Standard Packer.io JSON templates for different cloud providers are located in the `templates/` directory alongside with OS specific templates (ie `CentOS-7-x86_64-Minimal-1511.json`).

Each template declares a set of variables (`user variables` as per Packer.io), which can generally be divided in three types:
- Artifact specific variables (ie `namespace`, `vm_name` and `version`).
- OS specific variables (ie `os_name`, `os_version`, `iso` and `iso_checksum`). These are unset in the templates and instead should be defined for each supported OS in a OS specific template (ie `CentOS-7-x86_64-Minimal-1511.json`). These might not be declared if the template depends on another template's artifacts.
- General template variables needed by the builder/provisioners used within the template.

Default values are provider either on the template itself or on the OS specific template. Those variables with a default value of `null` will need to get their value from the OS specific template or the command line. Variables defined in the OS specific template will always override values from the template, and variables defined in the command line will override any other value.

This setup provides flexibility in image building process, like separating building process from OS, and allowing for versioning of artifacts.

Templates will bootstrap artifacts for one or more cloud providers, usually providing an additional VirtualBox artifact for development/testing purposes.

Note that some of the templates depend on the output produced by the `base` template. `vmware` template is an exception and replicated most of the options from `base`. Care should be taken to keep them in sync.

## Kickstarts

Kickstart files are located in the `http/` directory. The main kickstart file is `ks.php`, and requires a server running php. On a local computer the easiest is to install apache and symlink this directory to `~/public_html`. The kickstart system is divided in multiple files that get included depending on the variables defined the machine file for the specified server (found in `machines/`). All Packer.io templates will use definitions found `packer.php` while bare metal servers will look for definition files with their hostname. Different kickstart sections get included for metal or packer appropriately. URLs to target different servers are in the form of `http://server.domain:port/ks.php?machine=packer` (substitute `packer` with any machine defined under `machines/`).

Note that some Packer.io templates depend on previously generated artifacts and therefore will not use a kickstart.

### First boot options

Templates using a kickstart pass the following boot options:
- `selinux=0` will disable selinux.

The customized kickstart also understand the following options:
- `EJECT` will eject the first cd/dvd drive. This is mainly use in bare metal.

## Ansible playbooks

Each template will provision the image by calling an Ansible playbook by the same name as the template. The structure of these playbooks does not conform to best practices due to the specificity of this projects. In particular, tasks are used directly instead of roles. Tasks (as well as templates and files) are shared among all playbooks to better maintain consistency among generated artifacts. Note that idempotency is not key when running Ansible in this project because a playbook is only run once.

## Finishing shell scripts

Several shell scripts located in `scripts/` are run by each template to clean up the images:
- `cleanup.up` remove caches and sessions.
- `zero.sh` compresses the image.

## Directory Structure

```
|-- ansible/                                      Ansible playbooks (one per Packer.io template)
|   |-- base.yml                                  Base
|   |-- aws.yml                                   AWS
|   |-- virtualbox.yml                            VirtualBox
|   |-- vmware.yml                                VMware
|   |-- ...
|   |-- tasks/                                    Tasks used in playbooks
|   |-- files/                                    Files used by Ansible tasks
|   `-- templates/                                Templates used by Ansible tasks
|-- artifacts/                                    Artifacts (intermediate/end images)
|   |-- 0.1.0/                                    Ordered by version and template
|   |   |-- aws/                                  Each template might contain multiple artifacts
|   |   |   `-- centos72.ova                      in different formats
|   |   .                                         Directory structure is in VC, but not the artifacts [!VC]
|   |   `-- vmware/
|   |       |-- centos72.box
|   |       |-- centos72.ova
|   |       |-- ...
|   |       `-- disk.vmdk
|   `-- CentOS-7.2.1511.json                      Vagrant boxes catalog metadata
|-- http/                                         HTTP server directory for Packer.io and metal PXE
|   |-- includes/
|   |   |-- header.php
|   |   |-- metal/
|   |   |   |-- disk.php
|   |   |   |-- network.php
|   |   |   |-- post.php
|   |   |   `-- rootpw.php
|   |   `-- packer/
|   |       |-- disk.php
|   |       |-- network.php
|   |       |-- post.php
|   |       `-- rootpw.php
|   |-- ks.php                                    Kickstart entry point
|   `-- machines/                                 Per machine definition (used mostly for bare metal)
|       `-- packer.php                            Definitions for machines bootstrap by Packer.io
|-- isos/                                         Locally downloaded isos
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
|   |-- base.json                                 Base (used by other templates)
|   |-- aws.json                                  AWS
|   |-- virtualbox.json                           VirtualBox
|   |-- vmware.json                               VMware
|   |-- ...
|   `-- CentOS-7-x86_64-Minimal-1511.json         CentOS 7 variables
|-- test/                                         Vagrant tests
|   `-- Vagrantfile
`-- .gitignore                                    Files to ignore in VC
```

# TODO

- User vagrant? Different users might be needed for each artifact
- Use EPM for all repositories
- Document generation of vagrant and packer keys
- Bigger disk size and allow to grow?
- Move vm_name out of OS templates?
- guest_os_type variables for vbox and VMware should go in OS template
