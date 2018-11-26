<?php
namespace Rindow\Persistence\OrmShell\Transaction;

use Rindow\Persistence\Orm\EntityManagerFactory as EntityManagerFactoryInterface;
use Rindow\Persistence\OrmShell\EntityManager;
/*use Rindow\Container\ServiceLocator;*/

class EntityManagerFactory implements EntityManagerFactoryInterface
{
    protected $logger;
    protected $criteriaMapper;
    protected $serviceLocator;
    //protected $resource;
    protected $config;

    public static function factory(/*ServiceLocator*/ $serviceManager,$component=null,$args=null)
    {
        if(!isset($args['component']))
            throw new Exception\DomainException('Factory\'s name is not specified for EntityManagerFactory.');
        $factory = $serviceManager->get($args['component']);
        return $factory->createEntityManager();
    }

    public function setConfig($config)
    {
        $this->config = $config;
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

    public function createEntityManager()
    {
        $entityManager = new EntityManager($this);
        $entityManager->setConfig($this->config);
        $entityManager->setServiceLocator($this->serviceLocator);
        $entityManager->setCriteriaMapper($this->criteriaMapper);
        //$entityManager->setResource($this->resource);
        $entityManager->setLogger($this->logger);
        return $entityManager;
    }
}
