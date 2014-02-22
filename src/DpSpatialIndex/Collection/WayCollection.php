<?php
/**
 * User: Dominik
 * Date: 18.06.13
 */

namespace DpSpatialIndex\Collection;


use DpDoctrineExtensions\Collection\AForceTypeCollection;

class WayCollection extends AForceTypeCollection implements IWayCollection {
	protected $_entityType = 'DpOsmParser\Model\Way';
}