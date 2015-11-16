<?php

namespace ZfSimpleMigrations\Library;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterAwareInterface;
use Zend\Db\Adapter\Driver\Pdo\Pdo;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\Metadata\Metadata;
use Zend\Db\Sql\Ddl;
use Zend\Db\Sql\Sql;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZfSimpleMigrations\Library\OutputWriter;
use ZfSimpleMigrations\Model\MigrationVersion;
use ZfSimpleMigrations\Model\MigrationVersionTable;

/**
 * Main migration logic
 */
class Migration implements ServiceLocatorAwareInterface
{
    protected $migrationsDir;
    protected $migrationsNamespace;
    protected $adapter;
    protected $source;
    /**
     * @var \Zend\Db\Adapter\Driver\ConnectionInterface
     */
    protected $connection;
    protected $metadata;
    protected $migrationVersionTable;
    protected $outputWriter;

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * @return \ZfSimpleMigrations\Library\OutputWriter
     */
    public function getOutputWriter()
    {
        return $this->outputWriter;
    }

    /**
     * @param \Zend\Db\Adapter\Adapter $adapter
     * @param array $config
     * @param \ZfSimpleMigrations\Model\MigrationVersionTable $migrationVersionTable
     * @param OutputWriter $writer
     * @throws MigrationException
     */
    public function __construct(Adapter $adapter, array $config, MigrationVersionTable $migrationVersionTable, OutputWriter $writer = null)
    {
        $this->adapter = $adapter;
        $this->metadata = new Metadata($this->adapter);
        $this->connection = $this->adapter->getDriver()->getConnection();
        $this->migrationsDir = is_array($config['dir']) ? $config['dir'] : ['default' => $config['dir']];
        $this->migrationsNamespace = $config['namespace'];
        $this->migrationVersionTable = $migrationVersionTable;
        $this->outputWriter = is_null($writer) ? new OutputWriter() : $writer;

        if (is_null($this->migrationsDir))
            throw new MigrationException('Migrations directory not set!');

        if (is_null($this->migrationsNamespace))
            throw new MigrationException('Unknown namespaces!');


        foreach ($this->migrationsDir as $source) {
            if (!is_dir($source)) {
                if (!mkdir($source, 0775)) {
                    throw new MigrationException(sprintf('Failed to create migrations directory %s', $source));
                }
            }
        }
    }

    /**
     * Create migrations table of not exists
     */
    public function checkCreateMigrationTable()
    {
        $table = new Ddl\CreateTable(MigrationVersion::TABLE_NAME);
        $table->addColumn(new Ddl\Column\Integer('id', false, null, ['autoincrement' => true]));
        $table->addColumn(new Ddl\Column\BigInteger('version'));
        $table->addColumn(new Ddl\Column\Varchar('source', 64));
        $table->addConstraint(new Ddl\Constraint\PrimaryKey('id'));
        $table->addConstraint(new Ddl\Constraint\UniqueKey('version'));

        $sql = new Sql($this->adapter);

        try {
            $this->adapter->query($sql->getSqlStringForSqlObject($table), Adapter::QUERY_MODE_EXECUTE);
        } catch (\Exception $e) {
            // currently there are no db-independent way to check if table exists
            // so we assume that table exists when we catch exception
        }
    }

    /**
     * @return int
     */
    public function getCurrentVersion()
    {
        return $this->migrationVersionTable->getCurrentVersion();
    }

    /**
     * @return int
     */
    public function getCurrentSourceVersions()
    {
        return $this->migrationVersionTable->getCurrentSourceVersions();
    }

    /**
     * @param int $version target migration version, if not set all not applied available migrations will be applied
     * @param bool $force force apply migration
     * @param bool $down rollback migration
     * @param bool $fake
     * @param string $source
     * @throws MigrationException
     */
    public function migrate($version = null, $force = false, $down = false, $fake = false, $source = null)
    {
        $migrations = $this->getMigrationClasses($force, $source);

        if (!is_null($version) && !$this->hasMigrationVersions($migrations, $version)) {
            throw new MigrationException(sprintf('Migration version %s is not found!', $version));
        }

        $currentMigrationVersion = $this->migrationVersionTable->getCurrentVersion();
        $currentMigrationVersions = $this->migrationVersionTable->getCurrentSourceVersions();

        if (!is_null($version) && $version == $currentMigrationVersion && !$force) {
            throw new MigrationException(sprintf('Migration version %s is current version!', $version));
        }

        if ($version && $force) {
            foreach ($migrations as $migration) {
                if ($migration['version'] == $version) {
                    // if existing migration is forced to apply - delete its information from migrated
                    // to avoid duplicate key error
                    if (!$down) $this->migrationVersionTable->delete($migration['version']);
                    $this->applyMigration($migration, $down, $fake);
                    break;
                }
            }
            // target migration version not set or target version is greater than last applied migration -> apply migrations
        } elseif (is_null($version) || (!is_null($version) && $version > $currentMigrationVersion)) {
            foreach ($migrations as $migration) {
                if (!isset($currentMigrationVersions[$migration['source']]) || ($migration['version'] > $currentMigrationVersions[$migration['source']])) {
                    if (is_null($version) || (!is_null($version) && $version >= $migration['version'])) {
                        $this->applyMigration($migration, false, $fake);
                    }
                }
            }
            // target migration version is set -> rollback migration
        } elseif (!is_null($version) && $version < $currentMigrationVersion) {
            $migrationsByDesc = $this->sortMigrationsByVersionDesc($migrations);
            foreach ($migrationsByDesc as $migration) {
                if ($migration['version'] > $version && $migration['version'] <= $currentMigrationVersions[$migration['source']]) {
                    $this->applyMigration($migration, true, $fake);
                }
            }
        }
    }

    /**
     * @param \ArrayIterator $migrations
     * @return \ArrayIterator
     */
    public function sortMigrationsByVersionDesc(\ArrayIterator $migrations)
    {
        $sortedMigrations = clone $migrations;

        $sortedMigrations->uasort(function ($a, $b) {
            if ($a['version'] == $b['version']) {
                return 0;
            }

            return ($a['version'] > $b['version']) ? -1 : 1;
        });

        return $sortedMigrations;
    }

    /**
     * Check migrations classes existence
     *
     * @param \ArrayIterator $migrations
     * @param int $version
     * @return bool
     */
    public function hasMigrationVersions(\ArrayIterator $migrations, $version)
    {
        foreach ($migrations as $migration) {
            if ($migration['version'] == $version) return true;
        }

        return false;
    }

    /**
     * @param \ArrayIterator $migrations
     * @return int
     */
    public function getMaxMigrationVersion(\ArrayIterator $migrations)
    {
        $versions = [];
        foreach ($migrations as $migration) {
            $versions[] = $migration['version'];
        }

        sort($versions, SORT_NUMERIC);
        $versions = array_reverse($versions);

        return count($versions) > 0 ? $versions[0] : 0;
    }

    /**
     * @param \ArrayIterator $migrations
     *
     * @return array
     */
    public function getMaxMigrationSourceVersions(\ArrayIterator $migrations)
    {
        $data = [];

        foreach ($migrations as $migration) {
            if(!isset($data[$migration['source']])) {
                $data[$migration['source']] = $migration['version'];
            }

            if($migration['version'] > $data[$migration['source']]) {
                $data[$migration['source']] = $migration['version'];
            }
        }

        return $data;
    }

    /**
     * @param bool $all
     * @param string $source
     * @return \ArrayIterator
     */
    public function getMigrationClasses($all = false, $source = null)
    {
        $classes = new \ArrayIterator();

        foreach ($this->migrationsDir as $sourceName => $sourceDir) {
            if(!is_null($source) && $sourceName != $source) {
                continue;
            }

            $iterator = new \GlobIterator(sprintf('%s/Version*.php', $sourceDir), \FilesystemIterator::KEY_AS_FILENAME);
            foreach ($iterator as $item) {
                /** @var $item \SplFileInfo */
                if (preg_match('/(Version(\d+))\.php/', $item->getFilename(), $matches)) {
                    $applied = $this->migrationVersionTable->applied($matches[2]);
                    if ($all || !$applied) {
                        $className = $this->migrationsNamespace . '\\' . $matches[1];

                        if (!class_exists($className))
                            /** @noinspection PhpIncludeInspection */
                            require_once $sourceDir . '/' . $item->getFilename();

                        if (class_exists($className)) {
                            $reflectionClass = new \ReflectionClass($className);
                            $reflectionDescription = new \ReflectionProperty($className, 'description');

                            if ($reflectionClass->implementsInterface('ZfSimpleMigrations\Library\MigrationInterface')) {
                                $classes->append([
                                                     'version' => $matches[2],
                                                     'class' => $className,
                                                     'description' => $reflectionDescription->getValue(),
                                                     'applied' => $applied,
                                                     'source' => $sourceName
                                                 ]);
                            }
                        }
                    }
                }
            }
        }

        $classes->uasort(function ($a, $b) {
            if ($a['version'] == $b['version']) {
                return 0;
            }

            return ($a['version'] < $b['version']) ? -1 : 1;
        });

        return $classes;
    }

    protected function applyMigration(array $migration, $down = false, $fake = false)
    {
        $this->connection->beginTransaction();

        try {
            /** @var $migrationObject AbstractMigration */
            $migrationObject = new $migration['class']($this->metadata, $this->outputWriter);

            if ($migrationObject instanceof ServiceLocatorAwareInterface) {
                if (is_null($this->serviceLocator)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Migration class %s requires a ServiceLocator, but there is no instance available.',
                            get_class($migrationObject)
                        )
                    );
                }

                $migrationObject->setServiceLocator($this->serviceLocator);
            }

            if ($migrationObject instanceof AdapterAwareInterface) {
                if (is_null($this->adapter)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Migration class %s requires an Adapter, but there is no instance available.',
                            get_class($migrationObject)
                        )
                    );
                }

                $migrationObject->setDbAdapter($this->adapter);
            }

            $this->outputWriter->writeLine(sprintf("%sExecute migration class %s %s",
                $fake ? '[FAKE] ' : '', $migration['class'], $down ? 'down' : 'up'));

            if (!$fake) {
                $sqlList = $down ? $migrationObject->getDownSql() : $migrationObject->getUpSql();
                foreach ($sqlList as $sql) {
                    $this->outputWriter->writeLine("Execute query:\n\n" . $sql);
                    $this->connection->execute($sql);
                }
            }

            if ($down) {
                $this->migrationVersionTable->delete($migration['version']);
            } else {
                $this->migrationVersionTable->save($migration['version'], $migration['source']);
            }
            $this->connection->commit();
        } catch (InvalidQueryException $e) {
            $this->connection->rollback();
            $previousMessage = $e->getPrevious() ? $e->getPrevious()->getMessage() : null;
            $msg = sprintf('%s: "%s"; File: %s; Line #%d', $e->getMessage(), $previousMessage, $e->getFile(), $e->getLine());
            throw new MigrationException($msg, $e->getCode(), $e);
        } catch (\Exception $e) {
            $this->connection->rollback();
            $msg = sprintf('%s; File: %s; Line #%d', $e->getMessage(), $e->getFile(), $e->getLine());
            throw new MigrationException($msg, $e->getCode(), $e);
        }
    }

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;

        return $this;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
}
