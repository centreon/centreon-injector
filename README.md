# centreon-injector
Inject centreon objects directly in database

## Prerequisites

* composer
* docker (optional)

## Dependencies installation

Install PHP dependencies :
```
composer install
```

## Configuration

Edit file `data.yaml` to configure objects to inject

## Data injection

```
./bin/console centreon:inject-data
```
