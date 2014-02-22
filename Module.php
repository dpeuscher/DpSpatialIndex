<?php

namespace DpSpatialIndex;

use DpSpatialIndex\Factory\RasterRectangleFactory;
use DpSpatialIndex\Factory\RelationInRectangleFactory;
use Zend\Console\Adapter\AbstractAdapter;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class Module
 *
 * @package DpSpatialIndex
 */
class Module
{
	/**
	 * @return array
	 */

    public function getConfig()
    {
	    return include __DIR__ . '/config/module.config.php';
    }
	/**
	 * @return array
	 */
	public function getServiceConfig()
    {
	    return array(
		    'invokables' => array(
				'DpSpatialIndex\Controller\IndexController' => 'DpSpatialIndex\Controller\IndexController',
				'DpSpatialIndex\Controller\GeoHashController' => 'DpSpatialIndex\Controller\GeoHashController',
				'DpSpatialIndex\Collection\INodeCollection' => 'DpSpatialIndex\Collection\NodeCollection',
				'DpSpatialIndex\Collection\IWayCollection' => 'DpSpatialIndex\Collection\WayCollection',
				'DpSpatialIndex\Collection\IRelationInRectangleCollection' => 'DpSpatialIndex\Collection\RelationInRectangleCollection',
				'DpSpatialIndex\Model\RasterRectangle' => 'DpSpatialIndex\Model\RasterRectangle',
				'DpSpatialIndex\Model\RelationInRectangle' => 'DpSpatialIndex\Model\RelationInRectangle',
		    ),
		    'factories' => array(
			    'DpSpatialIndex\Factory\RasterRectangleFactory' => function (ServiceLocatorInterface $sm) {
				    $factory = RasterRectangleFactory::getInstance();
				    $factory->setServiceLocator($sm);
				    return $factory;
			    },
			    'DpSpatialIndex\Factory\RelationInRectangleFactory' => function (ServiceLocatorInterface $sm) {
				    $factory = RelationInRectangleFactory::getInstance();
				    $factory->setServiceLocator($sm);
				    return $factory;
			    },
		    ),
		    'initializers' => array(
			    function($instance, $serviceManager) {
				    if ($instance instanceof ServiceLocatorAwareInterface) {
					    $instance->setServiceLocator($serviceManager);
				    }
			    }
		    )
	    );
    }
	public function getConsoleUsage(AbstractAdapter $console)
	{
		return array(
			// Describe available commands
			'spatial create index [--relation=] --depth=',

			// Describe expected parameters
			array('--relation=','(optional) only add one relation into the index'),
			array('--depth=','how deep should the index be? The deeper, the bigger, the faster (queries)'),

			'spatial create index delta [--relation=] [--polygonNumber=]'.
				' --maxDepth= [--jobs=] --parallelProcesses= [--serialProcesses=] [--id=]',
			array('--relation=','(optional) only add one relation into the index'),
			array('--polygonNumber=','(optional) only add one Polygon of a relation into the index'),
			array('--maxDepth=','how deep should the index be? The deeper, the bigger, the faster (queries)'),
			array('--jobs=','(optional) the jobs to execute comma-separated (format: "minDepth.UR.BL,minDepth.UR.BL")'),
			array('--parallelProcesses=','how many processes should be started for parallel indexing?'),
			array('--serialProcesses=','how many processes should be started in one job?'),
			array('--id=','(optional) prefix of the redis-keys for profiling'),
		);
	}
	/**
	 * @return array
	 */
	public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}
