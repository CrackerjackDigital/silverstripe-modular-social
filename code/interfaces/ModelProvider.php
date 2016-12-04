<?php
namespace Modular\Interfaces;

interface SocialModelProvider {
    /**
     * Provider the model $modelClass for a particular mode. Generally the passed in mode is compared to an internal
     * mode and if they match then a model will be returned, otherwise null. This method is called as an extend so
     * multiple extensions can provide models, however only one should 'win' when it's mode matches the passed mode.
     *
     * @param $modelClass
     * @param $id
     * @param $mode
     *
     * @return SocialModel|null
     */
    public function provideModel($modelClass, $id, $mode);
}