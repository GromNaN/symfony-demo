
Flux des prix des carburants en France
https://www.data.gouv.fr/fr/datasets/prix-des-carburants-en-france-flux-instantane-v2-amelioree/

Implement the right interfaces
https://api-platform.com/docs/core/state-providers/


Stack of providers with responsabilities (security, validation)

Feature: pagination using before/after
Huge volume of data.

State Provider: READ
State Processor: WRITE
"Central Object": security, validation, 

Ne pas coupler sont API à sa base de données : Utiliser des DTO plutôt que des Entity pour décrire l'API.

Je ne vous parle pas des API internes que vous créez uniquement pour 1 interface. Mais plutôt de celles que vous publiez et sur lesquelles vous êtes engagées sur 
le long terme avec une promesse de compatibilité ascendante.


Feature: create class from BSON/JSON document.
=> For Symfony Maker?


API Platform with Laravel, the right way:
Use DTO for you API, they describe your API schema.
delegate storage to Eloquent or MongoDB codecs.

State provider can return a Response (redirect ?)


# Ideas for API Platform

Move the API client test to Symfony
https://github.com/api-platform/core/pull/2608



Data provider that automaps data?
Using Symfony Mapper.

https://github.com/symfony/symfony/pull/51741
https://www.youtube.com/watch?v=IVJjADhU7WM&list=PL3hoUDjLa7eQ4jnGymiYRBmmOBz_skNmM&index=3


Provider/Processor
https://youtu.be/aSZPIiqe3cg?si=O-5M2FtQmRZAG7G3&t=928


webrpc database


A parte: utilisation de registerAttributeForAutoconfiguration (JsonEncode, ApiResource)
même si ce n'est pas des services, on peut utiliser la détection automatique pour les injecter dans un service.


Using automapper.



More advanced patch syntax
https://datatracker.ietf.org/doc/html/rfc6902 JavaScript Object Notation (JSON) Patch
https://github.com/mrcranky/rfc6902-mongodb Helper module for converting JSON Patch documents into MongoDB update calls 


