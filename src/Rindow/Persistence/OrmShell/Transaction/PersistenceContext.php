<?php
namespace Rindow\Persistence\OrmShell\Transaction;

use Rindow\Persistence\Orm\EntityManagerProxy as EntityManagerProxyInterface;
use Rindow\Persistence\Orm\EntityManager;

class PersistenceContext implements EntityManager,EntityManagerProxyInterface
{
    protected $entityManagerHolder;

    public function setEntityManagerHolder(/* EntityManagerHolder */$entityManagerHolder)
    {
        $this->entityManagerHolder = $entityManagerHolder;
    }

    public function __call($method,array $params)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return call_user_func_array(array($entityManager,$method),$params);
    }

    public function unwrap($class=null)
    {
        return $this->entityManagerHolder->getCurrentEntityManager();
    }

    public function find(/*String*/ $entityClass, $primaryKey, $lockMode=null, array $properties=null)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->find($entityClass, $primaryKey, $lockMode, $properties);
    }

    public function contains($entity)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->contains($entity);
    }

    public function remove($entity)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->remove($entity);
    }

    public function persist($entity)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->persist($entity);
    }

    public function detach($entity)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->detach($entity);
    }

    public function merge($entity)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->merge($entity);
    }

    public function clear()
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->clear();
    }

    public function flush()
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->flush();
    }

    public function createQuery($query, /*String*/ $resultClass=null)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->createQuery($query, $resultClass);
    }

    public function createNamedQuery(/*String*/ $name, /*String*/ $resultClass=null)
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->createNamedQuery($name, $resultClass);
    }

    public function close()
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->close();
    }

    public function getCriteriaBuilder()
    {
        $entityManager = $this->entityManagerHolder->getCurrentEntityManager();
        return $entityManager->getCriteriaBuilder();
    }
}
