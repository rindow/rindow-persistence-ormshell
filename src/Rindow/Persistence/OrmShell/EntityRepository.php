<?php
namespace Rindow\Persistence\OrmShell;

use Rindow\Persistence\Orm\Exception;

class EntityRepository
{
    const MARK_CREATE = 1;
    const MARK_REMOVE = 2;
    const MARK_UPDATE = 3;

    protected $entities = array();
    protected $mapper;
    protected $entityManager;

    public function __construct($mapper=null,$entityManager=null)
    {
        $this->mapper = $mapper;
        $this->entityManager = $entityManager;
    }

    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
    }

    public function getMapper()
    {
        return $this->mapper;
    }

    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityManager()
    {
        return $this->entityManager;
    }

    public function getRepository($className)
    {
        return $this->entityManager->getRepository($className);
    }

    public function find($id, $lockMode=null, array $properties=null)
    {
        $idx = strval($id);
        if($idx=='(null)')
            throw new Exception\DomainException('Id is invalid.');
            
        if(array_key_exists($idx, $this->entities))
            return $this->entities[$idx]->entity;
        $entity = $this->mapper->find($id,null,$lockMode,$properties);
        if(!$entity)
            return $entity;
        // ** CAUTION **
        // Must do cache before calling "supplimentEntity",
        // because it happen infinity loop when entities have recusive reference.
        $status = new EntityStatus();
        $status->entity = $entity;
        $status->mark = self::MARK_UPDATE;
        $this->entities[$idx] = $status;
        $this->mapper->supplementEntity($this->entityManager,$entity);
        $status->hash = $this->mapper->hash($this->entityManager,$entity);
        return $entity;
    }

    public function findBy(
        $query,
        $params=null,
        $firstPosition=null,
        $maxResult=null,
        $lockMode=null)
    {
        $result = $this->mapper->findBy(
            array($this,'createResultList'),$query,$params,$firstPosition,$maxResult,$lockMode);
        //$this->mapperLoader = $result->getLoader();
        if($result->isMapped())
            $result->addFilter(array($this,'onLoadEntity'));

        return $result;
    }
/*
    public function findAll()
    {
        $result = $this->mapper->findAll();
        //$this->mapperLoader = $result->getLoader();
        if(!$result->isMapped())
            $result->addFilter(array($this,'onLoadEntity'));
        return $result;
    }
*/
    public function onLoadEntity($entity)
    {
        $idx = strval($this->mapper->getId($entity));
        if(array_key_exists($idx, $this->entities))
            return $this->entities[$idx]->entity;
        //if($this->mapperLoader)
        //    $entity = call_user_func($this->mapperLoader,$entity);
        // ** CAUTION **
        // Must do cache before calling "supplimentEntity",
        // because it happen infinity loop when entities have recusive reference.
        $idx = strval($this->mapper->getId($entity));
        $status = new EntityStatus();
        $status->entity = $entity;
        $status->mark = self::MARK_UPDATE;
        $this->entities[$idx] = $status;
        $this->mapper->supplementEntity($this->entityManager,$entity);
        $status->hash = $this->mapper->hash($this->entityManager,$entity);
        return $entity;
    }

    public function persist($entity)
    {
        if($this->contains($entity))
            return;
        $status = new EntityStatus();
        $status->entity = $entity;
        $status->hash = 'unknown';
        $id = $this->mapper->getId($entity);
        if($id===null) {
            $status->mark = self::MARK_CREATE;
            $this->entities[spl_object_hash($entity)] = $status;
        } else {
            $status->mark = self::MARK_UPDATE;
            $this->entities[strval($id)] = $status;
        }
        $this->mapper->subsidiaryPersist($this->entityManager,$status->entity);
    }

    public function remove($entity)
    {
        $id = $this->mapper->getId($entity);
        if($id===null) {
            throw new Exception\DomainException('Primary key must be specified to remove.');
        }
        $idx = strval($id);
        if(array_key_exists($idx, $this->entities)) {
            $status = $this->entities[$idx];
        } else {
            $status = new EntityStatus();
            $status->hash = 'unknown';
            $status->entity = $entity;
            $this->entities[$idx] = $status;
        }
        $status->mark = self::MARK_REMOVE;
        $this->mapper->subsidiaryRemove($this->entityManager,$status->entity);
    }

    public function detach($entity)
    {
        $id = $this->mapper->getId($entity);
        if($id===null)
            $idx = spl_object_hash($entity);
        else
            $idx = strval($id);
        if(!array_key_exists($idx, $this->entities)) {
            throw new Exception\DomainException('A entity is not exists.');
        }
        unset($this->entities[$idx]);
    }

    public function contains($entity)
    {
        $id = $this->mapper->getId($entity);
        if($id===null)
            $idx = spl_object_hash($entity);
        else
            $idx = strval($id);
        if(!array_key_exists($idx, $this->entities))
            return false;
        $entityTmp = $this->entities[$idx]->entity;
        if(spl_object_hash($entity)!=spl_object_hash($entityTmp))
            return false;
        return true;
    }

    public function merge($entity)
    {
        $id = $this->mapper->getId($entity);
        if($id===null)
            throw new Exception\DomainException('A entity must be specified primary key to merge.');
        $idx = strval($id);
        if(array_key_exists($idx, $this->entities)) {
            throw new Exception\DomainException('A entity is already exists.');
        }
        $status = new EntityStatus();
        $status->entity = $entity;
        $status->hash = 'unknown';
        $this->entities[$idx] = $status;
        return $entity;
    }

    public function clear()
    {
        $this->entities = array();
    }

    public function flush()
    {
        $entities = $this->entities;
        foreach ($entities as $idx => $status) {
            $id = $this->mapper->getId($status->entity);
            if($id!==null && $idx != strval($id)) {
                throw new Exception\DomainException('Primary key can not be changed in "'.$this->mapper->className().'".');
            }

            switch($status->mark) {
            case self::MARK_REMOVE:
                $this->mapper->remove($status->entity);
                unset($this->entities[$idx]);
                break;
            case self::MARK_CREATE:
                $status->entity = $this->mapper->create($status->entity);
                $status->hash = $this->mapper->hash($this->entityManager,$status->entity);
                $status->mark = self::MARK_UPDATE;
                $id = $this->mapper->getId($status->entity);
                $this->entities[strval($id)] = $status;
                unset($this->entities[$idx]);
                break;
            case self::MARK_UPDATE:
                $hash = $this->mapper->hash($this->entityManager,$status->entity);
                if($status->hash != $hash) {
                    $this->mapper->save($status->entity);
                    $status->hash = $hash;
                }
                break;
            default:
                throw new Exception\DomainException('Invalid status of entity.');
            }
        }
    }

    public function refresh($entity)
    {
        $id = $this->mapper->getId($entity);
        if($id===null)
            throw new Exception\DomainException('A entity must be specified primary key to merge.');
        $idx = strval($id);
        if(!array_key_exists($idx, $this->entities))
            throw new Exception\DomainException('A entity is already exists.');
        $newEntity = $this->mapper->find($id,$entity);
        if(!$newEntity)
            throw new Exception\EntityNotFoundException('entity not found.');
        // ** CAUTION **
        // Must do cache before calling "supplimentEntity",
        // because it happen infinity loop when entities have recusive reference.
        $status = $this->entities[$idx];
        $status->entity = $entity;
        $status->mark = self::MARK_UPDATE;
        $this->mapper->supplementEntity($this->entityManager,$entity);
        $status->hash = $this->mapper->hash($this->entityManager,$entity);
        return $entity;
    }

    public function createResultList($cursorFactory)
    {
        if(!is_callable($cursorFactory))
            throw new Exception\InvalidArgumentException('cursorFactory must be callable.');
        $resultList = new ResultList();
        $resultList->setCursorFactory($cursorFactory);
        $resultList->setEntityManager($this->entityManager);
        return $resultList;
    }

    public function close()
    {
        $this->mapper->close($this->entityManager);
    }

    public function getNamedQuery($name,$resultClass=null)
    {
        return $this->mapper->getNamedQuery($name,$resultClass);
    }
}