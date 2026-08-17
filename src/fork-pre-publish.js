import { useSelect } from '@wordpress/data';
import { PluginPrePublishPanel, store as editorStore } from '@wordpress/editor';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import { getSupports, getRestNamespace } from './supports';

export default function ForkPrePublishPanel() {
	const { postId, parentId } = useSelect((selectStore) => {
		const meta =
			selectStore(editorStore).getEditedPostAttribute('meta') || {};
		return {
			postId: selectStore(editorStore).getCurrentPostId(),
			parentId: meta._future_revision_of || 0,
		};
	}, []);
	const [forkInfo, setForkInfo] = useState(null);

	useEffect(() => {
		if (!postId || !parentId || !getSupports().futureRevisions) {
			return;
		}
		apiFetch({ path: `/${getRestNamespace()}/forks?post=${postId}` })
			.then(setForkInfo)
			.catch(() => setForkInfo(null));
	}, [postId, parentId]);

	if (
		!getSupports().futureRevisions ||
		!parentId ||
		forkInfo?.role !== 'fork'
	) {
		return null;
	}

	return (
		<PluginPrePublishPanel
			title={__('Future revision', 'wp-feature-future-revisions')}
			initialOpen
		>
			<Notice status="warning" isDismissible={false}>
				<p>
					{__(
						'Publishing will merge into the original post:',
						'wp-feature-future-revisions'
					)}{' '}
					<a href={forkInfo.parent_edit_url}>
						{forkInfo.parent_title}
					</a>
				</p>
			</Notice>
		</PluginPrePublishPanel>
	);
}
