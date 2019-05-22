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
RUN apt-get update && apt-get install -y --no-install-recommends nodejs

RUN npm install -g typescript ts-node

RUN curl -fsSL https://download.docker.com/linux/ubuntu/gpg | apt-key add -
RUN add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"
RUN apt-get update && apt-get install -y --no-install-recommends docker-ce docker-ce-cli containerd.io

RUN mkdir /main
RUN mkdir /main/base
WORKDIR /main

EXPOSE 8001

CMD bash /run.sh
