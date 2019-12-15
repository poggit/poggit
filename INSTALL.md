# Installation

**Before installing, please read [_Can I host Poggit myself?_](README.md#can-i-host-it-myself).**

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
