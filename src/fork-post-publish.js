import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	PluginPostPublishPanel,
	store as editorStore,
} from '@wordpress/editor';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getSupports } from './supports';

export default function ForkPostPublishRedirect() {
	const { parentId, parentEditUrl, postStatus } = useSelect((selectStore) => {
		const meta =
			selectStore(editorStore).getEditedPostAttribute('meta') || {};
		const parent = meta._future_revision_of || 0;
		return {
			parentId: parent,
			parentEditUrl: parent
				? `/wp-admin/post.php?post=${parent}&action=edit`
				: '',
			postStatus:
				selectStore(editorStore).getEditedPostAttribute('status'),
		};
	}, []);

	useEffect(() => {
		if (parentId && postStatus === 'publish' && parentEditUrl) {
			const timer = setTimeout(() => {
				window.location.href = parentEditUrl;
			}, 3000);
			return () => clearTimeout(timer);
		}
	}, [parentId, postStatus, parentEditUrl]);

	if (!getSupports().futureRevisions || !parentId) {
		return null;
	}

	return (
		<PluginPostPublishPanel
			title={__('Fork merged', 'wp-feature-future-revisions')}
			initialOpen
		>
			<p>
				{__(
					'This fork has been merged into the original post and will be trashed.',
					'wp-feature-future-revisions'
				)}
			</p>
			<Button variant="primary" href={parentEditUrl}>
				{__('Go to parent post now', 'wp-feature-future-revisions')}
			</Button>
		</PluginPostPublishPanel>
	);
}
