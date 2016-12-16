<?php
namespace Modular\Edges;

/**
 * SocialEdgeType record between an organisation and an organisation.
 */
class OrganisationOrganisation extends SocialRelationship {
	const FromModelClass = 'Modular\Models\Social\Organisation';
	const ToModelClass = 'Modular\Models\Social\Organisation';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'FromModel';

}