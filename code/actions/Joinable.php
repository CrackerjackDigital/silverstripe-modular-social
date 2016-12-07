<?php

/**
 * This extension implements the 'Membership' interface, internally relationship code is 'MEM' not JON or some such.
 */
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;
use Modular\Interfaces\UIModalProvider;

class Joinable extends SocialAction
	implements UIModalProvider {
	const ActionCode = 'JOI';
	const Action     = 'join';

	private static $url_handlers = [
		'$ID/join/modal' => 'joinReasons',
		'$ID/join'       => 'join',
		'$ID/leave'      => 'leave',
	];

	private static $allowed_actions = [
		'join'        => '->canJoin("action")',
		'joinReasons' => '->canJoin("action")',
		'leave'       => '->canJoin("action")',
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
			return self::isJoined();
		}
	}

	public function canJoin($source = null) {
		return parent::canDoIt(self::ActionCode, $source);
	}

	/**
	 * Process the join request sent by the modal dialog.
	 *
	 * @param SS_HTTPRequest $request
	 * @return bool|SS_HTTPResponse
	 */
	public function join(SS_HTTPRequest $request) {
		if (!$currentMemberId = Member::currentUserID()) {
			return $this()->httpError("You need to be logged in to join an organisation");
		}
		parent::makeRelationship(self::ActionCode, $request->postVars());

		return $this()->redirectBack();
	}

	public function leave() {
		parent::breakRelationship(self::ActionCode);
		return Controller::curr()->redirectBack();
	}

	public function isJoined() {
		return parent::checkRelationship(self::ActionCode);
	}

	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $mode
	 *
	 * @return SocialModelInterface|null
	 */
	public function provideModel($modelClass, $id, $mode) {
		return parent::provideModelByID($modelClass, $id, $mode);
	}

	/**
	 * Return the content of a modal dialog depending on mode which should match the
	 * extensions mode.
	 *
	 * @param SS_HTTPRequest $request
	 * @return SocialModelForm|null
	 */
	public function provideUIModal(SS_HTTPRequest $request) {
		$mode = $request->param('Mode');

		if ($mode === static::Action) {

		}
	}

	public function joinReasons(SS_HTTPRequest $request) {
		list($fields, $requiredFields) = $this()->getFieldsForMode(self::Action);

		$actions = FieldList::create(
			FormAction::create('Submit')
				->setTitle("Submit Request")
				->addExtraClass("btn btn-blue"),

			LiteralField::create('Cancel', "<a class='close'>Cancel</a>")
				->addExtraClass("btn btn-gray")
		);

		$form = new SocialModelForm(
			$this(),
			__FUNCTION__,
			$fields,
			$actions,
			new RequiredFields($requiredFields)
		);
		$form->setFormAction(
			$this()->getModelInstance(static::Action)->ActionLink(static::Action)
		);

		return $this()->renderWith(["OrganisationModel_joinModal"], compact("form"));
	}

}