<?php
namespace Rindow\Persistence\OrmShell\Paginator;

use IteratorAggregate;
use Rindow\Stdlib\Paginator\PaginatorAdapter;

class OrmAdaptor implements PaginatorAdapter,IteratorAggregate
{
	protected $query;
    protected $countQuery;
    protected $loader;
    protected $rowCount;

    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    public function setCountQuery($countQuery)
    {
        $this->countQuery = $countQuery;
        return $this;
    }

    public function setLoader($callback)
    {
        $this->loader = $callback;
        if(!is_callable($callback))
            throw new Exception\InvalidArgumentException('loader is not callable.');
        return $this;
    }

    public function count()
    {
        if($this->rowCount!==null)
            return $this->rowCount;
        if($this->countQuery===null)
            throw new Exception\DomainException('countQuery is not specified.');
        $result = $this->countQuery->getSingleResult();
        if($result===null)
            return $this->rowCount = 0;
        return $this->rowCount = $result;
    }

    public function getItems($offset, $itemMaxPerPage)
    {
        if($this->query===null)
            throw new Exception\DomainException('query is not specified.');
        $this->query->setFirstResult($offset);
        $this->query->setMaxResults($itemMaxPerPage);
        $result = $this->query->getResultList();
        if($result && $this->loader)
            $result->setLoader($this->loader);
        return $result;
    }

    public function getIterator()
    {
        if($this->query===null)
            throw new Exception\DomainException('query is not specified.');
        $sql = $this->query;
        $result = $this->query->getResultList();
        if($result && $this->loader)
            $result->setLoader($this->loader);
        return $result;
    }
}
