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

	// classes derived from this use their own class name
	private static $custom_class_name = '';

	private static $db = [
		'Action' => 'Varchar(32)'        // what was the actual action, or was it the 'reverse' action e.g. 'approve' or 'decline'?
	];

	/**
	 * Given a SocialEdgeType, a Code, a SocialEdgeType ID or null set the has_one relationship accordingly and what action
	 * was performed, e.g 'approve', 'decline' etc
	 *
	 * @param string|DataObject|int|null $edgeType
	 * @param string                     $actionName
	 * @return \Modular\Edges\Directed|\Modular\Edges\SocialRelationship
	 * @throws \Modular\Exceptions\Social
	 */
	public function setEdgeType($edgeType, $actionName = '') {
		if (is_object($edgeType) && (!$edgeType instanceof SocialEdgeType)) {
			throw new Exception("Got handed an object that wasn't a SocialEdgeType");
		}
		// now set the variant data on the Edge if passed
		if ($edgeType) {
			if (is_array($actionName)) {
				$this->update($actionName);
			} else if (static::TypeVariantFieldName) {
				$this->{static::TypeVariantFieldName} = $actionName;
			}
		} else {
			if (is_array($actionName)) {
				$this->update($actionName);
			} else if (static::TypeVariantFieldName) {
				$this->{static::TypeVariantFieldName} = $actionName;
			}
		}
		return parent::setEdgeType($edgeType);
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
	 * Alias for graph.
	 *
	 * @param        $fromModel
	 * @param        $toModel
	 * @param array  $actionCodes
	 * @param string $action
	 * @return \DataList
	 */
	public static function get_for_models($fromModel, $toModel, $actionCodes = [], $action = '') {
		return static::graph($fromModel, $toModel, $actionCodes, $action);
	}

	/**
	 * Returns a list of all edges which match on supplied models, edge types and edge type variants, not necessarily in any order.
	 *
	 * @param DataObject|int $fromModel  a model or an ID
	 * @param DataObject|int $toModel  a model or an ID
	 * @param array          $actionCodes
	 * @param string         $action filter also by extra data set on the Edge
	 * @return \DataList
	 */
	public static function graph($fromModel, $toModel, $actionCodes = [], $action = '') {
		$graph = parent::graph($fromModel, $toModel);
		if ($actionCodes) {
			$typeIDs = SocialEdgeType::get_heirarchy(
				$fromModel,
				$toModel,
				$actionCodes
			)->column('ID');

			$graph = $graph->filter([
				static::edge_type_field_name('ID') => $typeIDs,
			]);
		}
		if ($action) {
			$graph = $graph->filter([
				self::TypeVariantFieldName => $action,
			]);
		}
		return $graph;
	}

	/**
	 * Return tha latest model which satisfies the supplied parameters.
	 *
	 * @param        $fromModel
	 * @param        $toModel
	 * @param array  $typeCodes
	 * @param string $action
	 * @return \DataObject|SocialRelationship
	 */
	public static function latest($fromModel, $toModel, $typeCodes = [], $action = '') {
		return static::graph($fromModel, $toModel, $typeCodes, $action)->sort('Created', 'Desc')->first();
	}

	/**
	 * Return tha oldest model which satisfies the supplied parameters.
	 *
	 * @param        $fromModel
	 * @param        $toModel
	 * @param array  $typeCodes
	 * @param string $action
	 * @return \DataObject|SocialRelationship
	 */
	public static function oldest($fromModel, $toModel, $typeCodes = [], $action = '') {
		return static::graph($fromModel, $toModel, $typeCodes, $action)->sort('Created', 'Asc')->first();
	}

	/**
	 * Return a filter which can be used to select a Edge or edges using IDs of models passed, and filtering by type codes.
	 *
	 * @param int|DataObject $fromModelID
	 * @param int|DataObject $toModelID
	 * @return array e.g. [ 'FromModelID' => 10, 'ToModelID' => 20', 'EdgeType.Code' => 'APP' ]
	 */
	public static function archtype($fromModelID, $toModelID, $typeCodes = []) {
		$fromFieldname = static::from_field_name('ID');
		$toFieldName = static::to_field_name('ID');
		$edgeTypeFilterName = static::edge_type_class_name('.Code');

		$fromModelID = is_object($fromModelID) ? $fromModelID->ID : $fromModelID;
		$toModelID = is_object($toModelID) ? $toModelID->ID : $toModelID;

		return [
			$fromFieldname     => $fromModelID,
			$toFieldName       => $toModelID,
			$edgeTypeFilterName => $typeCodes,
		];
	}

	/**
	 * Returns object related 'from' the FromModel (so the to objects), the latest first.
	 *
	 * e.g. for a MemberOrganisationRelationship given a Member
	 * will return the related Organisations.
	 *
	 * @param \DataObject  $fromModel e.g. Member for a MemberOrganisationRelationship
	 * @param string|array $typeCodes  what action performed are we looking for e.g 'CRT'
	 * @return \DataList of 'to' models
	 * @api
	 */
	protected static function node_a_for_type(DataObject $fromModel, $typeCodes = []) {
		// return of list of relationship from $fromModelObject to any object
		$relationships = static::graph($fromModel, null, $typeCodes);
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
	 * @param \DataObject  $toModel e.g. SocialOrganisation for a MemberOrganisationRelationship
	 * @param string|array $typeCodes
	 * @return \DataList of 'from' models
	 * @api
	 */
	protected static function node_b_for_type(DataObject $toModel, $typeCodes = []) {
		// return of list of relationship from $fromModelObject to any object
		$relationships = static::graph(null, $toModel, $typeCodes);
		return \DataObject::get(static::node_a_class_name())->filter([
			'ID' => $relationships->column(static::node_a_field_name()),
		])->sort(static::config()->get('default_sort'));
	}

	/**
	 * Create an Edge or edges between the two models with the provided edge types.
	 *
	 * @param DataObject                         $fromModel
	 * @param DataObject                         $toModel
	 * @param string|DirectedEdgeType|DataObject $typeCode             Code string or a DirectedEdgeType model
	 * @param array|string                       $variantData          Extra data to set on the created Edge(s)
	 * @param bool                               $createImpliedActions also create relationships many many records listed in the DirectedEdgeType.ImpliedTypes.
	 * @return \ArrayList of all edges created (including Implied ones if requested to)
	 * @throws \Modular\Exceptions\Social
	 * @api
	 */
	public static function make(DataObject $fromModel, DataObject $toModel, $typeCode, $variantData = [], $createImpliedActions = true) {
		// check permissions
		if (is_object($typeCode)) {
			$typeCode = $typeCode->Code;
		}
		if (!isset($typeCode)) {
			throw new Exception("Need a type code to make an Edge");
		}

		$edges = new ArrayList();

		// get a list of DirectedEdgeType records (e.g. SocialEdgeType) between teo models and which handle the provided codes.
		$edgeTypes = SocialEdgeType::create()->get_for_models($fromModel, $toModel, $typeCode);

		/** @var DirectedEdgeType|SocialEdgeType $edgeType e.g. a SocialEdgeType implementor */
		foreach ($edgeTypes as $edgeType) {

			if ($edgeType::check_permission($fromModel, $toModel, $typeCode)) {
				// get all the Edge implementation class names between the two models.
				$edgeClasses = Edge::implementors($fromModel, $toModel, true);

				/** @var SocialRelationship $edge */
				foreach ($edgeClasses as $edgeClass) {
					$edge = new $edgeClass();

					$edge->setNodeA($fromModel);
					$edge->setNodeB($toModel);
					$edge->setEdgeType($edgeType, $variantData);

					$edge->write();

					$edges->push($edge);

					if ($createImpliedActions) {
						$edges->merge(
							$edgeType->createImpliedRelationships($fromModel, $toModel, $variantData)
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
	 * @param DataObject $fromModel
	 * @param DataObject $toModel
	 * @param string     $typeCode
	 * @param string     $actionName e.g. the action performed, such as 'accept' or 'decline'
	 * @return bool true if all removed, false if not (e.g. if no permissions or removing one of them failed).
	 *
	 * @api
	 */
	public static function remove(DataObject $fromModel, DataObject $toModel, $typeCode, $actionName = '') {
		// check we have permissions to perform supplied relationship
		if ($ok = SocialEdgeType::check_permission($fromModel, $toModel, $typeCode)) {
			$edges = static::graph(
				$fromModel,
				$toModel,
				$typeCode,
				$actionName
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
	 * @param DataObject   $fromModel e.g. a Member
	 * @param DataObject   $toModel e.g. a Post
	 * @param array|string $typeCode   e.g. 'MLP' for 'Member Likes Post'
	 * @return bool
	 * @api
	 */
	public static function exists_by_type(DataObject $fromModel, DataObject $toModel, $typeCode) {
		return !!self::graph($fromModel, $toModel, $typeCode)->count();
	}

}
