# TechSmith Relay API

REST-API for UNINETT TechSmith Relay som muliggjør uthenting av informasjon knyttet til tjenesten og dens abonnenter/brukere. Snakker med filserver og DB-server.

## Scopes

** Public Scope **

- Service info (version, workers, queue)

** Org Scope: **

- /org/ (presentations, users, user, employees, students)

** User Scope **

- /me/ (presentations, info)

** Superadmin Scope **

- Alt over samt /global/, /dev/

## Avhengigheter

- UNINETT Connect GateKeeper
- Alto Router