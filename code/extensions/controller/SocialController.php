<?php
namespace Modular\Extensions\Controller;

use Application;
use DataObject;
use Member;
use Modular\config;
use Modular\Edges\SocialRelationship as Edge;
use Modular\json;
use Modular\Types\SocialAction;
use Modular\Controllers\GraphNode;

/**
 * Base extension for Controller extensions. Adds some usefull functionality.
 *
 * SocialAction extensions such as Viewable, Listable, Postable etc should derive
 * from this.
 *
 */
class SocialController extends \Modular\Extensions\Controller\GraphNode {
	use config;
	use json;

	const Action = 'index';

	/**
	 * Test if we are in particular mode (calls extend.provideMode)
	 *
	 * @param $compareMode
	 * @return mixed
	 */
	public function isMode($compareMode) {
		return array_reduce(
			$this()->extend('provideMode', $compareMode),
			function ($prev, $item) {
				return $prev ?: $item;
			}
		);
	}

	/**
	 * Compares passed mode to this extensions mode and returns the mode if
	 * they match. Can be used to test what mode/action we are processing via
	 * extend.
	 *
	 * @param $compareMode
	 * @return string
	 */
	public function provideMode($compareMode) {
		if ($compareMode === static::Action) {
			return static::Action;
		}
	}

	/**
	 * Check if the action can be done on the controlled model instance if an
	 * ID is available, or class if not.
	 *
	 * @param string|array $actionCodes
	 * @param string       $source where call is being made from, e.g. a controller will set this to 'action' on checking allowed_actions
	 * @return bool|int
	 */
	public function canDoIt($actionCodes, $source = '') {
		$action = static::action();

		if ($id = $this()->getModelID()) {
			/** @var string|Edge $actionClassName */
			$actionClassName = current(Edge::implementors(
				$this()->getModelClass(),
				$this()->getModelClass()
			));
			$isCreator = $actionClassName::get()->filter([
				$actionClassName::from_field_name() => $id,
				$actionClassName::to_field_name()   => $id,
				'Type.Code'                   => 'CRT',
			])->count();

			if ($isCreator) {
				return true;
			}

		}
		$canDoIt = SocialAction::check_permission(
			$actionCodes,
			$this()->getModelID()
				? $this()->getModelInstance($action)
				: $this()->getModelClass()
		);
		if ($source && !$canDoIt) {
			if ($source == 'action') {
				$this()->httpError(403, "Sorry, you do not have permissions to do that");
			}
		}
		return $canDoIt;
	}

	/**
	 * Helper function will return a model of $modelClass with ID $id if $mode
	 * is same as the derived classes static::SocialAction.
	 *
	 * @param      $modelClass
	 * @param      $id
	 * @param      $action
	 * @param bool $createIfNotFound if there is an id and the model is not
	 *                               found then return a new one.
	 * @return DataObject|null
	 */
	protected function provideModel($modelClass, $id, $action, $createIfNotFound = false) {
		if ($action === static::Action) {
			if ($id) {
				if (!$model = DataObject::get($modelClass)->byID($id)) {
					if ($createIfNotFound) {
						$model = DataObject::create($modelClass);
					}
				}
				return $model;
			}
		}
	}

	/**
	 * If mode matches derived classes SocialAction then return a new Model of class
	 * $modelClass.
	 *
	 * @param $modelClass
	 * @param $mode
	 * @return DataObject|null
	 */
	protected function provideNewModel($modelClass, $mode) {
		if ($mode === static::Action) {
			return DataObject::create($modelClass);
		}
	}

	/**
	 * Returns true if a previous action exists from the current member
	 * to the model which matches the provided Code, for example if the 'CRT'
	 * was action was performed by the logged in member, then the member
	 * 'owns' the model and so we can tailor things like showing 'like' and
	 * 'follow' actions which wouldn't make sense for the creating member to
	 * do.
	 *
	 * @param $previousActionCode - the code of the action type we are
	 *                            checking to see exists as a record
	 */

	protected function hasPreviousAction($previousActionCode) {
		return SocialAction::find(
			Member::currentUser(),
			$this()->getModelInstance(),
			$previousActionCode
		);
	}

	protected function checkPermission($actionCode) {
		return SocialAction::check_permission(
			$actionCode,
			$this()->getModelInstance(null)
		);
	}

	/**
	 * Return data for use when populating the mosaic.jst file hooked via
	 * application requirements.yml SocialModelInterface. Provides the glue
	 * between SocialModels and mosaic front-end code.
	 *
	 * @param SocialController $controller
	 * @param string           $fileType    one of the ModularModule.FileTypeABC constants
	 * @param array            $info        additional information about the file from requirements.yml
	 * @return array
	 */
	public function modularRequirementsTemplateData(SocialController $controller, $fileType, $info) {
		return [
			'MosaicModelToEndpointMap' => static::template_encode(Application::social_model_routes()),
		];
	}

	public static function action() {
		return static::Action;
	}
}