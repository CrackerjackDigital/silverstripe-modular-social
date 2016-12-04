<?php
namespace Modular\Interfaces;
/**
 * Interface SocialModelControllerInterface
 */
interface SocialModelController extends SocialModel {

    /**
     * Returns templates to use for actions, mapping between an action and a template via config.action_templates
     * so templates can be re-used between actions, templates can have more meaningful names and actions without
     * templates filtered out.
     *
     * @param string $mode - new, view, edit
     * @return array templates to use in render call
     */
    public function getTemplates($mode);

    /**
     * Return action fields for the current model and mode.
     *
     * Calls extend.getFieldsForMode so extensions can add their own fields/required fields.
     *
     * @param string $mode
     * @return array of [array fields, array requiredfield]
     */
    public function getFieldsForMode($mode);

    /**
     * Return name of a template for a given action.
     */
    public function getTemplateName($action);


    /**
     * Return an href for an action/mode for the current model.
     * @param $action
     * @param bool $includeID - set to false to prevent the ID from being added (e.g in case of '/member/thanks')
     * @return mixed
     */
    public function ActionLink($action, $includeID = true);

    /**
     * Return <ModelClass>_Form as form name to use in templates for this class.
     *
     * @return string
     */
    public function getFormName();

    /**
     * Figure out what the current mode ('view', 'edit', 'post') is depending on extensions and return it
     * @return mixed
     */
    public function getMode();

	/**
	 * @param string $mode e.g. 'edit' a controller could provide a different model depending on mode.
	 * @return SocialModel|null
	 */
	public function getModelInstance($mode = '');

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
    public function getActionsForMode($mode);

    /**
     * Return a form for the model used for each mode.
     *
     * @param $mode
     * @return SocialModelForm
     */
    public function formForModel($mode);

}