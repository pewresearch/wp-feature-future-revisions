import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import {
	store as editorStore,
	PluginPostRevisionHeader,
} from '@wordpress/editor';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import { getSupports, getRestNamespace } from './supports';

function CreateForkButton({ context }) {
	const [isCreating, setIsCreating] = useState(false);
	const [error, setError] = useState(null);
	const postStatus = useSelect(
		(select) => select(editorStore).getEditedPostAttribute('status'),
		[]
	);

	if (postStatus !== 'publish') {
		return null;
	}

	async function handleCreate() {
		setIsCreating(true);
		setError(null);
		try {
			const result = await apiFetch({
				path: `/${getRestNamespace()}/forks`,
				method: 'POST',
				data: { post: context.postId },
			});
			if (result.edit_url) {
				window.location.href = result.edit_url;
			}
		} catch (err) {
			setError(err.message);
			setIsCreating(false);
		}
	}

	return (
		<div style={{ display: 'contents' }}>
			<Button
				variant="secondary"
				size="compact"
				onClick={handleCreate}
				isBusy={isCreating}
				disabled={isCreating}
			>
				{isCreating
					? __('Creating…', 'wp-feature-future-revisions')
					: __(
							'Create Future Revision',
							'wp-feature-future-revisions'
						)}
			</Button>
			{error ? <span>{error}</span> : null}
		</div>
	);
}

export default function RevisionHeaderFill() {
	if (typeof PluginPostRevisionHeader !== 'function') {
		return null;
	}
	if (!getSupports().futureRevisions) {
		return null;
	}
	return (
		<PluginPostRevisionHeader>
			{(fillContext) => <CreateForkButton context={fillContext} />}
		</PluginPostRevisionHeader>
	);
}
