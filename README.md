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
- RedHat 7.2

Note that OS name and versions are named after the value reported by `ansible_distribution` and `ansible_distribution_version`.

## Providers

This is the list of supported providers and their build name (and build type):
- [Amazon Web Services](#amazon-web-services): `aws` (`virtualbox-iso`)
- [Docker](#docker): `docker` (`qemu`)
- [Google Cloud Platform](#google-cloud-platform): `gcloud` (`qemu`)
- [Microsoft Azure](#microsoft-azure): `azure` (`qemu`)
- [OpenStack](#openstack): `openstack` (`qemu`)
- [Virtualbox](#virtualbox): `virtualbox` (`virtualbox-iso`)
- [VMware](#vmware): `vmware` (`vmware-iso`)

# Building artifacts

First select the VM to produce. Supported operating system are:
``` sh
vm_name=CentOS-7.2.1511
vm_name=RedHat-7.2
```

Then select the version to generate:
``` sh
version=0.0.0
```

Other variables used by Packer can be specified at this point using Packer's `-var` syntax. See `template/main.json` for a complete list. For example:
``` sh
-var namespace=my_namespace
```

Other useful options include:
``` sh
opts="-var headless=false"        # helps debugging kickstarts
opts="-force"                     # forces overwriting of artifacts
```

Run Packer in parallel to generate the all artifacts. Due to an incompatibility between Virtualbox and KVM running concurrently, builds need to be split:
``` sh
packer build $opts -var-file=templates/${vm_name}.json -var version=${version} --only=virtualbox,aws templates/main.json
packer build $opts -var-file=templates/${vm_name}.json -var version=${version} --except=virtualbox,aws templates/main.json
```

Note that, although Packer allows automated upload of images to cloud providers, this is not activated at the moment. See below for instructions on uploading/importing to the different providers:

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

aws_s3_bucket=mys3bucket

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

The following instructions assume  CLOUD SDK is installed and configured to access your GCP account. In particular, the `gsutil` and `gcloud` tools need to be available. In addition a GCP bucket for upload of the image before importing must already be available.

``` sh
build=gcloud
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}

gcloud_bucket=mygcpbucket

gsutil cp $artdir/$artifact.tar.gz gs://${gcloud_bucket}
gcloud compute images create $artifact --source-uri gs://${gcloud_bucket}/$artifact.tar.gz
```

### Microsoft Azure

Follow instructions to install and configure [Azure CLI](https://docs.microsoft.com/en-us/azure/xplat-cli-install)).

#### Upload

The following instructions assume Azure CLI is install and configured to access your Azure account. In addition a resource group and storage account must already be available.

``` sh
build=azure
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}

$resource_group=myresourcegroup
$storage_account=mystorageaccount

azure config mode arm
key=$(azure storage account keys list $storage_account -g $resource_group --json | jq -r '.[] | select(.keyName == "key1") | .value')
azure storage blob upload -t page -a storage_account -k $key --container images_container -f $artdir/$artifact.vhd
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

Virtualbox images are create to be used with Vagrant. A metadata file can be updated for each OS to manage the Vagrant images. These files are located in `artifacts/`, ie `artifacts/CentOS-7.2.1511.json`. Referencing this file in a `Vagrantfile` will automatically import the last image or prompt for an upgrade if a newer version is found. For example:

``` json
{
  "name": "mynamespace/CentOS-7.2.1511",
  "description": "Minimal Vagrant box for CentOS 7.2.1511 (64bits)",
  "short_description": "CentOS-7.2.1511",
  "versions": [
    {
      "version": "1.0.0",
      "status": "active",
      "providers": [
        {
          "name": "virtualbox",
          "url": "/path/to/artifacts/CentOS-7.2.1511-v1.0.0.box",
          "checksum_type": "sha256",
          "checksum": "..............................."
        }
      ]
    }, {
      "version": "2.0.0",
      "status": "active",
      "providers": [
        {
          "name": "virtualbox",
          "url": "/path/to/artifacts/CentOS-7.2.1511-v2.0.0.box",
          "checksum_type": "sha256",
          "checksum": "..............................."
        }
      ]
    }
  ]
}

```

``` ruby
# Vagrantfile

Vagrant.configure(2) do |config|
  config.vm.box = "mynamespace/CentOS-7.2.1511"
  config.vm.box_url = 'file://path/to/artifacts/CentOS-7.2.1511.json'
end
```

### VMware

To build VMware images VMware Player or VMware Workstation is needed. See [build dependencies](#vmware-iso) for details.

#### Upload

To upload the artifact we first need to convert it to an OVA format with `ovftool` (see [build dependencies](#ovftool) for details). After conversion, login to your VMware account to manually upload the OVA image.

``` sh
build=vmware
artdir=artifacts/${version}/${vm_name}/${build}
artifact=${vm_name}-${version}_${build}

ovftool --name=$artifact -dm=thin --vCloudTemplate --compress=1 $artdir/$artifact.vmx $artdir/$artifact.ova
```

# Components

The build of an artifact for use in a cloud environment start with a Packer's template, which in turn will first use a kickstart to bootstrap the machine and then provision if with Ansible playbooks and shell scripts. The end result will produce an artifact that can be deployed to the target cloud environment.

## Packer.io templates

A standard Packer's JSON template for parallel generation of artifacts for different cloud providers is located in the `templates/main.json` alongside with OS specific templates (ie `CentOS-7-x86_64-Minimal-1511.json`).

This standard template declares a set of variables (`user variables` as per Packer), which can generally be divided in three types:
- Artifact specific variables (ie `namespace`, `vm_name` and `version`).
- OS specific variables (ie `os_name`, `os_version`, `iso` and `iso_checksum`). These are unset in the templates and instead should be defined for each supported OS in a OS specific template (ie `CentOS-7-x86_64-Minimal-1511.json`). These might not be declared if the template depends on another template's artifacts.
- Specific template variables needed by the builders and post-processors (these have a prefix corresponding to the builder name).

Default values are provider either on the template itself or on the OS specific template. Those variables with a default value of `null` will need to get their value from the OS specific template or the command line. Variables defined in the OS specific template will always override values from the template, and variables defined in the command line will override any other value.

This setup provides flexibility in image building process, like separating building process from OS, and allowing for versioning of artifacts.

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

Kickstart files are located in the `http/` directory. The main kickstart file is `ks.php`, and requires a server running php. On a local computer the easiest is to install apache and symlink this directory to `~/public_html`. The kickstart system is divided in multiple files that get included depending on the variables defined the build type and build files for the specified server (found in `builds/`). All Packer templates will use definitions found in `builds/virtual` while bare metal servers will look for definition files in `build/metal`. Different kickstart sections get included for metal or virtual builds appropriately. URLs to target different servers are in the form of `http://server.domain:port/ks.php?build=buildtype/build`, where `buildtype` is `virtual` or `metal` and `build` is a specific build or host. If buildtype is not defined, `virtual` is used.

### First boot options

Builders using a kickstart pass the following boot options:
- `selinux=0` will disable selinux.

The customized kickstart also understands the following options:
- `EJECT` will eject the first cd/dvd drive. This is mainly use in bare metal outside of Packer.

## Ansible playbooks

Each builder will provision the image by calling an Ansbile playbook of the same name, ie `virtualbox.yml`. These playbooks start by running base tasks from `base.yml` common to all builders, and then running the builder specific tasks.

The structure of these playbooks does not conform to best practices due to the specificity of this projects. In particular, tasks are used directly instead of roles. Tasks (as well as templates and files) are shared among all playbooks to better maintain consistency among generated artifacts.

Note that idempotency is not key when running Ansible in this project because a playbook is only run once.

## Finishing shell scripts

Several shell scripts located in `scripts/` are run by each template to clean up the images:
- `cleanup.up` remove caches and sessions.
- `zero.sh` compresses the image.

## Directory Structure

Note: to simplify the description, only `CentOS` is listed here where multiple OS are possible, and only `aws` is listed where multiple builder/providers are possible.


```
|-- ansible/                                      Ansible playbooks
|   |-- base.yml                                  Base tasks included in all playbooks
|   |-- aws.yml                                   One playbook per builder
|   |-- tasks/                                    Tasks used in playbooks
|   |-- files/                                    Files used by Ansible tasks
|   `-- templates/                                Templates used by Ansible tasks
|-- artifacts/                                    Artifacts (intermediate/end images)
|   |-- 0.0.0/                                    Ordered by version
|   |   `-- CentOS-7.2.1511/                        and OS
|   |       `-- aws/                              Each template generates at least 2 artifacts
|   |           |-- CentOS-7.2.1511-0.0.0_aws.ova   A template specific artifact
|   |           |-- CentOS-7.2.1511-0.0.0_aws.box   A vagrant box for debugging purposes
|   |           `-- ...                             Other files might be generated by Packer. Artifacts are not kept in VC
|   `-- CentOS-7.2.1511.json                      Vagrant boxes catalog metadata (one per OS)
|-- http/                                         HTTP server directory for Packer and metal PXE
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
|   |       `-- CentOS.php
|   |-- builds/                                   Per build type definitions
|   |   |-- defaults.php                          Common defaults for all builds
|   |   |-- virtual
|   |   |   |-- defaults.php                      Default options for virtual builds
|   |   |   `-- aws.php                           Options for specific builds
|   |   `-- metal
|   |   |   |-- defaults.php                      Default options for metal hosts
|   |       `-- {host1,...}.php                   Options for specific hosts
|   ` -- isos/                                    Locally downloaded isos
|        `-- CentOS/                                Ordered by OS and version
|          `-- 7.2.1511/
|            `-- CentOS-7-x86_64-Minimal-1511.iso
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
|   |-- main.json                                 Parallel template
|   `-- CentOS-7.2.1511.json                      OS specific variables
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
- Move ovftool to post-processors in template
- Rename gcloud to gcp
