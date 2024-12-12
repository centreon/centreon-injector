# centreon-injector

Inject centreon objects directly in database

## Prerequisites

* git
* php >=8.0
* php-yaml module
* composer
* docker (optional)

> :warning: **If you are not using docker**, you need to clone
centreon-plugins repository on your virtual machine :
> ```
> git clone https://github.com/centreon/centreon-plugins.git
> cp -R centreon-plugins/src/* /usr/lib/centreon/plugins/
> chmod +x /usr/lib/centreon/plugins/centreon_plugins.pl
> ```
> Note that this step is not necessary to inject data in database.

## Dependencies installation

Install PHP dependencies :

```
composer install
```

## Configuration

If you want to change the data in the following file `data.yaml`, you
need to create a new file named `data.override.yml` with the same structure
with data changed and pass it as an argument.

If you are not using docker and you want to change the DATABASE_URL, you
can create a `.env.local` file with the following content for example :

```dotenv
DATABASE_URL=mysql://<user>:<password>@<ip_address>:<port>/<database_name>
```

This file will be charged automatically by the command.

## Usage

### Basic usage

Without custom configuration :

```shell
./bin/console centreon:inject-data
```

Custom configuration with a `data.override.yml` file to custom data :

```shell
./bin/console centreon:inject-data -c data.override.yml
```

### Use centreon injector with Docker

```shell
docker run -it -p xx:xx -v /var/run/docker.sock:/var/run/docker.sock -v centreon-injector:/src docker.centreon.com/centreon/injector:1.0 composer install && bin/console centreon:inject-data"
```

You can custom your command with a `.env.local` file and a `data.override.yml` file as explained above.

### Available arguments to pass to the command

```shell
./bin/console centreon:inject-data [options]

Options:
      --docker                                 Start docker container instead of configured database connection
  -i, --docker-image[=DOCKER-IMAGE]            Docker image to use [default: "docker.centreon.com/centreon/centreon-web-alma9:develop"]
      --container-id[=CONTAINER-ID]            Existing container id to use
  -c, --configurationFile[=CONFIGURATIONFILE]  Configuration file path [default: "data.yaml"]
  -p, --purge                                  Purge data
      --docker-http-port[=DOCKER-HTTP-PORT]    Docker http port to use [default: "80"]
      --docker-label[=DOCKER-LABEL]            Docker label to set [default: "injector"]
  -h, --help                                   Display help for the given command. When no command is given display help for the list command
  -q, --quiet                                  Do not output any message
  -V, --version                                Display this application version
      --ansi|--no-ansi                         Force (or disable --no-ansi) ANSI output
  -n, --no-interaction                         Do not ask any interactive question
  -e, --env=ENV                                The Environment name. [default: "dev"]
      --no-debug                               Switch off debug mode.
  -v|vv|vvv, --verbose                         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```
