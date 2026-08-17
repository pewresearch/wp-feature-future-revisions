import { registerPlugin } from '@wordpress/plugins';

import './revision-badges';
import RevisionInfoFill from './revision-info';
import RevisionHeaderFill from './revision-header';
import ForkPanel from './fork-panel';
import ForkEditorNotice from './fork-notice';
import ForkPreviewMenuItem from './fork-preview';
import ForkPrePublishPanel from './fork-pre-publish';
import ForkPostPublishRedirect from './fork-post-publish';
import { getSupports } from './supports';

registerPlugin('wp-future-revisions-info', {
	render: RevisionInfoFill,
});

registerPlugin('wp-future-revisions-header', {
	render: RevisionHeaderFill,
});

if (getSupports().futureRevisions) {
	registerPlugin('wp-future-revisions-fork-panel', {
		render: ForkPanel,
	});
	registerPlugin('wp-future-revisions-fork-notice', {
		render: ForkEditorNotice,
	});
	registerPlugin('wp-future-revisions-fork-preview', {
		render: ForkPreviewMenuItem,
	});
	registerPlugin('wp-future-revisions-fork-pre-publish', {
		render: ForkPrePublishPanel,
	});
	registerPlugin('wp-future-revisions-fork-post-publish', {
		render: ForkPostPublishRedirect,
	});
}
