<?php
namespace Modular\Relationships\Social;

use DataObject;
use Image;
use Modular\Actions\Uploadable;
use Modular\Extensions\Model\SocialModel;
use Modular\Forms\HasImagesForm;
use SS_HTTPRequest;

/**
 * Adds a has_many from extended class to Images.
 *
 * NB: this gets added to both Model and Controller so derives from ModelExtension to capture both sets of
 * functionality.
 *
 */
class HasImages extends SocialModel  {
	const Action = 'images';
	const RelationshipName = 'Images';
	const ActionCode = Uploadable::ActionCode;

	private static $many_many = [
		self::RelationshipName => 'Image',
	];
	/*  TODO:  This should be here, check it works first though.
		private static $allowed_actions = [
			'uploadImages' => '->canUploadImages'
		];
	*/
	private static $url_handlers = [
		'$ID/images' => 'uploadImages',
	];

	public function canUploadImages() {
		return true;

		// TODO: check this works as it should be this
		// return $this()->getModelInstance(Uploadable::Action)->canDoIt(static::ActionCode);

	}

	/**
	 * Adds an UploadField to the Root.Images tab.
	 *
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab('Root.Images', UploadField::create(
			self::RelationshipName,
			'',
			$this()->OrderedImages()
		));
	}

	public function HasImagesForm() {
		return new HasImagesForm($this());
	}

	public function provideUploadFormForMode(SS_HTTPRequest $request, $mode) {
		if ($mode === static::Action) {
			return $this->HasImagesForm();
		}
	}

	/**
	 * Returns images related to the extended object.
	 *
	 * @return SS_List
	 */
	public function OrderedImages() {
		// NB we could add sorting here if we've installed Sortable e.g. GridFieldSortableRows or such.
		return $this()->getManyManyComponents(
			self::RelationshipName
		);
	}

	/**
	 * Handles ID's passed in by the ExtraImages extension and adds each ID posted to the passed in models Images
	 * action.
	 *
	 * NB: this should be somewhere else to do with ExtraImages
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $attachToModel
	 * @param bool           $removeExisting if true then existing attached images will be cleared first
	 */
	public function attachUploadedImages(SS_HTTPRequest $request, DataObject $attachToModel, $removeExisting = true) {
		$postVars = $request->postVars();
		$relationshipName = static::RelationshipName;

		if ($removeExisting) {
			$attachToModel->$relationshipName()->removeAll();
		}

		if (isset($postVars[$relationshipName])) {

			foreach ($postVars[$relationshipName] as $fileArr => $fileID) {
				if ($fileID) {
					if ($file = Image::get()->byID($fileID)) {
						$attachToModel->$relationshipName()->add($file);
					}
				}
			}
		}
	}

}