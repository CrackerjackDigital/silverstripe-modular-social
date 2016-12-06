<?php
namespace Modular\Interfaces;

use DataObject;
use SS_HTTPRequest;

interface ModelWriteHandlers {
    public function beforeModelWrite(SS_HTTPRequest $request, DataObject $model, $mode, &$fieldsHandled = []);
    public function afterModelWrite(SS_HTTPRequest $request, DataObject $model, $mode);

}