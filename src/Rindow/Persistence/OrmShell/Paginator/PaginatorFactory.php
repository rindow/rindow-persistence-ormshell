<?php
namespace Rindow\Persistence\OrmShell\Paginator;

use Rindow\Stdlib\Paginator\Paginator;
use Rindow\Persistence\Orm\Exception;
use Rindow\Persistence\OrmShell\Query;

class PaginatorFactory
{
    protected $criteriaBuilder;

    public function setCriteriaBuilder($criteriaBuilder)
    {
        $this->criteriaBuilder = $criteriaBuilder;
    }

    public function createPaginator(Query $query)
    {
        $preparedCriteria = $query->getPreparedCriteria();
        if(!is_object($preparedCriteria) || !method_exists($preparedCriteria, 'getCriteria'))
            throw new Exception\InvalidArgumentException('A query must be built on the CriteriaBuilder.');
        $criteriaQuery = $preparedCriteria->getCriteria();
        $countCriteria = clone $criteriaQuery;
        $countCriteria->select(
            $this->criteriaBuilder
                ->count($countCriteria->getRoots()));
        $entityManager = $query->getEntityManager();
        $countQuery = $entityManager->createQuery($countCriteria);
        foreach ($query->getParameters() as $name => $value) {
            $countQuery->setParameter($name,$value);
        }

        $adapter = new OrmAdaptor();
        $adapter->setQuery($query);
        $adapter->setCountQuery($countQuery);
        return new Paginator($adapter);
    }
}
