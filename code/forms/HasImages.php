<?php
namespace Modular\Forms\Social;
use ArrayList;
use FieldList;
use FileAttachmentField;
use Modular\Forms\SocialForm;
use Modular\Relationships\HasImages;

/**
 * Simple form which allowed upload of images via the HasImagesExtension.
 */
class HasImagesForm extends SocialForm {
	public function __construct($controller, $name = 'HasImagesForm', $fields = null, $actions = null) {
		$actionName = HasImages::RelationshipName;

		if ($model = $controller->getModelInstance()) {
			$images = $model->$actionName();
		} else {
			$images = new ArrayList();
		}
		$fields = new FieldList(
			new FileAttachmentField($actionName, 'Images', $images)
		);
		$actions = new FieldList();

		return parent::__construct($controller, $name, $fields, $actions);
	}
}