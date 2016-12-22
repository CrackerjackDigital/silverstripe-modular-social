<?php
namespace Modular\Actions;

use DataObject;
use Director;
use Exception;
use File;
use FormAction;
use Image;
use Modular\Edges\MemberMember;
use Modular\Extensions\Controller\SocialAction;
use Modular\Extensions\Model\SocialMember;
use Modular\Forms\SocialForm;
use Modular\Interfaces\ModelWriteHandlers;
use Modular\Interfaces\SocialModelProvider;
use Session;
use SS_HTTPRequest;
use SS_HTTPResponse;
use ValidationException;
use ValidationResult;

class Createable extends SocialAction
	implements SocialModelProvider, ModelWriteHandlers {
	const ActionCode = 'CRT';
	const ActionName = 'create';

	// can't use new as it's a reserved word
	private static $url_handlers = [
		self::ActionName => 'donew',
	];

	private static $allowed_actions = [
		'donew' => '->canCreate("action")',
	];

	private static $action_templates = [
		self::ActionName => self::ActionName,
	];

	private static $action_modes = [
		self::ActionName => self::ActionName,
	];

	/**
	 * @param null $source
	 * @return bool|int|void
	 */
	public function canCreate($source = null) {
		return parent::canDoIt(static::ActionCode, $source);
	}

	/**
	 * Return the Model form configured for 'new' action.
	 *
	 * @return SocialForm
	 */
	public function CreateForm() {
		return $this()->formForModel($this->action_name());
	}

	/**
	 * Provide the model for this action, in this case an empty singleton of the provided $modelClass.
	 *
	 * @param $modelClass
	 * @param $action
	 * @return Object
	 */
	public function provideModel($modelClass, $id, $action) {
		if ($action === $this->action_name()) {
			return singleton($modelClass);
		}
	}

	/**
	 * Depending on GET or POST extends 'beforeCreate' or 'afterCreate' then calls same method on extended controller.
	 *
	 * Returns the reduced merged result of these calls to find the eventual action to take.
	 *
	 * @param SS_HTTPRequest $request
	 * @return mixed - a valid action response for handling by SilverStripe.
	 */
	public function donew(SS_HTTPRequest $request) {
		$action = $this->action_name();
		$model = $this()->getModelInstance($action);

		// let extensions do their thing and then call back to this controller for final outcome.
		if ($request->httpMethod() === 'POST') {
			$responses = array_merge(
				$this()->extend('afterCreate', $request, $model, $action),
//				[$this()->afterCreate($request, $model, $action)],
				[]
			);
		} else {
			$responses = array_merge(
				$this()->extend('beforeCreate', $request, $model, $action),
//				[$this()->beforeCreate($request, $model, $action)]
				[]
			);
		}
		// return the first non-falsish response
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
	public function beforeCreate(SS_HTTPRequest $request, DataObject $model, $action) {
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
	public function afterCreate(SS_HTTPRequest $request, DataObject $model, $action) {
		$formName = $this()->formForModel($action)->FormName();

		try {
			// validation errors should throw a ValidationException to be caught later.

			$fieldsHandled = [];

			// handle any data munging e.g. by ConfirmedPasswordField needed before we write the model.
			$this()->extend('beforeModelWrite', $request, $model, $action, $fieldsHandled);
			$model->extend('beforeModelWrite', $request, $model, $action, $fieldsHandled);

			$posted = $request->postVars();

			if (!Director::isDev()) {
				if (isset($_POST['g-recaptcha-response'])) {
					$captcha_secret = RE_CAPTCHA_KEY;
					$googleLink = "https://www.google.com/recaptcha/api/siteverify?secret=" . $captcha_secret . "&response=" . $request->postVar('g-recaptcha-response');
					$verify = json_decode(file_get_contents($googleLink));
					if (!$verify->success) {
						Session::setFormMessage($formName, "Please verify you are not a robot", 'error');
						return $this()->redirectBack();
					}
				}
			}
			// filter out data that is not actually a field on the model
			$updateWith = array_intersect_key(
				$posted,
				$model->config()->get('db')
			);

			$model->update($updateWith);

			// de-duplex the password field
			if (isset($updateWith['Password']) && is_array($updateWith['Password'])) {
				// get the first value
				$model->Password = current($updateWith['Password']);
			}

			$validationResult = new ValidationResult();
			$model->extend('validate', $validationResult);

			$model->write();

			if ($request->postVar('AttachImages')) {
				foreach ($request->postVar('AttachImages') as $fileArr => $fileID) {
					if ($fileID) {
						if ($file = Image::get()->byID($fileID)) {
							$model->Images()->add($file);
						}
					}
				}
			}

			if ($request->postVar('Files')) {
				foreach ($request->postVar('Files') as $fileArr => $fileID) {
					if ($fileID) {
						if ($file = File::get()->byID($fileID)) {
							$model->Files()->add($file);
						}
					}
				}
			}

			// handle any data manipulations to perform after model is succesfully written, e.g. update relationships.
			$model->extend('afterModelWrite', $request, $model, $action);
			$this()->extend('afterModelWrite', $request, $model, $action);

		} catch (ValidationException $e) {

			Session::setFormMessage($formName, $e->getMessage(), 'error');
			return $this()->redirectBack();

		} catch (Exception $e) {
			return $this()->httpError(500);
		}

		if ($request->isAjax()) {
			return new SS_HTTPResponse(null, 200);
		} else {

			/**
			 *
			 * TODO:
			 * - Needs tidy up
			 **/
			if ($model->ClassName == "Member" || $model->ClassName == "SocialOrganisation") {
				Session::setFormMessage(
					$formName,
					_t('Createable.SavedMessage', 'Created'),
					'good'
				);
			} else {
				return $this()->redirect($model->ActionLink(Viewable::ActionName));

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
	public function updateActionsForMode($model, $actions, $action) {
		if ($action == $this->action_name()) {
			if ($this->canCreate()) {
				$actions->push(new FormAction('Save', 'Save'));
			}
		}
	}

	/**
	 * Ensures we have a 'CRT' relationship between the current member and the model.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $model
	 * @param                $action
	 * @throws Exception
	 */
	public function afterModelWrite(SS_HTTPRequest $request, DataObject $model, $action) {
		if ($action === $this->action_name()) {
			$member = SocialMember::current_or_guest();

			Confirmable::disable();
			Approveable::disable();
			MemberMember::make($member, $model, static::ActionCode);
			Approveable::enable();
			Confirmable::enable();

		}
	}

	// doesn't do anything
	public function beforeModelWrite(SS_HTTPRequest $request, DataObject $model, $action, &$fieldsHandled = []) {
		// nowt to do
	}

	/**
	 * For uploads as a post we need to provide the form which handles them, in this case for a 'post' request.
	 *
	 * @param SS_HTTPRequest $request
	 * @param                $action
	 * @return SocialModelForm
	 */
	public function provideUploadFormForMode(SS_HTTPRequest $request, $action) {
		if ($action === self::ActionName) {
			return $this->CreateForm();
		}
	}
}