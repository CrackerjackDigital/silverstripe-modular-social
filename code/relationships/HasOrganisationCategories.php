<?php
namespace Modular\Relationships\Social;

use EmailNotifier;
use Member;
use Modular\Extensions\Model\SocialMember;
use Modular\Extensions\Model\SocialModelExtension;
use Modular\Forms\Social\CategoryRequestForm;
use Modular\Forms\Social\OrganisationCategoriesForm;
use Modular\Models\Social\Organisation;
use SS_HTTPRequest;

/**
 *
 */
class HasOrganisationCategories extends SocialModelExtension {
	const ActionName = 'categories';
	const ActionCode = "CRT";

	private static $url_handlers = [
		'$ID/categories'       => 'categories',
		'$ID/category-request' => 'RequestCategory',
	];
	private static $allowed_actions = [
		self::ActionName  => '->canEdit',
		'RequestCategory' => '->canEdit',
	];

	public function categories(SS_HTTPRequest $request) {

		if ($request->isPOST()) {
			$org_id = $request->postVar('ID');
			$OrgModel = Organisation::get()->byID($org_id);

			$cateID = $request->postVar('OrganisationSubTypeID');

			for ($i = 0; $i < count($cateID); $i++) {
				$OrgModel->OrganisationSubTypes()->add($cateID[ $i ]);
			}

			$this()->setSessionMessage("SocialOrganisation categories updated", "success");
		} else {
			$org_id = $request->param('ID');
			$OrgModel = Organisation::get()->byID($org_id);

		}

		return $this()->redirect("organisation/" . $OrgModel->ID . "/view-content/category");

	}

	public function HasOrganisationCategoriesForm() {
		$id = $this()->getModelID();
		return OrganisationCategoriesForm::create($this(), __FUNCTION__, $id);
	}

	public function HasCategoryRequestForm() {
		$id = $this()->getModelID();
		return CategoryRequestForm::create($this(), __FUNCTION__, $id);
	}

	public function RequestCategory(SS_HTTPRequest $request) {
		$org_id = $request->postVar('ID');
		$admin = SocialMember::getAdmins();
		$member = Member::currentUser();
		$CategoryRequest = $request->postVar('CategoryName');

		$notifier = EmailNotifier::create();
		$notifier->setEmailTemplate('Organisation_CategoryRequest');
		$notifier->setEmailSubject("New SocialOrganisation Category Request");
		$notifier->setRecipients($admin);
		$notifier->setMessage("New Category Request");
		$notifier->setEmailTemplateData(["Member" => $member, "CategoryRequest" => $CategoryRequest]);
		$notifier->send();

		$this()->setSessionMessage("New category request has been sent to Administrator.", "success");

		return $this()->redirect("organisation/" . $org_id . "/view-content/category");
	}

	/**
	 * Provide the GalleryForm to the
	 *
	 * @param SS_HTTPRequest $request
	 * @param                $mode
	 * @return mixed
	 */
	public function provideUploadFormForMode(SS_HTTPRequest $request, $mode) {
		if ($mode === static::ActionName) {
			return $this->HasOrganisationCategoriesForm();
		}
	}

}