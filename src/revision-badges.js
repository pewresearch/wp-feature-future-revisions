import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';

import { getSupports } from './supports';

addFilter(
	'editor.PostRevision.badges',
	'wp-future-revisions/badges',
	(badges) => {
		const supports = getSupports();
		const next = [...badges];
		if (supports.publicRevisions) {
			next.push({
				id: 'wp-future-revisions/public',
				label: __('Public', 'wp-feature-future-revisions'),
				intent: 'informational',
				isMatch: (item) => !!item.meta?.is_revision_public,
			});
		}
		if (supports.futureRevisions) {
			next.push({
				id: 'wp-future-revisions/merged',
				label: __('Merged', 'wp-feature-future-revisions'),
				intent: 'stable',
				isMatch: (item) => !!item.meta?.is_revision_merged,
			});
		}
		return next;
	}
);
