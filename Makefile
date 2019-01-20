.PHONY: restart logs whLogs
restart: .npm-installed
	curl localhost:21400/restart -X POST
	docker-compose exec wh curl localhost/restart
.npm-installed: \
		frontend/client/package.json frontend/server/package.json frontend/run.sh \
		webhook-handler/package.json webhook-handler/package.json webhook-handler/run.sh
	docker-compose restart frontend
	docker-compose restart wh
	touch .npm-installed
logs:
	docker-compose logs -ft frontend
whLogs:
	docker-compose logs -ft wh
mysql:
	docker-compose exec mysql bash -c "mysql -u \"\$$MYSQL_USER\" -p\"\$$MYSQL_PASSWORD\" \"\$$MYSQL_DATABASE\""
