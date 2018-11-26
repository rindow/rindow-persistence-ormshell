<?php
namespace Rindow\Persistence\OrmShell\Transaction;

use Interop\Lenient\Transaction\Synchronization as SynchronizationInterface;
use Rindow\Persistence\Orm\EntityManagerHolder;
use Rindow\Persistence\Orm\Exception;

class Synchronization implements SynchronizationInterface,EntityManagerHolder
{
    protected $synchronizationRegistry;
    protected $entityManagerFactory;
    protected $logger;

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function setEntityManagerFactory($entityManagerFactory)
    {
        $this->entityManagerFactory = $entityManagerFactory;
    }

    public function setSynchronizationRegistry($synchronizationRegistry)
    {
        $this->synchronizationRegistry = $synchronizationRegistry;
    }

    public function getCurrentEntityManager()
    {
        $key = $this;
        $entityManager = $this->synchronizationRegistry->getResource($key);
        if($entityManager==null) {
            $this->synchronizationRegistry->registerInterposedSynchronization($this);
            $entityManager = $this->entityManagerFactory->createEntityManager();
            $this->synchronizationRegistry->putResource($key, $entityManager);
        }
        return $entityManager;
    }

    public function beforeCompletion()
    {
        if($this->synchronizationRegistry->getTransactionKey()==null)
            return;
        $key = $this;
        $entityManager = $this->synchronizationRegistry->getResource($key);
        if($entityManager)
            $entityManager->flush();
    }
    public function afterCompletion($success)
    {
        if($this->synchronizationRegistry->getTransactionKey()==null)
            return;
        $key = $this;
        $entityManager = $this->synchronizationRegistry->getResource($key);
        if($entityManager) {
            $entityManager->close();
            $this->synchronizationRegistry->putResource($key, null);
        }
    }
}
