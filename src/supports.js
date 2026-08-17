export function getSupports() {
	return (
		window.wpFutureRevisions?.supports || {
			publicRevisions: false,
			futureRevisions: false,
		}
	);
}

export function getRestNamespace() {
	return window.wpFutureRevisions?.restNamespace || 'wp-future-revisions/v1';
}
