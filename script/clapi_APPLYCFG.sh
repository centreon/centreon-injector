#!/bin/bash

# Récupérer la liste des identifiants des collecteurs
poller_ids=$(centreon -u admin -p 'Centreon!2021' -a POLLERLIST | awk -F';' 'NR>1 {print $1}')

# Appliquer la configuration à chaque collecteur
for id in $poller_ids; do
    centreon -u admin -p 'Centreon!2021' -a APPLYCFG -v $id
done