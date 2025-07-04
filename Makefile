reset:
	rm -rf var/cache/*
	bin/console cache:warmup -n
	bin/console doctrine:mongodb:schema:drop -n || true
	bin/console doctrine:mongodb:schema:create -n
	bin/console doctrine:mongodb:fixtures:load -n
