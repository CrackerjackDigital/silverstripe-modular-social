<?php

/**
 * Handles decoding posted files from a request into the model and handles upload field requests such
 * as .../field/Images/filexists and .../field/Images/upload
 *
 * Class UploadableExtension
 */
namespace Modular\Actions;

use DataObject;
use \Modular\Extensions\Controller\SocialAction;
use Modular\Interfaces\ModelWriteHandlers;
use Modular\Interfaces\SocialModelProvider;
use SS_HTTPRequest;
use SS_List;

class Uploadable extends SocialAction
 implements ModelWriteHandlers , SocialModelProvider {
	const ActionCode = 'UPL';
	const Action = 'upload';

	private static $url_handlers = [
		'$ID/$Mode/field/$FieldName//$ActionType!' => 'field',
		'$Mode/field/$FieldName//$ActionType!' => 'field',
	];
	private static $allowed_actions = [
		'field' => '->canUpload("action")',
	];

	/**
	 * Check we have 'UPL' permission on the extended model.
	 *
	 * @return bool|int
	 */
	public function canUpload($source = null) {
		//return parent::canDoIt(self::ActionCode, $source);
		return true;
	}

	/**
	 * Get the form which will be handling this action (post for Postable etc) and return the
	 * requested field.
	 *
	 * @param SS_HTTPRequest $request
	 * @return mixed
	 */
	public function field(SS_HTTPRequest $request) {

		// extend wants variables to pass as reference
		$mode = $request->param('Mode');
		/** @var SocialModelForm $form */
		$form = array_reduce(
			$this()->extend('provideUploadFormForMode', $request, $mode),
			function ($prev, $item) {
				return $prev ?: $item;
			}
		);
		if ($form) {
			// return the requested field from the form to handle fileexists, upload actions.
			return $form->Fields()->fieldByName($request->param('FieldName'));
		}
	}

	public function beforeModelWrite(SS_HTTPRequest $request, DataObject $model, $mode, &$fieldsHandled = []) {
		// do nothing
	}
	/**
	 * Reads the request to process IDs of files previously uploaded via the UploadField and associate them with
	 * the model.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject $model
	 * @param $mode
	 * @param array $fieldsHandled
	 */
	public function afterModelWrite(SS_HTTPRequest $request, DataObject $model, $mode) {
		/** @var FieldList $fields */
		list($fields) = $this()->getFieldsForMode($mode);
		$files = $fields->filterByCallback(
			function ($field) {
				return array_key_exists(
					$field->getName(),
					$_FILES
				);
			}
		);
		// TODO handle non-ajax file uploads!
		foreach ($files as $fileField) {
			$name = $fileField->getName();

			// we need to check if the file has already been uploaded e.g. by Ajax in which case an ID will be provided
			// and we can skip manual upload of the file
			if ($postedFiles = $request->postVar($name)) {
				if (isset($postedFiles['Files'])) {
					// might be empty?
					foreach ($postedFiles['Files'] as $fileID) {
						if ($model->$name() instanceof SS_List) {
							$model->$name()->add($fileID);
						} else {
							$model->{"{$name}ID"} = $fileID;
						}
					}
				}
			}
			$fieldsHandled[$name] = $name;
		}
		$model->write();
		$fieldsHandled['MAX_FILE_SIZE'] = 'MAX_FILE_SIZE';
	}

	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $mode
	 *
	 * @return \DataObject
	 */
	public function provideModel($modelClass, $id, $mode) {
		if ($mode === $this->action()) {
			if ($id) {
				return DataObject::get($modelClass)->byID($id);
			}
		}
	}
}