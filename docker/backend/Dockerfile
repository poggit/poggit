FROM ubuntu:bionic
RUN apt-get update && apt-get install -y libpq-dev
RUN groupadd -g 1000 poggit && useradd -r -d /main -p "" -u 1000 -m -g poggit poggit
RUN chown poggit:poggit /main -R
WORKDIR /main
USER poggit
CMD ./main
