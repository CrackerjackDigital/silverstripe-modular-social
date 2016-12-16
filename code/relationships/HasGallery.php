<?php
namespace Modular\Relationships\Social;

use Modular\Actions\Uploadable;
use Modular\Forms\Social\HasGalleryForm;
use SS_HTTPRequest;

/**
 * Add Gallery functionality to a Model
 */
class HasGallery extends HasImages  {
	const Action = 'uploadgallery';
	const ActionCode = Uploadable::ActionCode;

	private static $url_handlers = [
		'$ID/uploadgallery' => 'uploadGallery',
	];
	private static $allowed_actions = [
		'uploadGallery' => '->canUploadGallery',
	];

	public function canUploadGallery($member = null) {
		return $this()->getModelInstance(Uploadable::ActionName)->canDoIt(static::ActionCode);
	}
	/**
	 *
	 * Add more pictures to gallery
	 *
	 **/
	public function HasGalleryForm() {
		$id = $this()->getModelID();
		return HasGalleryForm::create($this(), __FUNCTION__, $id);
	}

	/**
	 *
	 * Handle post of the HasGalleryForm
	 *
	 * @param SS_HTTPRequest $request
	 * @return mixed
	 */
	public function uploadGallery(SS_HTTPRequest $request) {
		if ($modelID = $request->postVar('ID')) {

			$modelClass = $this()->getModelClass();

			if ($model = $modelClass::get()->byID($modelID)) {
				$removeAll = true;
				$this()->extend('attachUploadedImages', $request, $model, $removeAll);
			}
		}
		return $this()->redirect("organisation/" . $modelID . "/view-content/gallery");

	}

	/**
	 * Provide the GalleryForm to the
	 * @param SS_HTTPRequest $request
	 * @param $mode
	 * @return mixed
	 */
	public function provideUploadFormForMode(SS_HTTPRequest $request, $mode) {
		if ($mode === static::Action) {
			return $this->HasGalleryForm();
		}
	}

}