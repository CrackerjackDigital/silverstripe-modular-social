<?php
/**
 * Extension to handle listing of a model
 */
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;

class Listable extends SocialAction  {
	const ActionCode = 'VEW';
	const Action = 'list';

	private static $url_handlers = [
		'list' => 'dolist',
	];
	private static $allowed_actions = [
		'dolist' => '->canList("action")',
	];
	private static $action_templates = [
		self::Action => self::Action,
	];

	private static $action_modes = [
		self::Action => self::Action,
	];

	/**
	 * Uses either the current logged in member or the 'Guest' member to check permissions for 'VEW' permission
	 * (there is no distinct 'LIS' for list permission).
	 *
	 * @return bool
	 */
	public function canList($source = null) {
		return parent::canDoIt(self::ActionCode, $source);
	}

	/**
	 * Asks extensions to give us a reasonable response to the list action via extend.beforeList.
	 *
	 * NB list is a reserved word hence dolist.
	 *
	 * @param SS_HTTPRequest $request
	 * @return SS_HttpResponse
	 */
	public function dolist(SS_HTTPRequest $request) {
		// extend takes references
		$mode = self::Action;

		$model = $this()->getModelInstance(self::Action);

		if ($request->httpMethod() === 'GET') {
			$responses = $this()->extend('beforeList', $request, $model, $mode);
		} else {
			$responses = [new SS_HTTPResponse_Exception('', 405)];
		}
		return array_reduce(
			$responses,
			function ($prev, $item) {
				return $prev ?: $item;
			}
		);
	}

	/**
	 * Return rendered templates for list mode.
	 *
	 * @return mixed
	 */
	public function beforeList() {
		return $this()->renderTemplates(self::Action);
	}

	/**
	 * Provides a singleton of the passed in modelClass.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $mode
	 * @return Object
	 */
	public function provideModel($modelClass, $id, $mode) {
		if ($mode === static::Action) {
			return singleton($modelClass);
		}
	}

	/**
	 * Return all items of extended controllers model class. Templates can do with as they please.
	 * @return PaginatedList
	 */
	public function ListView() {
		$className = $this()->getModelClass();
		$model = $this()->getModelInstance(self::Action);

		$items = new PaginatedList(DataObject::get($className), $this()->getRequest());
		$items->setPageLength(10);

		$data = [
			'Title' => $model->plural_name(),
			'ListItems' => $items,
		];
		return new ArrayData($data);
	}

	public function RelatedList() {
		return ForumTopic::get()->filter([
			'ForumID' => $this()->getModelID(),
		]);
	}

}