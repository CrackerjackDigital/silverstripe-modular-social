<?php
use Modular\Actions\Uploadable;
use Modular\Relationships\Social\HasImage;

/**
 * Add Logo functionality to a Model
 */
class HasLogo extends HasImage {
	const Action           = 'uploadlogo';
	const ActionCode       = Uploadable::ActionCode;
	const RelationshipName = 'Logo';
	const FieldName        = 'LogoID';

	private static $url_handlers = [
		'$ID/uploadlogo' => 'uploadLogo',
	];
	private static $allowed_actions = [
		'uploadLogo' => '->canUploadLogo',
	];

	private static $has_one = [
		self::RelationshipName => 'Image',
	];

	/**
	 * TODO: Implement proper permission check (at moment calling canDoIt results in controller being passed as $toModel)
	 *
	 * @param null $member
	 * @return bool
	 */
	public function canUploadLogo($member = null) {
		return true;
//        return parent::canDoIt(Uploadable::ActionCode);
	}

	protected function UploadForm() {
		return $this->HasLogoForm();
	}

	/**
	 *
	 * If the current model as viewed by the controller has an ID then
	 * returns a configured HasLogoForm
	 *
	 * @return HasLogoForm
	 **/
	public function HasLogoForm() {
		$id = $this()->getModelID();
		return HasLogoForm::create($this(), __FUNCTION__, $id);
	}

	/**
	 * Provide the LogoForm to the
	 *
	 * @param SS_HTTPRequest $request
	 * @param                $mode
	 * @return mixed
	 */
	public function provideUploadFormForMode(SS_HTTPRequest $request, $mode) {
		if ($mode === static::Action) {
			return $this->UploadForm();
		}
	}

	public function uploadLogo(SS_HTTPRequest $request) {
		$modelClassName = $this()->getModelClass();

		$model = $modelClassName::get()->byID($request->postVar('ID'));
		$model->{self::FieldName} = $request->postVar(self::FieldName);
		$model->write();

		return $this()->redirectBack();
	}

}