<?php
namespace Modular\Extensions\UI;
use ArrayData;
use ArrayList;
use DataObject;
use Modular\Actions\Createable;
use Modular\Extensions\Controller\SocialController;
use Modular\Extensions\Model\SocialMember;
use Modular\Types\SocialActionType;

/**
 * Base class for menus which display a list of available and permitted actions such as Like, Follow, Edit etc
 */

abstract class SocialActionMenu extends SocialController  {
	// override in concrete class with e.g. 'action-links'
	const MenuClass = '';
	// used to filter e.g. ShowInActionLinks or ShowInActionButtons.
	const FilterField = '';

	public static function action_links(DataObject $model, $restrictTo = null) {
		$member = SocialMember::current_or_guest();

		// get the list of all possible actions between the two objects (no permission checks)
		$possibleActions = SocialActionType::get_possible_actions(
			$member,
			$model,
			$restrictTo
		)->filter([
			static::FilterField => 1,
		]);

		$actions = new ArrayList();
		/** @var SocialActionType $actionRelationshipType */
		foreach ($possibleActions as $actionRelationshipType) {
			// for each possible action check we can apply it against the model instance.
			// so e.g. for action 'EDT' we can only do if current member has action 'CRT' or 'EDT' (or admin) or
			// action 'FOL'
			$requirementTally = [];

			$createRelationshipType = SocialActionType::get_heirarchy(
				$member,
				$model,
				Createable::ActionCode
			)->first();

			if ($createRelationshipType) {
				$createRelationship = $createRelationshipType->checkRelationshipExists(
					$member->ID,
					$model->ID
				);
				if ($createRelationship) {
					if ($createdBy = $createRelationship->getFrom()) {
						// check if CRT was perfomed by the (logged in) member.
						if ($createdBy->ID === $member->ID) {
							// drop back to foreach($possibleActions...)
							continue;
						}
					}
				}
			}

			if (SocialActionType::check_permission($actionRelationshipType->Code, $model, $member)) {

				// if the model was created by the currently logged in person then don't show the action

				// now check a previous relationship of this type exists or not to figure out if
				// action or reverse action
				$previous = $previous = $actionRelationshipType->findRelationship(
					$member->ID,
					$model->ID
				);
				$singularName = $model->singular_name();

				$singularName = str_replace("SocialOrganisation", "Us", $singularName);
				if ($previous) {

					// push action as reverse action as last action of type exists
					$actions->push([
						'ID' => $model->ID,
						'CurrentAction' => $actionRelationshipType->ReverseAction,
						'Title' => "$actionRelationshipType->ReverseAction $singularName",
						'ReverseTitle' => "$actionRelationshipType->Action $singularName",
						'Link' => $model->ActionLink($actionRelationshipType->ReverseAction),
						'ReverseLink' => $model->ActionLink($actionRelationshipType->Action),
						'ActionType' => $actionRelationshipType->Action,
						'ReverseAction' => $actionRelationshipType->ReverseAction,
						'ActionLinkType' => $actionRelationshipType->ActionLinkType,
						'SingularName' => $singularName,
					]);

				} else {

					// push action as action as no previous action
					$actions->push([
						'ID' => $model->ID,
						'CurrentAction' => $actionRelationshipType->Action,
						'Title' => "$actionRelationshipType->Action $singularName",
						'ReverseTitle' => "$actionRelationshipType->ReverseAction $singularName",
						'Link' => $model->ActionLink($actionRelationshipType->Action),
						'ReverseLink' => $model->ActionLink($actionRelationshipType->ReverseAction),
						'ActionType' => $actionRelationshipType->Action,
						'ReverseAction' => $actionRelationshipType->ReverseAction,
						'ActionLinkType' => $actionRelationshipType->ActionLinkType,
						'SingularName' => $singularName,
					]);

				}
			}
		}
		return new ArrayData([
			'MenuClass' => static::MenuClass,
			'Actions' => $actions,
		]);
	}

}