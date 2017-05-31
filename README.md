# Introduction

Gold Standard Images is a pillar of DevOps best practices. Images are repeatable, self-documented, and portable across multiple platforms and ensure production/staging/development parity. Other benefits include:
- Fast deployment, benefiting not only production, but development as well.
- Early test-ability.
- End-to-end control.
- Known and predictable state.
- Policy enforcement.

The system proposed here relies on [Packer.io](https://www.packer.io), [Ansible](https://www.ansible.com) and virtualization technologies ([Vagrant](https://www.vagrantup.com), [VirtualBox](https://www.virtualbox.org), [KVM](http://www.linux-kvm.org), [QEMU](http://wiki.qemu.org) and [Docker](https://www.docker.com)) to build minimal images in a repeatable and portable manner. In addition to virtual machines, bare metal is also supported. This is accomplished by providing kickstarts and Ansible playbooks that are similar between bare metal and VMs.

The images produced are minimal by design, containing only the minimum number of packages and configuration to make them workable, secure and general enough to be reused in multiple projects. Images are created from scratch based on ISO images and don't depend on previously uploaded images to the several cloud providers.

The system depends on four inputs, which are well known and maintained:
- OS ISO image.
- OS package repositories.
- Kickstart.
- Ansible playbooks.

# Supported images

## OS

- CentOS 7.2.1511
- CentOS 7.3.1611
- RedHat 7.2

Note that OS name and versions are named after the values reported by `ansible_distribution` and `ansible_distribution_version`.

## Providers

This is the list of supported providers by build name (Packer's build type in parenthesis):
- `aws`: [Amazon Web Services](#amazon-web-services) (`virtualbox-iso`)
- `azure`: [Microsoft Azure](#microsoft-azure) (`qemu`)
- `docker`: [Docker](#docker) (`qemu`)
- `gcp`: [Google Cloud Platform](#google-cloud-platform) (`qemu`)
- `openstack`: [OpenStack](#openstack) (`qemu`)
- `virtualbox`: [Virtualbox](#virtualbox) (`virtualbox-iso`)
- `vmware`: [VMware](#vmware) (`vmware-iso`)

# Building artifacts

First select the VM to produce, ie:
``` sh
vm_name=CentOS-7.2.1511      # or
vm_name=RedHat-7.2
```
Then, run Packer in parallel to generate the all artifacts. Note that due to an incompatibility between Virtualbox and KVM running concurrently, builds need to be split:
``` sh
packer build -var-file=templates/site.json -var-file=templates/os/${vm_name}.json $opts --only=virtualbox,aws templates/main.json
packer build -var-file=templates/site.json -var-file=templates/os/${vm_name}.json $opts --except=virtualbox,aws templates/main.json
```

Variables used by Packer templates are set in `templates/site.json` and the the OS specific templates found in directory `templates/os`. They can also be overriden in the command line using the `-var` option. A complete list of variables can be found at the top of `templates/main.json`. For example:
``` sh
-var version=0.0.0
```

The following options might also be useful:
``` sh
opts="-var headless=false"        # helps debugging kickstarts
opts="-force"                     # forces overwriting of artifacts
```

SHA256 checksums generated for all artifacts can be found in the artifacts directory for each provider.

Note that, although Packer allows automated upload of images to cloud providers, this is not activated at the moment. See below for instructions on uploading/importing to the different providers.

## Providers

In this section has specific instructions for the supported providers.

Note that in the upload/import subsections, an effort has been made to simplify the commands used by abstracting some of the variables used in all providers, ie `build`, `artdir` and `artifact`. In the future this will evolve into a shell utility to aid building/uploading/importing images.

### Amazon Web Services

Instructions to install and configure [AWS CLI](https://aws.amazon.com/cli/) can be found [here]((http://docs.aws.amazon.com/cli/latest/userguide/cli-chap-getting-set-up.html)). Some Linux distributions have packages available.

#### Upload

The following instructions assume AWS CLI installed and configured to access your AWS account. In addition a AWS S3 bucket for temporary upload of the image before final import into an AWS image must be already available.

``` sh
build=aws
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}

aws_s3_bucket=myawsbucket

cp $artdir/$artifact.ova s3://${aws_s3_bucket}/
aws ec2 import-image --disk-container "Format=ova,UserBucket={S3Bucket=${aws_s3_bucket},S3Key=$artifact.ova}"
aws ec2 describe-import-image-tasks
```

### Docker

Instructions to install [Docker](https://www.docker.com) can be found [here](https://docs.docker.com/engine/installation). The builder requires `virt-tar-out` to convert the artifact into the tar file (see [build dependencies](#) for details).

#### Import

``` sh
build=docker
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}

docker import $artdir/$artifact.tar.gz $artifact
```

### Google Compute Platform

Install and configure [CLOUD SDK](https://cloud.google.com/sdk) by following the instructions in that link.

#### Upload

The following instructions assume CLOUD SDK is installed and configured to access your GCP account. In particular, the `gsutil` and `gcloud` tools need to be available. In addition a GCP bucket for upload of the image before importing must already be available.

``` sh
build=gcp
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}

gcp_bucket=mygcpbucket
image=$(echo $artifact | tr [_.] - | tr [A-Z] [a-z])

gsutil cp $artdir/$artifact.tar.gz gs://${gcp_bucket}
gcloud compute images create $image --source-uri gs://${gcp_bucket}/$artifact.tar.gz
```

### Microsoft Azure

Follow instructions to install and configure [Azure CLI](https://docs.microsoft.com/en-us/azure/xplat-cli-install)).

#### Upload

The following instructions assume Azure CLI is install and configured to access your Azure account. In addition a resource group must already be available.

``` sh
build=azure
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}

resource_group=myresourcegroup
storage_account=mystorageaccount
container=images-container

azure config mode arm
azure group create -l northeurope $resource_group
azure storage account create -g $resource_group -l northeurope --kind Storage --sku-name RAGRS $storage_account
key=$(azure storage account keys list $storage_account -g $resource_group --json | jq -r '.[] | select(.keyName == "key1") | .value')
azure storage container create -a $storage_account -k $key $container
azure storage blob upload -t page -a $storage_account -k $key --container $container -f $artdir/$artifact.vhd
```

### OpenStack

To manage an OpenStack cloud from command line requires the [OpenStack command-line](http://docs.openstack.org/user-guide/common/cli-install-openstack-command-line-clients.html) client. Instructions to install and use the client can be found in its webpage. Briefly, being a python tool it is best to install it in a virtualenv:
``` sh
virtualenv ~/python-openstack
. ~/python-openstack/bin/activate
pip install python-openstackclient
```

We also need to configure our system to access the OpenStack cloud. The easiest route is to set environment variables using the OpenStack RC file. Download this file from your cloud at `Access & Security > API Access > Download OpenStack RC file` and save locally. Source it before issuing OpenStack client commands and enter your password:
``` sh
. openstack.rc
```

#### Upload

``` sh
build=openstack
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}

. ~/python-openstack/bin/activate
openstack image create --disk-format qcow2 --file $artdir/$artifact.qcow2 --tag packer --protected --public $artifact
```

### VirtualBox

Virtualbox images are create to be used as Vagrant boxes. A catalog metadata file can be used for each OS to manage Vagrant images. These files are located in `artifacts/`. An example can be found in `artifacts/CentOS-7.2.1511.json.example`. Referencing a catalog in a `Vagrantfile` will automatically import the last image or prompt for an upgrade if a newer version is found based on the data in the catalog. For example:

``` ruby
# Vagrantfile

Vagrant.configure(2) do |config|
  config.vm.box = "namespace/CentOS-7.2.1511"
  config.vm.box_url = 'file:///path/to/artifacts/CentOS-7.2.1511.json'
end
```

### VMware

To build VMware images VMware Player or VMware Workstation is needed. See [build dependencies](#vmware-iso) for details. Additionally, the resulting artifacts gets converted to OVA format with `ovftool` (see [build dependencies](#ovftool) for details) as part of the build.

#### Upload

To upload the artifact, login to your VMware account and manually upload the OVA image at `$artdir/$artifact.ova`.

``` sh
build=vmware
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}
```

# Components

The build of an artifact for use in a cloud environment start with a Packer's template, which in turn will first use a kickstart to bootstrap the machine and then provision if with Ansible playbooks and shell scripts. The end result will produce an artifact that can be deployed to the target cloud environment.

## Packer.io templates

A standard Packer's JSON template for parallel generation of artifacts for different cloud providers is located in the `templates/main.json` alongside with a site specific template in `templates/site.json` and OS specific templates in directory `templates/os` (ie `CentOS-7.2.1511.json`).

The main template declares a set of variables (`user variables` as per Packer). These variables are unset in the main template and instead should be set in the appropiate template (see below). Note that failure to set all variables declared with `null` in the main template will prevent the build process. Variables can also be overriden in command line (see above). Variables are divided into two types:
- Site specific variables (ie `namespace`, `vm_name`, `version` and `ks_server`). These should be set in `templates/site.json`.
- OS specific variables (ie `os_name`, `os_version`, `iso` and `iso_checksum`, `iso_server` and `repo_server`). These should be set in the OS specific template under `templates/os`. An example template can be found in `templates/os/CentOS-7.2.1511.json.example`.

This setup provides flexibility in the image building process and allows site and os selection.

The main templates will bootstrap artifacts for multiple providers, usually providing an additional VirtualBox artifact for development/testing purposes.

### Build dependencies

## virtualbox-iso

TODO

## qemu

TODO

## vmware-iso

TODO

### ovftool

TODO

### virt-tar-out

TODO

## Kickstarts

Kickstart files are located in the `http/` directory. The main kickstart file is `ks.php`, and requires a server running php. On a local computer the easiest is to install apache and symlink this directory to `~/public_html`.

### Packer variables

Packer site variables variables defined in `templates/site.json` and OS variables defined in `templates/os` are available in the kickstart in php objects `packer_site` and `packer_os`. Note that variables defined in the command line while running packer are not reflected here. Currently only `packer_os['os_version']` is used (see below).

### Build types

The kickstart system is divided in multiple files that get included depending on the variables defined the build type and build files for the specified server (found in `builds/`). All Packer templates will use definitions found in `builds/virtual` while bare metal servers will look for definition files in `build/metal`. Different kickstart sections get included for metal or virtual builds appropriately. URLs to target different servers are in the form of `http://server.domain:port/ks.php?build=buildtype/build`, where `buildtype` is `virtual` or `metal` and `build` is a specific build or host (with default values if `build` is not found). If buildtype is not defined, `virtual` is used.

### OS

Multiple OS (and OS version) are supported by passing `os=name-version` to the server, ie `http://server.domain:port/ks.php?os=CentOS-7.2.1511`. Currently this is only used to pass the OS version to the repositories in `packer_os['os_version']`. All Packer templates pass this information.

The URL for the repository is picked up from the packer template variables (see above).

### First boot options

Builders using a kickstart pass the following boot options:
- `selinux=0` will disable selinux.

The customized kickstart also understands the following options:
- `EJECT` will eject the first cd/dvd drive. This is mainly use in bare metal outside of Packer.

## Ansible playbooks

Each builder will provision the image by running Ansbile playbook `ansible/site.yml`, which in turns runs base tasks from `ansible/base.yml` (common to all builders), and then builder specific tasks based on the build name, ie `ansible/aws.yml`.

Packer site and OS specific variables are available to Ansible tasks. Site variables (see `templates/site.json`) are passed as Ansible command line variables and prefixed with `packer_` (ie `packer_namespace`). These variables are also reflected in Ansible if overrided as Packer's command line options. OS specific variables in `templates/os` are imported into object `packer_os` at the start of the main playbook (ie `packer_os.repo_server`) and are not reflected in Ansible if overriden as Packer's command line options.

The structure of these playbooks might not conform to best practices due to the specificity of this projects. In particular, tasks are used directly instead of roles. Tasks (as well as templates and files) are shared among all playbooks to better maintain consistency among generated artifacts. Note that idempotency is not key when running Ansible in this project because a playbook is only run once.

## Cleanup shell scripts

Several shell scripts located in `scripts/` are run by each template to clean up the images:
- `cleanup.up` remove caches and sessions.
- `zero.sh` compresses the image.

## Directory Structure

Note: to simplify the description, only `CentOS` is listed here where multiple OS are possible, and only `aws` is listed where multiple builder/providers are possible.


```
|-- ansible/                                      Ansible playbooks
|   |-- site.yml                                  Main playbook for all Packer builds
|   |-- base.yml                                  Base tasks common to all Packer builds
|   |-- aws.yml                                   Specific playbook for each Packer build
|   |-- group_vars/
|   |   `-- all.yml                               Variables for all playbooks
|   |-- tasks/                                    Tasks used in playbooks
|   |-- files/                                    Files used by Ansible tasks
|   `-- templates/                                Templates used by Ansible tasks
|-- artifacts/                                    Artifacts (intermediate/end images)
|   |-- 0.0.0/                                    Ordered by version
|   |   `-- CentOS-7.2.1511/                        and OS
|   |       `-- aws/                                and provider
|   |           |-- SHA256SUM                       SHA256 checksum for all artifacts
|   |           |-- CentOS-7.2.1511-0.0.0_aws.ova   Template specific artifact
|   |           |-- CentOS-7.2.1511-0.0.0_aws.box   Vagrant box for debugging purposes
|   |           `-- ...                             Other files might be generated. Artifacts are not kept in VC
|   `-- CentOS-7.2.1511.json.example              Exampe for vagrant boxes catalog metadata (one per OS)
|-- http/                                         HTTP server directory for Packer and metal PXE
|   |-- ks.php                                    Kickstart entry point
|   |-- includes/                                 Kickstart include files
|   |   |-- header.php                            Parse incoming URL and set settings
|   |   |-- repo.php                              Repositoy URLs for OSs
|   |   |-- metal/                                Metal specific Kickstart settings
|   |   |   |-- disk.php
|   |   |   |-- network.php
|   |   |   |-- post.php
|   |   |   `-- rootpw.php
|   |   |-- virtual/                              VMs specific Kickstart settings
|   |   |   |-- disk.php
|   |   |   |-- network.php
|   |   |   |-- post.php
|   |   |   `-- rootpw.php
|   `-- builds/                                   Per build type definitions
|       |-- defaults.php                          Common defaults for all builds
|       |-- virtual/
|       |   |-- defaults.php                      Default options for virtual builds
|       |   `-- aws.php                           Options for specific builds
|       |-- metal/
|       |   |-- defaults.php                      Default options for metal hosts
|       |   `-- {host1,...}.php                   Options for specific hosts
|       `-- packer/
|           |-- vars.php                          Read packer variables
|           |-- site.json ->                      Link to packer's templates/site.json
|           `-- templates/os/ ->                  Link to packer's templates/os
|-- keys/                                         SSH keys [!VC]
|   |-- packer
|   |-- packer.pub
|   |-- vagrant
|   `-- vagrant.pub
|-- README.md                                     Yeah, readme please!
|-- scripts/                                      Provisioning helper scripts
|   |-- cleanup.sh                                Cleanup boxes
|   `-- zero.sh                                   Compress boxes
|-- templates/                                    Packer templates
|   |-- main.json                                 Parallel template with all builds
|   |-- site.json                                 Site specific variables
|   `-- os/                                       OS specific variables
|       `-- CentOS-7.2.1511.json.example          Example of OS specific template
|-- test/                                         Vagrant tests
|   `-- Vagrantfile
`-- .gitignore                                    Files to ignore in VC
```

# TODO

- User vagrant? Different users might be needed for each artifact
- Document generation of vagrant and packer keys
- Bigger disk size and allow to grow?
- guest_os_type variables for vbox and VMware should go in OS template
- Move vm_name out of OS templates?
- Add other options, ie locale, root pass, ...
- Move files with options to example files
- Automate vagrant boxes catalog metadata files in artifacts
