<?php
namespace Modular\Edges;

/**
 * SocialEdgeType record between an organisation and an organisation.
 */
class OrganisationOrganisation extends SocialRelationship {
	const NodeAClassName = 'Modular\Models\Social\Organisation';
	const NodeBClassName = 'Modular\Models\Social\Organisation';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'FromModel';

}