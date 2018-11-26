<?php
namespace RindowTest\Persistence\OrmShell\TransactionManagementTest;

use PHPUnit\Framework\TestCase;
use Rindow\Container\ModuleManager;
use Interop\Lenient\Transaction\ResourceManager;
use Interop\Lenient\Transaction\TransactionDefinition;
use Interop\Lenient\Transaction\Status;
use Rindow\Persistence\Orm\Criteria\CriteriaMapper;
use Rindow\Persistence\OrmShell\DataMapper;
use Rindow\Persistence\OrmShell\Resource;

class TestException extends \Exception
{}

class TestLogger
{
    public $logdata = array();
    public function log($message)
    {
        $this->logdata[] = $message;
    }
    public function debug($message)
    {
        $this->log($message);
    }
    public function error($message)
    {
        $this->log($message);
    }
}

class TestResourceManager implements ResourceManager
{
    //public $listener;
    public $isolationLevel = TransactionDefinition::ISOLATION_DEFAULT;
    public $timeout;
    public $nestedTransactionAllowed = true;
    //public $allowSavepointForNestedTransaction = true;
    //public $savepointSerial = 0;
    public $connected = false;
    public $logger;
    public $commitError;
    public $rollbackError;
    public $accessError;
    public $suspendSupported;
    //public $suspended =false;
    public $depth = 0;
    public $name;
    public $maxDepth;
    public $readOnly;

    public function getName()
    {
        return $this->name;
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }
    //public function setNestedTransactionAllowed()
    //{
    //    $this->nestedTransactionAllowed = true;
    //}
    //public function clearUseSavepointForNestedTransaction()
    //{
    //    $this->allowSavepointForNestedTransaction = false;
    //}
    //public function setConnectedEventListener($listener)
    //{
    //    $this->logger->log('setConnectedEventListener');
    //    $this->listener = $listener;
    //}
    //public function connect()
    //{
    //    if($this->connected)
    //        return;
    //    //$this->logger->log('connect');
    //    $this->connected = true;
    //    //call_user_func($this->listener);
    //}
    //public function isConnected()
    //{
    //    return $this->connected;
    //}
    public function access()
    {
        //$this->connect();
        $this->logger->log('access'.($this->name ? '['.$this->name.']':''));
        if($this->accessError) {
            $this->logger->log('ACCESS ERROR');
            throw new \Exception('ACCESS ERROR');
        }
    }
    public function setIsolationLevel($isolationLevel)
    {
        $this->logger->log('setIsolationLevel:'.$isolationLevel);
        $this->isolationLevel = $isolationLevel;
    }
    public function getIsolationLevel()
    {
        return $this->isolationLevel;
    }
    public function setReadOnly($readOnly)
    {
        $this->logger->log('setReadOnly:'.($readOnly ? 'true':'false'));
        $this->readOnly = $readOnly;
    }

    public function setTimeout($seconds)
    {
        $this->logger->log('setTimeout:'.$seconds);
        $this->timeout = $seconds;
    }
    public function isNestedTransactionAllowed()
    {
        return $this->nestedTransactionAllowed;
    }
    public function beginTransaction($definition=null)
    {
        if(!$this->nestedTransactionAllowed && $this->depth>0)
            throw new \Exception('already transaction started.');
        $this->depth++;
        $this->logger->log('beginTransaction('.$this->depth.')'.($this->name ? '['.$this->name.']':''));
        if($this->maxDepth && ($this->depth > $this->maxDepth)) {
            $this->logger->log('BEGIN TRANSACTION ERROR('.$this->depth.')'.($this->name ? '['.$this->name.']':''));
            $this->depth--;
            throw new TestException('BEGIN TRANSACTION ERROR('.($this->depth+1).')'.($this->name ? '['.$this->name.']':''));
        }
        if($definition) {
            if(($isolation=$definition->getIsolationLevel())>0) {
                $this->setIsolationLevel($isolation);
            }
            if($definition->isReadOnly())
                $this->setReadOnly(true);
            if(($timeout=$definition->getTimeout())>0)
                $this->setTimeout($timeout);
        }
    }
    public function commit()
    {
        $this->logger->log('commit('.$this->depth.')'.($this->name ? '['.$this->name.']':''));
        if($this->depth<=0)
            throw new \Exception('no transaction.');
        $this->depth--;
        if($this->commitError) {
            $this->logger->log('COMMIT ERROR');
            throw new TestException('COMMIT ERROR');
        }
    }
    public function rollback()
    {
        $this->logger->log('rollback('.$this->depth.')'.($this->name ? '['.$this->name.']':''));
        if($this->depth<=0)
            throw new \Exception('no transaction.');
        $this->depth--;
        if($this->rollbackError) {
            $this->logger->log('ROLLBACK ERROR');
            throw new TestException('ROLLBACK ERROR');
        }
    }
    //public function createSavepoint()
    //{
    //    $this->savepointSerial++;
    //    $this->logger->log('createSavepoint('.$this->savepointSerial.')');
    //    return 'savepoint'.$this->savepointSerial;
    //}
    //public function releaseSavepoint($savepoint)
    //{
    //    $this->logger->log('releaseSavepoint('.$savepoint.')');
    //    if($this->commitError) {
    //        $this->logger->log('RELEASE SAVEPOINT ERROR');
    //        throw new \Exception('RELEASE SAVEPOINT ERROR');
    //    }
    //}
    //public function rollbackSavepoint($savepoint)
    //{
    //    $this->logger->log('rollbackSavepoint('.$savepoint.')');
    //}
    public function suspend()
    {
        $this->logger->log('suspend:txObject('.$this->depth.')'.($this->name ? '['.$this->name.']':''));
        if(!$this->suspendSupported) {
            $this->logger->log('suspend is not supported');
            throw new TestException('suspend is not supported');
        }
        //if($this->suspended) {
        //    $this->logger->log('already suspended');
        //    throw new TestException('already suspended');
        //}
        //$this->suspended = true;
        $object = array('txObject',$this->depth);
        $this->depth = 0;
        return $object;
    }
    public function resume($txObject)
    {
        list($txt,$depth) = $txObject;
        $this->logger->log('resume:'.$txt.'('.$depth.')'.($this->name ? '['.$this->name.']':''));
        if(!$this->suspendSupported) {
            $this->logger->log('suspend is not supported');
            throw new TestException('suspend is not supported');
        }
        $this->depth = $depth;
        //if(!$this->suspended) {
        //    $this->logger->log('not suspended');
        //    throw new TestException('not suspended');
        //}
        //$this->suspended = true;
    }
}


class TestDataSource
{
    protected $logger;
    protected $transactionManager;
    protected $connection;
    public $commitError;
    public $rollbackError;
    public $accessError;
    public $nestedTransactionAllowed = true;
    public $suspendSupported;
    public $name;
    public $maxDepth;

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function setTransactionManager($transactionManager)
    {
        $this->transactionManager = $transactionManager;
    }

    public function getConnection()
    {
        $this->logger->log('getConnection');
        if($this->connection) {
            $this->enlistResource($this->connection);
            return $this->connection;
        }
        $this->logger->log('newConnection');
        $connection =  new TestResourceManager();
        $connection->setLogger($this->logger);
        $connection->commitError   = $this->commitError;
        $connection->rollbackError = $this->rollbackError;
        $connection->accessError   = $this->accessError;
        $connection->nestedTransactionAllowed = $this->nestedTransactionAllowed;
        $connection->suspendSupported = $this->suspendSupported;
        $connection->name = $this->name;
        $connection->maxDepth = $this->maxDepth;
        $this->enlistResource($connection);
        $this->connection = $connection;
        return $connection;
    }

    public function enlistResource($connection)
    {
        $transaction = $this->transactionManager->getTransaction();
        if($transaction)
            $transaction->enlistResource($connection);
        else
            $this->logger->log('transaction is null');
    }
}
/*
class TestResource implements Resource
{
    protected $connection;
    protected $dataSource;

    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    public function getConnection()
    {
        if($this->connection)
            return $this->connection;
        $this->connection = $this->dataSource->getConnection();
        return $this->connection;
    }

    public function getTransaction()
    {
    }
}
*/
class TestDataMapper implements DataMapper
{
    protected $logger;
    //protected $resource;
    public function __construct(TestLogger $logger)
    {
        $this->logger = $logger;
        $this->logger->log('new DataMapper');
    }
    //public function setEntityManager($entityManager) {}
    //public function setResource($resource) {
    //    $this->resource = $resource;
    //}
    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;
    }
    protected function getConnection() {
        return $this->dataSource->getConnection();
    }
    public function close() {
        $this->logger->log('close');
    }
    public function className() {}
    public function getNamedQuery($name,$resultClass=null) {}
    public function getId($entity) {}
    public function hash($entityManager,$entity) {}
    public function create($entity) {
        $connection = $this->getConnection();
        $connection->access();
    }
    public function save($entity) {
        $connection = $this->getConnection();
        $connection->access();
    }
    public function remove($entity) {
        $connection = $this->getConnection();
        $connection->access();
    }
    public function find($id,$entity=null,$lockMode=null,array $properties=null) {
        $connection = $this->getConnection();
        $connection->access();
    }
    public function findBy($resultListFactory,$query,$params=null,$firstPosition=null,$maxResult=null,$lockMode=null) {}
    //public function findAll($entityManager) {}
    public function supplementEntity($entityManager,$entity) {}
    public function subsidiaryPersist($entityManager,$entity) {}
    public function subsidiaryRemove($entityManager,$entity) {}
}

class TestCriteriaMapper implements CriteriaMapper
{
    public function setContext($context) {}

    public function prepare(/* CriteriaQuery */$query,$resultClass=null) {}
}

class TestEntity
{

}

class Test extends TestCase
{
    public function setUp()
    {
        usleep( RINDOW_TEST_CLEAR_CACHE_INTERVAL );
        \Rindow\Stdlib\Cache\CacheFactory::clearCache();
        usleep( RINDOW_TEST_CLEAR_CACHE_INTERVAL );
    }

    public function getConfig()
    {
        $config = array(
            'module_manager' => array(
                'modules' => array(
                ),
            ),
            'container' => array(
                'aliases' => array(
                    'TestLogger' => __NAMESPACE__.'\TestLogger',
                ),
                'components' => array(
                    __NAMESPACE__.'\TestLogger'=>array(
                    ),
                    __NAMESPACE__.'\TestDataSource' => array(
                        'properties' => array(
                            'logger' => array('ref'=>'TestLogger'),
                            //'debug' => array('value' => true),
                            'transactionManager'=>array('ref'=>__NAMESPACE__.'\TestTransactionManager'),
                        ),
                    ),
                    __NAMESPACE__.'\TestTransactionManager' => array(
                        'class'=>'Rindow\Transaction\Local\TransactionManager',
                        //'properties' => array(
                        //    'useSavepointForNestedTransaction' => array('value'=>true),
                        //),
                    ),
                    __NAMESPACE__.'\TestTransactionSynchronizationRegistry' => array(
                        'class'=>'Rindow\Transaction\Support\TransactionSynchronizationRegistry',
                        'properties' => array(
                            'transactionManager' => array('ref'=>__NAMESPACE__.'\TestTransactionManager'),
                        ),
                    ),
                    __NAMESPACE__.'\TestSynchronization' => array(
                        'class' => 'Rindow\Persistence\OrmShell\Transaction\Synchronization',
                        'properties' => array(
                            'entityManagerFactory' => array('ref'=>__NAMESPACE__.'\TestEntityManagerFactory'),
                            'synchronizationRegistry' => array('ref'=>__NAMESPACE__.'\TestTransactionSynchronizationRegistry'),
                            'logger' => array('ref'=>'TestLogger'),
                        ),
                    ),
                    __NAMESPACE__.'\TestEntityManagerFactory' => array(
                        'class' => 'Rindow\Persistence\OrmShell\Transaction\EntityManagerFactory',
                        'properties' => array(
                            'config'=>array('config'=>'persistence'),
                            'serviceLocator'=>array('ref'=>'ServiceLocator'),
                            'criteriaMapper'=>array('ref'=>__NAMESPACE__.'\TestCriteriaMapper'),
                            //'resource'=>array('ref'=>__NAMESPACE__.'\TestResource'),
                        ),
                    ),
                    //__NAMESPACE__.'\TestResource' => array(
                    //    'properties' => array(
                    //        'dataSource' => array('ref'=>__NAMESPACE__.'\TestDataSource'),
                    //    ),
                    //),
                    __NAMESPACE__.'\TestPersistenceContext' => array(
                        'class' => 'Rindow\Persistence\OrmShell\Transaction\PersistenceContext',
                        'properties' => array(
                            'entityManagerHolder' => array('ref'=>__NAMESPACE__.'\TestSynchronization'),
                        ),
                    ),
                    __NAMESPACE__.'\TestCriteriaMapper'=>array(
                    ),
                    __NAMESPACE__.'\TestDataMapper'=>array(
                        'properties' => array(
                            'dataSource' => array('ref'=>__NAMESPACE__.'\TestDataSource'),
                        ),
                        //'scope'=>'prototype',
                    ),
                ),
            ),
            'persistence' => array(
                'mappers' => array(
                    __NAMESPACE__.'\TestEntity' => __NAMESPACE__.'\TestDataMapper',
                ),
            ),
        );
        return $config;
    }

    public function testNormalCommit()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');

        $this->assertNull($transactionManager->getTransaction());
        $this->assertFalse($transactionManager->isActive());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());
        $logger->log('begin txLevel1');
        $txLevel1 = $transactionManager->begin();
        $logger->log('in txLevel1');
        $this->assertEquals(spl_object_hash($txLevel1),spl_object_hash($transactionManager->getTransaction()));
        $this->assertTrue($transactionManager->isActive());
        $this->assertTrue($txLevel1->isActive());
        $this->assertEquals(Status::STATUS_ACTIVE,$transactionManager->getStatus());
        $this->assertEquals(Status::STATUS_ACTIVE,$txLevel1->getStatus());
        $testEntityManagerProxy->persist(new TestEntity());
        $logger->log('commit txLevel1');
        $transactionManager->commit();
        $this->assertNull($transactionManager->getTransaction());
        $this->assertFalse($transactionManager->isActive());
        $this->assertFalse($txLevel1->isActive());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());
        $this->assertEquals(Status::STATUS_COMMITTED,$txLevel1->getStatus());
        $result = array(
            'begin txLevel1',
            'in txLevel1',
            'new DataMapper',
            'commit txLevel1',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'access',
            'commit(1)',
            'close',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNestLevel2AndAccessOnTailCommit()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');

        $this->assertNull($transactionManager->getTransaction());
        $this->assertFalse($transactionManager->isActive());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());
        $logger->log('begin txLevel1');
        $txLevel1 = $transactionManager->begin();
        $logger->log('in txLevel1');
        $this->assertEquals(spl_object_hash($txLevel1),spl_object_hash($transactionManager->getTransaction()));
        $this->assertTrue($transactionManager->isActive());
        $this->assertTrue($txLevel1->isActive());
        $this->assertEquals(Status::STATUS_ACTIVE,$transactionManager->getStatus());
        $this->assertEquals(Status::STATUS_ACTIVE,$txLevel1->getStatus());
        $logger->log('begin txLevel2');
        $txLevel2 = $transactionManager->begin();
        $logger->log('in txLevel2');
        $this->assertEquals(spl_object_hash($txLevel2),spl_object_hash($transactionManager->getTransaction()));
        $this->assertNotEquals(spl_object_hash($txLevel2),spl_object_hash($txLevel1));
        $this->assertTrue($transactionManager->isActive());
        $this->assertTrue($txLevel2->isActive());
        $this->assertEquals(Status::STATUS_ACTIVE,$transactionManager->getStatus());
        $this->assertEquals(Status::STATUS_ACTIVE,$txLevel2->getStatus());
        $this->assertEquals(Status::STATUS_ACTIVE,$txLevel1->getStatus());
        $testEntityManagerProxy->persist(new TestEntity());
        $logger->log('commit txLevel2');
        $transactionManager->commit();
        $this->assertEquals(spl_object_hash($txLevel1),spl_object_hash($transactionManager->getTransaction()));
        $this->assertTrue($transactionManager->isActive());
        $this->assertFalse($txLevel2->isActive());
        $this->assertTrue($txLevel1->isActive());
        $this->assertEquals(Status::STATUS_ACTIVE,$transactionManager->getStatus());
        $this->assertEquals(Status::STATUS_COMMITTED,$txLevel2->getStatus());
        $this->assertEquals(Status::STATUS_ACTIVE,$txLevel1->getStatus());
        $logger->log('commit txLevel1');
        $transactionManager->commit();
        $this->assertNull($transactionManager->getTransaction());
        $this->assertFalse($transactionManager->isActive());
        $this->assertFalse($txLevel2->isActive());
        $this->assertFalse($txLevel1->isActive());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());
        $this->assertEquals(Status::STATUS_COMMITTED,$txLevel2->getStatus());
        $this->assertEquals(Status::STATUS_COMMITTED,$txLevel1->getStatus());

        $result = array(
            'begin txLevel1',
            'in txLevel1',
            'begin txLevel2',
            'in txLevel2',
            'new DataMapper',
            'commit txLevel2',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'beginTransaction(2)',
            'access',
            'commit(2)',
            'close',
            'commit txLevel1',
            'commit(1)',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNestLevel2AndAccessOnEachLevelPart1Commit()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');

        $logger->log('begin txLevel1');
        $transactionManager->begin();
        $logger->log('in txLevel1');
        $testEntityManagerProxy->persist(new TestEntity());
        $logger->log('begin txLevel2');
        $transactionManager->begin();
        $logger->log('in txLevel2');
        $testEntityManagerProxy->persist(new TestEntity());
        $logger->log('commit txLevel2');
        $transactionManager->commit();
        $logger->log('commit txLevel1');
        $transactionManager->commit();

        $result = array(
            'begin txLevel1',
            'in txLevel1',
            'new DataMapper',
            'begin txLevel2',
            'in txLevel2',
            //'new DataMapper',
            'commit txLevel2',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'beginTransaction(2)',
            'access',
            'commit(2)',
            'close',
            'commit txLevel1',
            'getConnection', //**//
            'access',
            'commit(1)',
            'close',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNestLevel2AndAccessOnEachLevelPart2Commit()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');

        $transactionManager->begin();
        $transactionManager->begin();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->commit();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->commit();

        $result = array(
            'new DataMapper',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'beginTransaction(2)',
            'access',
            'commit(2)',
            'close',
            //'new DataMapper',
            'getConnection',//**//
            'access',
            'commit(1)',
            'close',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNestLevel2Include2TransactionAndAccessOnEachLevelPart2Commit()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');

        $transactionManager->begin();
        $transactionManager->begin();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->commit();
        $transactionManager->begin();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->commit();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->commit();

        $result = array(
            'new DataMapper',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'beginTransaction(2)',
            'access',
            'commit(2)',
            'close',
            //'new DataMapper',
            'getConnection',//**//
            'beginTransaction(2)',
            'access',
            'commit(2)',
            'close',
            //'new DataMapper',
            'getConnection',//**//
            'access',
            'commit(1)',
            'close',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNoConnectionCommit()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');

        $transactionManager->begin();
        $transactionManager->begin();
        $transactionManager->commit();
        $transactionManager->commit();

        $result = array(
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNormalRollback()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');

        $this->assertNull($transactionManager->getTransaction());
        $this->assertFalse($transactionManager->isActive());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());
        $txLevel1 = $transactionManager->begin();
        $this->assertEquals(spl_object_hash($txLevel1),spl_object_hash($transactionManager->getTransaction()));
        $this->assertTrue($transactionManager->isActive());
        $this->assertTrue($txLevel1->isActive());
        $this->assertEquals(Status::STATUS_ACTIVE,$transactionManager->getStatus());
        $this->assertEquals(Status::STATUS_ACTIVE,$txLevel1->getStatus());
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->rollback();
        $this->assertNull($transactionManager->getTransaction());
        $this->assertFalse($transactionManager->isActive());
        $this->assertFalse($txLevel1->isActive());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());
        $this->assertEquals(Status::STATUS_ROLLEDBACK,$txLevel1->getStatus());

        $result = array(
            'new DataMapper',
            'close',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testAccessAndRollback()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testDataSource = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestDataSource');

        $transactionManager->begin();
        $testDataSource->getConnection()->access();
        $transactionManager->rollback();

        $result = array(
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'access',
            'rollback(1)',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNestLevel2CommitAndRollbackPart1()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');

        $transactionManager->begin();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->begin();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->commit();
        $transactionManager->rollback();

        $result = array(
            'new DataMapper',
            //'new DataMapper',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'beginTransaction(2)',
            'access',
            'commit(2)',
            'close',
            'rollback(1)',
            'close',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNestLevel2CommitAndRollbackPart2()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');

        $transactionManager->begin();
        $transactionManager->begin();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->commit();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->rollback();

        $result = array(
            'new DataMapper',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'beginTransaction(2)',
            'access',
            'commit(2)',
            'close',
            //'new DataMapper',
            'rollback(1)',
            'close',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testNestLevel2RollbackOnly()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');

        $txLevel1 = $transactionManager->begin();
        $testEntityManagerProxy->persist(new TestEntity());
        $txLevel2 = $transactionManager->begin();
        $transactionManager->getTransaction()->setRollbackOnly();
        $testEntityManagerProxy->persist(new TestEntity());
        $transactionManager->commit();
        $this->assertEquals(Status::STATUS_ROLLEDBACK,$txLevel2->getStatus());
        $transactionManager->commit();
        $this->assertEquals(Status::STATUS_COMMITTED,$txLevel1->getStatus());

        $result = array(
            'new DataMapper',
            //'new DataMapper',
            'close',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'access',
            'commit(1)',
            'close',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testErrorAtCommit()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');
        $dataSource = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestDataSource');
        $dataSource->commitError = true;

        try {
            $txLevel1 = $transactionManager->begin();
            $testEntityManagerProxy->persist(new TestEntity());
            $transactionManager->commit();
        } catch(\Exception $e) {
            $logger->log('exception:'.$e->getMessage());
        }
        $this->assertEquals(Status::STATUS_UNKNOWN,$txLevel1->getStatus());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());

        $result = array(
            'new DataMapper',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'access',
            'commit(1)',
            'COMMIT ERROR',
            'close',
            'exception:COMMIT ERROR',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testErrorAtFlush()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');
        $dataSource = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestDataSource');
        $dataSource->accessError = true;

        try {
            $txLevel1 = $transactionManager->begin();
            $testEntityManagerProxy->persist(new TestEntity());
            $transactionManager->commit();
        } catch(\Exception $e) {
            $logger->log('exception:'.$e->getMessage());
        }
        $this->assertEquals(Status::STATUS_ROLLEDBACK,$txLevel1->getStatus());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());

        $result = array(
            'new DataMapper',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'access',
            'ACCESS ERROR',
            'rollback(1)',
            'close',
            'exception:ACCESS ERROR',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function Disable_testErrorAtClose()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');

        try {
            $txLevel1 = $transactionManager->begin();
            $testEntityManagerProxy->persist(new TestEntity());
            $transactionManager->commit();
        } catch(\Exception $e) {
            $logger->log('exception:'.$e->getMessage());
        }
        //$this->assertEquals(Status::STATUS_UNKNOWN,$txLevel1->getStatus());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());

        $result = array(
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testErrorAtRollback()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');
        $dataSource = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestDataSource');
        $dataSource->rollbackError = true;

        try {
            $txLevel1 = $transactionManager->begin();
            $testEntityManagerProxy->persist(new TestEntity());
            $dataSource->getConnection()->access();
            $transactionManager->rollback();
        } catch(\Exception $e) {
            $logger->log('exception:'.$e->getMessage());
        }
        $this->assertEquals(Status::STATUS_UNKNOWN,$txLevel1->getStatus());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());

        $result = array(
            'new DataMapper',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'access',
            'rollback(1)',
            'ROLLBACK ERROR',
            'close',
            'exception:ROLLBACK ERROR',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testErrorAtFlushAndRollback()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');
        $dataSource = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestDataSource');
        $dataSource->accessError = true;
        $dataSource->rollbackError = true;

        try {
            $txLevel1 = $transactionManager->begin();
            $testEntityManagerProxy->persist(new TestEntity());
            $transactionManager->commit();
        } catch(\Exception $e) {
            $logger->log('exception:'.$e->getMessage());
        }
        $this->assertEquals(Status::STATUS_UNKNOWN,$txLevel1->getStatus());
        $this->assertEquals(Status::STATUS_NO_TRANSACTION,$transactionManager->getStatus());

        $result = array(
            'new DataMapper',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'access',
            'ACCESS ERROR',
            'rollback(1)',
            'ROLLBACK ERROR',
            'close',
            'exception:ACCESS ERROR',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testErrorAtLevel2Commit()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');
        $dataSource = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestDataSource');
        $dataSource->commitError = true;

        try {
            $transactionManager->begin();
            try {
                $testEntityManagerProxy->persist(new TestEntity());
                $transactionManager->begin();
                try {
                    $testEntityManagerProxy->persist(new TestEntity());
                } catch(\Exception $e) {
                    $logger->log('exception in level2 transaction:'.$e->getMessage());
                    $transactionManager->rollback();
                    throw $e;
                }
                $logger->log('commiting level 2');
                $transactionManager->commit();
            } catch(\Exception $e) {
                $logger->log('exception at level1 transaction:'.$e->getMessage());
                $transactionManager->rollback();
                throw $e;
            }
            $logger->log('commiting level 1');
            $transactionManager->commit();
        } catch(\Exception $e) {
            $logger->log('exception:'.$e->getMessage());
        }

        $result = array(
            'new DataMapper',
            //'new DataMapper',
            'commiting level 2',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'beginTransaction(2)',
            'access',
            'commit(2)',
            'COMMIT ERROR',
            'close',
            'exception at level1 transaction:COMMIT ERROR',
            'rollback(1)',
            'close',
            'exception:COMMIT ERROR',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }

    public function testErrorAtLevel2Flush()
    {
        $mm = new ModuleManager($this->getConfig());
        $transactionManager = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestTransactionManager');
        $testEntityManagerProxy = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestPersistenceContext');
        $logger = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger');
        $dataSource = $mm->getServiceLocator()->get(__NAMESPACE__.'\TestDataSource');
        $dataSource->accessError = true;

        try {
            $transactionManager->begin();
            try {
                $testEntityManagerProxy->persist(new TestEntity());
                $transactionManager->begin();
                try {
                    $testEntityManagerProxy->persist(new TestEntity());
                } catch(\Exception $e) {
                    $logger->log('exception in level2 transaction:'.$e->getMessage());
                    $transactionManager->rollback();
                    throw $e;
                }
                $logger->log('commiting level 2');
                $transactionManager->commit();
            } catch(\Exception $e) {
                $logger->log('exception at level1 transaction:'.$e->getMessage());
                $transactionManager->rollback();
                throw $e;
            }
            $logger->log('commiting level 1');
            $transactionManager->commit();
        } catch(\Exception $e) {
            $logger->log('exception:'.$e->getMessage());
        }

        $result = array(
            'new DataMapper',
            //'new DataMapper',
            'commiting level 2',
            'getConnection',
            'newConnection',
            //'setConnectedEventListener',
            //'connect',
            'beginTransaction(1)',
            'beginTransaction(2)',
            'access',
            'ACCESS ERROR',
            'rollback(2)',
            'close',
            'exception at level1 transaction:ACCESS ERROR',
            'rollback(1)',
            'close',
            'exception:ACCESS ERROR',
        );
        $this->assertEquals($result,$mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
        //print_r($mm->getServiceLocator()->get(__NAMESPACE__.'\TestLogger')->logdata);
    }
}
