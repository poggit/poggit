.PHONY: restart logs
restart: .npm-installed
	curl localhost:21400/restart -X POST
.npm-installed: package.json
	docker-compose restart app
	touch .npm-installed
logs:
	docker-compose logs app | less -r
