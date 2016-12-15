<?php
namespace Modular\Relationships\Social;

use Modular\Edges\OrganisationContactInfo;
use Modular\Forms\HasContactInfoForm;
use Modular\Models\Social\ContactInfo;

/**
 * Add Multiple Contact information functionality to a Model
 */
class HasContactInfo extends HasMany {
	const Action = 'ContactInfo';
	const ActionCode = "CRT";

	private static $url_handlers = [
		'$ID/contactinfo' => self::Action,
	];
	private static $allowed_actions = [
		self::Action => '->canEdit',
	];

	public function ContactInfo(SS_HTTPRequest $request) {
		if ($modelID = $request->postVar('OrganisationID')) {

			$modelClass = $this()->getModelClass();

			if ($model = $modelClass::get()->byID($modelID)) {
				if ($contact_id = $request->postVar('ID')) {
					$contactModel = ContactInfo::get()->byID($contact_id);
				} else {
					$contactModel = ContactInfo::create();
				}
				$contactModel->Address = $request->postVar('Address');
				$contactModel->Location = $request->postVar('Location');
				$contactModel->PhoneNumber = $request->postVar('PhoneNumber');
				$contactModel->Email = $request->postVar('Email');
				$contactInfo = $contactModel->write();

				if (!$contact_id) {
					/** @var OrganisationContactInfo $action */
					$action = OrganisationContactInfo::create();
					$action->setFrom($model);
					$action->setTo($contactModel);
					$action->write();
				}
			}

			$this()->setSessionMessage("Location info saved", "success");
		} else {
			return $this()->redirectBack();
		}

		if (Application::factory()->is_mobile()) {
			return $this()->redirect("organisation/" . $modelID . "/view-content/locations");
		}
		return $this()->redirectBack();
	}

	public function HasContactInfoForm() {
		$id = $this()->getModelID();
		return HasContactInfoForm::create($this(), __FUNCTION__, $id);
	}

	public function editContactInfoForm() {
		$id = $this()->getModelID();
		$contactid = $this()->request->param('SubID');
		//check action
		$action = OrganisationContactInfo::graph($id, $contactid)->first();
		if (!$action) {
			return $this()->httpError(404);
		}

		$form = HasContactInfoForm::create($this(), __FUNCTION__, $id);
		$contactModel = ContactInfo::get()->byID($contactid);
		return $form->loadDataFrom($contactModel);
	}

	/**
	 * Provide the GalleryForm to the
	 * @param SS_HTTPRequest $request
	 * @param $mode
	 * @return mixed
	 */
	public function provideUploadFormForMode(SS_HTTPRequest $request, $mode) {
		if ($mode === static::Action) {
			return $this->HasContactInfoForm();
		}
	}

}