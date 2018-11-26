<?php
namespace RindowTest\Persistence\OrmShell\EntityManagerTest;

use PHPUnit\Framework\TestCase;
use Rindow\Persistence\OrmShell\DataMapper;
use Rindow\Persistence\OrmShell\EntityManager;
use Rindow\Persistence\Orm\Criteria\CriteriaMapper;
use Rindow\Persistence\Orm\Criteria\PreparedCriteria;
use Rindow\Database\Dao\Support\ResultList as DaoResultList;
use Interop\Lenient\Dao\Query\Cursor;

class TestEntity
{
    public $id;
    public $p1;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setP1($p1)
    {
        $this->p1 = $p1;
    }

    public function getP1()
    {
        return $this->p1;
    }

    public function boo()
    {
        return 'Boo is called';
    }
}

class TestMapper implements DataMapper
{
    protected $seq = 0;
    protected $strage = array();
    protected $entityManager;
    protected function getseq()
    {
        $this->seq += 1;
        return $this->seq;
    }
    //public function setEntityManager($entityManager)
    //{
    //    $this->entityManager = $entityManager;
    //}
    //public function setResource($resource) {}
    public function close() {}
    public function className() { return __NAMESPACE__.'\TestEntity'; }
    public function getId($entity) { return $entity->id; }
    public function hash($entityManager,$entity) { return sha1($entity->id.$entity->p1); }
    public function create($entity)
    {
        $entity->id = $this->getseq();
        return $this->save($entity);
    }
    public function save($entity)
    {
        $newEntity = clone $entity;
        $this->strage[$entity->id] = $newEntity;
        return $entity;
    }
    public function remove($entity)
    {
       unset($this->strage[$entity->id]);
    }
    public function find($id,$entity=null,$lockMode=null,array $properties=null)
    {
        if(!isset($this->strage[$id]))
            return null;
        $tmpEntity = $this->strage[$id];
        if($entity==null)
            return clone $tmpEntity;
        foreach (array_keys(get_object_vars($entity)) as $field) {
            $entity->$field = $tmpEntity->$field;
        }
        return $entity;
    }
    public function findBy($resultListFactory,$query,$params=null,$firstPosition=null,$maxResult=null,$lockMode=null)
    {
        $data = $this->strage;
        $cursorFactory = function() use ($data) {
            return new TestCursor($data);
        };
        return call_user_func($resultListFactory,$cursorFactory);
    }
    //public function findAll($entityManager) {}
    public function supplementEntity($entityManager,$entity) { return $entity; }
    public function subsidiaryPersist($entityManager,$entity) { return $entity; }
    public function subsidiaryRemove($entityManager,$entity) { return $entity; }
    public function getNamedQuery($name,$resultClass=null) { return "SELECT * FROM test"; }
}

class TestCursor implements Cursor
{
    public function __construct(array $array) {
        $this->array = $array;
    }
    public function fetch()
    {
        return array_shift($this->array);
    }
    public function close()
    {
    }
}

class TestContainer
{
    public function get($name)//,$options=null)
    {
        if(isset($this->instance[$name]))
            return $this->instance[$name];
        $this->instance[$name] = new $name();
        return $this->instance[$name];
    }
}

class TestCriteriaMapper implements CriteriaMapper
{
    public function setContext($context)
    {
    }
    public function prepare(/* CriteriaQuery */$criteria,$resultClass=null)
    {
        if($resultClass==null)
            $resultClass = $criteria->getRoots()->getNodeName();
        return new TestPreparedCriteria($criteria,$resultClass);
    }
}

class TestPreparedCriteria implements PreparedCriteria
{
    protected $criteria;
    protected $resultClass;

    public function __construct($criteria,$resultClass)
    {
        $this->criteria = $criteria;
        $this->resultClass = $resultClass;
    }
    public function getCriteria()
    {
        return $this->criteria;
    }
    public function getEntityClass()
    {
        return $this->resultClass;
    }
}

class Test extends TestCase
{
    public function testFindAndPersist()
    {
        $container = new TestContainer();
        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $this->assertTrue($em->isOpen());

        $entity = new TestEntity();
        $this->assertFalse($em->contains($entity));
        $this->assertNull($em->find($entity,1));

        $em->persist($entity);
        $this->assertTrue($em->contains($entity));
        $this->assertNull($entity->id);
        $em->flush();
        $this->assertTrue($em->contains($entity));
        $this->assertEquals(1,$entity->id);

        $this->assertEquals(spl_object_hash($entity), spl_object_hash($em->find($entity,1)));
        $em->close();
        $this->assertFalse($em->isOpen());

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $this->assertFalse($em->contains($entity));
        $entity2 = $em->find(get_class($entity),1);
        $this->assertNotEquals(spl_object_hash($entity), spl_object_hash($entity2));
        $this->assertEquals($entity, $entity2);
        $this->assertFalse($em->contains($entity));
    }

    public function testGetReference()
    {
        $container = new TestContainer();
        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity = new TestEntity();
        $entity->p1 = 'foo';
        $em->persist($entity);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $lazyEntity = $em->getReference(get_class($entity),1);
        $this->assertInstanceof('Rindow\Persistence\OrmShell\LazyFetchEntity',$lazyEntity);
        $this->assertTrue($em->contains($lazyEntity));
        $this->assertEquals('foo', $lazyEntity->p1);
        $this->assertEquals('Boo is called', $lazyEntity->boo());
        $this->assertTrue($em->contains($lazyEntity));
        $em->remove($lazyEntity);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $this->assertNull($em->find($entity,1));
        $this->assertFalse($em->contains($lazyEntity));
    }

    public function testRemove()
    {
        $container = new TestContainer();
        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity = new TestEntity();
        $entity->p1 = 'foo';
        $em->persist($entity);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity2 = $em->find(get_class($entity),1);
        $this->assertEquals($entity,$entity2);
        $em->remove($entity2);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $this->assertNull($em->find(get_class($entity),1));
    }

    public function testUpdate()
    {
        $container = new TestContainer();
        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity = new TestEntity();
        $entity->p1 = 'foo';
        $em->persist($entity);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity2 = $em->find(get_class($entity),1);
        $this->assertEquals('foo',$entity2->p1);
        $entity2->p1 = 'bar';
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity3 = $em->find(get_class($entity),1);
        $this->assertNotEquals(spl_object_hash($entity2), spl_object_hash($entity3));
        $this->assertEquals('bar',$entity3->p1);
    }

    public function testRefresh()
    {
        $container = new TestContainer();
        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity = new TestEntity();
        $entity->p1 = 'foo';
        $em->persist($entity);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity2 = $em->find(get_class($entity),1);
        $this->assertEquals('foo',$entity2->p1);
        $entity2->p1 = 'bar';
        $em->refresh($entity2);
        $this->assertEquals('foo',$entity2->p1);
    }

    public function testDetachMerge()
    {
        $container = new TestContainer();
        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity = new TestEntity();
        $entity->p1 = 'foo';
        $em->persist($entity);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity2 = $em->find(get_class($entity),1);
        $this->assertEquals('foo',$entity2->p1);
        $em->detach($entity2);
        $this->assertFalse($em->contains($entity2));
        $em->merge($entity2);
        $this->assertTrue($em->contains($entity2));
    }

    public function testClear()
    {
        $container = new TestContainer();
        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity = new TestEntity();
        $entity->p1 = 'foo';
        $em->persist($entity);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity2 = $em->find(get_class($entity),1);
        $this->assertEquals('foo',$entity2->p1);
        $em->clear();
        $this->assertFalse($em->contains($entity2));
    }

    public function testQuery()
    {
        $container = new TestContainer();
        $criteriaMapper = new TestCriteriaMapper();
        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');
        $entity = new TestEntity();
        $entity->p1 = 'foo';
        $em->persist($entity);
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->setCriteriaMapper($criteriaMapper);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');

        $criteriaBuilder = $em->getCriteriaBuilder();
        $queryCriteria = $criteriaBuilder->createQuery();
        $root = $queryCriteria->from(get_class($entity))->alias('t0');
        $queryCriteria->select($root);

        $query = $em->createQuery($queryCriteria);
        $resultList = $query->getResultList();
        $resultList->setCascade(array('persist'));
        $count = 0;
        foreach ($resultList as $result) {
            $this->assertInstanceof(__NAMESPACE__.'\TestEntity',$result);
            $this->assertEquals('foo',$result->p1);
            $count += 1;
        }
        $this->assertEquals(1,$count);

        $entity = new TestEntity();
        $entity->p1 = 'cascade';
        $resultList[] = $entity;
        $em->flush();

        $em = new EntityManager();
        $em->setServiceLocator($container);
        $em->setCriteriaMapper($criteriaMapper);
        $em->registerMapper(__NAMESPACE__.'\TestEntity', __NAMESPACE__.'\TestMapper');

        $criteriaBuilder = $em->getCriteriaBuilder();
        $queryCriteria = $criteriaBuilder->createQuery();
        $root = $queryCriteria->from(get_class($entity))->alias('t0');
        $queryCriteria->select($root);

        $query = $em->createQuery($queryCriteria);
        $resultList = $query->getResultList();
        $resultList->setCascade(array('persist'));
        $count = 0;
        foreach ($resultList as $result) {
            $count += 1;
            $this->assertInstanceof(__NAMESPACE__.'\TestEntity',$result);
            if($count==1)
                $this->assertEquals('foo',$result->p1);
            if($count==2)
                $this->assertEquals('cascade',$result->p1);
        }
        $this->assertEquals(2,$count);
    }
}
