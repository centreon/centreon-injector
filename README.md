# centreon-injector
Inject centreon objects directly in database

## Prerequisites

* git
* php 7.2
* php-yaml module
* composer
* docker (optional)

> :warning: **If you are not using docker**, you need to clone centreon-plugins repository on your virtual machine :
> ```
> git clone https://github.com/centreon/centreon-plugins.git
> cp -R centreon-plugins/* /usr/lib/centreon/plugins/
> chmod +x /usr/lib/centreon/plugins/centreon_plugins.pl
> ```

## Dependencies installation

Install PHP dependencies :
```
composer install
```

## Configuration

Edit file `data.yaml` to configure objects to inject

> :warning: **If you are not using docker**, you need to configure connection in `.env` file :
> ```
> DATABASE_URL="mysql://<user>:<password>@<ip_address>:<port>/<database_name>?serverVersion=5.7"
> ```

## Usage

### Basic usage

```shell
./bin/console centreon:inject-data
```

### Available options

```shell
./bin/console centreon:inject-data [options]

Options:
      --docker                                 Start docker container instead of configured database connection
  -i, --docker-image[=DOCKER-IMAGE]            Docker image to use [default: "registry.centreon.com/des-bam-master:centos7"]
      --container-id[=CONTAINER-ID]            Existing container id to use
  -c, --configurationFile[=CONFIGURATIONFILE]  Configuration file path [default: "data.yaml"]
  -p, --purge                                  Purge data
  -h, --help                                   Display help for the given command. When no command is given display help for the list command
  -q, --quiet                                  Do not output any message
  -V, --version                                Display this application version
      --ansi                                   Force ANSI output
      --no-ansi                                Disable ANSI output
  -n, --no-interaction                         Do not ask any interactive question
  -e, --env=ENV                                The Environment name. [default: "dev"]
      --no-debug                               Switches off debug mode.
  -v|vv|vvv, --verbose                         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```
