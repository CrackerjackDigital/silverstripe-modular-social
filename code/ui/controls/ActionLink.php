<?php
namespace Modular\UI\Controls;
use LiteralField;

/**
 * Class which implements an anchor link which can be styled like a button
 */
class ActionLinkField extends LiteralField {
	private static $css_class = 'action-link';

	public function __construct($name, $href, $label) {
		$cssClass = strtolower(self::config()->get('css_class') . ' ' . str_replace(' ', '', $label));
		return parent::__construct(
			$name,
			'<a class="' . $cssClass . '" href="' . $href . '">' . $label . '</a>'
		);
	}
}