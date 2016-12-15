<?php
namespace Modular\Relationships\Social;

use DataObject;
use FieldList;
use File;
use SS_HTTPRequest;
use SS_List;
use UploadField;

/**
 * Adds a has_many from extended class to Files.
 *
 * NB: this gets added to both Model and Controller so derives from ModelExtension to capture both sets of functionality.
 *
 */
class HasFiles extends HasManyMany {
	const RelationshipName = 'Files';

	private static $many_many = [
		self::RelationshipName => 'File',
	];

	/**
	 * Adds an UploadField to the Root.Files tab.
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab('Root.Files', UploadField::create(
			self::RelationshipName,
			'',
			$this()->OrderedFiles()
		));
	}

	/**
	 * Returns Files related to the extended object.
	 * @return SS_List
	 */
	public function OrderedFiles() {
		// NB we could add sorting here if we've installed Sortable e.g. GridFieldSortableRows or such.
		return $this()->getManyManyComponents(
			self::RelationshipName
		);
	}

	/**
	 * Handles ID's passed in by the ExtraFiles extension and adds each ID posted to the passed in models Files
	 * action.
	 *
	 * NB: this should be somewhere else to do with ExtraFiles
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject     $attachToModel
	 * @param bool           $removeExisting if true then existing attached images will be cleared first
	 */
	public function attachUploadedFiles(SS_HTTPRequest $request, DataObject $attachToModel, $removeExisting = true) {
		$postVars = $request->postVars();
		$relationshipName = static::RelationshipName;

		if ($removeExisting) {
			$attachToModel->$relationshipName()->removeAll();
		}

		if (isset($postVars[$relationshipName])) {
			foreach ($postVars[$relationshipName] as $fileArr => $fileID) {
				if ($fileID) {
					if ($file = File::get()->byID($fileID)) {
						$attachToModel->$relationshipName()->add($file);
					}
				}
			}
		}
	}

}