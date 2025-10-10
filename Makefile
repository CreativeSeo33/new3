build:
	docker-compose build

up:
	docker-compose up -d

down:
	docker-compose down

logs:
	docker-compose logs -f

migrate:
	docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

fixtures:
	docker-compose exec php php bin/console doctrine:fixtures:load --no-interaction || true

cache-clear:
	docker-compose exec php php bin/console cache:clear

warmup:
	docker-compose exec php php bin/console cache:warmup --env=prod

restart:
	docker-compose restart php nginx

jwt-keys:
	docker-compose exec php sh -c "mkdir -p config/jwt && openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem && chmod 640 config/jwt/private.pem config/jwt/public.pem"




