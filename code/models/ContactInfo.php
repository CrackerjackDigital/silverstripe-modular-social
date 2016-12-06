<?php
namespace Modular\Models;

/**
 *
 * Contact Information Model
 *
 */
class SocialContactInfo extends SocialModel {
	private static $has_one = [
	];

	private static $db = [
		"Address"     => "Text",
		"PhoneNumber" => "Varchar",
		"Email"       => "Varchar",
		"Location"    => "Varchar",
	];

	private static $singular_name = 'Contact Information';

	private static $plural_name = 'Contact Information';

	public function GoogleMap($w = 200, $h = 250) {
		$loc = urlencode($this->Address . "," . $this->Location . "");
		return "https://maps.googleapis.com/maps/api/staticmap?key=" . GOOGLE_MAP_API_KEY
		. "&center=" . $loc
		. "&zoom=14&size=" . $w . "x" . $h
		. "&markers=size:small%7Ccolor:red%7Clabel:C%7C" . $loc;
	}

}