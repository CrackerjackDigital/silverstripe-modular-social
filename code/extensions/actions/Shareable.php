<?php
namespace Modular\Actions;

/**
 * Extension adds actions share and unshare which create relationship between viewed object and logged in member.
 *
 * Provides ShareableLink method to link to this objects share/unshare action.
 */
use Controller;
use DataObject;
use Member;
use Modular\Edges\SocialRelationship;
use \Modular\Extensions\Controller\SocialAction;
use Modular\Interfaces\SocialModel;

class Shareable extends SocialAction {
	const ActionCode = 'SHA';
	const ActionName = 'share';

	private static $url_handlers = [
		'$ID/share' => 'share',
		'$ID/unshare' => 'unshare',
	];

	private static $allowed_actions = [
		'share' => '->canShare("action")',
		'unshare' => '->canShare("action")',
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
			return self::isShared();
		}
	}
	/**
	 * Permissive for now. TODO: sort out permissions CAN_SHARE_xxx.
	 * @return bool
	 */
	public function canShare($source = null) {
		return parent::canDoIt(self::ActionCode, $source);
	}

	/**
	 * Member shares this->owner object, add a relationship from Member with type self::$actionTypeCode
	 */
	public function share() {
		parent::makeRelationship(self::ActionCode);
		return Controller::curr()->redirectBack();
	}

	/**
	 * Member unshares this->owner object, remove all self::$actionTypeCode relationships between them
	 */
	public function unshare() {
		parent::remove(Member::currentUser(), $this(), self::ActionCode);
		return Controller::curr()->redirectBack();
	}

	public function isShared() {
		return SocialRelationship::latest(Member::currentUser(), $this(), static::ActionCode);
	}
	/**
	 * Return a link appropriate for this object to be shareed by logged in Member if can be shareed.
	 * @param $action
	 * @return String
	 */
	public function ShareableLink($action) {
		if ($this->canShare()) {
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
	 * @return SocialModel|DataObject|null
	 */
	public function provideModel($modelClass, $id, $action) {
		if ($action === $this->action()) {
			if ($id) {
				return DataObject::get($modelClass)->byID($id);
			}
		}
	}

}