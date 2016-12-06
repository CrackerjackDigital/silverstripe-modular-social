<?php
namespace Modular\Actions;

use Form;
use \Modular\Extensions\Controller\SocialAction;
use SS_HTTPRequest;
use SS_HTTPResponse_Exception;

class ViewableSubContent extends SocialAction  {
	const ActionTypeCode = 'VEW';
	const ExtraAction = "ExtraAction";
	const Mode = "view";

	private static $url_handlers = [
		'$ID/view-content/$ActionType/$SubID' => self::ExtraAction,
		'$ID/view-content/$ActionType' => self::ExtraAction,
	];

	private static $allowed_actions = [
		self::ExtraAction => '->canView("action")',
	];

	private static $action_templates = [
		self::ExtraAction => self::ExtraAction,
	];

	private static $action_modes = [
		self::ExtraAction => self::Mode,
	];

	/**
	 * @return bool
	 */
	public function canView($source = null) {
		if (!$model = $this()->getModelInstance(self::Mode)) {
			$this()->httpError(404);
		}
		if (!parent::canDoIt('VEW', $source)) {
			$this()->httpError(401);
		}
		return true;
	}

	/**
	 * Return Form for view mode (disabled), GET only, a POST will 405.
	 *
	 * @param SS_HTTPRequest $request
	 * @returns Form|SS_HTTPResponse_Exception
	 */
	public function ExtraAction(SS_HTTPRequest $request) {
		$mode = self::Mode;
		$controller = $this();

		$model = $controller->getModelInstance(self::Mode);
		$viewContent = $request->param("ActionType");
		if ($request->isAjax()) {
			return $this()->renderWith([$model->ClassName . "_" . $viewContent], ["Model" => $model]);
		}
		return $this()->renderWith([$model->ClassName . "_" . $viewContent, "Page"], ["Model" => $model]);
	}

}