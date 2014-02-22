<?php
namespace DpSpatialIndexTest\Controller;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\Tools\ToolsException;
use DpOpenGis\Factory\LineStringFactory;
use DpOpenGis\Factory\MultiPolygonFactory;
use DpOpenGis\Factory\PointFactory;
use DpOpenGis\Factory\PolygonFactory;
use DpOpenGis\ModelInterface\IPointCollection;
use DpOsmParser\Factory\NodeFactory;
use DpOsmParser\Factory\NodeTagFactory;
use DpOsmParser\Factory\RelationFactory;
use DpOsmParser\Factory\RelationNodeFactory;
use DpOsmParser\Factory\RelationRelationFactory;
use DpOsmParser\Factory\RelationTagFactory;
use DpOsmParser\Factory\RelationWayFactory;
use DpOsmParser\Factory\WayFactory;
use DpOsmParser\Factory\WayNodeFactory;
use DpOsmParser\Factory\WayTagFactory;
use DpPHPUnitExtensions\PHPUnit\TestCase;
use DpSpatialIndex\Controller\IndexController;
use DpSpatialIndex\Factory\RasterRectangleFactory;
use DpSpatialIndex\Factory\RelationInRectangleFactory;
use DpSpatialIndex\Model\RasterRectangle;
use DpSpatialIndex\Model\RelationInRectangle;
use Zend\Cache\StorageFactory;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * Class IndexControllerTest
 *
 * @package DpSpatialIndexTest\Controller
 */
class IndexControllerTest extends TestCase {
	const SUT = 'DpSpatialIndex\Controller\IndexController';
	/**
	 * @var IndexController
	 */
	protected $_indexController;
	/**
	 * @var array
	 */
	protected $_emptyState;
	/**
	 * @var SchemaTool
	 */
	protected $_schemaTool;
	/**
	 * @var EntityManager
	 */
	protected $_entityManager;
	/**
	 * @var ServiceLocatorInterface
	 */
	protected $_serviceLocator;
	public function setUp() {
		$this->_indexController = new IndexController();
		$manager = new ServiceManager(new Config(array(
		   'aliases' => array(
			   'Cache' => 'Zend\Cache\Storage\Adapter\Memory',
			   'Redis' => 'Zend\Cache\Storage\Adapter\Redis',
			   'Memcache' => 'Zend\Cache\Storage\Adapter\Memory',
			   'Memcached' => 'Zend\Cache\Storage\Adapter\Memory',
			   'Apc' => 'Zend\Cache\Storage\Adapter\Memory',
			   'LongTermCache' => 'Zend\Cache\Storage\Adapter\Memory',
			   'ShortTermCache' => 'Zend\Cache\Storage\Adapter\Memory',
			   'IntraCache' => 'Zend\Cache\Storage\Adapter\Memory',
		   ),
		   'invokables' => array(
			   'DpOpenGis\Model\MultiPolygon'                   => 'DpOpenGis\Model\CachedMultiPolygon',
			   'DpOpenGis\Model\Polygon'                        => 'DpOpenGis\Model\CachedPolygon',
			   'DpOpenGis\Model\LineString'                     => 'DpOpenGis\Model\CachedLineString',
			   'DpOpenGis\Model\Point'                          => 'DpOpenGis\Model\Point',
			   'DpOpenGis\ModelInterface\IPointCollection'      => 'DpOpenGis\Collection\PointCollection',
			   'DpOpenGis\ModelInterface\IReversePointCollection' => 'DpOpenGis\Collection\ReversePointCollection',
			   'DpOpenGis\ModelInterface\ILineStringCollection' => 'DpOpenGis\Collection\LineStringCollection',
			   'DpOpenGis\ModelInterface\IPolygonCollection'    => 'DpOpenGis\Collection\PolygonCollection',
			   'DpOpenGis\Validator\MultiPolygon'               => 'DpOpenGis\Validator\MultiPolygon',
			   'DpOpenGis\Validator\Polygon'                    => 'DpOpenGis\Validator\Polygon',
			   'DpOpenGis\Validator\LineString'                 => 'DpOpenGis\Validator\LineString',
			   'DpOpenGis\Validator\Point'                      => 'DpOpenGis\Validator\Point',
			   'DpOsmParser\Model\Node' => 'DpOsmParser\Model\Node',
			   'DpOsmParser\Model\NodeTag' => 'DpOsmParser\Model\NodeTag',
			   'DpOsmParser\Model\Relation' => 'DpOsmParser\Model\Relation',
			   'DpOsmParser\Model\RelationNode' => 'DpOsmParser\Model\RelationNode',
			   'DpOsmParser\Model\RelationRelation' => 'DpOsmParser\Model\RelationRelation',
			   'DpOsmParser\Model\RelationTag' => 'DpOsmParser\Model\RelationTag',
			   'DpOsmParser\Model\RelationWay' => 'DpOsmParser\Model\RelationWay',
			   'DpOsmParser\Model\Way' => 'DpOsmParser\Model\Way',
			   'DpOsmParser\Model\WayNode' => 'DpOsmParser\Model\WayNode',
			   'DpOsmParser\Model\WayTag' => 'DpOsmParser\Model\WayTag',
			   'DpOsmParser\ModelInterface\INodeTagCollection' => 'DpOsmParser\Collection\NodeTagCollection',
			   'DpOsmParser\ModelInterface\IRelationNodeCollection' => 'DpOsmParser\Collection\RelationNodeCollection',
			   'DpOsmParser\ModelInterface\IRelationRelationCollection' =>
			        'DpOsmParser\Collection\RelationRelationCollection',
			   'DpOsmParser\ModelInterface\IRelationTagCollection' => 'DpOsmParser\Collection\RelationTagCollection',
			   'DpOsmParser\ModelInterface\IRelationWayCollection' => 'DpOsmParser\Collection\RelationWayCollection',
			   'DpOsmParser\ModelInterface\IWayNodeCollection' => 'DpOsmParser\Collection\WayNodeCollection',
			   'DpOsmParser\ModelInterface\IWayTagCollection' => 'DpOsmParser\Collection\WayTagCollection',
			   'DpOsmParser\Controller\Parser' => 'DpOsmParser\Controller\Parser',
			   'DpSpatialIndex\Controller\IndexController' => 'DpSpatialIndex\Controller\IndexController',
			   'DpSpatialIndex\Controller\GeoHashController' => 'DpSpatialIndex\Controller\GeoHashController',
			   'DpSpatialIndex\Collection\INodeCollection' => 'DpSpatialIndex\Collection\NodeCollection',
			   'DpSpatialIndex\Collection\IWayCollection' => 'DpSpatialIndex\Collection\WayCollection',
			   'DpSpatialIndex\Collection\IRelationInRectangleCollection' =>
			        'DpSpatialIndex\Collection\RelationInRectangleCollection',
			   'DpSpatialIndex\Model\RasterRectangle' => 'DpSpatialIndex\Model\RasterRectangle',
			   'DpSpatialIndex\Model\RelationInRectangle' => 'DpSpatialIndex\Model\RelationInRectangle',
			   'DpDoctrineExtensions\EventListener\ServiceLocatorInjector' =>
			        'DpDoctrineExtensions\EventListener\ServiceLocatorInjector',
			   'DpAsynchronJob\JobCenter\Manager' => 'DpAsynchronJob\JobCenter\Manager',
			   'DpAsynchronJob\ModelInterface\IJobManagement' => 'DpAsynchronJob\Model\ResqueJobManagement',
			   'DpZFExtensions\ServiceManager\ServiceLocatorDecorator' =>
			        'DpZFExtensions\ServiceManager\ServiceLocatorDecorator',
			   'Zend\Cache\Storage\StorageInterface' => 'Zend\Cache\Storage\Adapter\Redis',
			   'Zend\Cache\Storage\Adapter' => 'Zend\Cache\Storage\Adapter\Redis',
		   ),
		   'factories' => array(
			   'DpOpenGis\MappingType\MultiPolygonType'         => function (ServiceLocatorInterface $sm) {
				   if (!Type::hasType('multipolygon'))
					   Type::addType('multipolygon', 'DpOpenGis\MappingType\MultiPolygonType');
				   return Type::getType('multipolygon');
			   },
			   'DpOpenGis\MappingType\PolygonType'              => function (ServiceLocatorInterface $sm) {
				   if (!Type::hasType('polygon'))
					   Type::addType('polygon', 'DpOpenGis\MappingType\PolygonType');
				   return Type::getType('polygon');
			   },
			   'DpOpenGis\MappingType\LineStringType'           => function (ServiceLocatorInterface $sm) {
				   if (!Type::hasType('linestring'))
					   Type::addType('linestring', 'DpOpenGis\MappingType\LineStringType');
				   return Type::getType('linestring');
			   },
			   'DpOpenGis\MappingType\PointType'                => function (ServiceLocatorInterface $sm) {
				   if (!Type::hasType('point'))
					   Type::addType('point', 'DpOpenGis\MappingType\PointType');
				   return Type::getType('point');
			   },
			   'DpOpenGis\Factory\PointFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = PointFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOpenGis\Factory\LineStringFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = LineStringFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOpenGis\Factory\PolygonFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = PolygonFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOpenGis\Factory\MultiPolygonFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = MultiPolygonFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
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
			   'DpOsmParser\Factory\NodeFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = NodeFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\NodeTagFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = NodeTagFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\RelationFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = RelationFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\RelationNodeFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = RelationNodeFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\RelationRelationFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = RelationRelationFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\RelationTagFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = RelationTagFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\RelationWayFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = RelationWayFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\WayFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = WayFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\WayNodeFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = WayNodeFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'DpOsmParser\Factory\WayTagFactory' => function (ServiceLocatorInterface $sm) {
				   $factory = WayTagFactory::getInstance();
				   $factory->setServiceLocator($sm);
				   return $factory;
			   },
			   'Zend\Cache\Storage\Adapter\Memory' => function() {
				   return StorageFactory::adapterFactory('memory');
			   },
			   'config' => function (ServiceLocatorInterface $sm) {
		           return new \Zend\Config\Config(array(
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
				           )
			           )
		           ));
		       },
			   'doctrine.entitymanager.orm_default' => function (ServiceManager $serviceManager) {
				   $isDevMode = true;
				   $doctrineConfig =
					   Setup::createYAMLMetadataConfiguration(array(__DIR__."/../../../config/yaml"), $isDevMode);
				   // database configuration parameters
				   $conn = array(
					   'driver'   => 'pdo_mysql',
					   'host'     => 'amibeautiful.de',
					   'port'     => '3306',
					   'user'     => 'root',
					   'password' => 'of7K9KeqzJMfe5MU',
					   'dbname'   => 'test',
				   );
				   $cache = new ArrayCache();
				   $doctrineConfig->setQueryCacheImpl($cache);
				   $doctrineConfig->setResultCacheImpl($cache);
				   $doctrineConfig->setMetadataCacheImpl($cache);
				   $doctrineConfig->setHydrationCacheImpl($cache);
				   // obtaining the entity manager
				   $entityManager = EntityManager::create($conn, $doctrineConfig);
				   $serviceManager->get('DpOpenGis\MappingType\PointType');
				   $serviceManager->get('DpOpenGis\MappingType\LineStringType');
				   $serviceManager->get('DpOpenGis\MappingType\PolygonType');
				   $serviceManager->get('DpOpenGis\MappingType\MultiPolygonType');
				   $entityManager->getConnection()->getDatabasePlatform()
					   ->registerDoctrineTypeMapping('point', 'point');
				   $entityManager->getConnection()->getDatabasePlatform()
					   ->registerDoctrineTypeMapping('linestring', 'linestring');
				   $entityManager->getConnection()->getDatabasePlatform()
					   ->registerDoctrineTypeMapping('polygon', 'polygon');
				   $entityManager->getConnection()->getDatabasePlatform()
					   ->registerDoctrineTypeMapping('multipolygon', 'multipolygon');
				   $entityManager->getEventManager()->addEventListener(array(Events::postLoad),
				                 $serviceManager->get('DpDoctrineExtensions\EventListener\ServiceLocatorInjector'));
				   $this->_schemaTool = new SchemaTool($entityManager);
				   try {
				        $this->_schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
				   } catch (ToolsException $e) {
					   $this->_schemaTool->dropSchema($entityManager->getMetadataFactory()->getAllMetadata());
					   $this->_schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
				   }
				   $this->_entityManager = $entityManager;
				   return $entityManager;
			   }
		   ),
		   'initializers' => array(
			   function($instance, $serviceManager) {
				   if ($instance instanceof ServiceLocatorAwareInterface) {
					   $instance->setServiceLocator($serviceManager);
				   }
			   }
		   ))));
		$this->_serviceLocator = $manager;
		$this->_indexController->setServiceLocator($manager);
		$this->_entityManager = $manager->get('doctrine.entitymanager.orm_default');
	}
	protected function _generatePolygon($points,$startId)
	{
		$defaultValues = array(
			'version' => 1,
			'timestamp' => new \DateTime(),
			'changeset' => 1
		);
		/** @var $nodeCollection \DpOsmParser\ModelInterface\IRelationWayCollection */
		$wayCollection = clone $this->_serviceLocator->get('DpOsmParser\ModelInterface\IRelationWayCollection');
		/** @var \DpOsmParser\Model\Relation $relation */
		$relation = $this->_serviceLocator->get('DpOsmParser\Factory\RelationFactory')->create('Relation',
	                                                                                       array(
                                                                                            'relationId' => $startId,
                                                                                            'ways' => $wayCollection
	                                                                                       )+$defaultValues
		);
		$this->_entityManager->persist($relation);
		/** @var $nodeCollection \DpOsmParser\ModelInterface\IWayNodeCollection */
		$nodeCollection = clone $this->_serviceLocator->get('DpOsmParser\ModelInterface\IWayNodeCollection');
		/** @var \DpOsmParser\Model\Way $way */
		$way = $this->_serviceLocator->get('DpOsmParser\Factory\WayFactory')->create('Way',
		                                                                            array(
		                                                                                 'wayId' => $startId+1,
		                                                                                 'wayNodes' => $nodeCollection
		                                                                            )+$defaultValues
		);
		$this->_entityManager->persist($way);
		$relationWay = $this->_serviceLocator->get('DpOsmParser\Factory\RelationWayFactory')->create('RelationWay',
	                                                                                         array(
                                                                                                'way' => $way,
                                                                                                'relation' => $relation,
                                                                                                'role' => 'outer'
	                                                                                         ));
		$this->_entityManager->persist($relationWay);
		$wayCollection->add($relationWay);
		$first = null;
		$nr = 0;
		foreach ($points as $nr => $point) {
			/** @var \DpOsmParser\Model\Node $node */
			$node = $this->_serviceLocator->get('DpOsmParser\Factory\NodeFactory')->create('Node',
			                                                                              array(
		                                                                                   'nodeId' => $startId+2+$nr,
		                                                                                   'lon' => $point[0],
		                                                                                   'lat' => $point[1],
			                                                                              )+$defaultValues
			);
			$node->generatePoint();
			$this->_entityManager->persist($node);
			if (!isset($first))
				$first = $node;
			$wayNode = $this->_serviceLocator->get('DpOsmParser\Factory\WayNodeFactory')->create('WayNode',array(
			                                                                                         'node' => $node,
			                                                                                         'way' => $way,
			                                                                                         'step' => $nr
			                                                                                    ));
			$this->_entityManager->persist($wayNode);
			$nodeCollection->add($wayNode);
		}
		$wayNode = $this->_serviceLocator->get('DpOsmParser\Factory\WayNodeFactory')->create('WayNode',array(
                                                                                                'node' => $first,
                                                                                                'way' => $way,
                                                                                                'step' => $nr+1
		                                                                                               ));
		$this->_entityManager->persist($wayNode);
		$nodeCollection->add($wayNode);
		$way->generateLineString();
		$relation->generateMultiPolygon();
		$this->_entityManager->flush();
	}
	public function testIndex() {
		$startId = 1;
		$parallelProcesses = 2;
		$serialProcesses = 2;
		$points = array(
			array(-80.0,-40.0),
			array(-80.0,40.0),
			array(80.0,40.0),
			array(80.0,-40.0),
		);
		$this->_generatePolygon($points,$startId);

		/** @var \DpSpatialIndex\Controller\GeoHashController $geoHashController  */
		$geoHashController = $this->_serviceLocator->get('DpSpatialIndex\Controller\GeoHashController');
		$jobs = array(
			array('startDepth' => 2,'maxDepth' => 2,'ur' => $geoHashController->generateHash(180,90),
			      'bl' => $geoHashController->generateHash(0,0)),
			array('startDepth' => 2,'maxDepth' => 2,'ur' => $geoHashController->generateHash(180,0),
			      'bl' => $geoHashController->generateHash(0,-90)),
			array('startDepth' => 2,'maxDepth' => 2,'ur' => $geoHashController->generateHash(0,0),
			      'bl' => $geoHashController->generateHash(-180,-90)),
			array('startDepth' => 2,'maxDepth' => 2,'ur' => $geoHashController->generateHash(0,90),
			      'bl' => $geoHashController->generateHash(-180,0)),
		);

		$jobManagement = $this->getMock('DpAsynchronJob\ModelInterface\IJobManagement');
		$jobManagement->expects($this->any())->method('addJob')->will($this->returnCallback(function($queueName,$param)
			use ($startId,$parallelProcesses,$serialProcesses,$geoHashController,&$jobs) {
			$this->assertSame($queueName,'createSpatialIndex');
			$this->assertArrayHasKey('command',$param);
			preg_match('/spatial create index delta --relation=([0-9]+) --polygonNumber=([0-9]+) --maxDepth=([0-9]+)'.
				           ' --jobs=(([0-9]+.[a-zA-Z0-9]+.[a-zA-Z0-9]+,)*[0-9]+.[a-zA-Z0-9]+.[a-zA-Z0-9]+)'.
				           ' --parallelProcesses=([0-9]+) --serialProcesses=([0-9]+)/',
			           $param['command'],
				$matches);
			$this->assertEquals(2,count(explode(',',$matches[4])));
			foreach (explode(',',$matches[4]) as $job) {
				list($matchedStartDepth,$matchedUR,$matchedBL) = explode('.',$job);
				foreach ($jobs as $nr => $jobArray) {
					if ($jobArray['ur'] == $matchedUR) {
						extract($jobArray);
						unset($jobs[$nr]);
						break;
					}
				}
				if (isset($startDepth) && isset($maxDepth) && isset($ur) && isset($bl)) {
					$this->assertEquals($startId,$matches[1]);
					$this->assertEquals(0,$matches[2]);
					$this->assertEquals($startDepth,$matchedStartDepth);
					$this->assertEquals($maxDepth,$matches[3]);
					$this->assertEquals($ur,$matchedUR);
					$this->assertEquals($bl,$matchedBL);
					$this->assertEquals($parallelProcesses,$matches[6]);
					$this->assertEquals($serialProcesses,$matches[7]);
				}
				else {
					$this->fail('Job with wrong ur created: '.$matchedUR.' ('.implode(',',
					                                               $geoHashController->revertCoords($matchedUR)).')');
				}
			}
		}));
		/** @var \DpZFExtensions\ServiceManager\ServiceLocatorDecorator $decorator */
		$decorator = clone $this->_serviceLocator->get('DpZFExtensions\ServiceManager\ServiceLocatorDecorator');
		$decorator->setService('DpAsynchronJob\ModelInterface\IJobManagement',$jobManagement);
		$decorator->setDecoree($this->_serviceLocator);
		/** @var \DpAsynchronJob\JobCenter\Manager $jobManager */
		$jobManager = $this->_serviceLocator->get('DpAsynchronJob\JobCenter\Manager');
		$jobManager->setServiceLocator($decorator);
		$this->_indexController->deltaCreateByRelation(1,2,$geoHashController->generateHash(180,90),
	                                          $geoHashController->generateHash(-180,-90),$parallelProcesses,$startId,
	                                          null,$serialProcesses);
		$this->assertEmpty($jobs);
	}
	public function testIndexExtended() {
		$startId = 1;
		$parallelProcesses = 4;
		$serialProcesses = 1;
		$points = array(
			array(-80.0,-40.0),
			array(-80.0,40.0),
			array(80.0,40.0),
			array(80.0,-40.0),
		);
		$this->_generatePolygon($points,$startId);

		/** @var \DpSpatialIndex\Controller\GeoHashController $geoHashController  */
		$geoHashController = $this->_serviceLocator->get('DpSpatialIndex\Controller\GeoHashController');
		$jobs = array(
			array('startDepth' => 2,'maxDepth' => 2,'ur' => $geoHashController->generateHash(180,90),
			      'bl' => $geoHashController->generateHash(0,0)),
			array('startDepth' => 2,'maxDepth' => 2,'ur' => $geoHashController->generateHash(180,0),
			      'bl' => $geoHashController->generateHash(0,-90)),
			array('startDepth' => 2,'maxDepth' => 2,'ur' => $geoHashController->generateHash(0,0),
			      'bl' => $geoHashController->generateHash(-180,-90)),
			array('startDepth' => 2,'maxDepth' => 2,'ur' => $geoHashController->generateHash(0,90),
			      'bl' => $geoHashController->generateHash(-180,0)),
		);

		$jobManagement = $this->getMock('DpAsynchronJob\ModelInterface\IJobManagement');
		$jobManagement->expects($this->any())->method('addJob')->will($this->returnCallback(function($queueName,$param)
		use ($startId,$parallelProcesses,$serialProcesses,$geoHashController,&$jobs) {
			$this->assertSame($queueName,'createSpatialIndex');
			$this->assertArrayHasKey('command',$param);
			preg_match('/spatial create index delta --relation=([0-9]+) --polygonNumber=([0-9]+) --maxDepth=([0-9]+)'.
				           ' --jobs=(([0-9]+.[a-zA-Z0-9]+.[a-zA-Z0-9]+,)*[0-9]+.[a-zA-Z0-9]+.[a-zA-Z0-9]+)'.
				           ' --parallelProcesses=([0-9]+) --serialProcesses=([0-9]+)/',
			           $param['command'],
			           $matches);
			foreach (explode(',',$matches[4]) as $job) {
				list($matchedStartDepth,$matchedUR,$matchedBL) = explode('.',$job);
				foreach ($jobs as $nr => $jobArray) {
					if ($jobArray['ur'] == $matchedUR) {
						extract($jobArray);
						unset($jobs[$nr]);
						break;
					}
				}
				if (isset($startDepth) && isset($maxDepth) && isset($ur) && isset($bl)) {
					$this->assertEquals($startId,$matches[1]);
					$this->assertEquals(0,$matches[2]);
					$this->assertEquals($startDepth,$matchedStartDepth);
					$this->assertEquals($maxDepth,$matches[3]);
					$this->assertEquals($ur,$matchedUR);
					$this->assertEquals($bl,$matchedBL);
					$this->assertEquals($parallelProcesses,$matches[6]);
					$this->assertEquals($serialProcesses,$matches[7]);
				}
				else {
					$this->fail('Job with wrong ur created: '.$matchedUR.' ('.implode(',',
					                                                                  $geoHashController->revertCoords($matchedUR)).')');
				}
			}
		}));
		/** @var \DpZFExtensions\ServiceManager\ServiceLocatorDecorator $decorator */
		$decorator = clone $this->_serviceLocator->get('DpZFExtensions\ServiceManager\ServiceLocatorDecorator');
		$decorator->setService('DpAsynchronJob\ModelInterface\IJobManagement',$jobManagement);
		$decorator->setDecoree($this->_serviceLocator);
		/** @var \DpAsynchronJob\JobCenter\Manager $jobManager */
		$jobManager = $this->_serviceLocator->get('DpAsynchronJob\JobCenter\Manager');
		$jobManager->setServiceLocator($decorator);
		$this->_indexController->deltaCreateByRelation(1,2,$geoHashController->generateHash(180,90),
		                                        $geoHashController->generateHash(-180,-90),$parallelProcesses,$startId,
		                                        null,$serialProcesses);
		$this->assertEmpty($jobs);

		$rectangles = array(
			array('depth' => 1,'ur' => $geoHashController->generateHash(180,90),
			      'bl' => $geoHashController->generateHash(0,0),'coverage' => 0.5),
			array('depth' => 1,'ur' => $geoHashController->generateHash(180,0),
			      'bl' => $geoHashController->generateHash(0,-90),'coverage' => 0.5),
			array('depth' => 1,'ur' => $geoHashController->generateHash(0,0),
			      'bl' => $geoHashController->generateHash(-180,-90),'coverage' => 0.5),
			array('depth' => 1,'ur' => $geoHashController->generateHash(0,90),
			      'bl' => $geoHashController->generateHash(-180,0),'coverage' => 0.5),
			array('depth' => 2,'ur' => $geoHashController->generateHash(90,45),
			      'bl' => $geoHashController->generateHash(0,0),'coverage' => 0.5),
			array('depth' => 2,'ur' => $geoHashController->generateHash(90,0),
			      'bl' => $geoHashController->generateHash(0,-45),'coverage' => 0.5),
			array('depth' => 2,'ur' => $geoHashController->generateHash(0,0),
			      'bl' => $geoHashController->generateHash(-90,-45),'coverage' => 0.5),
			array('depth' => 2,'ur' => $geoHashController->generateHash(0,45),
			      'bl' => $geoHashController->generateHash(-90,0),'coverage' => 0.5),
		);
		foreach ($this->_entityManager->getRepository('DpSpatialIndex\Model\RasterRectangle')->findAll() as $rectangle){
			/** @var RasterRectangle $rectangle */
			foreach ($rectangles as $nr => $rectData) {
				if ($rectData['ur'] == $rectangle->getGeoHashUR() && $rectData['bl'] == $rectangle->getGeoHashBL()) {
					extract($rectData);
					unset($rectangles[$nr]);
					break;
				}
			}
			if (isset($depth) && isset($coverage) && isset($ur) && isset($bl)) {
				/** @var RelationInRectangle $relation */
				$relation = $rectangle->getRelations()->first();
				$this->assertEquals($depth,$rectangle->getDepth());
				$this->assertEquals($coverage,$relation->getCoverage());
				$this->assertEquals($ur,$rectangle->getGeoHashUR());
				$this->assertEquals($bl,$rectangle->getGeoHashBL());
			}
			else {
				$this->fail('Rectangle with wrong ur created. UR: '.$rectangle->getGeoHashUR().' ('.implode(',',
				            $geoHashController->revertCoords($rectangle->getGeoHashUR())).'), BL: '.
					            $rectangle->getGeoHashBL().' ('.implode(',',
				            $geoHashController->revertCoords($rectangle->getGeoHashBL())).')');
			}

		}
	}
	public function testIndexExtended2() {
		/** @var \DpSpatialIndex\Controller\GeoHashController $geoHashController  */
		$geoHashController = $this->_serviceLocator->get('DpSpatialIndex\Controller\GeoHashController');
		/** @var IPointCollection $pointCollectionPrototype */
		$pointCollectionPrototype = $this->_serviceLocator->get('DpOpenGis\ModelInterface\IPointCollection');
		/** @var PointFactory $pointFactory */
		$pointFactory = $this->_serviceLocator->get('DpOpenGis\Factory\PointFactory');
		/** @var LineStringFactory $lineStringFactory */
		$lineStringFactory = $this->_serviceLocator->get('DpOpenGis\Factory\LineStringFactory');

		$startId = 1;
		$parallelProcesses = 4;
		$points = array(
			array(-80.0,-40.0),
			array(-80.0,40.0),
			array(80.0,40.0),
			array(80.0,0.0),
			array(0.0,0.0),
			array(0.0,-40.0),
			array(-80.0,-40.0),
		);
		$this->_generatePolygon($points,$startId);

		$outerRectangles = array(
			array('lonUR' => 180.0,'latUR' => 90.0,'lonBL' => 0.0,'latBL' => 0.0,'coverage' => 0.5),
			array('lonUR' => 180.0,'latUR' => 0.0,'lonBL' => 0.0,'latBL' => -90.0,'coverage' => 0.5),
			array('lonUR' => 0.0,'latUR' => 0.0,'lonBL' => -180.0,'latBL' => -90.0,'coverage' => 0.0),
			array('lonUR' => 0.0,'latUR' => 90.0,'lonBL' => -180.0,'latBL' => 0.0,'coverage' => 1.0),
		);
		$relation = $this->_entityManager->find('DpOsmParser\Model\Relation',$startId);
		foreach ($outerRectangles as $outer) {
			$points = clone $pointCollectionPrototype;
			$points->add($pointFactory->create('Point',array('lon' => $outer['lonBL'],'lat' => $outer['latBL'])));
			$points->add($pointFactory->create('Point',array('lon' => $outer['lonUR'],'lat' => $outer['latBL'])));
			$points->add($pointFactory->create('Point',array('lon' => $outer['lonUR'],'lat' => $outer['latUR'])));
			$points->add($pointFactory->create('Point',array('lon' => $outer['lonBL'],'lat' => $outer['latUR'])));
			$points->add($pointFactory->create('Point',array('lon' => $outer['lonBL'],'lat' => $outer['latBL'])));
			$lineString = $lineStringFactory->create('LineString',array('points' => $points));
			$rect = $this->_serviceLocator->get('DpSpatialIndex\Factory\RasterRectangleFactory')->create(
				'RasterRectangle',
				array(
				     'geoHashUR' => $geoHashController->generateHash($outer['lonUR'],$outer['latUR']),
				     'geoHashBL' => $geoHashController->generateHash($outer['lonBL'],$outer['latBL']),
				     'depth' => 1,
				     'lineString' => $lineString
				)
			);
			$this->_entityManager->persist($rect);
			$this->_entityManager->flush();
			$rasterInRect = $this->_serviceLocator->get('DpSpatialIndex\Factory\RelationInRectangleFactory')->create(
				'RelationInRectangle',
			     array(
			          'relation' => $relation,
			          'rasterRectangle' => $rect,
			          'coverage' => $outer['coverage'],
			     )
			);
			$this->_entityManager->persist($rasterInRect);
			$this->_entityManager->flush();
		}
		$this->_entityManager->clear();

		$jobManagement = $this->getMock('DpAsynchronJob\ModelInterface\IJobManagement');
		/** @var \DpZFExtensions\ServiceManager\ServiceLocatorDecorator $decorator */
		$decorator = clone $this->_serviceLocator->get('DpZFExtensions\ServiceManager\ServiceLocatorDecorator');
		$decorator->setService('DpAsynchronJob\ModelInterface\IJobManagement',$jobManagement);
		$decorator->setDecoree($this->_serviceLocator);
		/** @var \DpAsynchronJob\JobCenter\Manager $jobManager */
		$jobManager = $this->_serviceLocator->get('DpAsynchronJob\JobCenter\Manager');
		$jobManager->setServiceLocator($decorator);
		$this->_indexController->deltaCreateByRelation(2,2,$geoHashController->generateHash(180,90),
		                                   $geoHashController->generateHash(-180,-90),$parallelProcesses,$startId);

		$rectangles = array(
			array('depth' => 1,'ur' => $geoHashController->generateHash(180,90),
			      'bl' => $geoHashController->generateHash(0,0),'coverage' => 0.5),
			array('depth' => 1,'ur' => $geoHashController->generateHash(180,0),
			      'bl' => $geoHashController->generateHash(0,-90),'coverage' => 0.5),
			array('depth' => 1,'ur' => $geoHashController->generateHash(0,0),
			      'bl' => $geoHashController->generateHash(-180,-90),'coverage' => 0.0),
			array('depth' => 1,'ur' => $geoHashController->generateHash(0,90),
			      'bl' => $geoHashController->generateHash(-180,0),'coverage' => 1.0),
			array('depth' => 2,'ur' => $geoHashController->generateHash(90,45),
			      'bl' => $geoHashController->generateHash(0,0),'coverage' => 0.5),
			array('depth' => 2,'ur' => $geoHashController->generateHash(90,0),
			      'bl' => $geoHashController->generateHash(0,-45),'coverage' => 0.0),
			array('depth' => 2,'ur' => $geoHashController->generateHash(0,0),
			      'bl' => $geoHashController->generateHash(-90,-45),'coverage' => 0.0),
			array('depth' => 2,'ur' => $geoHashController->generateHash(0,45),
			      'bl' => $geoHashController->generateHash(-90,0),'coverage' => 1.0),
		);
		$query = $this->_entityManager->createQuery('SELECT r FROM DpSpatialIndex\Model\RasterRectangle r JOIN '.
			                                            'r._relations');
		foreach ($query->getResult() as $rectangle){
			/** @var RasterRectangle $rectangle */
			foreach ($rectangles as $nr => $rectData) {
				if ($rectData['ur'] == $rectangle->getGeoHashUR() && $rectData['bl'] == $rectangle->getGeoHashBL()) {
					extract($rectData);
					unset($rectangles[$nr]);
					break;
				}
			}
			if (isset($depth) && isset($coverage) && isset($ur) && isset($bl)) {
				/** @var RelationInRectangle $relation */
				$relation = $rectangle->getRelations()->get(0);
				$this->assertEquals($depth,$rectangle->getDepth());
				$this->assertEquals($coverage,$relation->getCoverage());
				$this->assertEquals($ur,$rectangle->getGeoHashUR());
				$this->assertEquals($bl,$rectangle->getGeoHashBL());
			}
			else {
				$this->fail('Rectangle with wrong ur created. UR: '.$rectangle->getGeoHashUR().' ('.implode(',',
				                                                                                            $geoHashController->revertCoords($rectangle->getGeoHashUR())).'), BL: '.
					            $rectangle->getGeoHashBL().' ('.implode(',',
				                                                        $geoHashController->revertCoords($rectangle->getGeoHashBL())).')');
			}

		}
	}
	public function testMultiJobStarter() {
		/** @var \DpSpatialIndex\Controller\GeoHashController $geoHashController  */
		$geoHashController = $this->_serviceLocator->get('DpSpatialIndex\Controller\GeoHashController');

		$request = $this->getMock('Zend\Console\Request',array('getParam'),array(),'',false);
		$request->expects($this->atLeastOnce())->method('getParam')->will($this->returnCallback(function($key) {
			/** @var \DpSpatialIndex\Controller\GeoHashController $geoHashController  */
			$geoHashController = $this->_serviceLocator->get('DpSpatialIndex\Controller\GeoHashController');
			switch ($key) {
				case 'relation': return 2;
				case 'maxDepth': return 3;
				case 'parallelProcesses': return 4;
				case 'serialProcesses': return 5;
				case 'id': return 6;
				case 'polygonNumber': return 0;
				case 'jobs': return '3.'.$geoHashController->generateHash(20,20).'.'.
					$geoHashController->generateHash(10,10).','.'3.'.$geoHashController->generateHash(30,30).'.'.
					$geoHashController->generateHash(20,20).','.'3.'.$geoHashController->generateHash(60,60).'.'.
					$geoHashController->generateHash(20,20);
			}
			return null;
		}));
		$jobs = array(
			$geoHashController->generateHash(20,20) => array('depth' => 3,
			      'bl' => $geoHashController->generateHash(10,10)),
			$geoHashController->generateHash(30,30) => array('depth' => 3,
			      'bl' => $geoHashController->generateHash(20,20)),
			$geoHashController->generateHash(60,60) => array('depth' => 3,
			      'bl' => $geoHashController->generateHash(20,20)),
		);
		$indexController = $this->getMock('DpSpatialIndex\Controller\IndexController',array('deltaCreateByRelation',
		                                                                              'getRequest'));
		$indexController->setServiceLocator($this->_serviceLocator);
		$indexController->expects($this->atLeastOnce())->method('deltaCreateByRelation')->will($this->returnCallback(
			function ($startDepth,$maxDepth,$UR,$BL,$parallelProcesses,$relation,$polygonNumber,$serialProcesses)
				use (&$jobs) {
				if (!isset($jobs[$UR]))
					$this->fail('Wrong job started');
				$this->assertEquals($jobs[$UR]['bl'],$BL);
				$this->assertEquals($jobs[$UR]['depth'],$startDepth);
				$this->assertEquals(3,$maxDepth);
				$this->assertEquals(2,$relation);
				$this->assertEquals(4,$parallelProcesses);
				$this->assertEquals(5,$serialProcesses);
				$this->assertEquals(0,$polygonNumber);
				unset($jobs[$UR]);
			}
		));
		$indexController->expects($this->atLeastOnce())->method('getRequest')->will($this->returnValue($request));
		$indexController->deltaCreateByRelationAction();
		$this->assertEmpty($jobs);
	}
	public function testMultiPolygonStart() {
		$startId = 1;
		$startId2 = 10;
		$parallelProcesses = 2;
		$serialProcesses = 2;
		$points = array(
			array(-80.0,-40.0),
			array(-80.0,40.0),
			array(80.0,40.0),
			array(80.0,-40.0),
		);
		$points2 = array(
			array(90.0,50.0),
			array(110.0,50.0),
			array(110.0,60.0),
			array(90.0,60.0),
		);
		$this->_generatePolygon($points,$startId);
		$this->_generatePolygon($points2,$startId2);

		/** @var \DpSpatialIndex\Controller\GeoHashController $geoHashController  */
		$geoHashController = $this->_serviceLocator->get('DpSpatialIndex\Controller\GeoHashController');
		$jobManagement = $this->getMock('DpAsynchronJob\ModelInterface\IJobManagement');
		$jobManagement->expects($this->exactly(2))->method('addJob')->will($this->returnCallback(function($queueName,
		                                                                                                  $param)
		use (&$startId,$parallelProcesses,$serialProcesses,$geoHashController,&$startId2) {
			$this->assertSame($queueName,'createSpatialIndex');
			$this->assertArrayHasKey('command',$param);
			preg_match('/spatial create index delta --relation=([0-9]+) --maxDepth=([0-9]+)'.
				           ' --jobs=(([0-9]+.[a-zA-Z0-9]+.[a-zA-Z0-9]+,)*[0-9]+.[a-zA-Z0-9]+.[a-zA-Z0-9]+)'.
				           ' --parallelProcesses=([0-9]+) --serialProcesses=([0-9]+)/',
			           $param['command'],
			           $matches);
			$this->assertEquals(1,count(explode(',',$matches[3])));
			foreach (explode(',',$matches[3]) as $job) {
				list($matchedStartDepth,$matchedUR,$matchedBL) = explode('.',$job);
				if (isset($matchedStartDepth) && isset($matchedUR) && isset($matchedBL)) {
					if ($startId == $matches[1])
						$startId = null;
					elseif ($startId2 == $matches[1])
						$startId2 = null;
					$this->assertEquals(1,$matchedStartDepth);
					$this->assertEquals(2,$matches[2]);
					$this->assertEquals($geoHashController->generateHash(180,90),$matchedUR);
					$this->assertEquals($geoHashController->generateHash(-180,-90),$matchedBL);
					$this->assertEquals($parallelProcesses,$matches[5]);
					$this->assertEquals($serialProcesses,$matches[6]);
				}
				else {
					$this->fail('Job with wrong ur created: '.$matchedUR.' ('.implode(',',
					$geoHashController->revertCoords($matchedUR)).')');
				}
			}
		}));
		/** @var \DpZFExtensions\ServiceManager\ServiceLocatorDecorator $decorator */
		$decorator = clone $this->_serviceLocator->get('DpZFExtensions\ServiceManager\ServiceLocatorDecorator');
		$decorator->setService('DpAsynchronJob\ModelInterface\IJobManagement',$jobManagement);
		$decorator->setDecoree($this->_serviceLocator);
		/** @var \DpAsynchronJob\JobCenter\Manager $jobManager */
		$jobManager = $this->_serviceLocator->get('DpAsynchronJob\JobCenter\Manager');
		$jobManager->setServiceLocator($decorator);
		$this->_indexController->deltaCreateByRelation(1,2,$geoHashController->generateHash(180,90),
		                                               $geoHashController->generateHash(-180,-90),$parallelProcesses,
		                                               null,null,$serialProcesses);
		$this->assertNull($startId);
		$this->assertNull($startId2);
	}
	public function tearDown() {
		if (isset($this->_entityManager) && isset($this->_schemaTool))
			$this->_schemaTool->dropSchema($this->_entityManager->getMetadataFactory()->getAllMetadata());
	}
}
