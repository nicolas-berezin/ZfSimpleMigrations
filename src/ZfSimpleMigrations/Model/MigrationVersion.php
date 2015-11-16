<?php
namespace ZfSimpleMigrations\Model;


class MigrationVersion
{
    const TABLE_NAME = 'migration_version';

    /**
     * @var int
     */
    protected $id;
    /**
     * @var int
     */
    protected $version;

    /**
     * @var string
     */
    protected $source;

    public function exchangeArray($data)
    {
        foreach (array_keys(get_object_vars($this)) as $property) {
            $this->{$property} = (isset($data[$property])) ? $data[$property] : null;
        }
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param string $source
     */
    public function setSource($source)
    {
        $this->source = $source;
    }
}
