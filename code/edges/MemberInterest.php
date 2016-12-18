<?php
namespace Modular\Edges;

/**
 * SocialRelationship between a Member and an SocialInterestType.
 */
class MemberInterest extends SocialRelationship {
	const NodeAClassName = 'Member';
	const NodeBClassName   = 'Modular\Types\Social\InterestType';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToInterest';
}