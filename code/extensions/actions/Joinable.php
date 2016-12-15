<?php
namespace Modular\Actions;

use Controller;
use FieldList;
use FormAction;
use LiteralField;
use Member;
use Modular\Extensions\Controller\SocialAction;
use Modular\Forms\SocialForm;
use Modular\Interfaces\SocialModel;
use Modular\Interfaces\UIModalProvider;
use Modular\Models\Graph\Edge;
use RequiredFields;
use SS_HTTPRequest;
use SS_HTTPResponse;

class Joinable extends SocialAction
	implements UIModalProvider {
	const ActionCode = 'JOI';
	const ActionName = 'join';

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
		Edge::make(Member::currentUser(), $this(), self::ActionCode, $request->postVars());

		return $this()->redirectBack();
	}

	public function leave() {
		Edge::remove(Member::currentUser(), $this(), self::ActionCode);
		return Controller::curr()->redirectBack();
	}

	public function isJoined() {
		return Edge::exists_by_type(Member::currentUser(), $this(), self::ActionCode);
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
	 * @return SocialModel|null
	 */
	public function provideModel($modelClass, $id, $mode) {
		return parent::provideModelByID($modelClass, $id, $mode);
	}

	/**
	 * Return the content of a modal dialog depending on mode which should match the
	 * extensions mode.
	 *
	 * @param SS_HTTPRequest $request
	 * @return SocialForm|null
	 */
	public function provideUIModal(SS_HTTPRequest $request) {
		$mode = $request->param('Mode');

		if ($mode === $this->action()) {

		}
	}

	public function joinReasons(SS_HTTPRequest $request) {
		list($fields, $requiredFields) = $this()->getFieldsForMode(self::ActionName);

		$actions = FieldList::create(
			FormAction::create('Submit')
				->setTitle("Submit Request")
				->addExtraClass("btn btn-blue"),

			LiteralField::create('Cancel', "<a class='close'>Cancel</a>")
				->addExtraClass("btn btn-gray")
		);

		$form = new SocialForm(
			$this(),
			__FUNCTION__,
			$fields,
			$actions,
			new RequiredFields($requiredFields)
		);
		$form->setFormAction(
			$this()->getModelInstance(static::action_code())->ActionLink(static::action_code())
		);

		return $this()->renderWith(["OrganisationModel_joinModal"], compact("form"));
	}

}