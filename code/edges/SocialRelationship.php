<?php
namespace Modular\Edges;

/**
 * SocialModel between two models with a relationship type, used to track historical actions and relationships made/broken.
 */
class SocialRelationship extends Directed {
	const NodeAClassName = 'Modular\Models\SocialModel';
	const NodeBClassName = 'Modular\Models\SocialModel';

	private static $from_model_class = self::NodeAClassName;
	private static $to_model_class = self::NodeBClassName;

	private static $from_field_name = self::NodeAFieldName;
	private static $to_field_name = self::NodeBFieldName;

}
