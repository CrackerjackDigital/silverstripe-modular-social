<?php

/**
 * Extension adds actions like and unlike which create relationship between viewed object and logged in member.
 *
 * Provides LikeableLink method to link to this objects like/unlike action.
 */
namespace Modular\Actions;

use Controller;
use DataObject;
use Member;
use Modular\Edges\SocialRelationship;
use Modular\Extensions\Controller\SocialAction;
use Modular\Interfaces\SocialModel;

class Likeable extends SocialAction {
	const ActionCode = 'LIK';
	const ActionName = 'like';

	private static $allowed_actions = [
		'like'   => '->canLike("action")',
		'unlike' => '->canLike("action")',
	];

	/**
	 * Return boolean to indicate if the action for this extension has been taken, or null if not the action
	 * that this extension deals with.
	 *
	 * @param $action
	 * @return bool|mixed
	 */
	public function actionTaken($action) {
		if ($action === self::ActionCode) {
			return self::isLiked();
		}
	}

	/**
	 * Checks the user can like the extended model.
	 *
	 * TODO: should also filter out e.g. Posts which the user has made themselves.
	 *
	 * @return bool
	 */
	public function canLike($source = null) {
		return parent::canDoIt(self::ActionCode, $source);
	}

	/**
	 * Member likes this->owner object, add a relationship from Member with type self::$actionTypeCode
	 */
	public function like() {
		parent::make(Member::currentUser(), $this(), self::ActionCode);
		return Controller::curr()->redirectBack();
	}

	/**
	 * Member unlikes this->owner object, remove all self::$actionTypeCode relationships between them
	 */
	public function unlike() {
		parent::remove(Member::currentUser(), $this(), self::ActionCode);
		return Controller::curr()->redirectBack();
	}

	public function isLiked() {
		return SocialRelationship::latest(Member::currentUser(), $this(), self::ActionCode);
	}

	/**
	 * Return a link appropriate for this object to be likeed by logged in Member if can be likeed.
	 *
	 * @param $action
	 * @return String
	 */
	public function LikeableLink($action) {
		if ($this->canLike()) {
			return Controller::curr()->join_links(
				$this()->Link(),
				$action
			);
		}
	}

	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $action
	 *
	 * @return SocialModel|null
	 */
	public function provideModel($modelClass, $id, $action) {
		if ($action === $this->action()) {
			if ($id) {
				return DataObject::get($modelClass)->byID($id);
			}
		}
	}
}