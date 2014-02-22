<?php
namespace DpSpatialIndex;
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

return array(
	'controllers' => array(
		'invokables' => array(
			'DpSpatialIndex\Controller\IndexController' => 'DpSpatialIndex\Controller\IndexController'
		),
	),
	'console' => array(
	    'router' => array(
	        'routes' => array(
		        'spatial-create-index' => array(
	                'options' => array(
	                    'route' => 'spatial create index [--relation=] --depth=',
	                    'defaults' => array(
	                        'controller' => 'DpSpatialIndex\Controller\IndexController',
	                        'action'     => 'createByRelation',
	                    ),
	                ),
	            ),
		        'spatial-create-index-delta' => array(
			        'options' => array(
				        'route' => 'spatial create index delta [--relation=] [--polygonNumber=]'.
				            ' --maxDepth= [--jobs=] --parallelProcesses= [--serialProcesses=] [--id=]',
				        'defaults' => array(
					        'controller' => 'DpSpatialIndex\Controller\IndexController',
					        'action'     => 'deltaCreateByRelation',
				        )
			        )
		        )
	        ),
	    ),
	),
	'DpSpatialIndex' => array(
		'geoHash' => array(
			'defaultPrecision' => 80,
			'base32Map' => array(
				0 => '0',
				1 => '1',
				2 => '2',
				3 => '3',
				4 => '4',
				5 => '5',
				6 => '6',
				7 => '7',
				8 => '8',
				9 => '9',
				10 => 'b',
				11 => 'c',
				12 => 'd',
				13 => 'e',
				14 => 'f',
				15 => 'g',
				16 => 'h',
				17 => 'j',
				18 => 'k',
				19 => 'm',
				20 => 'n',
				21 => 'p',
				22 => 'q',
				23 => 'r',
				24 => 's',
				25 => 't',
				26 => 'u',
				27 => 'v',
				28 => 'w',
				29 => 'x',
				30 => 'y',
				31 => 'z',
			)
		),
	),
	'doctrine' => array(
        'driver' => array(
            __NAMESPACE__ . '_driver' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\YamlDriver',
                'cache' => 'array',
                'paths' => array(getcwd()."/config/yaml")
            ),
            'orm_default' => array(
                'drivers' => array(
	                __NAMESPACE__ . '\Model' => __NAMESPACE__ . '_driver',
                )
            ),
        )
	)
);
