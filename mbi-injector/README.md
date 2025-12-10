# Centreon MBI injector

## Concept

You need some datas to vizualize your MBI widgets or reports? you are on the good way.

The goal is to fill aggregate tables into MBI datawarehouse following some steps:
- Generate monitoring object configuration into the Central 
- Execute ETL MBI with only import and dimensions steps
- Apply the script to inject BI datas

## Prerequisistes 

### Apply centreon-injector

Follow the documentation to create few resources on the Central https://github.com/centreon/centreon-injector/tree/master

*data.yml example:*

```
poller:
  hostsOnCentral: true # dispatch hosts on central server ?

timeperiod:
  count: 3 # count of injected timeperiods

contact:
  count: 3 # count of injected timeperiods (non admin users)

command:
  count: 3 # count of injected commands
  metrics:
    min: 1 # minimum count of metrics per service
    max: 5 # maximum count of metrics per service

host:
  count: 10 # count of injected hosts

service:
  count: 100 # count of injected services (randomly linked to a host)

metaservice:
  count: 0 # count of injected metaservices

hostgroup:
  count: 5 # count of injected hostgroups
  hosts:
    min: 1 # minimum count of hosts linked to a group
    max: 10 # maximum count of hosts linked to a group

servicegroup:
  count: 6 # count of injected servicegroups
  services:
    min: 10 # minimum count of services linked to a group
    max: 100 # maximum count of services linked to a group

host_category:
  count: 3 # count of injected host categories
  hosts:
    min: 1 # minimum count of hosts linked to a category
    max: 10 # maximum count of hosts linked to a category

service_category:
  count: 3 # count of injected service categories
  hosts:
    min: 10 # minimum count of services linked to a category
    max: 100 # maximum count of services linked to a category

ba:
  count: 10 # count of injected bas

kpi:
  count: 100 # count of injected kpis (randomly linked to existing bas)

host_disco_job:
  count: 0 # count of injected host discovery jobs

acl_resource:
  count: 10
  hosts: 10
  servicegroups: 100

acl_group:
  count: 10
  resources: 3

user:
  administrators: 3
  editors: 10
  users: 10
```

### ETL execution

Before launch mbi injector, you need to execute this command line on MBI server:

```
/usr/share/centreon-bi//bin/centreonBIETL -rIiD
```

### Python dependencies installation

As the script uses python, you need to install some dependencies

``` 
dnf install python3 python3-pip

pip install -r requirements.txt 
```

## Script execution

Connect on your MBI server and execute this command:

```
 python3 fill_missing_date.py --host localhost --port 3306 --user centreonbi --password centreonbi --database centreon_storage --inject --parallel 8 --fake-data --metric-name metric.1 
```

Available Options: 

--host : MBI database (localhost)

--port : Port MBI (3306 by default)

--user : user grant access for MBI database

--password : user password for MBI database

--database: centreon_storage

--inject : inject directly into database otherwise generate SQL dumps

--parrallel : number of workers for multiprocessing (8 by default)

--fake-data: generate fake data otherwise loop on real data on specific period

--tables: Specific tables to process (default: all configured tables)

--metric-name: Specific metric name to process (default: none)

--truncate: Truncate table to overwrite aggregated data

--liveservice-name: Specify timeperiod for aggregated data (default: 24x7)
