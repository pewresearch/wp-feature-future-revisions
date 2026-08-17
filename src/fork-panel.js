import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch, select } from '@wordpress/data';
import { PluginPostStatusInfo, store as editorStore } from '@wordpress/editor';
import { store as coreStore } from '@wordpress/core-data';
import {
	Button,
	Spinner,
	Notice,
	Flex,
	FlexItem,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import { getSupports, getRestNamespace } from './supports';

function clearActiveForkEntityMeta(postType, postId, receiveEntityRecords) {
	const queries = [undefined, { context: 'edit' }];
	queries.forEach((query) => {
		const record = select(coreStore).getEntityRecord(
			'postType',
			postType,
			postId,
			query
		);
		if (!record) {
			return;
		}
		receiveEntityRecords(
			'postType',
			postType,
			{
				...record,
				meta: {
					...(record.meta || {}),
					_active_future_revision: 0,
				},
			},
			query,
			true
		);
	});
}

export default function ForkPanel() {
	const { postId, postStatus, postType } = useSelect(
		(selectStore) => ({
			postId: selectStore(editorStore).getCurrentPostId(),
			postStatus:
				selectStore(editorStore).getEditedPostAttribute('status'),
			postType: selectStore(editorStore).getCurrentPostType(),
		}),
		[]
	);
	const { receiveEntityRecords } = useDispatch(coreStore);
	const [forkInfo, setForkInfo] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [isCreating, setIsCreating] = useState(false);
	const [isDiscarding, setIsDiscarding] = useState(false);
	const [isConfirmOpen, setIsConfirmOpen] = useState(false);
	const [error, setError] = useState(null);

	const fetchForkInfo = useCallback(async () => {
		if (!postId || !getSupports().futureRevisions) {
			setIsLoading(false);
			return;
		}
		try {
			const data = await apiFetch({
				path: `/${getRestNamespace()}/forks?post=${postId}`,
			});
			setForkInfo(data);
		} catch {
			setForkInfo({ role: 'none' });
		} finally {
			setIsLoading(false);
		}
	}, [postId]);

	useEffect(() => {
		fetchForkInfo();
	}, [fetchForkInfo]);

	if (!getSupports().futureRevisions || !postId) {
		return null;
	}

	async function handleCreateFork() {
		setIsCreating(true);
		setError(null);
		try {
			const result = await apiFetch({
				path: `/${getRestNamespace()}/forks`,
				method: 'POST',
				data: { post: postId },
			});
			if (result.edit_url) {
				window.location.href = result.edit_url;
			}
		} catch (err) {
			setError(err.message);
			setIsCreating(false);
		}
	}

	async function handleDiscardFork() {
		if (isDiscarding) {
			return;
		}
		setIsDiscarding(true);
		setError(null);
		try {
			const id = forkInfo?.fork_id || postId;
			await apiFetch({
				path: `/${getRestNamespace()}/forks/${id}`,
				method: 'DELETE',
			});
			clearActiveForkEntityMeta(postType, postId, receiveEntityRecords);
			setForkInfo({ role: 'none' });
			setIsConfirmOpen(false);
		} catch (err) {
			setError(err.message);
		} finally {
			setIsDiscarding(false);
		}
	}

	if (isLoading) {
		return (
			<PluginPostStatusInfo>
				<Spinner />
			</PluginPostStatusInfo>
		);
	}

	if (forkInfo?.role === 'fork') {
		return (
			<PluginPostStatusInfo>
				<span>
					{__('Future revision of', 'wp-feature-future-revisions')}{' '}
					<a href={forkInfo.parent_edit_url}>
						{forkInfo.parent_title}
					</a>
					.{' '}
					{__(
						'Publish to merge back.',
						'wp-feature-future-revisions'
					)}
				</span>
			</PluginPostStatusInfo>
		);
	}

	if (forkInfo?.role === 'parent') {
		return (
			<PluginPostStatusInfo>
				<ConfirmDialog
					isOpen={isConfirmOpen}
					onConfirm={handleDiscardFork}
					onCancel={() => {
						if (!isDiscarding) {
							setIsConfirmOpen(false);
						}
					}}
					confirmButtonText={
						isDiscarding
							? __('Discarding…', 'wp-feature-future-revisions')
							: __('Discard', 'wp-feature-future-revisions')
					}
					isBusy={isDiscarding}
					shouldCloseOnEsc={!isDiscarding}
					shouldCloseOnClickOutside={!isDiscarding}
				>
					{__(
						'Discard this Future Revision? It will be moved to the trash. The published post will not be changed.',
						'wp-feature-future-revisions'
					)}
				</ConfirmDialog>
				<Flex direction="column" gap={2} style={{ flexGrow: 1 }}>
					{error ? (
						<Notice status="error" isDismissible={false}>
							{error}
						</Notice>
					) : null}
					<FlexItem>
						<span>
							{__(
								'Active fork exists.',
								'wp-feature-future-revisions'
							)}{' '}
							<a href={forkInfo.fork_edit_url}>
								{__(
									'Edit the fork',
									'wp-feature-future-revisions'
								)}
							</a>
						</span>
					</FlexItem>
					<FlexItem>
						<Button
							variant="link"
							isDestructive
							onClick={() => setIsConfirmOpen(true)}
							disabled={isDiscarding || isConfirmOpen}
						>
							{__(
								'Discard Future Revision',
								'wp-feature-future-revisions'
							)}
						</Button>
					</FlexItem>
				</Flex>
			</PluginPostStatusInfo>
		);
	}

	if (postStatus !== 'publish') {
		return null;
	}

	return (
		<PluginPostStatusInfo>
			<Flex direction="column" gap={2} style={{ flexGrow: 1 }}>
				{error ? (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				) : null}
				<FlexItem>
					<Button
						variant="secondary"
						onClick={handleCreateFork}
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
				</FlexItem>
			</Flex>
		</PluginPostStatusInfo>
	);
}
