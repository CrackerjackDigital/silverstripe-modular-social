<?php
namespace Modular\Controllers\Social;

use Application;
use ArrayList;
use DataObject;
use FieldList;
use Image;
use Member;
use Modular\Controllers\SocialModelController;
use Modular\Edges\MemberOrganisation;
use Modular\Edges\OrganisationContactInfo;
use Modular\Extensions\Model\SocialMember;
use Modular\Forms\SocialForm;
use Modular\Models\Social\ContactInfo;
use Modular\Models\Social\Organisation;

class Organisation_Controller extends SocialModelController {
	private static $model_class = 'SocialOrganisation';

	private static $file_upload_path = [
		'Logo' => 'organisations/profile/images',
	];

	public function init() {
		parent::init();
	}

	private static $url_handlers = [
		'$ID/remove-location/$LocationID' => 'RemoveLocation',
		'$ID/cover-image/$ImageID'        => 'SetCoverImage',
		'$ID/featured-image/$ImageID'     => 'SetFeaturedImage',
		'$ID/cover-focus'                 => 'SetCoverImageFocusArea',
		'$ID/remove-type/$TypeID'         => 'RemoveOrganisationType',
	];

	private static $allowed_actions = [
		'UploadGalleryForm',
		'RemoveLocation'         => "->canEdit",
		'SetCoverImage'          => "->canEdit",
		'SetCoverImageFocusArea' => "->canEdit",
		'SetFeaturedImage'       => "->canEdit",
		'RemoveOrganisationType' => "->canEdit",
	];

	/**
	 * Call through to ModelForm as this is the 'default' form for this Controller.
	 *
	 * @return SocialForm
	 */
	public function OrganisationForm($mode) {
		return $this->EditForm($mode);
	}

	/**
	 * Default View for this form, calls through to ModelView.
	 *
	 * @return SocialForm
	 */
	public function OrganisationView() {
		return $this->ViewForm();
	}

	public function ViewRedirect() {
		$currenUserOrganisation = SocialMember::current_or_guest()->MemberOrganisation();
		if ($currenUserOrganisation) {
			$memberOrgID = $currenUserOrganisation->OrgModel->ID;
			if (!Application::is_mobile()) {
				if ($this->getModelID() == $memberOrgID) {
					return $this->redirect("/member/#tab_organisationTab");
				}
			} else {
				return $this->redirect("organisation/" . $this->getModelID() . "/view");
			}
		}

	}

	/**
	 *
	 * SocialOrganisation creationg form
	 *
	 **/
	public function OrganisationCreate() {
		return $this->CreateForm()->renderWith(array("OrganisationRegisterForm"));
	}

	/**
	 * Make sure we have a 'CRT' action added between the current member and the
	 * created organisation.
	 * Company details have to be fetched form MBIE to save in their various fields
	 *
	 * @param $request
	 * @param $model
	 * @param $mode
	 * @return \SS_HTTPResponse
	 */
	public function afterCreate($request, $model, $mode) {
		if ($model) {
			MemberOrganisation::make(
				Member::currentUser(), $model, ['CRT', 'EDT']
			);
		}

		return $this->redirect($this->getModelInstance($mode)->ActionLink('view'));
	}

	/**
	 * If we have a field called 'OrganisationSubType' then set it's values from the model as its
	 * probably an OrganisationSubType field.
	 *
	 * @param DataObject $model
	 * @param FieldList  $fields
	 * @param            $mode
	 * @param array      $requiredFields
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, &$requiredFields = []) {
		if ($uploadField = $fields->fieldByName('Logo')) {
			$uploadField->setAllowedMaxFileNumber(1);
		}
	}

	public function decorateFields(FieldList $fields, $mode) {
		if ($field = $fields->fieldByName('Title')) {
			$field->setAttribute('placeholder', _t('SocialOrganisation.TitlePlaceholder', 'SocialOrganisation/Company name'));
		}
		if ($field = $fields->fieldByName('Description')) {
			$field->setAttribute("data-ballon-show", "true")
				->setAttribute("title", "Describe your company - who you are, what you do, how you can help etc.");
		}
		return $fields;
	}

	/**
	 * Returns the 'SocialOrganisation listings' and 'Advanced search' tabs.
	 *
	 * @return ArrayList
	 */
	public function NavTabBar() {
		return new ArrayList([
			[
				'ID'    => 'allOrganisations',
				'Title' => _t('Groups.AllOrganisationsTabLabel', 'SocialOrganisation Listings'),
			],
			[
				'ID'    => 'AdvancedSearch',
				'Title' => _t('Groups.FollowingAdvancedSearchTabLabel', 'Advanced Search'),
			],
		]);
	}

	/**
	 * Returns true if we can edit the current model according to 'active' Controller extension.
	 *
	 * @param null $member
	 * @return mixed
	 */
	public function canEdit($member = null) {
		return array_reduce(
			$this->extend('canEdit', $member),
			function ($prev, $item) {
				return $prev ?: $item;
			}
		);
	}

	/**
	 *
	 * SocialOrganisation location mobile view
	 *
	 */
	public function LocationInfo($location = null) {
		$request = $this()->getRequest();
		if ($location == null) {

			$location = (int) $request->Param("SubID");
		}
		$contact = ContactInfo::get()->byID($location);
		if ($contact) {
			return $contact;
		} else {
			return false;
		}

	}

	public function RemoveLocation() {
		$contactid = (int) $this->request->Param('LocationID');
		$id = $this->getModelID();
		if ($contactid == 0) {
			return $this->redirectBack();
		}

		$action = OrganisationContactInfo::get()->filter(['FromModelID' => $id, 'ToContactInfoID' => $contactid])->first();
		if (!$action) {
			return $this()->httpError(404);
		}

		$contact = ContactInfo::get()->byID($contactid);
		$contact->delete();
		$action->delete();
		$this->setSessionMessage("SocialOrganisation location removed");
		return $this->redirectBack();
	}

	/**
	 *
	 * set cover image from image gallery
	 *
	 */
	public function SetCoverImage() {
		$image_id = (int) $this->request->Param('ImageID');
		$id = $this->getModelID();
		if ($image_id == 0) {
			return $this->redirectBack();
		}

		$id = $this->getModelID();
		$model = Organisation::get()->byID($id);
		$model->CoverImageID = $image_id;
		$model->write();

		$this->setSessionMessage("SocialOrganisation cover image changed. You can now set the focus area.");
		return $this->redirect('/organisation/' . $id . '/view-content/cover');
	}

	/**
	 *
	 * Set cover image focus area
	 *
	 */
	public function SetCoverImageFocusArea() {
		if (!$this->request->isPost()) {
			return $this->redirectBack();
		}
		$focusPoints = [];
		parse_str($this->request->postVar('focusVal'), $focusPoints);
		$x1 = $focusPoints['x1'];
		$x2 = $focusPoints['x2'];
		$y1 = $focusPoints['y1'];
		$y2 = $focusPoints['y2'];
		$cropHeight = $focusPoints['height'];

		$yCordinate = $y1;
		$xCordinate = $x1;
		$id = $this->getModelID();
		$model = Organisation::get()->byID($id);

		if ($model->CoverImageID) {
			$imgObj = Image::get()->byID($model->CoverImageID);
			if ($imgObj) {
				$coverImageWidth = $imgObj->setWidth(808);
				$imgHeight = $coverImageWidth->getHeight();
				$imgWidth = $coverImageWidth->getWidth();
				$fX = 0; //($x1 / $imgWidth) * 100;
				$fY = -$y2 + ($y2 / 2); //($y2 / $imgHeight) * 100;
				//update image focus area
				$imgObj->FocusX = round($fX);
				$imgObj->FocusY = round($fY);
				$imgObj->write();
			}
		}

		$this->setSessionMessage("Cover image focus area updated.");
		return $this->redirect('/organisation/' . $id . '/view');
	}

	/**
	 *
	 * set featured image
	 *
	 */
	public function SetFeaturedImage() {
		$image_id = (int) $this->request->Param('ImageID');
		$id = $this->getModelID();
		if ($image_id == 0) {
			return $this->redirectBack();
		}

		$id = $this->getModelID();
		$model = Organisation::get()->byID($id);
		$model->FeaturedImageID = $image_id;
		$model->write();

		$this->setSessionMessage("Gallery featured image saved");
		return $this->redirect('/organisation/' . $id . '/view');
	}

	/**
	 *
	 * remove organisation type
	 *
	 */
	public function RemoveOrganisationType() {
		$type_id = (int) $this->request->Param('TypeID');
		$id = $this->getModelID();
		if ($type_id == 0) {
			return $this->redirectBack();
		}

		$orgSubType = OrganisationSubType::get()->byID($type_id);

		if ($orgSubType) {
			$id = $this->getModelID();
			$model = Organisation::get()->byID($id);
			$model->OrganisationSubTypes()->remove($orgSubType);
			$model->write();
		}

		$this->setSessionMessage("SocialOrganisation type removed.");
		return $this->redirectBack();
	}

}