<?php
/**
 * User: Dominik
 * Date: 20.06.13
 */

namespace DpSpatialIndex\Model;

use DpZFExtensions\Validator\IExchangeState;
use DpZFExtensions\Validator\TExchangeState;
use DpOsmParser\Model\Relation;

class RelationInRectangle implements IExchangeState {
	use TExchangeState;

	/**
	 * @var RasterRectangle
	 */
	protected $_rasterRectangle;
	/**
	 * @var Relation
	 */
	protected $_relation;
	/**
	 * @var float
	 */
	protected $_coverage;

	/**
	 * @return float
	 */
	public function getCoverage() {
		return $this->_coverage;
	}

	/**
	 * @return \DpSpatialIndex\Model\RasterRectangle
	 */
	public function getRasterRectangle() {
		return $this->_rasterRectangle;
	}

	/**
	 * @return \DpOsmParser\Model\Relation
	 */
	public function getRelation() {
		return $this->_relation;
	}

	public function getStateVars() {
		return array(
			'rasterRectangle',
			'relation',
			'coverage'
		);
	}
}
