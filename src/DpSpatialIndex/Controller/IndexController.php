<?php
/**
 * User: Dominik
 * Date: 18.06.13
 */

namespace DpSpatialIndex\Controller;


use DpProfiler\IDistributedProfiler;
use DpProfiler\IPrintableProfiler;
use DpProfiler\IProfiler;
use DpZFExtensions\Cache\ICacheAware;
use DpZFExtensions\Cache\TCacheAware;
use DpZFExtensions\ServiceManager\ServiceLocatorDecorator;
use DpZFExtensions\ServiceManager\TServiceLocator;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;
use DpOpenGis\Factory\LineStringFactory;
use DpOpenGis\Factory\MultiPolygonFactory;
use DpOpenGis\Factory\PointFactory;
use DpOpenGis\Factory\PolygonFactory;
use DpOpenGis\Model\Point;
use DpOpenGis\Model\Polygon;
use DpOpenGis\ModelInterface\IPointCollection;
use DpOsmParser\Model\Relation;
use DpProfiler\Profiler;
use DpSpatialIndex\Factory\RasterRectangleFactory;
use DpSpatialIndex\Model\RasterRectangle;
use DpSpatialIndex\Model\RelationInRectangle;
use HttpRequest;
use Zend\Console\Request;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController implements ServiceLocatorAwareInterface,ICacheAware {
	use TServiceLocator,TCacheAware;

	/**
	 * @var array
	 */
	protected $_restorePointInfo;
	/**
	 * @var array
	 */
	protected $_restoreLineStringInfo;
	/**
	 * @var array
	 */
	protected $_restorePolygonInfo;
	/**
	 * @var array
	 */
	protected $_restoreMultiPolygonInfo;
	/**
	 * @var EntityManager
	 */
	protected $em;
	/**
	 * @var array
	 */
	protected $memberRelationCache = array();
	/**
	 * @var Profiler
	 */
	protected $_profiler;
	/**
	 * @var string
	 */
	protected $_id;
	/**
	 * @return EntityManager
	 */
	protected function _getEntityManager() {
		if (null === $this->em)
			$this->em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
		return $this->em;
	}

	/**
	 * @return string
	 */
	protected function _getId() {
		if (!isset($this->_id) || empty($this->_id) || (!$this->_id))
			$this->_id = md5(time().rand(1,999999));
		return $this->_id;
	}

	/**
	 * @param null|string $key
	 * @param null|boolean $start
	 * @return Profiler
	 */
	protected function profile($key = null,$start = null) {
		if (!isset($this->_profiler) && $this->getServiceLocator()->has('Profiler')) {
			/** @var IProfiler $profiler */
			$this->_profiler = $this->getServiceLocator()->get('Profiler');
			if ($this->_profiler instanceof IDistributedProfiler)
				$this->_profiler->setIdentifier($this->_getId());
			$this->_profiler->trackGlobal(true);
			if ($this->_profiler instanceof IPrintableProfiler)
				$this->_profiler->setPrintInterval(40);
		}
		if (isset($this->_profiler)) {
			if (is_null($key) && is_null($start) && $this->_profiler instanceof IPrintableProfiler)
				echo $this->_profiler->trackPrintTime();
			elseif (!is_null($key) && !is_null($start))
				$this->_profiler->track($key,$start);
		}
	}

	/**
	 * @param Polygon $polygon
	 * @return array
	 */
	protected  function _generateMBR(Polygon $polygon) {
		$this->profile('generateMBR',true);
		$hash = 'SpatialIndex\Controller\IndexController->_generateMBR('.$polygon.')';
		if ($this->getLongTermCache()->hasItem($hash))
			return $this->getLongTermCache()->getItem($hash);
		$leftBorder = $polygon->getOuter()->getPoints()->first()->getLon();
		$rightBorder = $polygon->getOuter()->getPoints()->first()->getLon();
		$bottomBorder = $polygon->getOuter()->getPoints()->first()->getLat();
		$topBorder = $polygon->getOuter()->getPoints()->first()->getLat();
		foreach ($polygon->getOuter()->getPoints() as $point) {
			/** @var Point $point */
			if ($point->getLon() < $leftBorder)
				$leftBorder = $point->getLon();
			if ($point->getLon() > $rightBorder)
				$rightBorder = $point->getLon();
			if ($point->getLat() < $bottomBorder)
				$bottomBorder = $point->getLat();
			if ($point->getLat() > $topBorder)
				$topBorder = $point->getLat();
		}
		$result = array($leftBorder,$rightBorder,$bottomBorder,$topBorder);
		$this->getLongTermCache()->setItem($hash,$result);
		$this->profile('generateMBR',false);
		return $result;
	}

	/**
	 * @param float $lonStepSize
	 * @param float $latStepSize
	 * @param float $leftBorder
	 * @param float $rightBorder
	 * @param float $bottomBorder
	 * @param float $topBorder
	 * @param float $lonMin
	 * @param float $lonMax
	 * @param float $latMin
	 * @param float $latMax
	 * @return array
	 */
	protected function _calculateStepWindow($lonStepSize,$latStepSize,$leftBorder,$rightBorder,$bottomBorder,$topBorder,
											$lonMin,$lonMax,$latMin,$latMax) {
		$this->profile('calculateStepWindow',true);
		$mostLeftBorder = max($lonMin,floor(($leftBorder+180)/$lonStepSize)*$lonStepSize-180);
		$mostRightBorder = min($lonMax,ceil(($rightBorder+180)/$lonStepSize)*$lonStepSize-180);
		$mostBottomBorder = max($latMin,floor(($bottomBorder+90)/$latStepSize)*$latStepSize-90);
		$mostTopBorder = min($latMax,ceil(($topBorder+90)/$latStepSize)*$latStepSize-90);
		$this->profile('calculateStepWindow',false);
		return array($mostLeftBorder,$mostRightBorder,$mostBottomBorder,$mostTopBorder);
	}

	/**
	 * @param $lon
	 * @param $lonUR
	 * @param $lat
	 * @param $latUR
	 * @param $depth
	 * @internal param $lonBL
	 * @internal param $latBL
	 * @return RasterRectangle
	 */
	protected function _getRasterRectangle($lon,$lonUR,$lat,$latUR,$depth) {
		$this->profile('rasterGeneration',true);
		/** @var RasterRectangleFactory $rectangleFactory */
		$rectangleFactory = $this->getServiceLocator()->get('DpSpatialIndex\Factory\RasterRectangleFactory');
		/** @var IPointCollection $pointCollectionPrototype */
		$pointCollectionPrototype = $this->getServiceLocator()->get('DpOpenGis\ModelInterface\IPointCollection');
		/** @var PointFactory $pointFactory */
		$pointFactory = $this->getServiceLocator()->get('DpOpenGis\Factory\PointFactory');
		/** @var LineStringFactory $lineStringFactory */
		$lineStringFactory = $this->getServiceLocator()->get('DpOpenGis\Factory\LineStringFactory');
		/** @var GeoHashController $geoHashController */
		$geoHashController = $this->getServiceLocator()->
			get('DpSpatialIndex\Controller\GeoHashController');
		$geoHashUR = $geoHashController->generateHash($lonUR,$latUR);
		$geoHashBL = $geoHashController->generateHash($lon,$lat);
		$rect = $this->_getEntityManager()->getRepository('DpSpatialIndex\Model\RasterRectangle')->
			findBy(array('_geoHashUR' => $geoHashUR,'_geoHashBL' => $geoHashBL,'_depth' => $depth));

		if (empty($rect)) {
			$points = clone $pointCollectionPrototype;
			$points->add($pointFactory->create('Point',array('lon' => $lon,'lat' => $lat)));
			$points->add($pointFactory->create('Point',array('lon' => $lonUR,'lat' => $lat)));
			$points->add($pointFactory->create('Point',array('lon' => $lonUR,'lat' => $latUR)));
			$points->add($pointFactory->create('Point',array('lon' => $lon,'lat' => $latUR)));
			$points->add($pointFactory->create('Point',array('lon' => $lon,'lat' => $lat)));
			$lineString = $lineStringFactory->create('LineString',array('points' => $points));
			$rect = $rectangleFactory->create('RasterRectangle',array(
			                                                         'geoHashUR' => $geoHashUR,
			                                                         'geoHashBL' => $geoHashBL,
			                                                         'depth' => $depth,
			                                                         'lineString' => $lineString
			                                                    ));
			$this->_getEntityManager()->persist($rect);
			// Needed to generate id
			$this->_getEntityManager()->flush();
		}
		else
			$rect = array_pop($rect);
		$this->profile('rasterGeneration',false);
		return $rect;
	}

	/**
	 * @param RasterRectangle $rect
	 * @param Relation $relation
	 * @return float|null
	 */
	public function _getCoverageByOuterRectangle(RasterRectangle $rect,Relation $relation) {
		$this->profile('getCoverageByOuterRectangle',true);
		$query = $this->_getEntityManager()->createQuery('
								SELECT re._coverage FROM DpSpatialIndex\Model\RasterRectangle r JOIN r._relations re
								WHERE r._geoHashUR >= :rectUR AND r._geoHashBL <= :rectBL AND
									re._relation = :relation AND
									(re._coverage = 1 OR re._coverage = 0)');
		$query->setMaxResults(1);
		$query->setParameters(array('rectUR' => $rect->getGeoHashUR(),
		                            'rectBL' => $rect->getGeoHashBL(),'relation' => $relation));
		try {
			$coverage = $query->getSingleScalarResult();
		} catch (NoResultException $e) {
			$coverage = null;
		}
		$this->profile('getCoverageByOuterRectangle',false);
		return $coverage;
	}

	/**
	 * @param RasterRectangle $rect
	 * @param Relation $relation
	 * @param float $coverage
	 */
	protected function _saveRectangle(RasterRectangle $rect,Relation $relation,$coverage) {
		$this->profile('saveRectangle',true);
		$this->profile('checkForRectangle',true);
		/** @var RasterRectangleFactory $rectangleFactory */
		$relationInRectangleFactory = $this->getServiceLocator()->
			get('DpSpatialIndex\Factory\RelationInRectangleFactory');
		$criteria = Criteria::create()->where(Criteria::expr()->eq("_relation",$relation));
		/** @var RelationInRectangle $rir */
		$rir = $rect->getRelations()->matching($criteria)->first();
		if (!empty($rir) && $rir->getCoverage() != $coverage) {
			$this->_getEntityManager()->remove($rir);
			$this->_getEntityManager()->flush();
		}
		$this->profile('checkForRectangle',false);
		if (empty($rir)) {
			$this->profile('createNewRectangle',true);
			/** @var RelationInRectangle $rir */
			$rir = $relationInRectangleFactory->create('RelationInRectangle',
			                                           array(
			                                                'relation' => $relation,
			                                                'rasterRectangle' => $rect,
			                                                'coverage' => $coverage,
			                                           ));
			$this->_getEntityManager()->persist($rir);
			$rect->getRelations()->add($rir);
			$this->profile('createNewRectangle',false);
			$this->profile('createNewRectangleFlush',true);
			$this->_getEntityManager()->flush();
			$this->profile('createNewRectangleFlush',false);
		}
		$this->profile('saveRectangle',false);
	}

	/**
	 * @param Polygon         $polygon
	 * @param RasterRectangle $rect
	 * @param Relation        $relation
	 * @return float|int|null
	 */
	protected function _calculateCoverage(Polygon $polygon,RasterRectangle $rect,Relation $relation) {
		$coverage = null;
		$coverage = $this->_getCoverageByOuterRectangle($rect,$relation);

		if (is_null($coverage)) {
			$this->profile('containsTest',true);
			$contains = $polygon->ContainsLineString($rect->getLineString());
			$this->profile('containsTest',false);
			if (!$contains) {
				$this->profile('intersectsTest',true);
				if (isset($intersects)) $beforeIntersects = $intersects;
				else $beforeIntersects = null;
				$intersects = $polygon->IntersectsLineString($rect->getLineString(),
				                                             (!is_null($beforeIntersects))?$beforeIntersects:false);
				$this->profile('intersectsTest',false);
			}
			if ($contains)
				$coverage = 1;
			elseif (isset($intersects) && $intersects)
				$coverage = 0.5;
			else
				$coverage = 0;
		}
		return $coverage;
	}

	/**
	 * @param int   $startDepth
	 * @param int   $maxDepth
	 * @param float $lonMin
	 * @param float $lonMax
	 * @param float $latMin
	 * @param float $latMax
	 * @param int   $parallelProcesses
	 * @param float $leftBorder
	 * @param float $rightBorder
	 * @param float $bottomBorder
	 * @param float $topBorder
	 * @param       $serialProcesses
	 * @return int
	 */
	protected function _calculateSplittingDepth($startDepth,$maxDepth,$lonMin,$lonMax,$latMin,$latMax,
	                                            $parallelProcesses,$leftBorder,$rightBorder,$bottomBorder,$topBorder,
	                                            $serialProcesses) {

		for ($depthFactor = $startDepth;$depthFactor < $maxDepth;$depthFactor++) {

			$depth = pow(2,$depthFactor);
			$lonStepSize = 360/$depth;
			$latStepSize = 180/$depth;
			list($mostLeftBorder,$mostRightBorder,$mostBottomBorder,$mostTopBorder) =
				$this->_calculateStepWindow($lonStepSize,$latStepSize,$leftBorder,$rightBorder,$bottomBorder,
				                            $topBorder,$lonMin,$lonMax,$latMin,$latMax);

			$steps = ceil((($mostRightBorder+180)-($mostLeftBorder+180))/$lonStepSize)*
				ceil((($mostTopBorder+90)-($mostBottomBorder+90))/$latStepSize);
			if ($steps >= ($parallelProcesses*$serialProcesses))
				return $depthFactor;
		}
		return $maxDepth;
	}

	/**
	 * @param float    $lonMin
	 * @param float    $lonMax
	 * @param float    $latMin
	 * @param float    $latMax
	 * @param int      $startDepth
	 * @param int      $maxDepth
	 * @param int      $parallelProcesses
	 * @param Relation $relation
	 * @param int      $polygonNumber
	 * @param          $serialProcesses
	 */
	protected function _generateRectangleByPolygon($lonMin,$lonMax,$latMin,$latMax,$startDepth,$maxDepth,
	                                            $parallelProcesses,Relation $relation,$polygonNumber,$serialProcesses) {
		/** @var Polygon $polygon */
		$polygon = $relation->getMultiPolygon()->getPolygons()->get($polygonNumber);

		list($leftBorder,$rightBorder,$bottomBorder,$topBorder) = $this->_generateMBR($polygon);

		$startParallel = $this->_calculateSplittingDepth($startDepth,$maxDepth,$lonMin,$lonMax,$latMin,$latMax,
		                                $parallelProcesses,$leftBorder,$rightBorder,$bottomBorder,$topBorder,
		                                $serialProcesses);

		$rectangles = array();
		for ($depthFactor = $startDepth;$depthFactor <= $startParallel;$depthFactor++) {

			$depth = pow(2,$depthFactor);
			$lonStepSize = 360/$depth;
			$latStepSize = 180/$depth;
			list($mostLeftBorder,$mostRightBorder,$mostBottomBorder,$mostTopBorder) =
				$this->_calculateStepWindow($lonStepSize,$latStepSize,$leftBorder,$rightBorder,$bottomBorder,
				                            $topBorder,$lonMin,$lonMax,$latMin,$latMax);

			for ($lon = $mostLeftBorder;$lon < $mostRightBorder;$lon += $lonStepSize) {
				for ($lat = $mostBottomBorder;$lat < $mostTopBorder;$lat += $latStepSize) {

					$rect = $this->_getRasterRectangle($lon,$lon+$lonStepSize,$lat,$lat+$latStepSize,$depthFactor);
					$coverage = $this->_calculateCoverage($polygon,$rect,$relation);
					$this->_saveRectangle($rect,$relation,$coverage);

					if ($startParallel == $depthFactor && $depthFactor+1 <= $maxDepth)
						$rectangles[] = array($depthFactor+1,$rect->getGeoHashUR(),$rect->getGeoHashBL());
				}
			}
		}
		/** @var \DpAsynchronJob\JobCenter\Manager $jobCenter */
		$jobCenter = $this->getServiceLocator()->get('DpAsynchronJob\JobCenter\Manager');
		$this->_getEntityManager()->flush();
		$serial = floor(count($rectangles)/$parallelProcesses);
		$serialLast = $serial + ($serial*$parallelProcesses)-count($rectangles);
		for ($i = 0;$i < $parallelProcesses;$i++) {
			$jobs = array();
			if ($i+1 == $parallelProcesses)
				$count = $serialLast;
			else
				$count = $serial;
			for ($j = 0;$j < $count;$j++)
				if ($rectangles[$i*$serial+$j][0] <= $maxDepth)
					$jobs[] = $rectangles[$i*$serial+$j][0].'.'.$rectangles[$i*$serial+$j][1].
						'.'.$rectangles[$i*$serial+$j][2];
				else
					trigger_error('Job could not be added: '.$rectangles[$i*$serial+$j][0].'.'.$rectangles[$i*$serial+$j][1].
						'.'.$rectangles[$i*$serial+$j][2].'',E_USER_WARNING);
			if (!empty($jobs)) {
				$args = array("command" => 'spatial create index delta --relation='.$relation->getRelationId().
					' --polygonNumber='.$polygonNumber.' --maxDepth='.$maxDepth.' --jobs='.implode(',',$jobs).
					' --parallelProcesses='.$parallelProcesses.' --serialProcesses='.$serialProcesses.
					' --id='.$this->_getId());
				$jobCenter->addJob("createSpatialIndex", $args);
			}
		}
	}
	/**
	 * @param ServiceLocatorAwareInterface $factory
	 * @param String                       $oldValidatorString
	 * @return array
	 */
	protected function _removeValidator(ServiceLocatorAwareInterface $factory,$oldValidatorString) {
		$newServiceLocator = new ServiceLocatorDecorator();
		$newServiceLocator->setInvokableClass($oldValidatorString,
		                                      'DpZFExtensions\Validator\AllValidator');
		$oldValidator = $factory->getServiceLocator();
		$newServiceLocator->setDecoree($oldValidator);
		$factory->setServiceLocator($newServiceLocator);
		return array($factory,$oldValidator);
	}

	/**
	 * @param array $restorePointInfo
	 */
	protected function _restoreValidator($restorePointInfo) {
		/**
		 * @var ServiceLocatorAwareInterface $factory
		 * @var ServiceLocatorInterface $serviceLocator
		 */
		list($factory,$serviceLocator) = $restorePointInfo;
		$factory->setServiceLocator($serviceLocator);
	}
	protected function _deactivateValidators() {
		/** @var PointFactory $pointFactory */
		$pointFactory = $this->getServiceLocator()->get('DpOpenGis\Factory\PointFactory');
		/** @var LineStringFactory $lineStringFactory */
		$lineStringFactory = $this->getServiceLocator()->get('DpOpenGis\Factory\LineStringFactory');
		/** @var PolygonFactory $polygonFactory */
		$polygonFactory = $this->getServiceLocator()->get('DpOpenGis\Factory\PolygonFactory');
		/** @var MultiPolygonFactory $multiPolygonFactory */
		$multiPolygonFactory = $this->getServiceLocator()->get('DpOpenGis\Factory\MultiPolygonFactory');

		$this->_restorePointInfo = $this->_removeValidator($pointFactory,'DpOpenGis\Validator\Point');
		$this->_restoreLineStringInfo = $this->_removeValidator($lineStringFactory,'DpOpenGis\Validator\LineString');
		$this->_restorePolygonInfo = $this->_removeValidator($polygonFactory,'DpOpenGis\Validator\Polygon');
		$this->_restoreMultiPolygonInfo = $this->_removeValidator($multiPolygonFactory,'DpOpenGis\Validator\MultiPolygon');
	}
	protected function _reactivateValidators() {
		$this->_restoreValidator($this->_restorePointInfo);
		$this->_restoreValidator($this->_restoreLineStringInfo);
		$this->_restoreValidator($this->_restorePolygonInfo);
		$this->_restoreValidator($this->_restoreMultiPolygonInfo);
	}

	/**
	 * @param int      $startDepth
	 * @param int      $maxDepth
	 * @param string   $URHash
	 * @param string   $BLHash
	 * @param int      $parallelProcesses
	 * @param int|null $relationId
	 * @param int|null $polygonNumber
	 * @param int      $serialProcesses
	 */
	public function deltaCreateByRelation($startDepth,$maxDepth,$URHash,$BLHash,$parallelProcesses,$relationId = null,
	                                      $polygonNumber = null,$serialProcesses = 1) {
		$this->_deactivateValidators();

		/** @var GeoHashController $geoHashController */
		$geoHashController = $this->getServiceLocator()->
			get('DpSpatialIndex\Controller\GeoHashController');

		$urCoords = $geoHashController->revertCoords($URHash);
		$blCoords = $geoHashController->revertCoords($BLHash);
		$lonMax = $urCoords['lon'];
		$latMax = $urCoords['lat'];
		$lonMin = $blCoords['lon'];
		$latMin = $blCoords['lat'];
		$count = $query = $this->_getEntityManager()->createQuery('
						SELECT COUNT(r) FROM DpOsmParser\Model\Relation r
						WHERE r._multiPolygon IS NOT NULL'.
			            (!is_null($relationId)?' AND r._relationId = '.$relationId:''))->getSingleScalarResult();
		if ($count > 1) {
			/** @var \DpAsynchronJob\JobCenter\Manager $jobCenter */
			$jobCenter = $this->getServiceLocator()->get('DpAsynchronJob\JobCenter\Manager');
			$entityPerRun = 20;
			$query = $this->_getEntityManager()->createQuery('
							SELECT r._relationId FROM DpOsmParser\Model\Relation r
							WHERE r._multiPolygon IS NOT NULL'.
				            (!is_null($relationId)?' AND r._relationId = '.$relationId:''));
			for ($i = 0;$i < $count/$entityPerRun;$i++) {
				$query->setMaxResults($entityPerRun);
				$query->setFirstResult($i*$entityPerRun);
				foreach ($query->getArrayResult() as $relation) {
					$relationId = $relation['_relationId'];
					$jobCenter->addJob("createSpatialIndex",array('command' => 'spatial create index delta --relation='.
						$relationId.' --maxDepth='.$maxDepth.' --jobs='.$startDepth.
						'.'.$URHash.'.'.$BLHash.' --parallelProcesses='.$parallelProcesses.' --serialProcesses='.
						$serialProcesses.' --id='.$this->_getId()));
				}
			}

		}
		else {
			$query = $this->_getEntityManager()->createQuery('
							SELECT r FROM DpOsmParser\Model\Relation r
							WHERE r._multiPolygon IS NOT NULL'.
			                (!is_null($relationId)?' AND r._relationId = '.$relationId:''));
			foreach ($query->getResult() as $relation) {
				/** @var Relation $relation */
				if (isset($polygonNumber) && $polygonNumber)
					$this->_generateRectangleByPolygon($lonMin,$lonMax,$latMin,$latMax,$startDepth,$maxDepth,
									   $parallelProcesses,$relation,$polygonNumber,$serialProcesses);
				else
					foreach ($relation->getMultiPolygon()->getPolygons()->getKeys() as $nr)
						$this->_generateRectangleByPolygon($lonMin,$lonMax,$latMin,$latMax,$startDepth,$maxDepth,
										   $parallelProcesses,$relation,$nr,$serialProcesses);
			}
		}
		$this->_reactivateValidators();
		$this->_getEntityManager()->flush();
		$this->profile();
	}

	/**
	 * @param          $maxDepth
	 * @param int|null $relationId
	 */
	public function createByRelation($maxDepth,$relationId = null) {
		$this->_deactivateValidators();

		$count = $query = $this->_getEntityManager()->createQuery('
						SELECT COUNT(*) FROM DpOsmParser\Model\Relation r
						WHERE r._multiPolygon IS NOT NULL'.
			            (!is_null($relationId)?' AND r._relationId = '.$relationId:''))->getSingleScalarResult();
		$entityPerRun = 20;
		for ($i = 0;$i < $count/$entityPerRun;$i++) {
			$query = $this->_getEntityManager()->createQuery('
							SELECT r FROM DpOsmParser\Model\Relation r
							WHERE r._multiPolygon IS NOT NULL'.
				                  (!is_null($relationId)?' AND r._relationId = '.$relationId:''));
			$query->setMaxResults($entityPerRun);
			$query->setFirstResult($i*$entityPerRun);
			foreach ($query->getResult() as $relation) {
				/** @var Relation $relation */

				foreach ($relation->getMultiPolygon()->getPolygons() as $polygon) {
					/** @var Polygon $polygon */

					list($leftBorder,$rightBorder,$bottomBorder,$topBorder) = $this->_generateMBR($polygon);

					for ($depthFactor = 1;$depthFactor <= $maxDepth;$depthFactor++) {

						$depth = pow(2,$depthFactor);
						$lonStepSize = 360/$depth;
						$latStepSize = 180/$depth;
						list($mostLeftBorder,$mostRightBorder,$mostBottomBorder,$mostTopBorder) =
							$this->_calculateStepWindow($lonStepSize,$latStepSize,$leftBorder,$rightBorder,$bottomBorder,
							                            $topBorder,-180,180,-90,90);

						for ($lon = $mostLeftBorder;$lon < $mostRightBorder;$lon += $lonStepSize) {
							for ($lat = $mostBottomBorder;$lat < $mostTopBorder;$lat += $latStepSize) {
								$rect = $this->_getRasterRectangle($lon,$lon+$lonStepSize,$lat,$lat+$latStepSize,$depthFactor);
								$coverage = $this->_calculateCoverage($polygon,$rect,$relation);
								$this->_saveRectangle($rect,$relation,$coverage);
							}
						}
					}
				}
			}
			$this->_getEntityManager()->flush();
			$this->_getEntityManager()->clear();
		}
		$this->_reactivateValidators();
		$this->_getEntityManager()->flush();
		$this->profile();
	}
	/**
	 * @return ViewModel
	 */
	public function createByRasterAction() {
		/** @var HttpRequest|Request $request */
		$request = $this->getRequest();
		$depth = $request->getParam('depth');
		$this->createByRaster($depth);
		return new ViewModel(array());
	}
	/**
	 * @return ViewModel
	 */
	public function createByRelationAction() {
		/** @var HttpRequest|Request $request */
		$request = $this->getRequest();
		$depth = $request->getParam('depth');
		$this->createByRelation($depth,$request->getParam('relation'));
		return new ViewModel(array());
	}

	public function deltaCreateByRelationAction() {
		/** @var HttpRequest|Request $request */
		$request = $this->getRequest();
		$geoHashController = $this->getServiceLocator()->
			get('DpSpatialIndex\Controller\GeoHashController');
		$relation = (int) $request->getParam('relation');
		if (!$relation) $relation = null;
		$maxDepth = (int) $request->getParam('maxDepth');
		$parallelProcesses = $request->getParam('parallelProcesses');
		$serialProcesses = $request->getParam('serialProcesses');
		if (!$serialProcesses)
			$serialProcesses = 1;
		$id = $request->getParam('id');
		if ($id)
			$this->_id = $id;
		$polygonNumber = $request->getParam('polygonNumber');
		if (!$polygonNumber)
			$polygonNumber = null;

		$jobs = $request->getParam('jobs');
		if (!$jobs)
			$jobs = '1.'.$geoHashController->generateHash(180,90).'.'.$geoHashController->generateHash(-180,-90);
		foreach (explode(',',$jobs) as $job) {
			list($startDepth,$UR,$BL) = explode('.',$job);
			$this->deltaCreateByRelation($startDepth,$maxDepth,$UR,$BL,$parallelProcesses,$relation,$polygonNumber,
		                             $serialProcesses);
		}
		// Comment out for Unit-Testing
		exit;
		return new ViewModel();
	}
}
