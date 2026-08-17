import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { PluginPreviewMenuItem, store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { getSupports, getRestNamespace } from './supports';

export default function ForkPreviewMenuItem() {
	const { postId } = useSelect(
		(selectStore) => ({
			postId: selectStore(editorStore).getCurrentPostId(),
		}),
		[]
	);
	const [forkInfo, setForkInfo] = useState(null);

	useEffect(() => {
		if (!postId || !getSupports().futureRevisions) {
			return;
		}
		apiFetch({ path: `/${getRestNamespace()}/forks?post=${postId}` })
			.then(setForkInfo)
			.catch(() => setForkInfo(null));
	}, [postId]);

	if (!getSupports().futureRevisions) {
		return null;
	}
	if (!forkInfo || forkInfo.role !== 'parent' || !forkInfo.fork_id) {
		return null;
	}
	if (typeof PluginPreviewMenuItem !== 'function') {
		return null;
	}

	const previewUrl = `/?p=${forkInfo.fork_id}&preview=true`;

	return (
		<PluginPreviewMenuItem
			onClick={() => window.open(previewUrl, '_blank')}
		>
			{__('Preview future revision', 'wp-feature-future-revisions')}
		</PluginPreviewMenuItem>
	);
}
