<?php
class FileListField extends FormField {

	public $files = [];

	public function Value() {
		return $this->Field();
	}
	public function setValue($value) {
		$this->files = $value ?: [];
		return $this;
	}
	public function Field($properties = []) {
		return $this->renderWith(
			'FileListField',
			[
				'Files' => $this->files,
			]
		);
	}
	public function updateFieldFromModel(DataObject $model) {
		if ($model->hasMethod('$files')) {
			$this->files = $model->Files();
		}
	}
}