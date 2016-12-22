<?php
namespace Modular\Relationships\Social;

use Modular\Actions\Uploadable;
use Modular\Forms\Social\HasProfilePictureForm;
use SS_HTTPRequest;

/**
 * Add ProfileImage functionality to a Model
 */
class HasProfileImage extends HasImage {
    const ActionName = 'uploadProfileImage';
    const ActionCode = Uploadable::ActionCode;
    const RelationshipName = 'ProfileImage';
    const FieldName = 'ProfileImageID';

    private static $url_handlers = [
        '$ID/uploadProfileImage' => 'uploadProfileImage',
    ];
    private static $allowed_actions = [
        'uploadProfileImage' => '->canUploadProfileImage'
    ];

    private static $has_one = [
        self::RelationshipName => 'Image'
    ];

    /**
     * TODO: Implement proper permission check (at moment calling canDoIt results in controller being passed as $toModel)
     * @param null $member
     * @return bool
     */
    public function canUploadProfileImage($member = null) {
        return true;
    }

    protected function UploadForm() {
        return $this->HasProfileImageForm();
    }

    /**
     *
     * If the current model as viewed by the controller has an ID then
     * returns a configured HasProfileImageForm
     *
     * @return HasProfilePictureForm
     **/
    public function HasProfileImageForm() {
        $id = $this()->getModelID();
        return HasProfilePictureForm::create($this(), __FUNCTION__, $id);
    }

    public function uploadProfileImage(SS_HTTPRequest $request) {
        $modelClassName = $this()->getModelClass();

        $model = $modelClassName::get()->byID($request->postVar('ID'));
        $model->{self::FieldName} = $request->postVar(self::FieldName);
        $model->write();

        return $this()->redirectBack();
    }


}