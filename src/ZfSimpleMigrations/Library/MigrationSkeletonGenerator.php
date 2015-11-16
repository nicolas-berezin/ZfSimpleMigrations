<?php

namespace ZfSimpleMigrations\Library;

/**
 * Migration skeleton class generator
 */
class MigrationSkeletonGenerator
{
    protected $migrationsDir;
    protected $migrationNamespace;

    /**
     * @param string $migrationsDir migrations working directory
     * @param string $migrationsNamespace migrations namespace
     * @throws MigrationException
     */
    public function __construct($migrationsDir, $migrationsNamespace)
    {
        $this->migrationsDir = is_array($migrationsDir) ? $migrationsDir : ['default' => $migrationsDir];
        $this->migrationNamespace = $migrationsNamespace;

        foreach ($this->migrationsDir as $source) {
            if (!is_dir($source)) {
                if (!mkdir($source, 0775)) {
                    throw new MigrationException(sprintf('Failed to create migrations directory %s', $source));
                }
            }
        }

        if (!is_writable($source)) {
            throw new MigrationException(sprintf('Migrations directory is not writable %s', $source));
        }
    }

    /**
     * Generate new migration skeleton class
     *
     * @param string $source
     * @return string path to new skeleton class file
     * @throws MigrationException
     */
    public function generate($source = null)
    {
        $className = 'Version' . date('YmdHis', time());

        if(!is_null($source) && isset($this->migrationsDir[$source])) {
            $dir = $this->migrationsDir[$source];
        } elseif(isset($this->migrationsDir['default'])) {
            $dir = $this->migrationsDir['default'];
        } else {
            throw new MigrationException('You do not specify "default" migration data source in migrations config dir section.');
        }

        $classPath = $dir . DIRECTORY_SEPARATOR . $className . '.php';

        if (file_exists($classPath)) {
            throw new MigrationException(sprintf('Migration %s exists!', $className));
        }
        file_put_contents($classPath, $this->getTemplate($className));

        return $classPath;
    }

    /**
     * Get migration skeleton class raw text
     *
     * @param string $className
     * @return string
     */
    protected function getTemplate($className)
    {
        return sprintf('<?php

namespace %s;

use ZfSimpleMigrations\Library\AbstractMigration;
use Zend\Db\Metadata\MetadataInterface;

class %s extends AbstractMigration
{
    public static $description = "Migration description";

    public function up(MetadataInterface $schema)
    {
        //$this->addSql(/*Sql instruction*/);
    }

    public function down(MetadataInterface $schema)
    {
        //throw new \RuntimeException(\'No way to go down!\');
        //$this->addSql(/*Sql instruction*/);
    }
}
', $this->migrationNamespace, $className);
    }
}

?>
