<?php
/**
 * User: Dominik
 * Date: 18.06.13
 */

namespace DpSpatialIndex\Collection;


use DpDoctrineExtensions\Collection\AForceTypeCollection;

class RelationInRectangleCollection extends AForceTypeCollection implements IRelationInRectangleCollection {
	protected $_entityType = 'DpSpatialIndex\Model\RelationInRectangle';
}