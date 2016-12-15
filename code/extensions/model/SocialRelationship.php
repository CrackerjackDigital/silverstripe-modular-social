<?php
namespace Modular\Relationships;

use Modular\Edges\SocialRelationship;

/**
 * Should be added to Models which are Relationships, providing
 * dynamic generation of has_one fields from the extended models
 * constants FromModel, ToModel, FromField, ToField
 */
class SocialRelationshipExtension extends \Modular\ModelExtension {
	public function extraStatics($class = null, $extension = null) {
		$config = parent::extraStatics($class, $extension) ?: [];

		/** @var string|SocialRelationship $class typehint for methods, really a string */
		if ($class && $class != 'SocialModel') {
			$rels = array_filter([
				$class::from_field_name('') => $class::from_class_name(),
				$class::to_field_name('')   => $class::to_class_name(),
			]);
			if ($rels) {
				$config = array_merge_recursive(
					$config,
					[
						'has_one' => $rels,
					]
				);
			}
		}
		return $config;
	}
}