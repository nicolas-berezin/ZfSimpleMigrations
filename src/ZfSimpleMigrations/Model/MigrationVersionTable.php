<?php
namespace ZfSimpleMigrations\Model;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;

class MigrationVersionTable
{
    /**
     * @var \Zend\Db\TableGateway\TableGateway
     */
    protected $tableGateway;

    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    public function save($version, $source)
    {
        $this->tableGateway->insert(['version' => $version, 'source' => $source]);
        return $this->tableGateway->lastInsertValue;
    }

    public function delete($version)
    {
        $this->tableGateway->delete(['version' => $version]);
    }

    public function applied($version)
    {
        $result = $this->tableGateway->select(['version' => $version]);
        return $result->count() > 0;
    }

    public function getCurrentVersion()
    {
        $result = $this->tableGateway->select(function (Select $select) {
            $select->order('version DESC')->limit(1);
        });
        if (!$result->count()) return 0;
        return $result->current()->getVersion();
    }

    public function getCurrentSourceVersions()
    {
        $data = [];
        $result = $this->tableGateway->select(function (Select $select) {
            $select->columns(['version' => new Expression('MAX(version)'), 'source'])
                ->order('version DESC')
                ->group('source');
        });
        if (!$result->count()) return $data;

        foreach ($result as $row) {
            $data[$row->getSource()] = $row->getVersion();
        }

        return $data;
    }


}
