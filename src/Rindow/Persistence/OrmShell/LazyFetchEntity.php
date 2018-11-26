<?php
namespace Rindow\Persistence\OrmShell;

use Rindow\Persistence\Orm\Exception;

class LazyFetchEntity
{
    protected $_system = array();

    public function __construct($entityManager,$entityClass, $primaryKey)
    {
        $this->_system['entityManager'] = $entityManager;
        $this->_system['entityClass'] = $entityClass;
        $this->_system['primaryKey'] = $primaryKey;
    }

    public function _getEntity()
    {
        if(isset($this->_system['entity']))
            return $this->_system['entity'];
        $entity = $this->_system['entityManager']
            ->find($this->_system['entityClass'], $this->_system['primaryKey']);
        if($entity==null)
            throw new Exception\EntityNotFoundException('entity not found for "'.$this->_system['entityClass'].'"');
        $this->_system['entity'] = $entity;
        return $this->_system['entity'];
    }

    public function _hasEntity()
    {
        return isset($this->_system['entity']);
    }

    public function __get($name)
    {
        $entity = $this->_getEntity();
        return $entity->$name;
    }

    public function __set($name,$value)
    {
        $entity = $this->_getEntity();
        return $entity->$name = $value;
    }

    public function __call($method,$args)
    {
        $entity = $this->_getEntity();
        return call_user_func_array(array($entity,$method),$args);
    }
}