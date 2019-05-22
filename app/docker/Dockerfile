FROM ubuntu:bionic

RUN apt-get update && apt-get install -y --no-install-recommends \
	ca-certificates \
	make \
	software-properties-common \
	vim \
	curl \
	gnupg \
	sudo

RUN curl -sL https://deb.nodesource.com/setup_10.x | DEBIAN_FRONTEND=noninteractive bash -
RUN apt-get update && apt-get install -y --no-install-recommends \
	nodejs \
	openjdk-8-jdk-headless

RUN npm install -g typescript ts-node tsify browserify

RUN curl -s https://dl.google.com/closure-compiler/compiler-latest.tar.gz | \
	tar xz && \
	mv closure-compiler-*.jar /closure-compiler.jar

RUN mkdir /main
RUN mkdir /main/base
WORKDIR /main

EXPOSE 8002

CMD bash /run.sh
