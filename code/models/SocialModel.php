<?php
namespace Modular\Models;

use Modular\Edges\SocialRelationship;
use Modular\Interfaces\SocialModel as SocialModelInterface;
use Modular\Models\Graph\Node;

/**
 * 'Base' class for models. Adds common functionality, shouldn't add data
 * fields, they should be in extensions.
 *
 */
class SocialModel extends Node implements SocialModelInterface {

	private static $db = [];

	private static $has_one = [];

	// classes derived from this use their own class name
	private static $custom_class_name = '';

	public function endpoint() {
		return $this->config()->get('route_part');
	}

	/**
	 * Return the latest action that was performed To this model of the particular type code.
	 * @param $typeCode
	 * @return \DataObject
	 */
	public function latest($typeCode) {
		return SocialRelationship::latest(null, $this(), $typeCode);
	}

}