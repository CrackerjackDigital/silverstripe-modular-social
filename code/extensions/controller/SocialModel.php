<?php
namespace Modular\Extensions\Controller;

use Controller;
use Modular\ModelExtension;
use Modular\Types\SocialEdgeType;

/**
 * Extension to add to a controller which controls a SocialModel, this needs to inherit from ModelExtension instead of a controller extension
 * so extraStatics is called on it.
 */
class SocialModelController extends ModelExtension {
	public function extraStatics($class = null, $extension = null) {
		return array_merge_recursive(
			parent::extraStatics($class, $extension) ?: [],
			[
				'url_handlers' => $this->urlHandlers($class, $extension),
			]
		);
	}

	protected function urlHandlers($class, $extension) {
		$extensions = $class::get_extensions(get_class($this()));

		$handlers = [];

		foreach ($extensions as $extension) {
			if ($extension instanceof SocialAction) {
				if ($typeCode = $extension::action_code()) {
					/** @var SocialEdgeType $type */
					if ($type = SocialEdgeType::get_by_code($typeCode)) {
						$handlers[ '$ID/' . $type->ActionName ] = $type->ActionName;
						$handlers[ '$ID/' . $type->ReverseActionName ] = $type->ReverseActionName;
					}

				}
			}
		}
		return $handlers;
	}
}