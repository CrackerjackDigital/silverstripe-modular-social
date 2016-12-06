<?php
namespace Modular\Extensions\Controller;

use DataObject;
use Modular\Actions\Editable;
use Modular\Extensions\Model\SocialMember;
use Modular\Interfaces\SocialModel;
use Modular\Interfaces\SocialModelProvider;
use ValidationException;

/**
 * Base extension for actions which can be performed by the logged in Member to establish
 * a action to the extended object, such as follow or like. Adds basic functionality common
 */
abstract class SocialAction extends SocialController
	implements SocialModelProvider {
	const ActionCode        = '';
	const Action            = '';
	const MemberClassName   = 'Member';
	const ActionClassSuffix = 'SocialAction';

	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $mode
	 *
	 * @return SocialModel|DataObject|null
	 */
	public function provideModel($modelClass, $id, $action, $createIfNotFound = false) {
		if ($action === static::Action) {
			if ($id) {
				return SocialModel::get($modelClass)->byID($id);
			}
		}
	}

	/**
	 * Build the name of the SocialAction between 'Member' and the owner model, e.g 'MemberOrganisationAction'
	 *
	 * @return string
	 */
	protected function getActionClassName() {
		$modelClass = $this()->getModelClass();
		if (substr($modelClass, -5, 5) === 'Model') {
			$modelClass = substr($modelClass, 0, -5);
		}
		return self::MemberClassName . $modelClass . self::ActionClassSuffix;
	}

	/**
	 * Create a action of the provided type between the logged in member and the controller model.
	 *
	 * @param string $actionCode
	 * @param array  $extraData to add to action record
	 * @return SocialAction
	 * @throws ValidationException
	 */
	protected function makeAction($actionCode, array $extraData = []) {
		/** @var \Modular\Edges\SocialRelationship|string $className */
		$className = $this->getActionClassName();

		// TODO set mode on all extensions, e.g. 'follow'
		/** @var SocialModel $action */
		$action = $className::make(
			SocialMember::current_or_guest(),
			$this()->getModelInstance(Editable::Action),
			$actionCode
		);
		if ($action && $extraData) {
			$action->update($extraData);
			$action->write();
		}
		return $action;
	}

	/**
	 * Remove all actions of provided type from current logged in member to the extended controllers model.
	 *
	 * @param $actionCode
	 */
	protected function breakAction($actionCode) {
		/** @var SocialAction $className */
		$className = $this->getActionClassName();

		// TODO set mode on all extensions, e.g. 'follow'

		$className::remove(
			SocialMember::current_or_guest(),
			$this()->getModelInstance(Editable::Action),
			$actionCode
		);
		// TODO: emailer?
	}

	/**
	 * Return boolean to indicate a action of provided type exists between the current logged in member
	 * and the controller model. Reversing a action (e.g. calling unfollow) will have deleted this action.
	 *
	 * @param $actionCode
	 * @return boolean
	 */
	protected function checkAction($actionCode) {
		/** @var SocialAction $className */
		$className = $this->getActionClassName();

		return $className::has_related(
			SocialMember::current_or_guest(),
			$this()->getModelInstance(null),
			$actionCode
		);
	}

}