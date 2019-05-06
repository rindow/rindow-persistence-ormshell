<?php
namespace Rindow\Persistence\OrmShell;

class Module
{
    public function getConfig()
    {
        return array(
            'container' => array(
                'aliases' => array(
                    'Rindow\\Persistence\\Orm\\Repository\\DefaultEntityManager' => 'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultPersistenceContext',
                /*
                *    'Rindow\\Persistence\\OrmShell\\DefaultCriteriaMapper' => 'your Orm CriteriaMapper',
                *    'Rindow\\Persistence\\OrmShell\\DefaultResource'       => 'your Orm Resouce',
                *    'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultTransactionSynchronizationRegistry' => 'your TransactionSynchronizationRegistry',
                */
                ),
                'components' => array(
                    'Rindow\\Persistence\\OrmShell\\DefaultCriteriaBuilder' => array(
                        'class' => 'Rindow\\Persistence\\Orm\\Criteria\\CriteriaBuilder',
                    ),
                    'Rindow\\Persistence\\OrmShell\\Paginator\\DefaultPaginatorFactory' => array(
                        'class' => 'Rindow\\Persistence\\OrmShell\\Paginator\\PaginatorFactory',
                        'properties' => array(
                            'criteriaBuilder' => array('ref'=>'Rindow\\Persistence\\OrmShell\\DefaultCriteriaBuilder'),
                        ),
                    ),
                    'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultEntityManagerFactory' => array(
                        'class' => 'Rindow\\Persistence\\OrmShell\\Transaction\\EntityManagerFactory',
                        'properties' => array(
                            'config'=>array('config'=>'persistence'),
                            'serviceLocator'=>array('ref'=>'ServiceLocator'),
                            'criteriaMapper'=>array('ref'=>'Rindow\\Persistence\\OrmShell\\DefaultCriteriaMapper'),
                            //'resource'=>array('ref'=>'Rindow\\Persistence\\OrmShell\\DefaultResource'),
                        ),
                        'proxy' => 'disable',
                    ),
                    'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultPersistenceContext' => array(
                        'class' => 'Rindow\\Persistence\\OrmShell\\Transaction\\PersistenceContext',
                        'properties' => array(
                            'entityManagerHolder' => array('ref'=>'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultSynchronization'),
                        ),
                        'proxy' => 'disable',
                    ),
                    'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultSynchronization' => array(
                        'class' => 'Rindow\\Persistence\\OrmShell\\Transaction\\Synchronization',
                        'properties' => array(
                            'entityManagerFactory' => array('ref'=>'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultEntityManagerFactory'),
                            'synchronizationRegistry' => array('ref'=>'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultTransactionSynchronizationRegistry'),
                        ),
                        'proxy' => 'disable',
                    ),
                    'Rindow\\Persistence\\OrmShell\\DefaultEntityManager' => array(
                        'class' => 'Rindow\\Persistence\\OrmShell\\EntityManager',
                        'factory' => 'Rindow\\Persistence\\OrmShell\\Transaction\\EntityManagerFactory::factory',
                        'factory_args' => array(
                            'component' => 'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultEntityManagerFactory',
                        ),
                        'proxy' => 'disable',
                    ),
                    'Rindow\\Persistence\\OrmShell\\DefaultCriteriaContainer' => array(
                        'class' => 'Rindow\\Persistence\\Orm\\Criteria\\CriteriaContainer',
                        'properties' => array(
                            'criteriaBuilder' => array('ref'=>'Rindow\\Persistence\\OrmShell\\DefaultCriteriaBuilder'),
                            'criteriaMapper'  => array('ref'=>'Rindow\\Persistence\\OrmShell\\DefaultCriteriaMapper'),
                            'context'   => array('ref'=>'Rindow\\Persistence\\OrmShell\\DefaultEntityManager'),
                            'configCacheFactory' => array('ref'=>'ConfigCacheFactory'),
                        ),
                    ),
                ),
            ),
        );
    }

    public function checkDependency($config)
    {
        $aliases = array(
                'Rindow\\Persistence\\OrmShell\\DefaultCriteriaMapper',
                //'Rindow\\Persistence\\OrmShell\\DefaultResource',
                'Rindow\\Persistence\\OrmShell\\Transaction\\DefaultTransactionSynchronizationRegistry',
        );
        foreach ($aliases as $alias) {
            if(!isset($config['container']['aliases'][$alias]))
                throw new \DomainException('module configuration must include the alias "'.$alias.'".');
        }
    }
}