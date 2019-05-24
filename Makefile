.PHONY: build clean-started up wait db db/stdout wh/stdout app/stdout db/sh wh/sh app/sh

build: .make/build
.make/build: webhook/docker/Dockerfile app/docker/Dockerfile
	docker-compose build
	touch $@

clean-started:
	rm .started/* -f || sudo rm -f .started/*

up: .make/up
.make/up: clean-started docker-compose.yml .make/build
	docker-compose up -d
	touch $@

wait: .make/wait
.make/wait: .started/wh .started/app

.started/db: .make/up
	docker-compose exec db /wait-db.sh

.started/wh: .make/up ; ./wait-file.sh $@
.started/app: .make/up ; ./wait-file.sh $@

db: .started/db
	docker-compose exec db bash -c "mysql -u \"\$$MYSQL_USER\" -p\"\$$MYSQL_PASSWORD\" \"\$$MYSQL_DATABASE\""

db/stdout: ; docker-compose logs -tf db
wh/stdout: ; docker-compose logs -tf wh
app/stdout: ; docker-compose logs -tf app

db/sh: ; docker-compose exec db /bin/bash -i
wh/sh: ; docker-compose exec wh /bin/bash -i
app/sh: ; docker-compose exec app /bin/bash -i

restart: wh/restart app/restart
wh/restart:
	docker-compose exec wh curl -iX POST 127.0.0.1:8001/server-restart
app/restart:
	docker-compose exec app curl -iX POST 127.0.0.1:8002/server-restart

