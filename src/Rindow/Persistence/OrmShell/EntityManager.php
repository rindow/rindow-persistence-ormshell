<?php
namespace Rindow\Persistence\OrmShell;

use Rindow\Persistence\Orm\Criteria\CriteriaQuery;
use Rindow\Persistence\Orm\Criteria\CriteriaBuilder;
use Rindow\Persistence\OrmShell\Paginator\OrmAdapter;
use Rindow\Persistence\Orm\Exception;
use Rindow\Persistence\Orm\EntityManager as EntityManagerInterface;
use Rindow\Persistence\Orm\FlushModeType;
/*use Rindow\Container\ServiceLocator;*/

class EntityManager implements EntityManagerInterface
{
    protected $config;
    protected $mapperName;
    protected $criteriaMapper;
    //protected $resource;
    protected $serviceLocator;
    protected $criteriaBuilder;
    protected $logger;
    protected $repositories = array();
    protected $flushMode = FlushModeType::AUTO;
    protected $entityManagerFactory;
    protected $closed = false;

    public function __construct($entityManagerFactory=null)
    {
        $this->entityManagerFactory = $entityManagerFactory;
    }

    public function getEntityManagerFactory($entityManagerFactory)
    {
        return $this->entityManagerFactory;
    }

    public function setConfig($config)
    {
        $this->config = $config;
        $this->mapperName = $config['mappers'];
    }

    public function setServiceLocator(/*ServiceLocator*/ $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    public function setCriteriaMapper($criteriaMapper)
    {
        $this->criteriaMapper = $criteriaMapper;
    }

    //public function setResource($resource)
    //{
    //    $this->resource = $resource;
    //}

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function setFlushMode($flushMode)
    {
        $this->flushMode = $flushMode;
    }

    public function getFlushMode()
    {
        return $this->flushMode;
    }

    public function registerMapper($className, $mapperName)
    {
        if(!is_string($className))
            throw new Exception\InvalidArgumentException('className must be string.');
        if(!is_string($mapperName))
            throw new Exception\InvalidArgumentException('className must be string.');
        if(isset($this->mapperName[$className]))
            throw new Exception\InvalidArgumentException('a mapper is already exist for class "'.$className.'".');
        $this->mapperName[$className] = $mapperName;
    }

    protected function createMapper($className)
    {
        if(!isset($this->mapperName[$className]))
            throw new Exception\InvalidArgumentException('a mapper is not registed for class "'.$className.'".');
        $mapper = $this->serviceLocator->get($this->mapperName[$className]);//,array('scope'=>'prototype'));
        //$mapper->setResource($this->resource);
        //$mapper->setEntityManager($this);
        return $mapper;
    }

    public function getRepository($entityClass)
    {
        if(is_object($entityClass))
            $entityClass = get_class($entityClass);
        if(isset($this->repositories[$entityClass]))
            return $this->repositories[$entityClass];
        $mapper = $this->createMapper($entityClass);
        $repository = new EntityRepository($mapper,$this);
        $this->repositories[$entityClass] = $repository;
        return $repository;
    }

    public function find($entityClass, $primaryKey, $lockMode=null, array $properties=null)
    {
        if($entityClass instanceof LazyFetchEntity) {
            if(!$entity->_hasEntity())
                throw new Exception\DomainException('LazyFetchEntity is not allowed.');
            $entityClass = $entityClass->_getEntity();
        }
        return $this->getRepository($entityClass)->find($primaryKey,$lockMode,$properties);
    }

    public function remove($entity)
    {
        if($entity instanceof LazyFetchEntity)
            $entity = $entity->_getEntity();
        return $this->getRepository($entity)->remove($entity);
    }

    public function persist($entity)
    {
        if($entity instanceof LazyFetchEntity)
            return $entity->_getEntity();
        return $this->getRepository($entity)->persist($entity);
    }

    public function detach($entity)
    {
        if($entity instanceof LazyFetchEntity) {
            if(!$entity->_hasEntity())
                return;
            $entity = $entity->_getEntity();
        }
        return $this->getRepository($entity)->detach($entity);
    }

    public function merge($entity)
    {
        if($entity instanceof LazyFetchEntity) {
            if(!$entity->_hasEntity())
                return;
            $entity = $entity->_getEntity();
        }
        return $this->getRepository($entity)->merge($entity);
    }

    public function refresh($entity)
    {
        if($entity instanceof LazyFetchEntity) {
            if(!$entity->_hasEntity())
                return;
            $entity = $entity->_getEntity();
        }
        return $this->getRepository($entity)->refresh($entity);
    }

    public function clear()
    {
        foreach ($this->repositories as $repository) {
            $repository->clear();
        }
    }

    public function flush()
    {
        foreach ($this->repositories as $repository) {
            $repository->flush();
        }
    }

    public function contains($entity)
    {
        if($entity instanceof LazyFetchEntity) {
            if(!$entity->_hasEntity())
                return true;
            $entity = $entity->_getEntity();
        }
        return $this->getRepository($entity)->contains($entity);
    }

    public function createQuery($query, $resultClass=null)
    {
        if($query instanceof CriteriaQuery) {
            if($this->criteriaMapper==null)
                throw new Exception\DomainException('a criteria mapper is not specified.');
            $this->criteriaMapper->setContext($this);
            $query = $this->criteriaMapper->prepare($query, $resultClass);
        }
        return new Query($this, $query, $resultClass);
    }

    public function createNamedQuery($name, $resultClass=null)
    {
        $query = null;
        if($resultClass) {
            $query = $this->getRepository($resultClass)->getNamedQuery($name);
        }
        if($query==null) {
            foreach ($this->mapperName as $className => $dummy) {
                $query = $this->getRepository($className)->getNamedQuery($name,$resultClass);
                if($query)
                    break;
            }
        }
        if($query==null)
            throw new Exception\InvalidArgumentException('no named query found:'.$name);
        return new Query($this, $query, $resultClass);
    }

    public function close()
    {
        foreach ($this->repositories as $repository) {
            $repository->close();
        }
        $this->repositories = array();
        $this->closed = true;
    }

    public function isOpen()
    {
        return !$this->closed;
    }

    public function lock($value='')
    {
        # code...
    }

    public function getReference($entityClass, $primaryKey)
    {
        if(is_object($entityClass))
            $entityClass = get_class($entityClass);
        return new LazyFetchEntity($this, $entityClass, $primaryKey);
    }

    public function getCriteriaBuilder()
    {
        if($this->criteriaBuilder)
            return $this->criteriaBuilder;
        return $this->criteriaBuilder = new CriteriaBuilder();
    }
}
