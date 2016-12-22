<?php
namespace Modular\Relationships\Social;

use HasCoverImageForm;
use Modular\Actions\Uploadable;
use Modular\Relationships\Social\HasImage;
use SS_HTTPRequest;

/**
 * Add CoverImage functionality to a Model
 */
class HasCoverImage extends HasImage {
	const RelationshipName = 'CoverImage';
	const RelatedClassName = 'Image';

	const ActionCode = Uploadable::ActionCode;

	const ActionName = 'uploadcoverimage';

	private static $url_handlers = [
		'$ID/uploadcoverimage' => 'uploadCoverImage',
	];
	private static $allowed_actions = [
		'uploadCoverImage' => '->canUploadCoverImage',
	];

	/**
	 * TODO: proper check for 'UPL' permission
	 *
	 * @param null $member
	 * @return bool
	 */
	public function canUploadCoverImage($member = null) {
		return true;
//        return parent::canDoIt(Uploadable::ActionCode);
	}

	protected function UploadForm() {
		return $this->HasCoverImageForm();
	}

	/**
	 *
	 * If the current model as viewed by the controller has an ID then
	 * returns a configured HasCoverImageForm
	 *
	 * @return HasCoverImageForm
	 **/
	public function HasCoverImageForm() {
		// ID can be null e.g. if uploading via a FileAttachmentField
		$id = $this()->getModelID();
		return HasCoverImageForm::create($this(), __FUNCTION__, $id);
	}

	/**
	 * Provide the CoverImageForm to the
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

	public function uploadCoverImage(SS_HTTPRequest $request) {
		$modelClassName = $this()->getModelClass();

		$model = $modelClassName::get()->byID($request->postVar('ID'));
		$model->{self::FieldName} = $request->postVar(self::FieldName);
		$model->write();

		return $this()->redirectBack();
	}

}