<?php
namespace Rindow\Persistence\OrmShell;

use Rindow\Persistence\Orm\Criteria\Parameter;
use Rindow\Persistence\Orm\Exception;
use Rindow\Persistence\Orm\FlushModeType;
use Rindow\Persistence\Orm\Query as QueryInterface;

class Query implements QueryInterface
{
    protected $entityManager;
    protected $preparedCriteria;
    protected $resultClass;
    protected $parameters = array();
    protected $firstPosition;
    protected $maxResult;
    protected $lockMode;

    public function __construct($entityManager,/* PreparedCriteria */$preparedCriteria, $resultClass=null)
    {
        $this->entityManager = $entityManager;
        $this->preparedCriteria = $preparedCriteria;
        $this->resultClass = $resultClass;
    }

    public function getEntityManager()
    {
        return $this->entityManager;
    }

    public function getPreparedCriteria()
    {
        return $this->preparedCriteria;
    }

    public function setParameter($name, $value)
    {
        if($name instanceof Parameter)
            $name = $name->getName();
        $this->parameters[$name] = $value;
        return $this;
    }

    public function setFirstResult($startPosition)
    {
        $this->firstPosition = $startPosition;
        return $this;
    }

    public function setMaxResults($maxResult)
    {
        $this->maxResult = $maxResult;
        return $this;
    }

    public function setLockMode($lockMode)
    {
        $this->lockMode = $lockMode;
        return $this;
    }

    public function getParameterValue($name)
    {
        if(!array_key_exists($name, $this->parameters))
            throw new Exception\DomainException('parameter "'.$name.'" is not found.');
            
        return $this->parameters[$name];
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function getFirstResult()
    {
        return $this->firstPosition;
    }

    public function getMaxResults()
    {
        return $this->maxResult;
    }

    public function getLockMode()
    {
        return $this->lockMode;
    }

    protected function autoCommit($entityClass)
    {
        if($this->entityManager->getFlushMode()==FlushModeType::COMMIT)
            return;
        // Don't need to flush. Because the ResultList will use persisted entities when already has it.
        // $this->entityManager->flush();
    }

    public function getResultList()
    {
        if(is_object($this->preparedCriteria) &&
            method_exists($this->preparedCriteria, 'getEntityClass')) {
            $entityClass = $this->preparedCriteria->getEntityClass();
        } else {
            $entityClass = $this->resultClass;
        }
        $this->autoCommit($entityClass);
        $repository = $this->entityManager->getRepository($entityClass);
        return $repository->findBy(
            $this->preparedCriteria,
            $this->parameters,
            $this->firstPosition,
            $this->maxResult,
            $this->lockMode);
    }

    public function getSingleResult()
    {
        $resultList = $this->getResultList();
        $result = $resultList->current();
        $resultList->close();
        return $result;
    }

    public function unwrap($class=null)
    {
        return $this;
    }
}