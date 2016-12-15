<?php
namespace Modular\Forms\Social;

use DataObject;
use FormField;

class ImageEditField extends FormField {

	public $images = [];

	public function Value() {
		return $this->Field();
	}
	public function setValue($value) {
		$this->images = $value ?: [];
		return $this;
	}
	public function Field($properties = []) {
		return $this->renderWith(
			'ImageEditField',
			[
				'Images' => $this->images,
			]
		);
	}
	public function updateFieldFromModel(DataObject $model) {
		if ($model->hasMethod('Images')) {
			$this->images = $model->Images();
		}
	}
}