
install:
	composer update

reset:
	bin/console doctrine:database:drop --force
	bin/console doctrine:schema:update --force
	bin/console doctrine:fixtures:load --no-interaction
