<?php
namespace ZfSimpleMigrations\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\Console\Request as ConsoleRequest;
use ZfSimpleMigrations\Library\Migration;
use ZfSimpleMigrations\Library\MigrationException;
use ZfSimpleMigrations\Library\MigrationSkeletonGenerator;
use ZfSimpleMigrations\Library\OutputWriter;

/**
 * Migration commands controller
 */
class MigrateController extends AbstractActionController
{
    /**
     * @var \ZfSimpleMigrations\Library\Migration
     */
    protected $migration;
    /** @var  MigrationSkeletonGenerator */
    protected $skeleton_generator;

    /**
     * @return MigrationSkeletonGenerator
     */
    public function getSkeletonGenerator()
    {
        return $this->skeleton_generator;
    }

    /**
     * @param MigrationSkeletonGenerator $skeleton_generator
     * @return self
     */
    public function setSkeletonGenerator($skeleton_generator)
    {
        $this->skeleton_generator = $skeleton_generator;
        return $this;
    }

    public function onDispatch(MvcEvent $e)
    {
        if (!$this->getRequest() instanceof ConsoleRequest) {
            throw new \RuntimeException('You can only use this action from a console!');
        }

        return parent::onDispatch($e);
    }

    /**
     * Overridden only for PHPDoc return value for IDE code helpers
     *
     * @return ConsoleRequest
     */
    public function getRequest()
    {
        return parent::getRequest();
    }

    /**
     * Migrations initialization
     */
    public function initAction()
    {
        $this->getMigration()->checkCreateMigrationTable();
    }

    /**
     * Get current migration version
     *
     * @return int
     */
    public function versionAction()
    {
        return sprintf("Current version %s\n", $this->getMigration()->getCurrentVersion());
    }

    /**
     * List migrations - not applied by default, all with 'all' flag.
     *
     * @return string
     */
    public function listAction()
    {
        $migrations = $this->getMigration()->getMigrationClasses($this->getRequest()->getParam('all'), $this->getRequest()->getParam('source'));
        $list = [];
        foreach ($migrations as $m) {
            $list[] = ($m['applied'] ? OutputWriter::LIGHTGRAY : OutputWriter::LIGHTGREEN) .
                      sprintf(
                          "%s %s %s - %s", $m['applied'] ? '-' : '+', $m['version'],
                          str_pad('[' . substr($m['source'], 0, 20) . ']', 22, ' '),
                          $m['description']) .
                      OutputWriter::NO_COLOR;
        }
        return (empty($list) ? 'No migrations available.' : implode("\n", $list)) . "\n";
    }

    /**
     * Apply migration
     */
    public function applyAction()
    {
        $migrations = $this->getMigration()->getMigrationClasses();
        $currentMigrationVersions = $this->getMigration()->getCurrentSourceVersions();

        $version = $this->getRequest()->getParam('version');
        $source = $this->getRequest()->getParam('source');
        $force = $this->getRequest()->getParam('force');
        $down = $this->getRequest()->getParam('down');
        $fake = $this->getRequest()->getParam('fake');
        $name = $this->getRequest()->getParam('name');

        if (is_null($version) && $force) {
            return "Can't force migration apply without migration version explicitly set.";
        }
        if (is_null($version) && $fake) {
            return "Can't fake migration apply without migration version explicitly set.";
        }

        $noMigrationsToApply = true;
        $maxMigrationVersionsBySource = $this->getMigration()->getMaxMigrationSourceVersions($migrations);

        foreach ($maxMigrationVersionsBySource as $source => $_version) {
            if (!$force && is_null($version) && isset($currentMigrationVersions[$source]) && $currentMigrationVersions[$source] >= $_version) {
                //$noMigrationsToApply = true;
            } else {
                $noMigrationsToApply = false;
            }
        }

        if(!$force && is_null($version) && $noMigrationsToApply) {
            return OutputWriter::CYAN .  "No migrations to apply.\n" . OutputWriter::NO_COLOR;
        }

        $this->getMigration()->migrate($version, $force, $down, $fake, $source);
        return OutputWriter::CYAN . "Migrations applied!\n" . OutputWriter::NO_COLOR;
    }

    /**
     * Generate new migration skeleton class
     */
    public function generateSkeletonAction()
    {
        $classPath = $this->getSkeletonGenerator()->generate($this->getRequest()->getParam('source'));

        return OutputWriter::LIGHTGREEN . sprintf("Generated skeleton class @ %s\n", realpath($classPath)) . OutputWriter::NO_COLOR;
    }

    /**
     * @return Migration
     */
    public function getMigration()
    {
        return $this->migration;
    }

    /**
     * @param Migration $migration
     * @return self
     */
    public function setMigration(Migration $migration)
    {
        $this->migration = $migration;
        return $this;
    }


}
