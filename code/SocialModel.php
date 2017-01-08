<?php
namespace Modular\Models;
use Modular\Interfaces\SocialModel as SocialModelInterface;
use Modular\Models\Graph\Node;

/**
 * 'Base' class for models. Adds common functionality, shouldn't add data
 * fields, they should be in extensions.
 *
 */
class SocialModel extends Node implements SocialModelInterface  {

	private static $db = [];

	private static $has_one = [];

	public function endpoint() {
        return $this->config()->get('route_part');
    }

}