<?php
/**
 * User: dpeuscher
 * Date: 02.04.13
 */

namespace DpSpatialIndex\Factory;


use DpZFExtensions\ServiceManager\AbstractModelFactory;

/**
 * Class RasterRectangleFactory
 *
 * @package DpSpatialIndex\Factory
 */
class RasterRectangleFactory extends AbstractModelFactory {
	/**
	 * @var AbstractModelFactory
	 */
	protected static $_instance;
	/**
	 * @var array
	 */
	protected $_buildInModels = array(
		'RasterRectangle' => 'DpSpatialIndex\Model\RasterRectangle',
		'DpSpatialIndex\Model\RasterRectangle' => 'DpSpatialIndex\Model\RasterRectangle',
	);
	/**
	 * @var string
	 */
	protected $_modelInterface = 'DpSpatialIndex\Model\RasterRectangle';

}