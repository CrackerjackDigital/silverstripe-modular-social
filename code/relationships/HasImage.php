<?php
namespace Modular\Relationships\Social;

use HasImageForm;
use Modular\Extensions\Model\SocialModel;
use SS_HTTPRequest;

/**
 * Add Image functionality to a Model TODO complete from e.g. HasLogo or HasCoverImage
 */
class HasImage extends SocialModel  {
    const Action = '';
    const ActionCode = '';
    const RelationshipName = '';
    const FieldName = '';

    private static $has_one = [
        self::RelationshipName => 'Image'
    ];

    /**
     * TODO: Implement proper permission check (at moment calling canDoIt results in controller being passed as $toModel)
     * @param null $member
     * @return bool
     */
    public function canUploadLogo($member = null) {
        return true;
    }


    protected function UploadForm() {
        return $this->HasImageForm();
    }

    /**
     *
     * If the current model as viewed by the controller has an ID then
     * returns a configured HasLogoForm
     *
     * @return HasImageForm
     **/
    public function HasImageForm() {
        // ID can be null e.g. if uploading via a FileAttachmentField
        $id = $this()->getModelID();
        return HasImageForm::create($this(), __FUNCTION__, $id);
    }

    /**
     * Provide the LogoForm to the
     * @param SS_HTTPRequest $request
     * @param $mode
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