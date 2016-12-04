<?php

/**
 * Extension adds actions post and unpost which create relationship between viewed object and logged in member.
 *
 * Provides PostableLink method to link to this objects post/unpost action.
 */
namespace Modular\Actions;

use \Modular\Extensions\Controller\SocialAction;

class Postable extends SocialAction {
	const ActionTypeCode = 'POS';
	const Action = 'post';

	private static $url_handlers = [
		'$ID/post' => 'post',
		'$ID/unpost' => 'unpost',
	];

	private static $allowed_actions = [
		'post' => '->canPost("action")',
		'unpost' => '->canPost("action")',
	];

	/**
	 * Returns a simple form with a text input and a 'post' button.
	 *
	 * @return SocialModelForm
	 */
	public function PostableForm() {
		if ($this->canPost()) {
			$fields = [];
			$fieldList = new FieldList(
				// new TextField('Title', _t('PostableWidget.TitleLabel', _t('PostableWidget.TitleLabel', 'Post something'))),
				TextareaField::create('Body', _t('PostableWidget.BodyLabel', 'Post something'))
					->setRows(1),
				$uploadField = FileAttachmentField::create('Images', _t('PostableWidget.ImageLabels', 'Attach some images'))
					->setView('grid')
					->setMultiple(true)
					->imagesOnly()
			);
			if (Member::currentUser()->MemberCreatedOrganisation()) {
				$fieldList->push(DropdownField::create("PostAs", _t('PostableWidget.PostAsLabel', 'Post As'))->setSource(array("Individual" => "My Personal Post", 'Organisation' => "My Organisation Post")));
			}

			$actionList = new FieldList(
				new FormAction('post', 'Post')
			);
			$validator = new RequiredFields('Body');

			$uploadField->setFolderName(Member::currentUser()->ActionLink(self::Action));

			$form = new SocialModelForm($this(), 'PostableForm', $fieldList, $actionList, $validator);

			// we want to post to the full url with id etc and action 'post'

			$modelClass = $this()->getModelClass();

			$form->setFormAction(
				$this()->join_links(
					'/',
					$modelClass::config()->get('route_part'),
					Member::currentUserID(),
					self::Action
				)
			);
			return $form;
		}
	}
	/**
	 * Return boolean to indicate if the action for this extension has been taken, or null if not the action
	 * that this extension deals with.
	 *
	 * @param $action
	 * @return bool|mixed
	 */
	public function actionTaken($action) {
		if ($action === self::ActionTypeCode) {
			return self::isPostd();
		}
	}
	/**
	 * Permissive for now. TODO: sort out permissions CAN_POST_xxx.
	 * @return bool
	 */
	public function canPost($source = null) {
		// return parent::canDoIt(self::ActionTypeCode, $source);
		return true;
	}

	/**
	 * Member posts this->owner object, add a relationship from Member with type self::$actionTypeCode
	 */
	public function post(SS_HTTPRequest $request) {
		if ($request->postVar('Body')) {
			$post = new Post(
				$request->postVars()
			);
			if ($post->write()) {

				// Post should have HasImagesExtension
				// $post->extend('attachUploadedImage', $request, $post);
				$this->attachUploadedImages($request, $post);

				SocialRelationship::make(
					Member::currentUser(),
					$post,
					'MCP'
				);
				if ($request->postVar('PostAs') == "Organisation") {
					SocialRelationship::make(
						Member::currentUser()->MemberCreatedOrganisation(),
						$post,
						'OCP'
					);
				}
			}

		} else {
			Session::setFormMessage('SocialModelForm_PostableForm', _t("PostableWidget.MissingTitleMessage", "Posts something"), "bad");
		}

		$this()->setSessionMessage("New post created successfully.");

		if (Application::device_mode() == 'mobile') {
			return $this()->redirect("/");
		}
		return $this()->redirectBack();
	}

	/**
	 * For uploads as a post we need to provide the form which handles them, in this case for a 'post' request.
	 * @param SS_HTTPRequest $request
	 * @param $mode
	 * @return SocialModelForm
	 */
	public function provideUploadFormForMode(SS_HTTPRequest $request, $mode) {
		if ($mode === self::Action) {
			return $this->PostableForm();
		}
	}

	/**
	 * Member unposts this->owner object, remove all self::$actionTypeCode relationships between them
	 * @param null $mmeberID
	 */
	public function unpost() {
		// parent::breakRelationship(self::ActionTypeCode);
		$post = Post::get()->byID($this->owner->request->param('ID'));
		if ($post && $post->canEdit()) {
			$post->delete();
		}
		$backUrl = $this->owner->request->getVar('backUrl');
		if ($backUrl) {
			return Controller::curr()->redirect($backUrl);
		}
		return Controller::curr()->redirect("/");
	}

	public function isPosted() {
		return parent::checkRelationship(self::ActionTypeCode);
	}
	/**
	 * Return a link appropriate for this object to be posted by logged in Member if can be posted.
	 * @param $action
	 * @return String
	 */
	public function PostableLink($action) {
		if ($this->canPost()) {
			return Controller::curr()->join_links(
				$this()->Link(),
				$action
			);
		}
	}

	/**
	 * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
	 * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
	 * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
	 *
	 * @param $modelClass
	 * @param $id
	 * @param $mode
	 *
	 * @return SocialModelInterface|null
	 */
	public function provideModel($modelClass, $id, $mode) {
		if ($mode === static::Action) {
			if ($id) {
				return DataObject::get($modelClass)->byID($id);
			}
		}
	}

	/**
	 * Handles ID's passed in by the ExtraImages extension and adds each ID posted to the passed in models Images
	 * relationship.
	 *
	 * NB: this should be somewhere else to do with ExtraImages
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $attachToModel
	 * @param bool           $removeExisting if true then existing attached images will be cleared first
	 */
	public function attachUploadedImages(SS_HTTPRequest $request, DataObject $attachToModel, $removeExisting = true) {
		$postVars = $request->postVars();
		$relationshipName = "Images";

		if ($removeExisting) {
			$attachToModel->$relationshipName()->removeAll();
		}

		if (isset($postVars[$relationshipName])) {

			foreach ($postVars[$relationshipName] as $fileArr => $fileID) {
				if ($fileID) {
					if ($file = Image::get()->byID($fileID)) {
						$attachToModel->$relationshipName()->add($file);
					}
				}
			}
		}
	}
}