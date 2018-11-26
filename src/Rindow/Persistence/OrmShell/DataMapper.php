<?php
namespace Rindow\Persistence\OrmShell;

interface DataMapper
{
    //public function setEntityManager($entityManager);
    //public function setResource($resource);
    public function close();
    public function className();
    public function getNamedQuery($name,$resultClass=null);
    public function getId($entity);
    public function create($entity);
    public function save($entity);
    public function remove($entity);
    public function find($id,$entity=null,$lockMode=null,array $properties=null);
    public function findBy($resultListFactory,$query,$params=null,$firstPosition=null,$maxResult=null,$lockMode=null);
    //public function findAll($resultListFactory);
    public function hash($entityManager,$entity);
    public function supplementEntity($entityManager,$entity);
    public function subsidiaryPersist($entityManager,$entity);
    public function subsidiaryRemove($entityManager,$entity);
}
