<?php

namespace App\Domain;

Enum InjectionPriority: int
{
    case Timeperiod = 1000;
    case Command = 950;
    case Contact = 900;
    case Host = 850;
    case Service = 800;
    case Metaservice = 750;
    case Hostgroup = 700;
    case Servicegroup = 650;
    case HostCategory = 600;
    case ServiceCategory = 550;
    case Ba = 500;
    case Kpi = 450;
    case HostDiscoJob = 400;
    case AclMenu = 350;
    case AclResource = 300;
    case AclGroup = 250;
    case User = 200;
}
