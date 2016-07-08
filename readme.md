# TechSmith Relay API

REST-API for UNINETT TechSmith RelaySQL som muliggjør uthenting av informasjon knyttet til tjenesten og dens abonnenter/brukere. 
Snakker med filserver og DB-servere (mongo, MSSQL, MySQL).

MERK: Alle kall som går på presentations baserer seg på data fra DB. Dette vil ikke alltid stemme overens med det som er på disk. 
Eksempelvis fanger ikke relay-harvester opp presentasjoner som er slettet. Endel problemer med lesing/konsistent data etter flytting 
av drift til UNINETT (treg lesetilgang, manglende tilganger, ødelagt Harvester (mangler hits), etc.).

## Scopes

** Public Scope **

- Service info (version, workers, queue)

** Org Scope: **

- /org/ (presentations, users, user, employees, students)

** User Scope **

- /me/ (presentations, info)
- /me/*/deletelist/ (lagt til sommeren 2016 - selvbetjent sletting)

** Superadmin Scope **

- Alt over samt /global/, /dev/

## Avhengigheter

- UNINETT Dataporten GateKeeper
- Alto Router

## Brukes av ##

RelayAdmin (klient)