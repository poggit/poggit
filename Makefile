.PHONY: restart logs
restart: .npm-installed
	curl localhost:21400/restart -X POST
.npm-installed: package.json
	docker-compose restart app
	touch .npm-installed
logs:
	docker-compose logs app | less -r
mysql:
	docker-compose exec mysql bash -c "mysql -u \"\$$MYSQL_USER\" -p\"\$$MYSQL_PASSWORD\" \"\$$MYSQL_DATABASE\""
