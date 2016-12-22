<?php
namespace Modular\Extensions\Controller;

use DataObject;
use Modular\Edges\SocialRelationship;
use Modular\Extensions\Model\SocialMember;
use Modular\Interfaces\SocialModelProvider;
use Modular\Models\SocialModel;
use Modular\Types\SocialEdgeType as SocialEdgeType;

/**
 * Base extension for actions which can be performed by the logged in Member to establish
 * a action to the extended object, such as follow or like. Ultimately this derives from a DataExtension not an Extension
 * so extraStatics will be called and we can define url_handlers dynamically.
 */
abstract class SocialAction extends SocialController
	implements SocialModelProvider {
	const ActionCode        = '';
	const ActionName        = '';
	const ReverseActionName = '';
	const MemberClassName   = 'Member';
	const ActionClassSuffix = '';

	public function extraStatics($class = null, $extension = null) {
		return array_merge_recursive(
			parent::extraStatics($class, $extension) ?: [],
			[
				'url_handlers' => $this->urlHandlers($class, $extension),
			]
		);
	}

	/**
	 *
	 * @param                     $class
	 * @param SocialAction|string $extension
	 * @return array
	 */
	protected function urlHandlers($class, $extension) {
		$handlers = [];
		if ($typeCode = $extension::action_code()) {
			/** @var SocialEdgeType $type */
			if ($type = SocialEdgeType::get_by_code($typeCode)) {
				if ($name = $type->ActionName) {
					$handlers[ '$ID/' . $name ] = $name;
				}
				if ($name = $type->ReverseActionName) {
					$handlers[ '$ID/' . $name ] = $name;
				}
			}
		}
		return $handlers;
	}

	/**
	 * Check if the action can be done on the controlled model instance if an
	 * ID is available, or class if not.
	 *
	 * @param string|array $actionCodes
	 * @param string       $source where call is being made from, e.g. a controller will set this to 'action' on checking allowed_actions
	 * @return bool|int
	 */
	public function canDoIt($actionCodes = null, $source = '') {
		$action = static::ActionCode;
		$modelClass = $this()->getModelClass();

		if ($id = $this()->getModelID()) {
			$isCreator = SocialRelationship::graph(
				\Member::currentUser(),
				$modelClass,
				'CRT'
			)->count();

			if ($isCreator) {
				return true;
			}
		}
		$canDoIt = SocialEdgeType::check_permission(
			SocialMember::current_or_guest(),
			$modelClass,
			$actionCodes
		);
		if ($source && !$canDoIt) {
			if ($source == 'action') {
				$this()->httpError(403, _t('Global.ActionNotAllowed', "Sorry, you do not have permissions to do that"));
			}
		}
		return $canDoIt;
	}

	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param string $modelClass
	 * @param int    $id
	 * @param string $action
	 * @param bool   $createIfNotFound
	 *
	 * @return \DataObject|\Modular\Interfaces\SocialModel|null
	 */
	public function provideModel($modelClass, $id, $action, $createIfNotFound = false) {
		$model = null;
		if ($action === static::ActionName) {
			if ($id) {
				$model = SocialModel::get($modelClass)->byID($id);
			}
			if (!$model && $createIfNotFound) {
				$model = $model::create();
			}
		}
		return $model;
	}

	/**
	 * Creates SocialRelationship records for each of the SocialRelationship derived classes that represent an edge between the
	 * provided models.
	 *
	 * e.g. given a Member and an Organisation will create a MemberOrganisation edge and any others which are between Member and Organisation Models.
	 *
	 * @param $fromModel
	 * @param $toModel
	 * @return SocialRelationship
	 */
	public static function make($fromModel, $toModel, $extraData = []) {
		/** @var string $implementorClassName class name */
		foreach (SocialRelationship::implementors($fromModel, $toModel) as $implementorClassName) {
			/** @var SocialRelationship $relationship */
			$relationship = new $implementorClassName();
			$relationship->update($extraData);

			$relationship->setFrom($fromModel);
			$relationship->setTo($toModel);
			$relationship->setEdgeType(static::action());
			$relationship->write();
			return $relationship;
		}
	}

	/**
	 * Remove all edges that exist between two models of this actions edge type
	 *
	 * @param        $fromModel
	 * @param        $toModel
	 * @param string $actionCode if not supplied then static::ActionCode will be used.
	 * @return bool true if none removed or all removed succesfully, false if anything fails
	 */
	public static function remove($fromModel, $toModel, $actionCode = '') {
		$ok = true;
		$actionCode = $actionCode ?: static::ActionCode;

		/** @var SocialRelationship $relationship */
		foreach (SocialRelationship::graph($fromModel, $toModel, $actionCode) as $relationship) {
			$ok = $ok && $relationship->prune();
		}
		return $ok;
	}

	/**
	 * Return the SocialEdgeType for this extensions ActionCode.
	 *
	 * @return \Modular\Interfaces\Graph\EdgeType|SocialEdgeType
	 */
	public static function action() {
		static $action;
		return $action ?: $action = SocialEdgeType::get_by_code(static::ActionCode);
	}

	/**
	 * Return the ActionCode for this action.
	 *
	 * @return string
	 */
	public static function action_code() {
		return static::ActionCode;
	}

	/**
	 * Return the ActionName for this action.
	 *
	 * @return string
	 */
	public static function action_name() {
		return static::ActionName ?: static::action()->ActionName;
	}

	/**
	 * Return the ActionName for this action.
	 *
	 * @return string
	 */
	public static function reverse_action_name() {
		return static::ReverseActionName ?: static::action()->ReverseActionName;
	}

	/**
	 * Return latest relationship if one exists from $fromModel to this model of this actions type
	 *
	 * @param \DataObject $fromModel
	 * @return SocialRelationship
	 */
	protected function existsFrom(DataObject $fromModel) {
		return SocialRelationship::latest($fromModel, $this(), static::ActionCode);
	}

	/**
	 * Return latest relationship if one exists from this model to $toModel of this actions type
	 *
	 * @param \DataObject $toModel
	 * @return SocialRelationship
	 */
	protected function existsTo(DataObject $toModel) {
		return SocialRelationship::latest($this(), $toModel, static::ActionCode);
	}

}