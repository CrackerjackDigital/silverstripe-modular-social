<?php
namespace Modular\Forms\Social;

use FieldList;
use FileAttachmentField;
use FormAction;
use HiddenField;
use Modular\Actions\Editable;
use Modular\Forms\SocialForm;
use Modular\Models\Social\Organisation;
use Modular\Relationships\Social\HasGallery;
use RequiredFields;

/**
 * Provide a form to upload to the gallery added by the 'HasGallery' extension.
 */
class HasGalleryForm extends SocialForm {
	const Action = HasGallery::Action;

	public function __construct($controller, $name, $id) {
		$fields = FieldList::create(
			FileAttachmentField::create('Images', 'Add some more images')
				->imagesOnly()
				->setMultiple(true)
				->setView('grid')
				->setPermissions(array(
					'delete' => true,
					'detach' => false,
				)),
			HiddenField::create("ID")->setValue($id)
		);

		$actions = FieldList::create(
			FormAction::create('uploadGallery')
				->setTitle("Apply Changes")
				->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields('Images');
		parent::__construct($controller, $name, $fields, $actions, $validator);

		//load existing gallery for editing
		if ($id) {
			$org = Organisation::get()->byID($id);
			if ($images = $org->Images()) {
				$payload = [];
				foreach ($images as $img) {
					$payload[] = $img->ID;
				}
				$this->Fields()->fieldByName("Images")->setValue($payload);
			}
		}

		if ($model = $controller->getModelInstance(Editable::ActionName)) {
			$this->setFormAction($model->ActionLink(self::Action));
		}
	}

}