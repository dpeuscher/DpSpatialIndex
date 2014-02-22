<?php
/**
 * User: Dominik
 * Date: 18.06.13
 */

namespace DpSpatialIndex\Model;


use DpZFExtensions\ServiceManager\TServiceLocator;
use DpZFExtensions\Validator\IExchangeState;
use DpZFExtensions\Validator\TExchangeState;
use DpDoctrineExtensions\Collection\TDecoreeCollection;
use DpOpenGis\Model\LineString;
use DpSpatialIndex\Collection\INodeCollection;
use DpSpatialIndex\Collection\IRelationInRectangleCollection;
use DpSpatialIndex\Collection\IWayCollection;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

class RasterRectangle implements IExchangeState,ServiceLocatorAwareInterface {
	use TExchangeState,TServiceLocator,TDecoreeCollection;

	/**
	 * @var integer
	 */
	protected $_rasterRectangleId;
	/**
	 * @var String
	 */
	protected $_geoHashUR;
	/**
	 * @var String
	 */
	protected $_geoHashBL;
	/**
	 * @var integer
	 */
	protected $_depth;
	/**
	 * @var LineString
	 */
	protected $_lineString;
	/**
	 * @var IRelationInRectangleCollection
	 */
	protected $_relations;
	/**
	 * @var IWayCollection
	 */
	protected $_ways;
	/**
	 * @var INodeCollection
	 */
	protected $_nodes;

	/**
	 * @return int
	 */
	public function getRasterRectangleId() {
		return $this->_rasterRectangleId;
	}

	/**
	 * @return int
	 */
	public function getDepth() {
		return $this->_depth;
	}

	/**
	 * @return String
	 */
	public function getGeoHashBL() {
		return $this->_geoHashBL;
	}

	/**
	 * @return String
	 */
	public function getGeoHashUR() {
		return $this->_geoHashUR;
	}

	/**
	 * @return \DpOpenGis\Model\LineString
	 */
	public function getLineString() {
		return $this->_lineString;
	}

	/**
	 * @return \DpSpatialIndex\Collection\INodeCollection
	 */
	public function getNodes() {
		return $this->_getDecoreeCollection('_nodes','DpSpatialIndex\Collection\INodeCollection');
	}

	/**
	 * @return \DpSpatialIndex\Collection\IRelationInRectangleCollection
	 */
	public function getRelations() {
		return $this->_getDecoreeCollection('_relations','DpSpatialIndex\Collection\IRelationInRectangleCollection');
	}

	/**
	 * @return \DpSpatialIndex\Collection\IWayCollection
	 */
	public function getWays() {
		return $this->_getDecoreeCollection('_ways','DpSpatialIndex\Collection\IWayCollection');
	}

	/**
	 * @return array of all fields that represent the state (only atomic fields and VOs atomic fields - no VO itself)
	 */
	public function getStateVars() {
		return array(
			'geoHashUR',
			'geoHashBL',
			'depth',
			'lineString',
			'relations',
			'ways',
			'nodes',
		);
	}
}
