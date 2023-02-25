# Install through Kubernetes

Pogigt is only designed for running on a single node.
The recommended setup uses [k3s](https://k3s.io).

The container image `ghcr.io/poggit/poggit-apache` is built from Dockerfile directly.

1. Install k3s.
2. `cp deploy.yaml secret-deploy.yaml` and edit the configuration (especially the secrets).
3. Run `k3s kubectl apply -f deploy.yaml`.
4. `kubectl exec -n poggit mysql-0 -i -- bash -c 'mysql -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE <doc/mysql.sql`

# Manual installation

1. Clone this repository, or download a zipball/tarball from [GitHub](https://github.com/poggit/poggit):
    ```bash
    > git clone https://github.com/poggit/poggit.git
    ```
2. Place/extract Poggit somewhere **outside** your web server's document root, but readable by the server.
2. Install packages via composer (*In the Poggit folder*):
    ```bash
    > Composer install
    ```
2. Install [MySQL](https://dev.mysql.com/downloads/installer/), create a user for Poggit, and create a schema for Poggit.
2. Run the MySQL queries at [doc/mysql.sql](doc/mysql.sql) for that schema.
2. Create a directory **in the Poggit repo** called `secret`, and copy [secret-stub.json](stub/secret-stub.json) to `secret/secrets.json`. Edit the file according to instructions in it. Remember to delete `/** */` comments - they make a JSON file invalid.
2. Copy or append [`.htaccess`](stub/.htaccess) to the **document root** of your web server, regardless of where in your web server is Poggit to be accessed at. Edit the file according to instructions in it.
2. Copy `poggit.php` to `index.php` inside the directory in your web server that Poggit will be accessed. Edit it (the second last line) according to instructions in it.
