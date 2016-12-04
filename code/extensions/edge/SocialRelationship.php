<?php
namespace Modular\Relationships;
/**
 * Provided dynamic generation of has_one fields from the extended models constants FromModel, ToModel, FromField, ToField
 */
class SocialRelationshipExtension extends \Modular\ModelExtension {
	public function extraStatics($class = null, $extension = null) {
		$config = parent::extraStatics($class, $extension) ?: [];

		if ($class && $class != 'SocialModel') {
			$rels = array_filter([
				$class::from_field_name('') => $class::from_model_class(),
				$class::to_field_name('')   => $class::to_model_class(),
			]);
			if ($rels) {
				/** @var string|SocialRelationship $class typehint for methods really a string */
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