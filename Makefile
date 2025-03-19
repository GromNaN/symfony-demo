
install:
	composer update

reset-sql:
	bin/console doctrine:database:drop --force
	bin/console doctrine:schema:update --force
	bin/console doctrine:fixtures:load --no-interaction

reset-mongodb:
	bin/console doctrine:mongodb:schema:update
	bin/console doctrine:mongodb:fixtures:load --no-interaction

reset: reset-sql reset-mongodb
