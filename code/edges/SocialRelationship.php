<?php
namespace Modular\Edges;

use DataObject;
use Modular\Exceptions\Social as Exception;
use Modular\Types\Social\ActionType as SocialActionType;

/**
 * SocialModel between two models with a relationship type, used to track historical actions and relationships made/broken.
 */
class SocialRelationship extends Directed {
	const NodeAClassName = 'Modular\Models\SocialModel';
	const NodeBClassName = 'Modular\Models\SocialModel';

	private static $from_model_class = self::NodeAClassName;
	private static $to_model_class = self::NodeBClassName;

	// keep these in sync then we should be able to create a new relationship
	// with an 'update' from the Action being created (providing 'ID' is being added to the field name)
	private static $from_field_name = \Modular\Types\Social\ActionType::FromModelFieldName;
	private static $to_field_name = \Modular\Types\Social\ActionType::ToModelFieldName;

	private static $db = [
		'Action' => 'Varchar(32)'        // what was the actual action, or was it the 'reverse' action e.g. 'approve' or 'decline'?
	];

	/**
	 * Given a SocialActionType, a Code, a SocialActionType ID or null set the has_one relationship accordingly and what action
	 * was performed, e.g 'approve', 'decline' etc
	 *
	 * @param string|DataObject|int|null $actionType
	 * @param string                     $action
	 * @return \Modular\Edges\Directed|\Modular\Edges\SocialRelationship
	 * @throws \Modular\Exceptions\Social
	 */
	public function setActionType($actionType, $action) {
		if (is_object($actionType) && (!$actionType instanceof SocialActionType)) {
			throw new Exception("Got handed an object that wasn't a SocialActionType");
		}
		return parent::setEdgeType($actionType, $action);
	}

	/**
	 * Return a filter which can be used to select a Edge or edges based on parameters;
	 *
	 * @param int|DataObject $nodeAID
	 * @param int|DataObject $nodeBID
	 * @return array e.g. [ 'FromModelID' => 10, 'ToModelID' => 20', 'EdgeType.Code' => 'APP' ]
	 */
	public static function archtype($nodeAID, $nodeBID, $typeCodes = []) {
		$fromFieldname = static::node_a_field_name('ID');
		$toFieldName = static::node_b_field_name('ID');
		$identityFieldName = static::edge_type_class_name('.Code');

		$nodeAID = is_object($nodeAID) ? $nodeAID->ID : $nodeAID;
		$nodeBID = is_object($nodeBID) ? $nodeBID->ID : $nodeBID;

		return [
			$fromFieldname     => $nodeAID,
			$toFieldName       => $nodeBID,
			$identityFieldName => $typeCodes,
		];
	}

	/**
	 * Look back in time for a relationship between the two models which matches the provided Code.
	 *
	 * @param DataObject $fromModel
	 * @param DataObject $toModel
	 * @param            $actionCode
	 * @return SocialRelationship
	 */
	public static function check_relationship_exists(DataObject $fromModel, DataObject $toModel, $actionCode) {
		/** @var SocialActionType $edgeType */
		$edgeType = SocialActionType::get_for_models(
			$fromModel,
			$toModel,
			$actionCode
		)->first();
	}

}
