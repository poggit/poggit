Poggit Stubs
===

This directory consists of files that are useful to developers, but should not be used at all in runtime. You are recommended to delete this directory in production servers.

## Index
### IDE stubs
* `yaml.php`: Used by IDEs that do not have builtin declarations for YAML functions (`yaml_*` functions)

### Installation stubs
* `secret-stub.json`: In order for Poggit to run, a `secret/secrets.json` file is required (path relative to the Poggit repository root). Copy this file to the required path, and edit the file according to the instructions in the JSON file. Remember to delete any comments (`/** */`) in the file.
* `poggit.php`: Place or append this file as `index.php` to the directory that clients would access Poggit through.
* `.htaccess`: Place or append this file to the `.htaccess` at your server document root. Edit the file according to instructions in it.
