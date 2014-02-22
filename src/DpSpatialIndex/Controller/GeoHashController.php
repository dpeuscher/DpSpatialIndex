<?php
/**
 * User: Dominik
 * Date: 18.06.13
 */

namespace DpSpatialIndex\Controller;


use DpZFExtensions\ServiceManager\TServiceLocator;
use Exception;
use HttpRequest;
use Zend\Config\Config;
use Zend\Console\Request;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\View\Model\ViewModel;

class GeoHashController extends AbstractActionController implements ServiceLocatorAwareInterface {
	use TServiceLocator;
	protected function _nextBit($mid,$value) {
		if ($value <= $mid)
			return 0;
		else
			return 1;
	}
	protected function _generateBits($min,$mid,$max,$value,$precision) {
		if ($precision <= 0)
			return "";
		$bit = $this->_nextBit($mid,$value);
		if (!$bit)
			$next = $this->_generateBits($min,($mid-$min)/2+$min,$mid,$value,$precision-1);
		else
			$next = $this->_generateBits($mid,($max-$mid)/2+$mid,$max,$value,$precision-1);
		return ((string)$bit).$next;
	}
	protected function _revertBits($min,$mid,$max,$restValue) {
		$bit = substr($restValue,0,1);
		$rest = substr($restValue,1);
		if (!$rest)
			return (!$bit?$min:$max);
		if (!$bit)
			$result = $this->_revertBits($min,($mid-$min)/2+$min,$mid,$rest);
		else
			$result = $this->_revertBits($mid,($max-$mid)/2+$mid,$max,$rest);
		return $result;
	}
	protected function _alternateBits($bitsLon,$bitsLat) {
		$result = '';
		for ($i = 0;$i < strlen($bitsLon)+strlen($bitsLat);$i++) {
			if (!($i%2))
				$result .= $bitsLon[(int) floor($i/2)];
			else
				$result .= $bitsLat[(int) floor($i/2)];
		}
		return $result;
	}
	protected function _splitBits($bitMask) {
		$lat = '';
		$lon = '';
		for ($i = 0;$i < strlen($bitMask);$i++) {
			if (!($i%2))
				$lon .= $bitMask[$i];
			else
				$lat .= $bitMask[$i];
		}
		return array($lon,$lat);
	}
	protected function _mapBase32($bitMask) {
		$geoHash = '';
		$chars = str_split($bitMask,5);
		$config = $this->getServiceLocator()->get('config');
		$map = $config['DpSpatialIndex']['geoHash']['base32Map'];
		foreach ($chars as $bitHash)
			$geoHash .= $map[bindec($bitHash)];#
		return $geoHash;
	}
	protected function _reverseMapBase32($geoHash) {
		$bitMask = '';
		$config = $this->getServiceLocator()->get('config');
		if (!is_array($config) && $config instanceof Config)
			$config = $config->toArray();
		$map = array_flip($config['DpSpatialIndex']['geoHash']['base32Map']);
		for ($i = 0;$i < strlen($geoHash);$i++)
			$bitMask .= str_pad(decbin($map[$geoHash[$i]]), 5, "0", STR_PAD_LEFT);
		return $bitMask;
	}
	public function generateHash($lon,$lat,$precision = null) {
		if (is_null($precision)) {
			$config = $this->getServiceLocator()->get('config');
			$precision = $config['DpSpatialIndex']['geoHash']['defaultPrecision'];
		}
		if ($precision % 5)
			throw new Exception("Precision must be dividable by 5");
		$bitsLat = $this->_generateBits(-90,0,90,$lat,floor($precision/2));
		$bitsLon = $this->_generateBits(-180,0,180,$lon,ceil($precision/2));
		$bitMask = $this->_alternateBits($bitsLon,$bitsLat);
		$geoHash = $this->_mapBase32($bitMask);
		return $geoHash;
	}
	public function revertCoords($geoHash) {
		$bitMask = $this->_reverseMapBase32($geoHash);
		list($bitsLon,$bitsLat) = $this->_splitBits($bitMask);
		$lat = $this->_revertBits(-90,0,90,$bitsLat);
		$lon = $this->_revertBits(-180,0,180,$bitsLon);
		return array('lon' => $lon,'lat' => $lat);
	}
	public function generateHashAction() {
		/** @var HttpRequest|Request $request */
		$request = $this->getRequest();
		$lat = $request->getParam('lat');
		$lon = $request->getParam('lon');
		$precision = $request->getParam('precision');
		if (!is_null($precision))
			$geoHash = $this->generateHash($lat,$lon,$precision);
		else
			$geoHash = $this->generateHash($lat,$lon);
		return new ViewModel(array('geoHash' => $geoHash));
	}
	public function revertCoordsAction() {
		/** @var HttpRequest|Request $request */
		$request = $this->getRequest();
		$geoHash = $request->getParam('geoHash');
		$coords = $this->revertCoords($geoHash);
		return new ViewModel(array('lon' => $coords['lon'],'lat' => $coords['lat']));
	}
}
