import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import apiFetch from '@wordpress/api-fetch';

import { getSupports, getRestNamespace } from './supports';

const FORK_NOTICE_ID = 'wfr-fork-notice';

export default function ForkEditorNotice() {
	const { postId, parentId } = useSelect((selectStore) => {
		const meta =
			selectStore(editorStore).getEditedPostAttribute('meta') || {};
		return {
			postId: selectStore(editorStore).getCurrentPostId(),
			parentId: meta._future_revision_of || 0,
		};
	}, []);
	const { createWarningNotice, removeNotice } = useDispatch('core/notices');
	const [forkInfo, setForkInfo] = useState(null);

	useEffect(() => {
		if (!postId || !parentId || !getSupports().futureRevisions) {
			return;
		}
		apiFetch({ path: `/${getRestNamespace()}/forks?post=${postId}` })
			.then(setForkInfo)
			.catch(() => setForkInfo({ role: 'none' }));
	}, [postId, parentId]);

	useEffect(() => {
		if (!parentId || forkInfo?.role !== 'fork') {
			removeNotice(FORK_NOTICE_ID);
			return;
		}
		createWarningNotice(
			sprintf(
				/* translators: %s: parent post title */
				__(
					'This is a future revision of "%s"',
					'wp-feature-future-revisions'
				),
				forkInfo.parent_title
			),
			{
				id: FORK_NOTICE_ID,
				isDismissible: false,
				actions: [
					{
						label: __(
							'View original post',
							'wp-feature-future-revisions'
						),
						url: forkInfo.parent_edit_url,
					},
				],
			}
		);
		return () => removeNotice(FORK_NOTICE_ID);
	}, [parentId, forkInfo, createWarningNotice, removeNotice]);

	return null;
}
