<?php
namespace Rindow\Persistence\OrmShell;

use ArrayAccess;
use Rindow\Database\Dao\Support\ResultList as DaoResultList;
use Rindow\Persistence\Orm\Exception;

class ResultList extends DaoResultList implements ArrayAccess
{
    const CASCADE_PERSIST = 'persist';
    const CASCADE_REMOVE  = 'remove';
    const CASCADE_MERGE   = 'merge';
    const CASCADE_DETACH  = 'detach';
    const CASCADE_REFRESH = 'refresh';

    protected $cursorFactory;
    protected $results;
    protected $array = array();
    protected $cascade = array();
    protected $pos = 0;
    protected $maxPos = -1;
    protected $mapped = true;
    protected $loaders = array();
    protected $endOfRealCursor = false;

    public function setMapped($mapped)
    {
        $this->mapped = $mapped;
    }

    public function isMapped()
    {
        return $this->mapped ? true : false;
    }

    public function setCascade(array $cascade)
    {
        $cascadeFlg = array();
        foreach ($cascade as $switch) {
            $cascadeFlg[$switch] = true;
        }
        $this->cascade = $cascadeFlg;
    }

    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function rewind()
    {
        $this->pos = 0;
        $this->endOfCursor = false;
    }

    public function valid()
    {
        if($this->pos <= $this->maxPos) {
            $this->currentRow = $this->array[$this->pos];
            return true;
        }
        if($this->endOfRealCursor)
            return false;
        if(!parent::valid()) {
            $this->endOfRealCursor = true;
            return false;
        }
        $this->maxPos = $this->pos;
        $this->array[$this->pos] = $this->currentRow;
        return true;
    }

    protected function persist($entity)
    {
        return $this->entityManager->persist($entity);
    }

    protected function remove($entity)
    {
        return $this->entityManager->remove($entity);
    }

    public function toArray()
    {
        while($this->fetch()) {
            ;
        }
        return $this->array;
    }

    public function offsetExists( $offset )
    {
        return isset($this->array[$offset]);
    }

    public function offsetGet( $offset )
    {
        if(!$this->offsetExists($offset))
            return null;
        return $this->array[$offset];
    }

    public function offsetSet( $offset ,  $value )
    {
        if($offset===null) {
            if(isset($this->cascade[self::CASCADE_PERSIST]))
                $this->persist($value);
            $this->maxPos += 1;
            $this->array[$this->maxPos] = $value;
            return;
        }
        if(!$this->offsetExists($offset)) {
            if(isset($this->cascade[self::CASCADE_PERSIST]))
                $this->persist($value);
            $this->array[$offset] = $value;
            return;
        }
        $this->array[$offset] = $value;
    }

    public function offsetUnset( $offset )
    {
        if(!$this->offsetExists($offset))
            return;
        if(isset($this->cascade[self::CASCADE_REMOVE]))
            $this->remove($this->array[$offset]);
        elseif (isset($this->cascade[self::CASCADE_DETACH])) {
            $this->detach($this->array[$offset]);
        }
        unset($this->array[$offset]);
    }
}
