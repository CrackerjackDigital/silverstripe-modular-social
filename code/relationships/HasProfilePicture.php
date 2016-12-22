<?php
namespace Modular\Relationships\Social;

use Modular\Actions\Uploadable;
use Modular\Forms\Social\HasProfilePictureForm;
use SS_HTTPRequest;

/**
 * Add ProfilePicture functionality to a Model
 */
class HasProfilePicture extends HasImage {
	const ActionName       = 'uploadprofilepicture';
	const ActionCode       = Uploadable::ActionCode;
	const RelationshipName = 'ProfileImage';
	const FieldName        = 'ProfileImageID';

	private static $url_handlers = [
		'$ID/uploadprofilepicture' => 'uploadProfilePicture',
	];
	private static $allowed_actions = [
		'uploadProfilePicture' => '->canUploadProfilePicture',
	];

	private static $has_one = [
		self::RelationshipName => 'Image',
	];

	/**
	 * TODO: Implement proper permission check (at moment calling canDoIt results in controller being passed as $toModel)
	 *
	 * @param null $member
	 * @return bool
	 */
	public function canUploadProfilePicture($member = null) {
		return true;
	}

	protected function UploadForm() {
		return $this->HasProfilePictureForm();
	}

	/**
	 *
	 * If the current model as viewed by the controller has an ID then
	 * returns a configured HasProfilePictureForm
	 *
	 * @return HasProfilePictureForm
	 **/
	public function HasProfilePictureForm() {
		$id = $this()->getModelID();
		return HasProfilePictureForm::create($this(), __FUNCTION__, $id);
	}

	/**
	 * Provide the ProfilePictureForm to the
	 *
	 * @param SS_HTTPRequest $request
	 * @param                $mode
	 * @return mixed
	 */
	public function provideUploadFormForMode(SS_HTTPRequest $request, $mode) {
		if ($mode === static::ActionName) {
			return $this->UploadForm();
		}
	}

	public function uploadProfilePicture(SS_HTTPRequest $request) {
		$modelClassName = $this()->getModelClass();

		$model = $modelClassName::get()->byID($request->postVar('ID'));
		$model->{self::FieldName} = $request->postVar(self::FieldName);
		$model->write();

		return $this()->redirectBack();
	}

}