<?php
namespace Modular\Controllers;

use Application;
use FieldList;
use GroupedList;
use Member;
use Modular\Actions\Approveable;
use Modular\Forms\SocialForm;

class Member_Controller extends SocialModel {
	private static $model_class = 'Member';

	// type of approval needed to view.
	private static $approveable_mode = Approveable::ApprovalManual;

	private static $allowed_actions = [
	];

	public function init() {
		parent::init();
	}

	public function __construct($dataRecord = []) {
		parent::__construct($dataRecord);
	}

	/**
	 * Default Form for this controller, just calls through to ModelForm.
	 *
	 * @param string $mode
	 * @return SocialForm
	 */
	public function MemberForm($mode) {
		return $this->EditForm($mode);
	}

	/**
	 * Default View for this form, calls through to ModelView.
	 *
	 * @return SocialForm
	 */
	public function MemberView() {
		return $this->ViewForm();
	}

	public function ViewRedirect() {

		if (!Application::isMobile()) {
			if ($this->getModelID() == Member::currentUserID()) {
				return $this->redirect("/member/#tab_myPersonalProfileTab");
			}
		} else {
			return $this->redirect("member/" . $this->getModelID() . "/view");
		}

	}

	public function decorateFields(FieldList $fields, $mode) {
		if ($field = $fields->fieldByName('Bio')) {
			$field->setAttribute("data-ballon-show", "true")
				->setAttribute("title", "Describe yourself - who you are, what you do, etc.");
		}

		return $fields;
	}

	/**
	 *
	 * new member registration form
	 *
	 **/
	public function MemberCreate() {
		return $this->CreateForm()->renderWith(array("MemberRegisterForm"));
	}

	/**
	 * Override to return the logged in member if parent call fails, e.g. for index action.
	 *
	 * @return DataModel|Member|null
	 */
	// public function getModelInstance($mode) {
	// 	$modelClass = $this()->getModelClass();
	// 	$id = $this()->getModelID();

	// 	$possibleInstances = $this->extend('provideModel', $modelClass, $id, $mode);

	// 	// return the first valid provided model
	// 	return array_reduce(
	// 		$possibleInstances,
	// 		function ($prev, $item) {
	// 			return $prev ?: $item;
	// 		}
	// 	);
	// }

	public function EditProfilePicture() {
		return ProfilePictureForm::create($this, __FUNCTION__);
	}

	public function MembersListAlphabetsGroup() {
		$orgObj = Member::get()->filter(['FirstName:not' => ''])->sort("FirstName", 'ASC');
		return GroupedList::create($orgObj);
	}

}