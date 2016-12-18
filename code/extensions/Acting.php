<?php
namespace Modular\Extensions;

use DataList;
use DataObject;
use Modular\Edges\SocialRelationship;
use Modular\ModelExtension;

class Acting extends ModelExtension {

	/**
	 * Return all the actions performed on the extended model by the actor.
	 *
	 * @param string            $forAction           a relationship type/action Code e.g. 'CRT' or empty for all
	 * @param string|DataObject $actorModels         of the class related to the extended model,
	 *                                               e.g. 'Member' or 'SocialOrganisation'
	 * @return \ArrayList of all ManyManyRelationships which match passed criteria.
	 */
	public function Actions($forAction = '', $actorModels = 'Member') {
		$out = new \ArrayList();

		if ($actionModel = $this()->getModelInstance($forAction)) {
			if ($actionModel->ID) {
				$actionModelClass = $actionModel->ClassName;

				$actorModels = is_object($actorModels) ? get_class($actorModels) : $actorModels;

				if (!is_array($actorModels)) {
					$actorModels = [$actorModels];
				}

				/** @var string|SocialRelationship $relationshipClass */
				$relationshipClasses = SocialRelationship::implementors(
					$actorModels,
					$actionModelClass
				);

				foreach ($relationshipClasses as $relationshipClass) {
					/** @var DataList $actions */
					$actions = $relationshipClass::get()->filter([
						$relationshipClass::to_field_name() => $actionModel->ID,
					]);
					if ($forAction) {
						$actions = $actions->filter([
							'Type.Code' => $forAction,
						]);
					}
					$out->merge($actions);
				}
			}
		}

		return $out;
	}

	/**
	 * Return the latest action from Actions of the matching type.
	 *
	 * @param        $actionCode
	 * @param string $actorModels
	 * @return \DataObject
	 */
	public function LatestAction($actionCode, $actorModels = 'Member') {
		return $this->Actions($actionCode, $actorModels)->sort('Created DESC')->first();
	}

	/**
	 * Return the oldest action from Actions of the matching type.
	 *
	 * @param        $actionCode
	 * @param string $actorModels
	 * @return \DataObject
	 */
	public function OldestAction($actionCode, $actorModels = 'Member') {
		return $this->Actions($actionCode, $actorModels)->sort('Created ASC')->first();
	}

	/**
	 * Return all the Actors who performed actions on the extended model
	 * (generally and by default Members but may be e.g. Organisations).
	 *
	 * @param string $forAction
	 * @param string $actorModel
	 * @return \ArrayList
	 */
	public function Actors($forAction = '', $actorModel = 'Member') {
		$actionModelClass = $this()->getModelClass();

		$actions = $this->Actions($forAction, $actorModel);

		/** @var string|SocialRelationship $relationshipClass */
		$relationshipClass = current(SocialRelationship::implementors(
			$actorModel,
			$actionModelClass,
			true
		));

		if ($relationshipClass) {
			return $actions->filter([
				'ID' => $actions->column($relationshipClass::from_field_name()),
			]);
		}
		return new \ArrayList();
	}

	/**
	 * Return the historically first actor who did an action on the extended model. Can be used in templates
	 * e.g. FirstActor(CRT) will get you the creator of a model.
	 *
	 * @param string            $forAction  a relationship type/action Code e.g. 'CRT'
	 * @param string|DataObject $actorModel of the class related to the extended model, e.g. 'Member'
	 * @return \DataObject
	 */
	public function FirstActor($forAction, $actorModel = 'Member') {
		$actorModel = is_object($actorModel) ? $actorModel->ClassName : $actorModel;

		$action = $this->Actors($forAction, $actorModel)->sort('Created asc')->first();
		return ($action && $action->exists()) ? $action->getFrom() : null;
	}

	/**
	 * Return the historically last actor who did an action on the extended model.
	 *
	 * @param string            $forAction  a relationship type/action Code e.g. 'CRT'
	 * @param string|DataObject $actorModel of the class related to the extended model, e.g. 'Member'
	 * @return \DataObject
	 */
	public function LastActor($forAction, $actorModel = 'Member') {
		$actorModel = is_object($actorModel) ? $actorModel->ClassName : $actorModel;

		$action = $this->Actors($forAction, $actorModel)->sort('Created desc')->first();
		return ($action && $action->exists()) ? $action->getFrom() : null;
	}
}