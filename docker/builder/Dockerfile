FROM ubuntu:bionic
RUN apt-get update && apt-get install -y apt-transport-https ca-certificates curl gnupg-agent software-properties-common
RUN curl -fsSL https://download.docker.com/linux/ubuntu/gpg | apt-key add - && add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu xenial stable" && apt-get update && apt-get install -y docker-ce docker-ce-cli containerd.io
RUN groupadd -g 1000 poggit && useradd -r -d /main -p "" -u 1000 -m -g poggit poggit
RUN chown poggit:poggit /main -R
WORKDIR /main
USER poggit
CMD ./main
