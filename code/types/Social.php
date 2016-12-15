<?php
namespace Modular\Types;

use Modular\Type;

class SocialType extends Type {
	/**
	 * Return passed string as an array of codes, the passed string may be an array already, a single code or a csv list of codes.
	 * @param array|string $typeCodes
	 * @return array
	 */
	public static function parse_type_codes($typeCodes) {
		if (!is_array($typeCodes)) {
			$typeCodes = array_filter(explode(',', $typeCodes));
		}
		return $typeCodes;
	}
}