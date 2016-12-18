<?php
namespace Modular\Extensions\Controller;

use Modular\ModelExtension;
use Modular\Types\SocialEdgeType;

/**
 * Extension to add to a controller which controls a SocialModel
 */
class SocialModelController extends ModelExtension {
	public function extraStatics($class = null, $extension = null) {
		return array_merge_recursive(
			parent::extraStatics($class, $extension),
			[
				'url_handlers' => $this->urlHandlers(),
			]
		);
	}

	protected function urlHandlers() {
		$x = new \ContentController();
		$extensions = $x::get_extensions(get_class($this()));

		$handlers = [];

		foreach ($extensions as $extension) {
			if ($extension instanceof SocialAction) {
				$typeCode = $extension::ActionCode;
				/** @var SocialEdgeType $type */
				if ($type = SocialEdgeType::get_by_code($typeCode)) {
					$handlers[ '$ID/' . $type->ActionName ] = $type->ActionName;
					$handlers[ '$ID/' . $type->ReverseActionName ] = $type->ReverseActionName;
				}
			}
		}
		return $handlers;
	}
}