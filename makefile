.PHONY: check audit pint test stan rector fix

check: audit pint test stan rector

audit:
	composer audit

pint:
	./vendor/bin/pint --test

test:
	php artisan test

stan:
	./vendor/bin/phpstan analyse

rector:
	./vendor/bin/rector process --dry-run

fix:
	./vendor/bin/pint
	./vendor/bin/rector process
