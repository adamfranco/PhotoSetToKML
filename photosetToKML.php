<?php

/**
 * Photo Set to KML
 * Use it: http://www2.adamfranco.com/photosetToKML.php
 * About it: http://www.adamfranco.com/2007/08/23/flickr-photo-set-to-kml/
 * Source: https://github.com/adamfranco/PhotoSetToKML
 *
 * Features:
 *
 *	- Generate a KML file from a Flickr photo set
 *	- Directly open the KML file in Google Maps
 *	- Choose what size image to include in the placemark description for each photo.
 *	- Optionaly draw a path (line) from photo to photo ordered in one of several ways: 
 *		- by date taken
 *		- by date uploaded
 *		- by set order
 *	  Useful for making a quick and dirty map of a trip.
 *
 *
 * Requirements:
 *
 *	- PHP 5.2 or greater	http://www.php.net
 *	- PEAR Flickr API		http://code.iamcal.com/php/flickr/readme.htm
 *
 *
 * Change-log:
 *
 * - 2007-08-27
 *		- Now uses htmlspecialchars() to clean titles instead of htmlentities(), 
 *		  the latter of which was causing excessive translation of German 
 *		  characters. Thanks <a href='http://www.ogleearth.com/'>Stefan Geens</a>, for 
 *		  pointing this out.
 *		- Form now generates valid XHTML 1.0 strict.
 *		- Now can use image thumbnails instead of camera icons. Thanks for the idea
 *		  Nicolas Hoizey.
 *
 * - 2007-08-24
 *		- Now escapes ampersands in titles and descriptions.
 *
 * - 2007-08-23
 *		- First Release
 *
 *
 * @since 11/7/06
 * @package com.adamfranco.flickr
 * 
 * @copyright Copyright &copy; 2006, Adam Franco
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */ 

ini_set('display_errors', 1);
require_once 'Flickr/API.php';

/**
 * Photosets are Flickr's version of slideshows.
 * 
 * @since 11/7/06
 * @package com.adamfranco.flickr
 * 
 * @copyright Copyright &copy; 2006, Adam Franco
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */
class Photoset 
	extends FlickrObject
{
	
	/**
	 * @var string $id;
	 * @access public
	 * @since 8/21/07
	 */
	public $id;
	
	/**
	 * @var integer $currentPhoto;  
	 * @access private
	 * @since 8/21/07
	 */
	private $currentPhoto = 0;
	
	/**
	 * @var integer $currentPageNumber;  
	 * @access private
	 * @since 8/21/07
	 */
	private $currentPageNumber = 0;
	
	/**
	 * @var object XML_TreeNode $currentPage;  
	 * @access private
	 * @since 8/21/07
	 */
	private $currentPage;
	
	/**
	 * @var object Photo $primaryPhoto;  
	 * @access private
	 * @since 8/21/07
	 */
	private $primaryPhoto;
	
	/**
	 * @var array $properties;  
	 * @access private
	 * @since 8/21/07
	 */
	private $properties = array();
	
	/**
	 * @var boolean $loaded;  
	 * @access private
	 * @since 8/21/07
	 */
	private $loaded = false;

	/**
	 * Constructor
	 * 
	 * @param object Flickr_API $api
	 * @param string $id The Photoset Id
	 * @return void
	 * @access public
	 * @since 11/7/06
	 */
	public function __construct (Flickr_API $api, $id) {
		if (!preg_match('/^[0-9]+$/', $id))
			throw new Exception("Invalid photo set id, '$id', passed.");
		
		$this->api = $api;
		$this->id = $id;
		$this->currentPhoto = 0;
		$this->currentPageNumber = 0;
	}
	
	/**
	 * Answer one of our properties
	 * 
	 * @param string $name
	 * @return mixed
	 * @access public
	 * @since 8/21/07
	 */
	public function __get ($name) {
		$this->loadInfo();
		if (!isset($this->properties[$name]))
			throw new Exception("Cannot access unknown property, '$name'.");
		
		return $this->properties[$name];
	}
	
	/**
	 * Answer true if this attribute is set
	 * 
	 * @param string $name
	 * @return boolean
	 * @access public
	 * @since 8/21/07
	 */
	public function __isset ($name) {
		$this->loadInfo();
		if (isset($this->properties[$name]))
			return true;
		else
			return false;
	}
	
	/**
	 * Set one of our properties
	 * 
	 * @param string $name
	 * @return void
	 * @access public
	 * @since 8/21/07
	 */
	public function __set ($name, $val) {
		$this->properties[$name] = $val;
	}
	
	/**
	 * Unset one of our properties
	 * 
	 * @param string $name
	 * @return void
	 * @access public
	 * @since 8/21/07
	 */
	public function __unset ($name) {
		unset($this->properties[$name]);
	}
	
	/**
	 * Load the info
	 * 
	 * @return void
	 * @access private
	 * @since 11/7/06
	 */
	private function loadInfo () {
		if (!$this->loaded) {
			$photoset = $this->callMethod('flickr.photosets.getInfo', 
				array('photoset_id' => $this->id));
			
// 			print "<pre>".htmlentities($photoset->ownerDocument->saveXML($photoset))."</pre>";
			
			$this->owner = $photoset->getAttribute('owner');
			$this->numPhotos = $photoset->getAttribute('photos');
			
			$this->title = "";
			if ($node = $this->getChild($photoset, 'title'))
				if ($node->firstChild)
					$this->title = $node->firstChild->data;
				
			
			$this->description = "";
			if ($node = $this->getChild($photoset, 'description'))
				if ($node->firstChild)
					$this->description = $node->firstChild->data;
				
			
			$this->loaded = true;
			
// 			print "myproperties: "; var_dump($this->properties);
		}
	}
	
	/**
	 * Answer true if this photoset has more photos
	 * 
	 * @return boolean
	 * @access public
	 * @since 11/7/06
	 */
	function hasNextPhoto () {
		return ($this->currentPhoto < $this->numPhotos);
	}
	
	/**
	 * Answer the next photo
	 * 
	 * @return mixed object or false
	 * @access public
	 * @since 11/7/06
	 */
	function nextPhoto () {
		if (!$this->hasNextPhoto())
			return false;
		
		// Load a new page if needed
		if (!isset($this->currentPage) 
			|| ($this->currentPhoto &&  $this->currentPhoto % 100 == 0))
		{
			$this->currentPageNumber++;
			$this->currentPage = $this->callMethod('flickr.photosets.getPhotos', 
				array(
					'photoset_id' => $this->id,
					'per_page' => 100,
					'page' => $this->currentPageNumber
				));
			
//			print "<hr/>";
//			print "<pre>";
//			print_r($this->_currentPage);
//			print "</pre>";
		}
		
		$photoNode = $this->currentPage->getElementsByTagName('photo')->item($this->currentPhoto % 100);
		$photo = new Photo($this->api, 
							$photoNode->getAttribute('id'),
							$photoNode->getAttribute('server'),
							$photoNode->getAttribute('secret'),
							$photoNode->getAttribute('title'));
		
		if ($photoNode->getAttribute('isprimary'))
			$this->primaryPhoto = $photo;
		
		$this->currentPhoto++;
		return $photo;
	}
	
	/**
	 * Accept a visitor
	 * 
	 * @param ref object $visitor
	 * @return mixed
	 * @access public
	 * @since 11/7/06
	 */
	function acceptVisitor ( $visitor ) {
		$result = $visitor->visitPhotoset($this);
		return $result;
	}
}

/**
 * A Photo.
 * 
 * @since 11/7/06
 * @package com.adamfranco.flickr
 * 
 * @copyright Copyright &copy; 2006, Adam Franco
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */
class Photo
	extends FlickrObject
{

	/**
	 * @var string $serverId; The Id of the server the photo should be fetched from 
	 * @access private
	 * @since 8/21/07
	 */
	private $serverId;
	
	/**
	 * @var string $secret;  
	 * @access private
	 * @since 8/21/07
	 */
	private $secret;
	
	/**
	 * @var string $id;  
	 * @access private
	 * @since 8/21/07
	 */
	private $id;
	
	/**
	 * @var array $properties;  
	 * @access private
	 * @since 8/21/07
	 */
	private $properties = array();
	
	/**
	 * @var boolean $loaded;  
	 * @access private
	 * @since 8/21/07
	 */
	private $loaded = false;

	/**
	 * Constructor
	 * 
	 * @param <##>
	 * @return <##>
	 * @access public
	 * @since 11/7/06
	 */
	public function __construct (Flickr_API $api, $id, $serverId = null, $secret = null, $title = null, $origFormat = null) {
		if (!preg_match('/^[0-9]+$/', $id))
			throw new Exception("Invalid photo set id, '$id', passed.");
		
		$this->api = $api;
		$this->id = $id;
		
		if ($serverId)
			$this->serverId = $serverId;
		if ($secret)
			$this->secret = $secret;
		if ($title)
			$this->title = $title;
		if ($origFormat)
			$this->origFormat = $origFormat;
	}
	
		/**
	 * Answer one of our properties
	 * 
	 * @param string $name
	 * @return mixed
	 * @access public
	 * @since 8/21/07
	 */
	public function __get ($name) {
		$this->loadInfo();
		if (!isset($this->properties[$name]))
			throw new Exception("Cannot access unknown property, '$name'.");
		
		return $this->properties[$name];
	}
	
	/**
	 * Answer true if this attribute is set
	 * 
	 * @param string $name
	 * @return boolean
	 * @access public
	 * @since 8/21/07
	 */
	public function __isset ($name) {
		$this->loadInfo();
		if (isset($this->properties[$name]))
			return true;
		else
			return false;
	}
	
	/**
	 * Set one of our properties
	 * 
	 * @param string $name
	 * @return void
	 * @access public
	 * @since 8/21/07
	 */
	public function __set ($name, $val) {
		$this->properties[$name] = $val;
	}
	
	/**
	 * Unset one of our properties
	 * 
	 * @param string $name
	 * @return void
	 * @access public
	 * @since 8/21/07
	 */
	public function __unset ($name) {
		unset($this->properties[$name]);
	}
	
	/**
	 * Answer the URL of the image
	 * 
	 * @param string $size [square, thumb, small, medium, large, original]
	 * @return string
	 * @access public
	 * @since 11/7/06
	 */
	function getImageUrl ($size = 'small') {
		if (!isset($this->serverId) || !isset($this->secret))
			$this->loadInfo();
			
		switch ($size) {
			case 'medium':
				return 'http://static.flickr.com/'.$this->serverId.'/'
					.$this->id.'_'.$this->secret.'.jpg';
			case 'original':
				$this->loadInfo();
				return 'http://static.flickr.com/'.$this->serverId.'/'
					.$this->id.'_'.$this->secret.'_o.'.$this->origFormat;
			case 'square':
				$key = 's';
				break;
			case 'thumb':
				$key = 't';
				break;
			case 'small':
				$key = 'm';
				break;
			case 'large':
				$key = 'b';
				break;
		}
		
		return 'http://static.flickr.com/'.$this->serverId.'/'
					.$this->id.'_'.$this->secret.'_'.$key.'.jpg';
	}
	
	/**
	 * Load the info
	 * 
	 * @return void
	 * @access private
	 * @since 11/7/06
	 */
	private function loadInfo () {
		if (!$this->loaded) {
			$this->loaded = true;
			
			$photo = $this->callMethod('flickr.photos.getInfo', 
				array('photo_id' => $this->id));
			
//			print "<hr/>";
//			print "<pre>";
//			print_r($photo);
//			print "</pre>";
			
			// License
			$this->licenseId = $photo->getAttribute('license');
			$this->serverId = $photo->getAttribute('server');
			$this->secret = $photo->getAttribute('secret');
			$this->origFormat = $photo->getAttribute('originalformat');
			
			// Owner info
			if ($node = $this->getChild($photo, 'owner')) {
				$this->ownerId = $node->getAttribute('nsid');
				$this->ownerUserName = $node->getAttribute('username');
				$this->ownerRealName = $node->getAttribute('realname');
				$this->ownerLocation = $node->getAttribute('location');
			}
			
			// Title
			if ($node = $this->getChild($photo, 'title')) {
				if ($node->firstChild)
					$this->title = $node->firstChild->data;
			}
			
			if (!isset($this->title))
				$this->title = '';
			
			
			// Description
			if ($node = $this->getChild($photo, 'description')) {
				if ($node->firstChild)
					$this->description = $node->firstChild->data;
			}
			
			if (!isset($this->description))
				$this->description = '';
			
			// Authorizations
			if ($node = $this->getChild($photo, 'visibility')) {
				$this->isPublic = $node->getAttribute('ispublic');
				$this->isFriend = $node->getAttribute('isfriend');
				$this->isFamily = $node->getAttribute('isfamily');
			}
			
			// Dates
			if ($node = $this->getChild($photo, 'dates')) {
				$this->datePosted = $node->getAttribute('posted');
				$this->dateLastUpdated = $node->getAttribute('lastupdate');
				$this->dateTaken = $node->getAttribute('taken');
			}
			
			// Tags
			$tags = array();
			if ($node = $this->getChild($photo, 'tags')) {
				foreach ($node->childNodes as $tagNode) {
					if ($tagNode->nodeType == XML_ELEMENT_NODE)
						$tags[$tagNode->firstChild->data] = $tagNode->getAttribute('raw');
				}
			}
			$this->tags = $tags;
			
			// Location
			if ($node = $this->getChild($photo, 'location')) {
				$this->latitude = $node->getAttribute('latitude');
				$this->longitude = $node->getAttribute('longitude');
				$this->locationAccuracy = $node->getAttribute('accuracy');
			}
			
			// GeoPerms
			if ($node = $this->getChild($photo, 'geoperms')) {
				$this->geoIsPublic = $node->getAttribute('ispublic');
				$this->geoIsContact = $node->getAttribute('iscontact');
				$this->geoIsFriend = $node->getAttribute('isfriend');
				$this->geoIsFamily = $node->getAttribute('isfamily');
			}
			
			// Urls
			if ($node = $this->getChild($photo, 'urls')) {
				$this->photoPageUrl = $node->getElementsByTagName('url')->item(0)->firstChild->data;
			}			
		}
	}
	
	/**
	 * Accept a visitor
	 * 
	 * @param ref object $visitor
	 * @return mixed
	 * @access public
	 * @since 11/7/06
	 */
	function acceptVisitor ( $visitor ) {
		$result = $visitor->visitPhoto(array($this));
		return $result;
	}
}

/**
 * General methods needed by all Flickr objects
 * 
 * @since 11/7/06
 * @package com.adamfranco.flickr
 * 
 * @copyright Copyright &copy; 2006, Adam Franco
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */
class FlickrObject {
	
	/**
	 * @var object Flickr_API $api;  
	 * @access private
	 * @since 8/21/07
	 */
	protected $api;
		
	/**
	 * Call a method
	 * 
	 * @param string $method
	 * @param array $parameters
	 * @return object
	 * @access private
	 * @since 11/7/06
	 */
	function callMethod ($method, $parameters) {
		$doc = $this->api->callMethod($method, $parameters);
		if (!$doc)
			throw new FlickrException($this->api);
		
		$response = $doc->getElementsByTagName('rsp')->item(0);
		
		if ($response && $response->getAttribute('stat') == 'ok') {
// 			print "<pre>";
// 			ReflectionClass::export($response);
// 			print "</pre>";

// 			print "<hr/><pre>Method: $method\n\tParams: ".print_r($parameters, true)."\n".($doc->saveXML())."</pre>";
			
			foreach ($response->childNodes as $child) {
				if ($child->nodeType == XML_ELEMENT_NODE)
					return $child;
			}
			throw new Exception("No node of type zero found in <pre>".htmlentities($doc->saveXML($response))."</pre>");
		} else
			throw new FlickrException($this->api, "<pre>$method\n".print_r($parameters, true)."</pre>");
	}
	
	/**
	 * Answer the child with the name given or false if not available
	 * 
	 * @param object $node
	 * @param string name
	 * @return mixed
	 * @access public
	 * @since 11/8/06
	 */
	function getChild ( $node, $name ) {
		foreach ($node->childNodes as $child) {
			if ($child->nodeName == $name)
				return $child;
		}
		
		$false = false;
		return $false;
	}
}

/**
 * This exception also handles api errors.
 * 
 * @since 8/20/07
 * @package com.adamfranco.flickr
 * 
 * @copyright Copyright &copy; 2007, Adam Franco
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */
class FlickrException
	extends Exception
{	
	/**
	 * Construct this message.
	 * 
	 * @param object Flickr_API $api
	 * @param string $extraMessage
	 * @return void
	 * @access public
	 * @since 8/21/07
	 */
	public function __construct(Flickr_API $api, $extraMessage = "") {
		parent::__construct($api->getErrorMessage().$extraMessage, $api->getErrorCode());
	}
}

/**
 * This class overloads some of the PEAR Flickr API methods to return a DomDocument
 * instead of using XML_tree
 * 
 * @since 8/22/07
 * @package com.adamfranco.flickr
 * 
 * @copyright Copyright &copy; 2007, Adam Franco
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */
class AdamsFlickr_API
	extends Flickr_API
{
		
	function callMethod($method, $params = array()){
		$this->_err_code = 0;
		$this->_err_msg = '';
		
		#
		# create the POST body
		#
		
		$p = $params;
		$p['method'] = $method;
		$p['api_key'] = $this->_cfg['api_key'];
		
		if ($this->_cfg['api_secret']){
		
			$p['api_sig'] = $this->signArgs($p);
		}
		
		
		$p2 = array();
		foreach($p as $k => $v){
			$p2[] = urlencode($k).'='.urlencode($v);
		}
		
		$body = implode('&', $p2);
		
		
		#
		# create the http request
		#
		
		$req =& new HTTP_Request($this->_cfg['endpoint'], array('timeout' => $this->_cfg['conn_timeout']));
		
		$req->_readTimeout = array($this->_cfg['io_timeout'], 0);
		
		$req->setMethod(HTTP_REQUEST_METHOD_POST);
		$req->addRawPostData($body);
		
		$req->sendRequest();
		
		$this->_http_code = $req->getResponseCode();
		$this->_http_head = $req->getResponseHeader();
		$this->_http_body = $req->getResponseBody();
		
		if ($this->_http_code != 200){
		
			$this->_err_code = 0;
		
			if ($this->_http_code){
				$this->_err_msg = "Bad response from remote server: HTTP status code $this->_http_code";
                error_log("Bad response from remote server:\n\tHTTP status code: $this->_http_code \n\tResponse body: $this->_http_body");
			}else{
				$this->_err_msg = "Couldn't connect to remote server";
                error_log("Couldn't connect to remote server:\n\tHTTP status code: $this->_http_code \n\tResponse body: $this->_http_body");
			}
		
			return 0;
		}
		
		/*********************************************************
		 * Code below has been changed by Adam
		 *********************************************************/
		#
		# create xml DomDocument
		# 
		#
		
		$doc = new DomDocument();
		$doc->loadXML($this->_http_body);
				
		
		#
		# check we got an <rsp> element at the root
		#
		
		if ($doc->documentElement->nodeName != 'rsp'){
		
			$this->_err_code = 0;
			$this->_err_msg = "Bad XML response";
		
			return 0;
		}
		
		
		#
		# stat="fail" ?
		#
		
		if ($doc->documentElement->getAttribute('stat') == 'fail'){
		
			$error = $doc->getElementsByTagName('err')->item(0);
		
			$this->_err_code = $error->getAttribute('code');
			$this->_err_msg = $error->getAttribute('msg');
		
			return 0;
		}
		
		
		#
		# weird status
		#
		
		if ($doc->documentElement->getAttribute('stat') != 'ok'){
		
			$this->_err_code = 0;
			$this->_err_msg = "Unrecognised REST response status";
		
			return 0;
		}
		
		
		#
		# return the document
		#
		
		return $doc;
	}
}


/**
 * Prints out the photoset as an html page
 * 
 * @since 11/7/06
 * @package com.adamfranco.flickr
 * 
 * @copyright Copyright &copy; 2006, Adam Franco
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */
class KmlVisitor {
	
	/**
	 * @var string $photoSize; 
	 * @access private
	 * @since 8/22/07
	 */
	private $photoSize = 'medium';
	
	/**
	 * @var array $linePoints; 
	 * @access private
	 * @since 8/23/07
	 */
	private $linePoints = array();
	
	/**
	 * @var array $takenDates; 
	 * @access private
	 * @since 8/23/07
	 */
	private $takenDates = array();
	
	/**
	 * @var array $postDates; 
	 * @access private
	 * @since 8/23/07
	 */
	private $postDates = array();
	
	/**
	 * @var mixed $printLines; 
	 * @access private
	 * @since 8/23/07
	 */
	private $printLines = false;
	
	/**
	 * Set the photo size
	 * 
	 * @param string $size
	 * @return void
	 * @access public
	 * @since 8/22/07
	 */
	public function setSize ($size) {
		$sizes = array('square', 'thumb', 'small', 'medium', 'large', 'original');
		if (!in_array($size, $sizes))
			throw new Exception("Unknown size, '$size'.");
		
		$this->photoSize = $size;
	}
	
	/**
	 * Set a line printing style: false, 'chron', or 'upload'
	 * 
	 * @param string $style
	 * @return void
	 * @access public
	 * @since 8/22/07
	 */
	public function setLineStyle ($style) {
		$styles = array(false, 'chron', 'upload', 'set_order');
		if (!in_array($style, $styles))
			throw new Exception("Unknown style, '$style'. Should be one of false, 'chron', or 'upload'");
		
		$this->printLines = $style;
	}
    
    /**
     * Print the Header for the KML File
     *
     * @param string $photosetTitle
     * @param string $photosetDescription
     * @return void
     * @access private
     * @since 7/26/16
     */
    private function printKMLHeader ($photosetTitle, $photosetDescription) {
        header("Content-Type: application/vnd.google-earth.kml+xml;");
        header('Content-Disposition: attachment; filename="'.$photosetTitle.'.kml"');
        
        print '<?xml version="1.0" encoding="UTF-8"?>
            <kml xmlns="http://earth.google.com/kml/2.1">
            <Document>
                <name>'.htmlspecialchars($photosetTitle).'.kml</name>
                <Style id="flickr_photo">
                    <IconStyle>
                        <Icon>
                        <href>http://maps.google.com/mapfiles/kml/pal4/icon46.png</href>
                        </Icon>
                    </IconStyle>
                </Style>
                <Style id="flickr_photo_path">
                    <LineStyle>
                        <color>df0000ff</color>
                        <width>2</width>
                    </LineStyle>
                </Style>
                <Folder>
                    <name>'.htmlspecialchars($photosetTitle).'</name>
                    <description>'.htmlspecialchars($photosetDescription).'</description>
                    <Folder><name>Photos</name>';
    }

    /**
     * Print the Footer for the KML File
     *
     * @return void
     * @access private
     * @since 7/26/16
     */
    private function printKMLFooter () {
        print '
        </Folder>';
        if ($this->printLines) {
            if ($this->printLines == 'chron')
                array_multisort($this->takenDates, SORT_STRING, $this->linePoints);
            
            if ($this->printLines == 'upload')
                array_multisort($this->uploadDates, SORT_NUMERIC, $this->linePoints);
            
            print '
            <Placemark>
            <name>Photo Path</name>
            <styleUrl>#flickr_photo_path</styleUrl>
            <MultiGeometry>
            <LineString>
            <coordinates> ';
            
            print implode(' ', $this->linePoints);
            
            print ' </coordinates>
            </LineString>
            </MultiGeometry>
            </Placemark>';
        }
            
        print '
        </Folder>
        </Document>
        </kml>';
    }

	/**
	 * Print out the photoset
	 * 
	 * @return void
	 * @access public
	 * @since 11/7/06
	 */
	function visitPhotoset ( $photoset ) {
        $body = "";

        if (isset($_REQUEST['geoGroup']) && $_REQUEST['geoGroup'] == 'true') {
           
            // group photos by geocoordinates
            $groups = array();
            while ($photoset->hasNextPhoto()) {
                $photo = $photoset->nextPhoto();
                if (isset($photo->longitude) && isset($photo->latitude)) {
                    $geoString = $photo->longitude.",".$photo->latitude;
                    
                    if (!isset($groups[$geoString])) {
                        $groups[$geoString] = array();
                    }
                    
                    $groups[$geoString][] = $photo;
                }
            }
            
            // render each group
            foreach($groups as $geoString => $group) {
                $body .= $this->visitPhoto($group);
            }
            
        } else {
            while ($photoset->hasNextPhoto()) {
                $photo = $photoset->nextPhoto();
                $body .= $photo->acceptVisitor($this);
            }
        }
        
        $this->printKMLHeader($photoset->title, $photoset->description);
        
        print $body;
        
        $this->printKMLFooter();
	}
	
	/**
	 * Print out the photo
	 * 
	 * @return void
	 * @access public
	 * @since 11/7/06
	 */
	function visitPhoto ( $photoArray ) {
        $placemark = "";
        $photoCount = count($photoArray);
        $idx = 1;
        $description = "";
        $photoLinks = "";
        $lastPhoto = NULL;
        foreach ($photoArray as $photo) {
            if (isset($photo->longitude) && isset($photo->latitude)) {
                
                $description .= "<p>";
                
                if ($photoCount > 1)
                    $description .= $idx.": ";
                
                $description .= $photo->title." (".$photo->photoPageUrl.")";
                
                if (isset($_REQUEST['userLink']) && $_REQUEST['userLink'] == 'true') {
                    $description .= " by ";
                    if ($photo->ownerRealName)
                        $description .= $photo->ownerRealName;
                    else
                        $description .= $photo->ownerUserName;
                    $description .= " (http://www.flickr.com/people/".$photo->ownerId."/)";
                }
                
                $description .= "</p>";
                
                if ($idx > 1)
                    $photoLinks .= " ";
                $photoLinks .= $photo->getImageUrl($this->photoSize);
                
                $this->linePoints[] = $photo->longitude.",".$photo->latitude.",0";
                $this->takenDates[] = $photo->dateTaken;
                $this->postDates[] = $photo->datePosted;
                
                $idx = $idx + 1;
                $lastPhoto = $photo;
            }
        }
        if (!is_null($lastPhoto)) {
            
            $placemark .= "\n\t\t\t<Placemark>";
            
            if ($photoCount > 1) {
                $placemark .= "\n\t\t\t\t<name>Group of ".$photoCount." photos</name>";
            } else {
                $placemark .= "\n\t\t\t\t<name>".htmlspecialchars($lastPhoto->title)."</name>";
            }
            
            $placemark .= "\n\t\t\t\t<description><![CDATA[";
            
            if ($photoCount == 1)
                $placemark .= "<p>".$lastPhoto->description."</p>";
            
            $placemark .= $description . "]]></description><ExtendedData><Data name='gx_media_links'><value>";
            $placemark .= $photoLinks . "</value></Data></ExtendedData>\n\t\t\t\t<Style>\n\t\t\t\t\t<IconStyle>\n\t\t\t\t\t\t<Icon>";
            
            if ($photoCount == 1) {
                $placemark .= "\n\t\t\t\t\t\t\t<href>".$lastPhoto->getImageUrl('square')."</href>";
            } else {
                $placemark .= "\n\t\t\t\t\t\t\t<href>http://maps.google.com/mapfiles/kml/pal4/icon46.png</href>";
            }
            $placemark .= "\n\t\t\t\t\t\t</Icon>\n\t\t\t\t\t</IconStyle>\n\t\t\t\t</Style>\n\t\t\t\t<Point>\n\t\t\t\t<coordinates>";
            $placemark .= $lastPhoto->longitude.",".$lastPhoto->latitude.",0</coordinates>\n\t\t\t\t</Point>\n\t\t\t</Placemark>";
            
        }
        return $placemark;
	}
}

/**
 * Script execution
 *
 * @since 11/7/06
 * @package com.adamfranco.flickr
 * 
 * @copyright Copyright &copy; 2006, Adam Franco
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License (GPL)
 *
 * @version $Id$
 */ 

if (!isset($_REQUEST['set'])) {
header("Content-type: text/html; charset=utf-8");
		print "<"."?xml version='1.0' encoding='iso-8859-1'?".">
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Strict//EN'
        'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' lang='en' xml:lang='en'>
<head>
	<title>Flickr Photo Set to KML by Adam Franco</title>
	<style type='text/css'>
		.about {
			font-size: small;
			margin-top: 50px;
		}
		
		form div {
			margin-top: 10px;
			margin-bottom: 10px;
		}
		
		form div.set {
			margin-left: 20px;
			background-color: #CCC;
			padding: 5px;
			width: 500px;
		}
		
		form div.lines_chooser div {
			margin-left: 20px;
		}
		
		form div.photo_style_chooser div {
			margin-left: 20px;
		}
		
		.notes {
			margin-left: 30px;
			font-size: small;
		}
	
	</style>
</head>
<body>
	<h1>Flickr Photo Set to KML</h1>
	<form action='".$_SERVER['PHP_SELF']."' method='get'>
		<div>Enter the id number of the <a href='http://www.flickr.com/'>Flickr</a> photo set you wish to generate KML for:
		<div class='set'>http://www.flickr.com/photos/xxxxxxxx/albums/<input type='text' name='set'/>/</div>
		<input type='submit'/>
		<h2>Options:</h2>
		<div>Photo size:
			<select name='size'>
				<option value='square'>square</option>
				<option value='thumb'>thumbnail</option>
				<option value='small'>small</option>
				<option value='medium' selected='selected'>medium</option>
				<option value='large'>large</option>
				<option value='original'>original</option>
			</select>
		</div>
    
		<div class='lines_chooser'>
		<input type='checkbox' name='paths' value='true' />
		Draw lines (paths) between photos.
		
		<div><input type='radio' name='path_order' value='chron' checked='checked'/>
		<strong>Chronologically</strong> - Paths will be drawn based on photo creation date.</div>
		
		<div><input type='radio' name='path_order' value='upload'/>
		<strong>Chronologically - by upload</strong> - Paths will be drawn based on date the photo was uploaded to Flickr.</div>
		
		<div><input type='radio' name='path_order' value='set_order'/>
		<strong>Set Order</strong> - Paths will be drawn based on the order they appear in the set.</div>
		
		</div>
		
        <div>
            <input type='checkbox' name='userLink' value='true'/>
            Include link to flickr user profile.
        </div>
        <div>
            <input type='checkbox' name='geoGroup' value='true' checked='checked'/>
            Group photos with shared coordinates into one point
        </div>
	</div>
	</form>
	<p class='about'>Photo Set to KML was written by <a href='http://www.adamfranco.com'>Adam Franco</a> and is licensed under the <a href='http://www.gnu.org/copyleft/gpl.html'>GNU General Public License (GPL)</a> version 3 or later.
	<br/><br/><a href='http://www.adamfranco.com/2007/08/23/flickr-photo-set-to-kml/'>More information</a>, <a href='https://github.com/adamfranco/PhotoSetToKML'>Source Code on Github</a>.</p>
</body>
</html>
";

}

// Output the KML
else {
	try {
		$photoset = new Photoset (
			new AdamsFlickr_API(array(
                    'api_key'  => '431f47e7c3952108d31df985e7b3b5a5',
                    'endpoint'	=> 'https://www.flickr.com/services/rest/',
                    'auth_endpoint'	=> 'https://www.flickr.com/services/auth/?',
            )),
			$_REQUEST['set']);
			
		$visitor = new KmlVisitor;
		if (isset($_REQUEST['size'])) {
			$visitor->setSize($_REQUEST['size']);
		}
		if (isset($_REQUEST['paths']) && $_REQUEST['paths'] == 'true') {
			if (isset($_REQUEST['path_order']))
				$visitor->setLineStyle($_REQUEST['path_order']);
		}
		
		$photoset->acceptVisitor($visitor);
	} catch (Exception $e) {
		header("HTTP/1.0 500 ".$e->getMessage());
		header("Content-type: text/html");
		print "
<html>
<head>
</head>
<body>
	<h1>An error has occurred:</h1>
	<p>".$e->getMessage()."</p>
</body>
</html>
";
	}
}

?>