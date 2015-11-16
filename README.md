# ZfSimpleMigrations

Simple Migrations for Zend Framework 2. Project originally based on [ZendDbMigrations](https://github.com/vadim-knyzev/ZendDbMigrations) but module author did not response for issues and pull-requests so fork became independent project.

## Supported Drivers
The following DB adapter drivers are supported by this module.

  * Pdo_Sqlite
  * Pdo_Mysql
  * Mysqli _only if you configure the driver options with `'buffer_results' => true`_


## Installation

### Using composer

Add following code to your composer.json file

```php
...
"require" : {
    "nicolas-berezin/zf-simple-migrations" : "dev-master",
},
...
"repositories": [
  {
    "type": "package",
    "package": {
      "name": "nicolas-berezin/zf-simple-migrations",
      "version": "dev-master",
      "source": {
        "url": "https://github.com/nicolas-berezin/ZfSimpleMigrations.git",
        "type": "git",
        "reference": "master"
      },
      "autoload": {
        "classmap": [""]
      }
    }
  }
]
```

```bash
php composer.phar update
```
add `ZfSimpleMigrations` to the `modules` array in application.config.php

## Usage

### Available commands

* `migration init` - initialize migration module (create DB table e.t.c)
* `migration version [<source>] [<name>]` - show last applied migration (`source` specifies a migration files path and `name` specifies a configured migration)
* `migration list [<source>] [<name>] [--all]` - list available migrations (`source` specifies a migration files path and `all` includes applied migrations)
* `migration apply [<source>] [<name>] [<version>] [--force] [--down] [--fake]` - apply or rollback migration (`source` specifies a migration files path)
* `migration generate [<source>] [<name>]` - generate migration skeleton class (`source` specifies a migration files path where migration file will be generated)

Migration classes are stored in `/path/to/project/module/Application/migrations/` dir by default.

Generic migration class has name `Version<YmdHis>` and implement `ZfSimpleMigrations\Library\MigrationInterface`.

### Migration class example

``` php
<?php

namespace ZfSimpleMigrations\Migrations;

use ZfSimpleMigrations\Library\AbstractMigration;
use Zend\Db\Metadata\MetadataInterface;

class Version20130403165433 extends AbstractMigration
{
    public static $description = "Migration description";

    public function up(MetadataInterface $schema)
    {
        //$this->addSql(/*Sql instruction*/);
    }

    public function down(MetadataInterface $schema)
    {
        //$this->addSql(/*Sql instruction*/);
    }
}
```

#### Multi-statement sql
While this module supports execution of multiple SQL statements it does not have way to detect if any other statement than the first contained an error. It is *highly* recommended you only provide single SQL statements to `addSql` at a time.
I.e instead of

```
$this->addSql('SELECT NOW(); SELECT NOW(); SELECT NOW();');
```

You should use

```
$this->addSql('SELECT NOW();');
$this->addSql('SELECT NOW();');
$this->addSql('SELECT NOW();');
```

### Accessing ServiceLocator In Migration Class

By implementing the `Zend\ServiceManager\ServiceLocatorAwareInterface` in your migration class you get access to the
ServiceLocator used in the application.

``` php
<?php

namespace ZfSimpleMigrations\Migrations;

use ZfSimpleMigrations\Library\AbstractMigration;
use Zend\Db\Metadata\MetadataInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class Version20130403165433 extends AbstractMigration
                            implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    public static $description = "Migration description";

    public function up(MetadataInterface $schema)
    {
         //$this->getServiceLocator()->get(/*Get service by alias*/);
         //$this->addSql(/*Sql instruction*/);

    }

    public function down(MetadataInterface $schema)
    {
        //$this->getServiceLocator()->get(/*Get service by alias*/);
        //$this->addSql(/*Sql instruction*/);
    }
}
```

### Accessing Zend Db Adapter In Migration Class

By implementing the `Zend\Db\Adapter\AdapterAwareInterface` in your migration class you get access to the
Db Adapter configured for the migration.

```php
<?php

namespace ZfSimpleMigrations\Migrations;

use Zend\Db\Adapter\AdapterAwareInterface;
use Zend\Db\Adapter\AdapterAwareTrait;
use Zend\Db\Sql\Ddl\Column\Integer;
use Zend\Db\Sql\Ddl\Column\Varchar;
use Zend\Db\Sql\Ddl\Constraint\PrimaryKey;
use Zend\Db\Sql\Ddl\CreateTable;
use Zend\Db\Sql\Ddl\DropTable;
use ZfSimpleMigrations\Library\AbstractMigration;
use Zend\Db\Metadata\MetadataInterface;

class Version20150524162247 extends AbstractMigration implements AdapterAwareInterface
{
    use AdapterAwareTrait;

    public static $description = "Migration description";

    public function up(MetadataInterface $schema)
    {
        $table = new CreateTable('my_table');
        $table->addColumn(new Integer('id', false));
        $table->addConstraint(new PrimaryKey('id'));
        $table->addColumn(new Varchar('my_column', 64));
        $this->addSql($table->getSqlString($this->adapter->getPlatform()));
    }

    public function down(MetadataInterface $schema)
    {
        $drop = new DropTable('my_table');
        $this->addSql($drop->getSqlString($this->adapter->getPlatform()));
    }
}
```


## Configuration
  
### User Configuration

The top-level key used to configure this module is `migrations`. 

#### Migration Configurations: Migrations Name

Each key under `migrations` is a migrations configuration, and the value is an array with one or more of
the following keys.

##### Sub-key: `dir`

Array of paths to the directories where migration files are stored.
Each path represents a single migration files source.
Defaults to `./migrations` in the project Application module dir.
Every module with migrations needs to register it's own migration files source in "dir" section.

##### Sub-key: `namespace` 

The class namespace that migration classes will be generated with. Defaults to `ZfSimpleMigrations\Migrations`.

##### Sub-key: `show_log` (optional)

Flag to log output of the migration. Defaults to `true`.

##### Sub-key: `adapter` (optional)

The service alias that will be used to fetch a `Zend\Db\Adapter\Adapter` from the service manager.

#### User configuration example:

```php
'migrations' => [
    'default' => [
        'dir' => [
            'default' => dirname(__FILE__) . '/../../module/Application/migrations',
        ],
        'namespace' => 'ZfSimpleMigrations\Migrations',
        'show_log' => true,
        'adapter' => 'Zend\Db\Adapter\Adapter'
    ]
]
```
