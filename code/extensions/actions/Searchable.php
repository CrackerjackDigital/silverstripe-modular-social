<?php
namespace Modular\Actions;

/**
 * Extension to add a search action on a controller, uses the controller in combination with
 * models 'search' mode fields to get what to search on. If a model ID is provided then only
 * that model will show in results and plumbing is 'automatic'.
 */

use FieldList;
use FormAction;
use HiddenField;
use SS_HTTPRequest;
use Modular\Models\SocialModel as SocialModel;
use Modular\Extensions\Controller\SocialAction;

class Searchable extends SocialAction  {
    const Action = 'search';
    const WhatFieldName = 'SearcheableWhat';
    const ModelClassAttributeName = 'data-model';
    const RelationshipName = 'SCH';

    private static $url_handlers = [
        self::Action => self::Action
    ];
    private static $allowed_actions = [
        self::Action => '->canSearch("action")'
    ];

    public function canSearch($source = null) {
        return parent::canDoIt(self::RelationshipName);
    }


    public function search(SS_HTTPRequest $request) {
        return $this()->renderTemplates(self::Action);
    }
    /**
     * Return a form with fields which are obtained from other extensions (e.g. HasOrganisations) depending on what
     * model names are passed in from template as a csv.
     *
     * @param string $csvWhat - models to search for
     * @return Form
     */
    public function SearchForm($csvWhat) {
        $searchFields = $this->getSearchFields($csvWhat);

        $searchFields->push(
            new HiddenField(self::WhatFieldName, '', $csvWhat)
        );

        $actions = new FieldList([
            new FormAction('Search', self::Action)
        ]);

        $form = new SocialForm($this(), 'SearchForm', $searchFields, $actions);


        $form->setFormAction($this()->getRequest()->getUrl());

        return $form;
    }

    protected function getSearchValues($csvWhat) {
        $postVars = $this()->getRequest()->postVars();

        $searchFields = $this->getSearchFields($csvWhat);

        $values = [];
        foreach ($postVars as $name => $value) {
            if (!empty($value)) {

                // look for top level fields which match the name
                /** @var FormField $searchField */
                if ($searchField = $searchFields->fieldByName($name)) {
                    $modelName = SocialModel::get_field_model_class($searchField);

                    $values[$modelName][$name] = $value;
                }
                // now deal with composite fields
                /** $var FormField $field */
                foreach ($searchFields as $searchField) {

                    if ($searchField->isComposite()) {
                        // use the model namespace Modular\Actions;

                        $modelName = SocialModel::get_field_model_class($searchField);

                        if ($searchField->fieldByName($name)) {
                            $modelName = $modelName ?: SocialModel::get_field_model_class($searchField);

                            $values[$modelName][$name] = $value;
                        }
                    }
                }
            }
        }
        return $values;
    }

    /**
     * Used by SocialModel_list to show the search results.
     * @return ArrayData
     */
    public function SearchView() {
        $results = new ArrayList();
        $request = $this()->getRequest();

        if ($csvWhat = $request->postVar(self::WhatFieldName)) {

            $searchValues = $this->getSearchValues($csvWhat);

            $modelNames = explode(',', $csvWhat);

            foreach ($modelNames as $modelName) {
                if (ClassInfo::exists($modelName)) {

                    // search values are mapped with key as modelName
                    if (isset($searchValues[$modelName])) {
                        $results->merge(
                            $modelName::get()->filter($searchValues[$modelName])
                        );
                    }
                }
            }
        }
        $numResults = $results->count();

        // SocialModel_list expects to get a 'ListView' so wrap our results in one.
        return new ArrayData(
            [
                'ListView' => $numResults
                        ? [
                            'Title' => _t('Searchable.ResultsCountMessage', '{count} item(s) found', [
                                'count' => $numResults
                             ]),
                            'ListItems' => $results
                        ]
                        : [
                            'Title' => _t('Searchable.NoResultsFoundMessage', 'No search results found')
                        ]
            ]
        );

    }

    protected function getSearchFields($csvWhat) {
        $searchFields = new FieldList();
        $csvWhat = is_array($csvWhat) ? $csvWhat : explode(',', $csvWhat);

        $this()->getModelInstance(self::Action)->extend('updateSearchFields', $searchFields, $csvWhat);

        return $searchFields;
    }

}