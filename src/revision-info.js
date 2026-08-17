import { __ } from '@wordpress/i18n';
import { ToggleControl, ExternalLink } from '@wordpress/components';
import { PluginPostRevisionInfo } from '@wordpress/editor';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import { getSupports, getRestNamespace } from './supports';

function PublicToggle({ context }) {
	const revisionId = context?.revisionId;
	const postId = context?.postId;
	const [isSaving, setIsSaving] = useState(false);
	const [isPublic, setIsPublic] = useState(false);
	const [publicUrl, setPublicUrl] = useState('');

	useEffect(() => {
		setIsPublic(!!context?.revision?.meta?.is_revision_public);
	}, [context]);

	if (!revisionId || !context?.revision) {
		return null;
	}

	async function handleToggle(next) {
		setIsSaving(true);
		try {
			const result = await apiFetch({
				path: `/${getRestNamespace()}/public-revisions/${postId}/${revisionId}`,
				method: 'POST',
				data: { public: next },
			});
			setIsPublic(!!result.public);
			setPublicUrl(result.url || '');
		} finally {
			setIsSaving(false);
		}
	}

	return (
		<div>
			<ToggleControl
				__nextHasNoMarginBottom
				label={__('Public', 'wp-feature-future-revisions')}
				checked={!!isPublic}
				disabled={isSaving}
				onChange={handleToggle}
			/>
			{isPublic && publicUrl ? (
				<ExternalLink href={publicUrl}>
					{__('View public revision', 'wp-feature-future-revisions')}
				</ExternalLink>
			) : null}
		</div>
	);
}

export default function RevisionInfoFill() {
	if (typeof PluginPostRevisionInfo !== 'function') {
		return null;
	}
	if (!getSupports().publicRevisions) {
		return null;
	}
	return (
		<PluginPostRevisionInfo>
			{({ context } = {}) => <PublicToggle context={context} />}
		</PluginPostRevisionInfo>
	);
}
