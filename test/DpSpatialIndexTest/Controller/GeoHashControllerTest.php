<?php
namespace DpSpatialIndexTest\Controller;

use DpSpatialIndex\Controller\GeoHashController;
use DpPHPUnitExtensions\PHPUnit\TestCase;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * Class NodeTest
 *
 * @package DpSpatialIndexTest\Controller
 */
class GeoHashControllerTest extends TestCase {
	const SUT = 'DpSpatialIndex\Controller\GeoHashController';
	/**
	 * @var GeoHashController
	 */
	protected $_geoHashController;
	/**
	 * @var array
	 */
	protected $_emptyState;
	public function setUp() {
		$this->_geoHashController = new GeoHashController();
		$manager = new ServiceManager(new Config(array(
		   'invokables' => array(
				'DpOpenGis\Model\Point' => 'DpOpenGis\Model\Point',
				'DpOpenGis\Validator\Point' => 'DpOpenGis\Validator\Point'),
		   'factories' => array(
		       'config' => function (ServiceLocatorInterface $sm) {
		           return new \Zend\Config\Config(array(
			           'DpSpatialIndex' => array(
				           'geoHash' => array(
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
		       }
		   ))));
		$this->_geoHashController->setServiceLocator($manager);
	}
	public function testGenerateGeoHash()
	{
		$this->assertSame('u4pruydqqvj',$this->_geoHashController->generateHash(10.40744,57.64911,55));
	}
	public function testReverseGeoHash()
	{
		$coords = $this->_geoHashController->revertCoords('u4pruydqqvj');
		$this->assertSame(10.40744,round($coords['lon'],5));
		$this->assertSame(57.64911,round($coords['lat'],5));
	}
}
