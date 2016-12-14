<?php
namespace Modular\Extensions\Controller;

use Application;
use DataObject;
use Member;
use Modular\config;
use Modular\Edges\SocialRelationship as Edge;
use Modular\json;
use Modular\Types\SocialActionType;
use Modular\Controllers\GraphNode;

/**
 * Base extension for Controller extensions. Adds some usefull functionality.
 *
 * SocialActionType extensions such as Viewable, Listable, Postable etc should derive
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
	 * Helper function will return a model of $modelClass with ID $id if $mode
	 * is same as the derived classes static::SocialActionType.
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
	 * If mode matches derived classes SocialActionType then return a new Model of class
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
	 * Return data for use when populating the mosaic.jst file hooked via
	 * application requirements.yml SocialModelInterface. Provides the glue
	 * between SocialModels and mosaic front-end code.
	 *
	 * @param SocialController $controller
	 * @param string           $fileType one of the ModularModule.FileTypeABC constants
	 * @param array            $info     additional information about the file from requirements.yml
	 * @return array
	 */
	public function modularRequirementsTemplateData(SocialController $controller, $fileType, $info) {
		return [
			'MosaicModelToEndpointMap' => static::template_encode(Application::social_model_routes()),
		];
	}

}