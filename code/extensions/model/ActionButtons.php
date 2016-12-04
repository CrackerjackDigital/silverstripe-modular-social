<?php
namespace Modular\Extensions\Controller;

/**
 * Extends Models to add actions specific to that model, such as view, like etc.
 */
class ActionButtons extends ActionMenu {
    const MenuClass = 'action-buttons';
    const FilterField = 'ShowInActionButtons';

    /**
     * Return a rendered ActionMenu component with actions allowed the logged in member to the extended class.
     * @param string $restrictTo - csv list of actions we wan't to limit to
     * @return ArrayData
     */
    public function ActionButtons($restrictTo = null) {
        $model = $this()->getModelInstance(null);
        return self::action_links($model, $restrictTo);
    }

}