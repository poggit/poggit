.PHONY: restart logs
restart: .npm-installed
	curl localhost:21400/restart -X POST
.npm-installed: frontend/client/package.json frontend/server/package.json
	docker-compose restart frontend
	touch .npm-installed
logs:
	docker-compose logs -ft frontend
mysql:
	docker-compose exec mysql bash -c "mysql -u \"\$$MYSQL_USER\" -p\"\$$MYSQL_PASSWORD\" \"\$$MYSQL_DATABASE\""
