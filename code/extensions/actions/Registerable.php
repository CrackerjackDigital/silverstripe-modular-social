<?php

/**
 * Extend the Creatable extension with functionality specific for the registration process, such as templates,
 * logging in member etc.
 *
 */
namespace Modular\Actions;

use Controller;
use Convert;
use DataObject;
use EmailNotifier;
use FieldList;
use Form;
use FormAction;
use Member;
use Modular\Edges\MemberMember;
use Modular\Edges\MemberOrganisation;
use Modular\Forms\InitSignUpForm;
use Modular\Interfaces\ModelWriteHandlers;
use Modular\Models\Social\Organisation;
use Modular\Types\Social\OrganisationSubType;
use Modular\UI\Components\Social\OrganisationChooser;
use Permission;
use Requirements;
use Session;
use SS_HTTPRequest;
use ValidationException;

class Registerable extends Createable implements ModelWriteHandlers {
	const ActionCode = 'REG';
	const Action             = 'register';
	const HasRegisteredFlag  = 'HasRegisteredFlag';
	const ThanksURLSegment   = 'thanks';
	const SessionTempVarName = 'RegisteringMemberID';

	private static $default_member_security_group_code = 'social-default';

	private static $url_handlers = [
		'signup'               => 'signup',
		self::Action           => self::Action,
		self::ThanksURLSegment => self::ThanksURLSegment,
	];

	private static $allowed_actions = [
		'signup'               => '->canRegister("action")',
		'register'             => '->canRegister("action")',
		self::ThanksURLSegment => true,
	];

	private static $action_templates = [
		self::Action           => self::Action,
		self::ThanksURLSegment => self::Action,
	];

	private static $action_modes = [
		self::Action           => self::Action,
		self::ThanksURLSegment => self::Action,
	];

	/**
	 * Check if registration is allowed for Guest Member permissions to the extended model class, unless the current extended model
	 * is 'Member' in which case you can always register.
	 *
	 * @return bool|int
	 */
	public function canRegister($source = null) {
		$modelClass = $this()->getModelClass();
		return ($modelClass == 'Member') || parent::canDoIt(self::ActionCode, $source);
	}

	public function onBeforeInit() {
		// TODO custom action javascript should be split into an action directory?
		Requirements::block(THIRDPARTY_DIR . '/jquery/jquery.js');
	}

	/**
	 * Co-opt CreateForm but
	 *
	 * @return Form
	 */
	public function CreateForm() {
		$organisationName = Session::get(InitSignUpForm::transient_key(InitSignUpForm::OrganisationFieldName));

		return parent::CreateForm()->loadDataFrom([
			'Email'                  => Session::get(InitSignUpForm::transient_key(InitSignUpForm::EmailFieldName)),
			'MbieRegistrationNumber' => $organisationName,
		]);
	}

	/**
	 * @param SS_HTTPRequest $request
	 * @return bool|\SS_HTTPResponse
	 */
	public function signup($request = null) {
		$data = $request->postVars();

		//Check for existing member email address
		if ($member = DataObject::get_one("Member", "`Email` = '" . Convert::raw2sql($data[ InitSignUpForm::EmailFieldName ]) . "'")) {
			//Set form data from submitted values
			Session::set("FormInfo.InitSignupForm.data", $data);

			//Set error message
			Session::setFormMessage('InitSignupForm', "Sorry, that email address already exists. \nPlease choose another.", 'bad');
			//Return back to form
			return $this()->redirectBack();
		}
		// save info into session for Registerable extension to pick up
		Session::set(InitSignUpForm::transient_key(InitSignUpForm::EmailFieldName), $data[ InitSignUpForm::EmailFieldName ]);
		Session::set(InitSignUpForm::transient_key(InitSignUpForm::OrganisationFieldName), $data[ InitSignUpForm::OrganisationFieldName ]);

		return $this()->redirect("member/register");
	}

	/**
	 * Handles the 'register' action, may be GET or POST. Just calls through to the Creatable
	 * 'action' method which does the extending and response gathering.
	 */
	public function register(SS_HTTPRequest $request) {
		Requirements::javascript('https://www.google.com/recaptcha/api.js');
		Requirements::javascript('themes/default/js/nzfin-register.js');
		Requirements::block('themes/default/js/nzfin.js');
		$mode = self::Action;
		$model = $this()->getModelInstance(self::Action);
		$method = $request->httpMethod();

		// let extensions do their thing and then call back to this controller for final outcome.
		if ($method === 'POST') {
			$responses = array_merge(
				$this()->extend('afterRegister', $request, $model, $mode),
//              [$this()->afterRegister($request, $model, $mode)]
				[]
			);
		} else {
			$responses = array_merge(
				$this()->extend('beforeRegister', $request, $model, $mode),
//              [$this()->beforeRegister($request, $model, $mode)]
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
	 * Renders the self::ThanksURLSegment template with 'HasRegisteredFlag' set.
	 *
	 * @param SS_HTTPRequest $request
	 * @return mixed
	 */
	public function thanks(SS_HTTPRequest $request) {
		Requirements::block('themes/default/js/nzfin.js');
		return $this()->renderTemplates(
			self::Action,
			[
				self::HasRegisteredFlag => self::HasRegisteredFlag,
			]
		);
	}

	/**
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $model
	 * @param                $mode
	 * @param array          $fieldsHandled
	 * @throws \ValidationException
	 */
	public function beforeModelWrite(SS_HTTPRequest $request, DataObject $model, $mode, &$fieldsHandled = []) {
		if ($mode == "new" || $mode == "register") {
			if ($model instanceof Member) {
				if (!($request->postVar(OrganisationChooser::CreateNewFieldName)
					|| $request->postVar(OrganisationChooser::IDFieldName))
				) {

					throw new ValidationException("Please choose an already registered SocialOrganisation or create a new one");
				}
				if (!$request->postVar('MembershipTypeID')) {
					throw new ValidationException("Please choose a membership type");
				}
			}
		}

	}

	public function afterModelWrite(SS_HTTPRequest $request, DataObject $model, $mode) {
		// TODO: Implement afterModelWrite() method.
	}

	/**
	 * Returns the 'GET' template after logging out the current user if logged in.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $model
	 * @param string         $mode
	 * @return mixed
	 */
	public function beforeRegister(SS_HTTPRequest $request, DataObject $model, $mode) {
		if (Controller::curr()->URLSegment == "Organisation_" && Member::currentUser()) {
			$memberOrgAvailable = Member::currentUser()->MemberOrganisation();
			if ($memberOrgAvailable) {
				Member::currentUser()->logOut();
			}
		} else {
			if (Member::currentUser()) {
				Member::currentUser()->logOut();
			}
		}

		return $this()->renderTemplates($mode);
	}

	/**
	 * Process a 'POST' request to @endpoint /model/register
	 *
	 * If we are registering Member
	 * - Add member to social-default group if ApprovalMode is automatic
	 * - Check for 'create new organisation' flag
	 *      - if set go to '/organisation/register'
	 *      - otherwise go to self::ThanksURLSegment
	 *
	 *
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $model
	 * @param string         $mode
	 * @return SS_HTTPResponse
	 */
	public function afterRegister(SS_HTTPRequest $request, DataObject $model, $mode) {
		// co-opt Createable functionality to validate and write model etc
		$result = parent::afterCreate($request, $model, $mode);

		// create may have returned something, if not then do custom registration handling.
		if (!$result) {
			if (!$model) {
				$this()->httpError(403);
			}
			$relationshipTypes = null;

			/** @var Member|Confirmable $model */
			if ($model instanceof Member) {
				$member = $model;
				$member->write();

				Session::set(self::SessionTempVarName, $member->ID);
				// send confirmation email for the member to the member
				$member->sendConfirmationLinkEmail($member);

				$member->addToGroupByCode($this->config()->get('default_member_security_group_code'));

				Permission::flush_permission_cache();

				Confirmable::disable();
				Approveable::disable();

				MemberMember::make($member, $member, 'REG');

				Approveable::enable();
				Confirmable::enable();

				// if member was registering and they want to create a new organisation then redirect to organisation register
				if ($request->postVar(OrganisationChooser::CreateNewFieldName)) {

					// redirect to the register link on the model.
					$result = $this()->redirect(singleton('SocialOrganisation')->ActionLink('register'));

				} else {

					$result = $this()->redirect($member->ActionLink(self::ThanksURLSegment, false));

				}
			}
			// member should be registered by now as this if after model written.
			if (Member::currentUser()) {
				$ActiveMember = Member::currentUser()->ID;
			} else {
				$ActiveMember = Session::get(self::SessionTempVarName);
			}

			if (!$member = Member::get()->byID($ActiveMember)) {
				$this()->httpError(400,
					_t(
						"Global.SessionTimeoutMessage",
						'Sorry, your session has expired, please <a href="{link}">{action}</a>{afterAction}',
						[
							'link'        => singleton('Member')->ActionLink(self::Action),
							'action'      => 'restart the registration process',
							'afterAction' => '.',
						]
					)
				);
			}

			// handle what happens after SocialOrganisation registration
			if ($model instanceof SocialOrganisation) {
				/** @var Organisation $organisation */
				$organisation = $model;

				if ($request->postVar(OrganisationSubType::IDFieldName)) {
					$subTypes = $request->postVar(OrganisationSubType::IDFieldName);
					if (count($subTypes)) {
						for ($i = 0; $i < count($subTypes); $i++) {
							$organisation->OrganisationSubTypes()->add($subTypes[ $i ]);
						}
					}
				}
				Approveable::disable();
				MemberOrganisation::make($member, $organisation, 'REG');
				Approveable::enable();
				if (!$member = Member::currentUser()) {
					if ($member = Member::get()->byID(Session::get(self::SessionTempVarName))) {
						static::send_organisation_notification($member);
					}
				}
				$result = $this()->redirect($member->ActionLink(self::ThanksURLSegment, false));
			}

			Session::clear('FormInfo');
			Session::clear(InitSignUpForm::transient_key('InitSignUpForm'));

		}
		return $result;
	}

	/**
	 * Adds an OrganisationSubType to form, we can't just put this in the fields_for_mode['register'] collection as
	 * then the stupid 'changeFieldOrder' method looses it because CompositeField doesn't track its name.
	 *
	 * @param DataObject $model
	 * @param FieldList  $fields
	 * @param            $mode
	 * @param array      $requiredFields
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, &$requiredFields = []) {
		if ($mode === Registerable::Action) {
			list($fieldName, $fieldLabel) = OrganisationSubType::get_field_config();

			if (!$chooserField = $fields->dataFieldByName($fieldName)) {
				$chooserField = new OrganisationSubType();
				$chooserField->setAttribute('placeholder', $fieldLabel);
				$fields->push($chooserField);

				// make it required
				$requiredFields[ $fieldName ] = $fieldName;
			}

			$chooserField->setValue($model->OrganisationTypeID);
		}
	}

	/**
	 * Adds FormActions for this mode.
	 *
	 * @param DataObject $model
	 * @param FieldList  $actions
	 * @param string     $mode
	 */
	public function updateActionsForMode($model, $actions, $mode) {
		if ($mode === $this->action()) {
			if ($this->canRegister()) {
				$actions->push(FormAction::create('register', 'SUBMIT')->addExtraClass("submit"));
			}
		}
	}

	/**
	 *
	 * Send organisation registration notification
	 *
	 */
	public static function send_organisation_notification($member) {
		$notifier = EmailNotifier::create();
		$notifier->setEmailTemplate('Welcome_OrgAdmin');
		$notifier->setEmailSubject("Welcome to NZ Food Portal");
		$notifier->setRecipients($member);
		$notifier->setMessage("Welcome Message");
		$notifier->setEmailTemplateData(["Member" => $member]);
		$notifier->send();
	}

}