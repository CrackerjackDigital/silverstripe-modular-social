<?php
namespace Modular\Edges;

use ArrayList;
use DataObject;
use Modular\Exceptions\Social as Exception;
use Modular\Types\Graph\DirectedEdgeType;
use Modular\Types\SocialEdgeType as SocialEdgeType;

/**
 * SocialModel between two models with a relationship type, used to track historical actions and relationships made/broken.
 */
class SocialRelationship extends Directed {
	const EdgeTypeClassName = 'Modular\Types\SocialEdgeType';
	const EdgeTypeFieldName = 'EdgeType';

	const NodeAClassName = 'Modular\Models\SocialModel';
	const NodeBClassName = 'Modular\Models\SocialModel';

	private static $db = [
		'Action' => 'Varchar(32)'        // what was the actual action, or was it the 'reverse' action e.g. 'approve' or 'decline'?
	];

	/**
	 * Given a SocialEdgeType, a Code, a SocialEdgeType ID or null set the has_one relationship accordingly and what action
	 * was performed, e.g 'approve', 'decline' etc
	 *
	 * @param string|DataObject|int|null $actionType
	 * @param string                     $action
	 * @return \Modular\Edges\Directed|\Modular\Edges\SocialRelationship
	 * @throws \Modular\Exceptions\Social
	 */
	public function setActionType($actionType, $action) {
		if (is_object($actionType) && (!$actionType instanceof SocialEdgeType)) {
			throw new Exception("Got handed an object that wasn't a SocialEdgeType");
		}
		return parent::setEdgeType($actionType, $action);
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
		/** @var SocialEdgeType $edgeType */
		$edgeType = SocialEdgeType::get_for_models(
			$fromModel,
			$toModel,
			$actionCode
		)->first();
	}

	/**
	 * Returns object related 'from' the FromModel (so the to objects), the latest first.
	 *
	 * e.g. for a MemberOrganisationRelationship given a Member
	 * will return the related Organisations.
	 *
	 * @param \DataObject  $nodeAModel e.g. Member for a MemberOrganisationRelationship
	 * @param string|array $typeCodes  what action performed are we looking for e.g 'CRT'
	 * @return \DataList of 'to' models
	 * @api
	 */
	protected static function node_a_for_type(DataObject $nodeAModel, $typeCodes = []) {
		// return of list of relationship from $nodeAObject to any object
		$relationships = static::graph($nodeAModel, null, $typeCodes);
		return \DataObject::get(static::node_b_class_name())->filter([
			'ID' => $relationships->column(static::node_b_field_name()),
		])->sort(static::config()->get('default_sort'));

	}

	/**
	 * Returns objects related to the ToModel (so the 'from' objects) the latest first.
	 *
	 * e.g. for a MemberOrganisation relationship given an SocialOrganisation
	 * will return its related Members.
	 *
	 * @param \DataObject  $nodeBModel e.g. SocialOrganisation for a MemberOrganisationRelationship
	 * @param string|array $typeCodes
	 * @return \DataList of 'from' models
	 * @api
	 */
	protected static function node_b_for_type(DataObject $nodeBModel, $typeCodes = []) {
		// return of list of relationship from $nodeAObject to any object
		$relationships = static::graph(null, $nodeBModel, $typeCodes);
		return \DataObject::get(static::node_a_class_name())->filter([
			'ID' => $relationships->column(static::node_a_field_name()),
		])->sort(static::config()->get('default_sort'));
	}

	/**
	 * Create an Edge or edges between the two models with the provided edge types.
	 *
	 * @param DataObject                         $nodeAModel
	 * @param DataObject                         $nodeBModel
	 * @param string|DirectedEdgeType|DataObject $typeCode             Code string or a DirectedEdgeType model
	 * @param array|string                       $variantData          Extra data to set on the created Edge(s)
	 * @param bool                               $createImpliedActions also create relationships many many records listed in the DirectedEdgeType.ImpliedTypes.
	 * @return \ArrayList of all edges created (including Implied ones if requested to)
	 * @throws \Modular\Exceptions\Social
	 * @api
	 */
	public static function make(DataObject $nodeAModel, DataObject $nodeBModel, $typeCode, $variantData = [], $createImpliedActions = true) {
		// check permissions
		if (is_object($typeCode)) {
			$typeCode = $typeCode->Code;
		}
		if (!isset($typeCode)) {
			throw new Exception("Need a type code to make an Edge");
		}

		$edges = new ArrayList();

		// get a list of DirectedEdgeType records (e.g. SocialEdgeType) between teo models and which handle the provided codes.
		$edgeTypes = DirectedEdgeType::create()->get_for_models($nodeAModel, $nodeBModel, $typeCode);

		/** @var DirectedEdgeType|SocialEdgeType $edgeType e.g. a SocialEdgeType implementor */
		foreach ($edgeTypes as $edgeType) {

			if ($edgeType::check_permission($nodeAModel, $nodeBModel, $typeCode)) {
				// get all the Edge implementation class names between the two models.
				$edgeClasses = Edge::implementors($nodeAModel, $nodeBModel);

				/** @var Edge $edge */
				foreach ($edgeClasses as $edgeClass) {
					$edge = new $edgeClass();

					$edge->setNodeA($nodeAModel);
					$edge->setNodeB($nodeBModel);
					$edge->setEdgeType($edgeType, $variantData);

					$edge->write();

					$edges->push($edge);

					if ($createImpliedActions) {
						$edges->merge(
							$edgeType->createImpliedRelationships($nodeAModel, $nodeBModel, $variantData)
						);
					}

				}
			}
		}
		return $edges;
	}

	/**
	 * Remove all relationships of a particular type between two models. Only one type allowed!
	 *
	 * @param DataObject $nodeAModel
	 * @param DataObject $nodeBModel
	 * @param string     $typeCode
	 * @param string     $variantType e.g. the action performed, such as 'accept' or 'decline'
	 * @return bool true if all removed, false if not (e.g. if no permissions or removing one of them failed).
	 *
	 * @api
	 */
	public static function remove(DataObject $nodeAModel, DataObject $nodeBModel, $typeCode, $variantType = '') {
		// check we have permissions to perform supplied relationship
		if ($ok = static::edge_type()->check_permission($typeCode, $nodeAModel, $nodeBModel)) {
			$edges = SocialRelationship::graph(
				$nodeAModel,
				$nodeBModel,
				$typeCode,
				$variantType
			);
			/** @var \Modular\Interfaces\Graph\Edge $edge */
			foreach ($edges as $edge) {
				$ok = $ok && $edge->prune();
			}
		}
		return $ok;
	}

	/**
	 * Check to to see if a DirectedEdgeType exists between two models with the supplied code.
	 *
	 * @param DataObject   $nodeAModel e.g. a Member
	 * @param DataObject   $nodeBModel e.g. a Post
	 * @param array|string $typeCode   e.g. 'MLP' for 'Member Likes Post'
	 * @return bool
	 * @api
	 */
	public static function exists_by_type(DataObject $nodeAModel, DataObject $nodeBModel, $typeCode) {
		return !!self::graph($nodeAModel, $nodeBModel, $typeCode)->count();
	}

}
