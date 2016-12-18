<?php
namespace Modular\Actions;

use DataObject;
use DateField;
use FieldList;
use Form;
use FormField;
use HiddenField;
use Modular\Controllers\SocialModel_Controller;
use Modular\Extensions\Controller\SocialAction;
use Modular\Extensions\Model\SocialMember;
use Modular\Types\SocialEdgeType as SocialEdgeType;
use Modular\UI\Controls\ActionLinkField;
use SS_HTTPRequest;
use SS_HTTPResponse_Exception;

class Viewable extends SocialAction  {
	const ActionCode = 'VEW';
	const ActionName = 'view';

	private static $url_handlers = [
		'$ID/view' => self::ActionName,
	];

	private static $allowed_actions = [
		self::ActionName => '->canView("action")',
	];

	private static $action_templates = [
		self::ActionName => self::ActionName,
	];

	private static $action_modes = [
		self::ActionName => self::ActionName,
	];

	/**
	 * Extend default checks with check that logged in member is the extended models Creator.
	 *
	 * @return bool
	 */
	public function canView($source = null) {
		if (!$model = $this()->getModelInstance(self::ActionName)) {
			$this()->httpError(404);
		}
		if ($creator = $this()->LastActor('CRT')) {
			$member = $source ?: \Member::currentUser();

			if ($member && $member->ID == $creator->ID) {
				return true;
			}
		}
		if (!parent::canDoIt(self::ActionCode, $source == null)) {
			$this()->httpError(401);
		}
		return true;
	}

	/**
	 * Adds an ID field with model ID if not already in fields collection. We need this on
	 * Viewable because it may later transform into Editable.
	 *
	 * @param DataObject $model
	 * @param $fields
	 * @param $mode
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, array &$requiredFields = []) {
		if ($mode === self::ActionName) {
			if (!$fields->fieldByName('ID')) {
				$fields->push(
					new HiddenField('ID', '', $model->ID)
				);
			}
		}
	}/**
	 * If we are in view mode and can edit then adds an 'edit' button.
	 *
	 * @param FieldList $actions
	 * @param $mode
	 */
	public function updateActionsForMode(DataObject $model, FieldList $actions, $mode) {
		if ($mode === self::ActionName) {
			$allowed = SocialEdgeType::check_permission(
				SocialMember::current_or_guest(), $this()->getModelInstance(self::ActionName), Editable::ActionCode
			);

			if ($allowed) {

				$href = $this()->getModelInstance(self::ActionName)->ActionLink(Editable::ActionName);
				$label = 'Edit';

				$actions->merge([
					new ActionLinkField('EditLink', $href, $label),
				]);
			}
		}
	}

	public function ViewForm() {
		/** @var Form $form */
		$form = $this()->formForModel(self::ActionName);
		// NB this is overridded in SocialForm to apply DisabledTransformation not ReadonlyTransformation
		$form->makeReadonly();
		return $form;
	}
	/**
	 * Return Form for view mode (disabled), GET only, a POST will 405.
	 *
	 * @param SS_HTTPRequest $request
	 * @returns Form|SS_HTTPResponse_Exception
	 */
	public function view(SS_HTTPRequest $request) {
		$mode = self::ActionName;
		/** @var SocialModel_Controller $controller */
		$controller = $this();

		$model = $controller->getModelInstance(self::ActionName);

		if ($request->httpMethod() === 'GET') {
			$responses = $controller->extend('beforeView', $request, $model, $mode);
		} else {
			$responses = $controller->httpError(405);
		}
		return array_reduce(
			$responses,
			function ($prev, $item) {
				return $prev ?: $item;
			}
		);
	}

	/**
	 * Return rendered templates for view mode.
	 *
	 * @return mixed
	 */
	public function beforeView() {
		return $this()->renderTemplates(self::ActionName);
	}

	/**
	 * We can't post to a view so throw an error (should be stopped vy view action handler anyway)
	 */
	public function afterView() {
		$this()->httpError(405);
	}

	/**
	 * Provide the model for this action, in this case an instance of $modelClass found by $id.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $mode
	 * @return DataObject
	 */
	public function provideModel($modelClass, $id, $mode) {
		if ($mode === $this->action()) {
			return DataObject::get_by_id(
				$modelClass,
				$id
			);
		}
	}

	/**
	 * Decorate fields as required for framework/design:
	 * - sets field values of date fields to be nice
	 *
	 * @param FieldList $fields
	 * @param $mode - unused right now
	 */
	public function decorateFields(FieldList $fields, $mode) {
		/** @var FormField $field */
		foreach ($fields as $field) {

			if ($field instanceof DateField) {
//                $field->setValue($field->Long());
			}
		}
	}

}