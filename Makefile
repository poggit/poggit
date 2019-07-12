CARGO_BUILD_FLAGS =
DOCKER_COMPOSE_RUN_FLAGS =
CI_SASS := $(shell find ci/style -name \*.sass -print)
PLUGINS_SASS := $(shell find plugins/style -name \*.sass -print)
CI_CLIENT := $(shell find ci/client -name \*.ts -print)
PLUGINS_CLIENT := $(shell find plugins/client -name \*.ts -print)

.PHONY: build up .rust-build

up: build .docker-build
	docker-compose up $(DOCKER_COMPOSE_RUN_FLAGS)

build: .rust-build ci/static/style.css plugins/static/client.js plugins/static/style.css plugins/static/client.js .docker-build

.rust-build:
	rustup run nightly cargo build $(CARGO_BUILD_FLAGS)

ci/static/style.css: ci/static materialize-src $(CI_SASS); sass ci/style/main.sass $@
plugins/static/style.css: plugins/static materialize-src $(PLUGINS_SASS); sass plugins/style/main.sass $@

ci/static/client.js: ci/static ci/client/package.json ci/client/tsconfig.json $(CI_CLIENT)
	cd ci/client && npm install && tsc --noEmit && parcel build src/main.ts && cp dist/main.js ../static/client.js
plugins/static/client.js: plugins/static plugins/client/package.json plugins/client/tsconfig.json  $(PLUGINS_CLIENT)
	cd plugins/client && npm install && tsc --noEmit && parcel build src/main.ts && cp dist/main.js ../static/client.js

ci/static:; mkdir $@
plugins/static:; mkdir $@

materialize-src:
	echo Downloading materialize-src
	wget -O materialize.zip https://github.com/Dogfalo/materialize/releases/download/1.0.0/materialize-src-v1.0.0.zip
	unzip materialize.zip
	rm materialize.zip

.build-deps:
	command -v rustup >/dev/null
	command -v sass >/dev/null
	command -v tsc >/dev/null
	command -v docker-compose >/dev/null
	touch $@

.docker-build: docker/backend/Dockerfile docker/builder/Dockerfile docker/default/Dockerfile
	docker-compose build
	touch $@
