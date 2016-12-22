<?php
namespace Modular\Actions;

use DataObject;
use FieldList;
use File;
use FormAction;
use HiddenField;
use Image;
use Member;
use Modular\Extensions\Controller\SocialAction;
use Modular\Exceptions\Social as Exception;
use Modular\Forms\SocialForm;
use Session;
use SS_HTTPRequest;
use SS_HTTPResponse;
use ValidationException;

class Editable extends SocialAction {
	const ActionCode = 'EDT';
	const ActionName = 'edit';

	private static $url_handlers = [
		'$ID/edit' => self::ActionName,
	];

	private static $allowed_actions = [
		self::ActionName => '->canEdit("action")',
	];

	private static $action_templates = [
		self::ActionName => self::ActionName,
	];

	private static $action_modes = [
		self::ActionName => self::ActionName,
	];

	/**
	 * @param string|null $source set to 'action' by controller check
	 * @return bool
	 */
	public function canEdit($source = null) {
		return parent::canDoIt(self::ActionCode, $source);
	}

	/**
	 * Return a Form derived from SocialForm with namespace Modular\Actions;
	 *
	 * class name based on the model namespace Modular\Actions;
	 *
	 * class _Form. If not existing class
	 * then returns a SocialForm instance.
	 *
	 * In templates this can be used as $ModelForm or derived classes should have a Member <ModelClass>Form which can
	 * call through to this.
	 *
	 * @return SocialForm - or derived [modelClass]Form
	 */
	public function EditForm() {
		return $this()->formForModel(self::ActionName);
	}

	/**
	 * Provide the model for this action, in this case an instance of $modelClass found by $id, or if
	 * no id return the current member.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $action
	 * @return DataObject
	 */
	public function provideModel($modelClass, $id, $action) {
		if ($action === $this->action()) {

			$model = null;
			if ($id) {
				$model = DataObject::get_by_id(
					$modelClass,
					$id
				);
			}
			if (!$model) {
				$model = Member::currentUser();
			}
			return $model;
		}
	}

	/**
	 * Handles edit action, may be GET or POST.
	 * - If POST then returns then calls controller process and then controller.extend.afterEdit
	 * - If GET (or other) then returns controller.extend.beforeEdit
	 *
	 * @param SS_HTTPRequest $request
	 * @return mixed
	 */
	public function edit(SS_HTTPRequest $request) {

		$model = $this()->getModelInstance(self::ActionName);

		// need to this as extend takes a reference
		$action = self::ActionName;

		if ($request->httpMethod() === 'POST') {
			$responses = $this()->extend('afterEdit', $request, $model, $action);
		} else {
			$responses = $this()->extend('beforeEdit', $request, $model, $action);
		}
		// return the first non-falsish response, I don't think we can order them so may as well be first?
		return array_reduce(
			$responses,
			function ($prev, $item) {
				return $prev ?: $item;
			}
		);
	}

	/**
	 * Called on GET to show the model form via renderTemplates.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $model
	 * @param string         $action
	 * @return mixed
	 */
	public function beforeEdit(SS_HTTPRequest $request, DataObject $model, $action) {
		return $this()->renderTemplates($action);
	}

	/**
	 * Called on POST to update the model and write to database.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $model
	 * @param string         $action
	 * @return SS_HTTPResponse
	 */
	public function afterEdit(SS_HTTPRequest $request, DataObject $model, $action) {
		$formName = 'SocialModelForm_' . $this()->getFormName();

		try {

			$fieldsHandled = [];
			// handle any data munging e.g. by ConfirmedPasswordField needed before we update/write the model.
			// the 'fieldsHandled' array will be added to by extensions to identify which fields are
			// no longer required to be handled from the incoming post data as their values have
			// been used in an extension.
			$this()->extend('beforeModelWrite', $request, $model, $action, $fieldsHandled);
			$model->extend('beforeModelWrite', $request, $model, $action, $fieldsHandled);

			$posted = $request->postVars();

			$updateWith = array_diff_key(
				$posted,
				$fieldsHandled
			);

			// TODO filter out fields not in original form.
			$model->update($updateWith);

			$model->extend('validate');

			$model->write();

			//Check if images have been selected for removal
			if ($request->postVar('images_removed')) {
				$splitIds = array_filter(explode(",", $request->postVar('images_removed')));
				$modelImages = $model->Images()->filter(["ImageID" => $splitIds]);
				if ($modelImages) {
					foreach ($modelImages as $image) {
						$image->delete();
					}
				}
			}

			//save images
			// print_r($request->postVar('AttachImages'));
			// exit;

			if ($request->postVar('AttachImages')) {
				foreach ($request->postVar('AttachImages') as $fileArr => $fileID) {
					if ($fileID) {
						if ($file = Image::get()->byID($fileID)) {
							$model->Images()->add($file);
						}
					}
				}
			}

			//reattach new files
			if ($request->postVar('Files')) {
				if ($model->Files()) {
					$model->Files()->removeAll();
				}

				foreach ($request->postVar('Files') as $fileArr => $fileID) {
					if ($fileID) {
						if ($file = File::get()->byID($fileID)) {
							$model->Files()->add($file);
						}
					}
				}
			}

			// handle any data manipulations to perform after model is succesfully written,
			// e.g. update relationships.
			$this()->extend('afterModelWrite', $request, $model, $action);
			$model->extend('afterModelWrite', $request, $model, $action);

		} catch (ValidationException $e) {

			Session::setFormMessage("SocialModelForm_$formName", $e->getMessage(), 'error');
			return $this()->redirectBack();

		} catch (Exception $e) {
			return $this()->httpError(500);
		}

		if ($request->isAjax()) {
			return new SS_HTTPResponse(null, 200);
		} else {
			if ($model->ForumTopicID != 0) {
				$this()->setSessionMessage("Forum post updated.");
				return $this()->redirect("forumtopic/" . $model->ForumTopicID . "/view/#post" . $model->ID);
			}
			return $this()->redirect($model->ActionLink(Viewable::ActionName));
		}
	}

	/**
	 * Adds an ID field with model ID if not already in fields collection.
	 *
	 * @param DataObject $model
	 * @param            $fields
	 * @param            $action
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $action, array &$requiredFields = []) {
		if ($action === self::ActionName) {
			if (!$fields->fieldByName('ID')) {
				$fields->push(
					new HiddenField('ID', '', $model->ID)
				);
			}

			if ($model->ForumTopicID != 0 && $model->ClassName == "Post") {
				$fields->removeByName("AttachedImages");
				$fields->removeByName("AttachImages");
			}
		}
	}

	/**
	 * Adds FormActions for this mode.
	 *
	 * @param $model
	 * @param $actions
	 * @param $action
	 */
	public function updateActionsForMode(DataObject $model, $actions, $action) {
		if ($action === self::ActionName) {
			if ($this->canEdit()) {
				$actions->push(new FormAction('Save', 'Save'));
			}
		}
	}

	/**
	 * For uploads as a post we need to provide the form which handles them, in this case for a 'post' request.
	 *
	 * @param SS_HTTPRequest $request
	 * @param                $action
	 * @return SocialForm
	 */
	public function provideUploadFormForMode(SS_HTTPRequest $request, $action) {
		if ($action === self::ActionName) {
			return $this->EditForm();
		}
	}

}
