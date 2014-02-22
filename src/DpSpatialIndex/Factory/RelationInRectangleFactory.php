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
class RelationInRectangleFactory extends AbstractModelFactory {
	/**
	 * @var AbstractModelFactory
	 */
	protected static $_instance;
	/**
	 * @var array
	 */
	protected $_buildInModels = array(
		'RelationInRectangle' => 'DpSpatialIndex\Model\RelationInRectangle',
		'DpSpatialIndex\Model\RelationInRectangle' => 'DpSpatialIndex\Model\RelationInRectangle',
	);
	/**
	 * @var string
	 */
	protected $_modelInterface = 'DpSpatialIndex\Model\RelationInRectangle';

}