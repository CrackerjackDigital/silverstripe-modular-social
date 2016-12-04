<?php
namespace Modular\Controllers;

use DataObject;
use FieldList;
use Modular\Forms\SocialForm;
use SS_HTTPRequest;

class PostReply_Controller extends SocialModel {
	private static $model_class = 'PostReply';

	private static $allowed_actions = [

	];

	public function init() {
		$this->AuthenticateUser();
		parent::init();
	}

	/**
	 * Return a form for editing the controller model tailored to $mode.
	 * @param $mode
	 * @return SocialForm
	 */
	public function PostReplyForm($mode) {
		return $this->EditForm($mode);
	}

	/**
	 * Return a form for viewing the controller model.
	 * @return Form
	 */
	public function PostReplyView() {
		return $this->ViewForm();
	}

	/**
	 * Called before model creation on a 'GET' request.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject $model
	 * @param string $mode
	 */
	public function beforeCreate(SS_HTTPRequest $request, DataObject $model, $mode) {
		return $this()->renderTemplates($mode);
	}
	/**
	 * Called after model has been created via 'POST' request.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject $model
	 * @param string $mode
	 */
	public function afterCreate(SS_HTTPRequest $request, DataObject $model, $mode) {
		return $this()->redirectBack();
	}

	/**
	 * Manipulates form fields before they are returned as part of a form.
	 *
	 * @param DataObject $model
	 * @param FieldList $fields
	 * @param string $mode
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode) {

	}

	/**
	 * Manipulates fields after all extensions have had a change to modify via updateFieldsForMode
	 * so dependancies can be handled, placeholders set etc.
	 *
	 * @param FieldList $fields
	 * @param $mode
	 */
	public function decorateFields(FieldList $fields, $mode) {

	}
}