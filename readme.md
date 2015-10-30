# TechSmith RelaySQL API

REST-API for UNINETT TechSmith RelaySQL som muliggjør uthenting av informasjon knyttet til tjenesten og dens abonnenter/brukere. Snakker med filserver og DB-server.

MERK: Alle kall som går på presentations baserer seg på data fra DB. Dette vil ikke alltid stemme overens med det som er på disk. Kan endre dette hvis/når vi får en god løsning på plass for lesing av (XML) metadata fra disk.

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