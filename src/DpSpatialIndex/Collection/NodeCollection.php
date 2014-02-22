<?php
/**
 * User: Dominik
 * Date: 18.06.13
 */

namespace DpSpatialIndex\Collection;


use DpDoctrineExtensions\Collection\AForceTypeCollection;

class NodeCollection extends AForceTypeCollection implements INodeCollection {
	protected $_entityType = 'DpOsmParser\Model\Node';
}