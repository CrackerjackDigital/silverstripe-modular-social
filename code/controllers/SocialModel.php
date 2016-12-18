<?php
namespace Modular\Controllers;
use ArrayData;
use ClassInfo;
use Config;
use DataObject;
use FieldList;
use Member;
use Modular\Extensions\Model\SocialMember;
use Modular\Forms\SocialForm;
use Modular\Interfaces\SocialModelController as SocialModelControllerInterface;
use Modular\json;
use Modular\Models\Social\Organisation;
use MosaicFormControllerInterface as FormControllerInterface;
use RequiredFields;
use Requirements;
use Session;

/**
 * Base controller for SocialModel derived classes.
 *
 * SocialEdgeType methods are dual-purpose depending on HTTP method, GET will present a view/form, POST will save the data.
 */
class SocialModel extends GraphNode implements SocialModelControllerInterface, FormControllerInterface {
	use json;

	// what url's this controller handles. Added to by extensions such as Editable.
	private static $url_handlers = [];

	// security checks for actions. Added to by extensions such as Editable.
	private static $allowed_actions = [];

	// map one or more actions to a particular template suffix. Added to by extensions such as Editable.
	private static $action_templates = [];

	// map actions to a 'mode'. Added to by extensions such as Editable.
	private static $action_modes = [];

	private static $query_limit = 10;

	private static $transient_fields = [];

	protected $templates = [];

	protected $modelInstance = null;

	protected $mode = null;

	public function CurrentMember() {
		return Member::currentUser();
	}

	public function init() {
		parent::init();
		$this->profileStatusCheck();
		$host = $_SERVER['HTTP_HOST'];
		$mobile_urls = Config::inst()->get('NZFIN', 'mobile_urls');
		if (!in_array($host, $mobile_urls)) {
			$this->mobileCheck();
		}
	}

	/**
	 * In a public model controller 'this' is the controller itself.
	 * @return $this
	 */
	public function __invoke() {
		return $this;
	}

	/**
	 *
	 * Check if mobile and redirect, happens on client side
	 *
	 */
	public function mobileCheck() {
		$MobileURL = $_SERVER['HTTP_HOST'];
		Requirements::customScript(<<<JS
            if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent)
    || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0,4))) {
    		console.log("is mobile");
	    	window.location = "http://m.{$MobileURL}";
    }
JS
		);
	}

	/**
	 * Return a form for the model for the provided mode.
	 *
	 * @param $mode e.g. 'new', 'post', fields from model.fields_for_mode are used in form.
	 * @param array $initialData loaded into form on create
	 * @return SocialForm
	 */
	public function FormForModel($mode, array $initialData = []) {

		$formClassName = $this->getFormName();

		/** @var DataObject $model */
		if ($model = $this->getModelInstance($mode)) {

			$this->setDataModel($model);
			list($fields, $requiredFields) = $this->getFieldsForMode($mode);

		} else {

			$fields = new FieldList();
			$requiredFields = [];

		}

		$validator = new RequiredFields($requiredFields);

		// get the actions depending on the mode from the controller.
		// Controller extensions will add their actions in here too.
		$actions = $this->getActionsForMode($mode);

		if (ClassInfo::exists($formClassName)) {
			$form = new $formClassName($this, $this->getFormName(), $fields, $actions, $validator);
		} else {
			$form = new SocialForm($this, $this->getFormName(), $fields, $actions, $validator);
		}
		$form->disableSecurityToken();

		if ($model) {
			// we want to post to the controller for the model in the mode we are in
			// e.g. on newsfeeed we may render this form in 'edit' mode.
			$form->setFormAction(
				$model->ActionLink($mode)
			);
		} else {
			// we want to post back to the url which showed us.
			$form->setFormAction('/' . $this->getRequest()->getURL());
		}
		if ($initialData) {
			$form->loadDataFrom($initialData);
		} else {
			$form->loadDataFrom($model);
		}
		$form->addExtraClass('editable');

		return $form;
	}

	/**
	 * Returns an empty form for a supplied model in a particular mode. The form action is set to post to the
	 * endpoint for the model returned, not to the endpoint of the extended controller. A hidden ID field is with the
	 * ID of the request model, e.g. for a page generated by /post/345/view then there will be a hidden field 'PostID'
	 * with a value of 345 added to the form so when the endpoint is posted to '/post-reply/new' then the PostID can
	 * be added.
	 *
	 * @param $modelClass
	 * @param $mode
	 * @return mixed
	 */
	public function NewRelatedModelForm($modelClass, $mode = 'new') {
		$model = singleton($modelClass);
		/** @var SocialForm $form */
		$form = $model->formForMode($mode);

		// controller extensions can modify fields
		$this->extend('updateFieldsForMode', $model, $fields, $mode, $requiredFields);

		// controller extensions can format fields
		$this->extend('decorateFields', $fields, $mode);

		// route form to the model's controller, not this controller
		$form->setFormAction($model->ActionLink($mode));
		return $form;
	}

	/**
	 * Return SocialModelForm_<ModelClass>Form as form name to use in templates for this class.
	 *
	 * @param string $className optional specific class name to use, if not supplied this.getModelClass is used.
	 * @return string
	 */
	public function getFormName($className = null) {
		return ($className ?: $this->getModelClass()) . "Form";
	}

	/**
	 * Return action fields for the current model and mode. First gets them from the model, then
	 * calls extend.updateFieldsForMode and extend.decorateFields so controller extensions
	 * can add their own fields/required fields.
	 */
	public function getFieldsForMode($mode) {
		/** @var SocialModel $model */
		if ($model = $this->getModelInstance($mode)) {
			list($fields, $requiredFields) = $model->getFieldsForMode($mode);

			// controller extensions can add fields (model.getFieldsForMode has already called updateFieldsForMode
			// on model extensions).
			$this->extend('updateFieldsForMode', $model, $fields, $mode, $requiredFields);

			// controller extensions can format fields depending on matched mode
			$this->extend('decorateFields', $fields, $mode);

			if (method_exists($this, "decorateFields")) {
				$this->decorateFields($fields, $mode);
			}

			return [$fields, $requiredFields];
		}
	}

	/**
	 * Returns valid actions for this controller and current model in a particular mode.
	 *
	 * Calls extend updateActionsForMode to allow extensions to add/configure their own actions.
	 *
	 * @sideeffect may update model!
	 *
	 * @param string $mode - 'view', 'edit' etc
	 * @return FieldList
	 */
	public function getActionsForMode($mode) {
		$actions = new FieldList();
		$model = $this->getModelInstance($mode);

		// controller extensions can add their actions
		$this()->extend('updateActionsForMode', $model, $actions, $mode);

		return $actions;
	}

	/**
	 * Returns true if we can edit the current model according to 'active' Controller extension.
	 *
	 * @param null $member
	 * @return mixed
	 */
	public function canEdit($member = null) {
		return array_reduce(
			$this->extend('canEdit', $member),
			function ($prev, $item) {
				return $prev ?: $item;
			}
		);
	}

	/**
	 * Figure out the mode from the URL, then double check by calling isMode which will query
	 * extensions to return if it is a valid mode.
	 *
	 * @return mixed
	 */
	public function getMode() {
		$urlReversed = array_reverse(explode('/', $this()->getRequest()->getURL()));
		$mode = reset($urlReversed);

		if ($this->isMode($mode)) {
			return $mode;
		}
	}

	/**
	 * If a GET request return request param ID otherwise postVar ID.
	 *
	 * @return int|null
	 */
	public function getModelID() {
		$request = $this->getRequest();
		return $request->isGET() ? $request->param('ID') : $request->postVar('ID');
	}

	/**
	 * Return an instance of the Model. If Mode is New then returns a singleton, otherwise returns an existing
	 * instance using request parameter ID on GET or postVar ID on POST.
	 *
	 * @param $mode - one of the ModeXXX constants.
	 * @return DataObject|null
	 */
	public function getModelInstance($mode = '') {
		/** @var DataObject $modelInstance */
		static $modelInstance;

		if (!$modelInstance || ($modelInstance && !$modelInstance->exists())) {
			$modelClass = $this->getModelClass();
			$id = $this->getModelID();

			$possibleInstances = $this->extend('provideModel', $modelClass, $id, $mode);

			// model is provided by extend.provideModel, first model returned wins.
			$modelInstance = array_reduce(
				$possibleInstances,
				function ($prev, $item) {
					return $prev ?: $item;
				}
			);
		}
		return $modelInstance;
	}

	/**
	 * Return config.model_class or name of derived class before the '_' e.g. Member for Member_.
	 *
	 * @return string
	 */
	public function getModelClass() {
		return static::config()->get('model_class') ?: substr($this->class, 0, strpos($this->class, '_'));
	}

	/**
	 * Return name of a template for a given action.
	 */
	public function getTemplateName($action) {
		return $this->getModelClass() . "_$action";
	}

	/**
	 * Returns templates to use for actions, mapping between an action and a template via config.action_templates
	 * so templates can be re-used between actions, templates can have more meaningful names and actions without
	 * templates filtered out.
	 *
	 * @param string $mode - new, view, edit
	 * @return array templates to use in render call
	 */
	public function getTemplates($mode) {
		$action_templates = self::config()->get('action_templates');

		$suffix = $action_templates[$mode];
		if ($this->getRequest()->isAjax()) {
			$templates = [
				$this->getTemplateName($suffix),
				"SocialModel_$suffix",
			];
		} else {
			$templates = [
				$this->getTemplateName($suffix),
				"SocialModel_$suffix",
				'Page',
			];
		}
		return $templates;
	}

	/**
	 * Return a rendered page or if isAjax then fragment using <ModelClass>_<mode> or SocialModel_<mode>
	 * as template name.
	 *
	 * @param $mode string - generally 'edit' for forms.
	 * @return \HTMLText
	 */
	public function renderTemplates($mode, array $extraData = []) {
		$templates = $this->getTemplates($mode);

		$model = $this->getModelInstance($mode);

		return $this->renderWith(
			$templates,
			array_merge(
				[
					'Mode' => $mode,
					'Model' => $model,
				],
				$extraData
			)
		);
	}

	/**
	 * Return an href for an action/mode for the current model.
	 * @param $action
	 * @return mixed
	 */
	public function ActionLink($action, $includeID = true) {
		return $this->getModelInstance($action)->ActionLink($action, $includeID);
	}

	/**
	 * Return the config.route_part from the controllers handled model.
	 * @return string|null
	 */
	public function endpoint() {
		return Config::inst()->get($this->getModelClass(), 'route_part');
	}

	/**
	 *
	 * redirect user to login page if user been has logouted out
	 *
	 */
	public function AuthenticateUser() {
		if (!Member::currentUser()) {
			return $this->redirect("Security/login");
		}

	}

	/**
	 *
	 * Get Session messages
	 *
	 **/
	public function get_message() {
		$msg = Session::get('message');
		Session::clear('message');
		if ($msg != null) {
			return new ArrayData(array(
				'Success' => $msg['success'],
				'Message' => $msg['message'],
			));
		}

		return false;
	}

	/**
	 *
	 * Set session messages
	 *
	 */
	public function setSessionMessage($message, $type = 'success') {
		Session::set("Page.message", $message);
		Session::set("Page.messageType", $type);
	}

	public function SessionMessage() {
		$Message = Session::get('Page.message');
		$Type = Session::get('Page.messageType');

		if ($Message) {
			return new ArrayData(compact('Message', 'Type'));
		}

		return false;
	}

	/**
	 *
	 * Clear all session messages
	 *
	 */

	public function ClearSessionMessage() {
		Session::clear('Page.message');
		Session::clear('Page.messageType');
	}

	/**
	 *
	 * Check profile status and redirect to edit pages
	 *
	 */
	public function profileStatusCheck() {
		$request = $this->getRequest();
		//check if user has logged in
		if (!$this->CurrentMember() || $request->isPost()) {
			return true;
		}

		$excludedPages = ['edit', 'settings'];
		$url = explode("/", $request->getVar('url'));
		if (isset($url[3]) && in_array($url[3], $excludedPages)) {
			//do not check profile if user is on edit pages
			return true;
		}

		//check if user has completed profile info
		/** @var \Member|SocialMember $member */
		$member = $this->CurrentMember();
		if (!$member->isProfileCompleted()) {
			//set flash message
			$this->setSessionMessage(_t("FlashMessage.Notice.CompleteProfile", "Please complete your profile"), "notice");
			//redirect here
			return $this->redirect("member/" . $member->ID . "/edit");
		}

		if (!$member->hasAddedInterests()) {
			//set flash message
			$this->setSessionMessage(_t("FlashMessage.Notice.CompleteSettings", "Please set your preferences"), "notice");
			//redirect here
			return $this->redirect("member/" . $member->ID . "/settings");
		}

		//check if user is an organisation admin and redirect if profile is incomplete
		if ($member->MemberCreatedOrganisation() != false) {
			/** @var Organisation $org */
			$org = $member->MemberCreatedOrganisation();
			if (!$org->isProfileCompleted()) {
				$this->setSessionMessage("Please complete your organisation's profile", "notice");
				return $this->redirect("organisation/" . $org->ID . "/edit");
			}
		}

		return true;
	}

}