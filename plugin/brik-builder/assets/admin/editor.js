/* "Edit with Brik" button in the block editor header. */
(function (wp) {
	'use strict';

	const cfg = window.brikEditor;
	if (!cfg || !wp || !wp.data) return;

	const open = (e) => {
		e.preventDefault();
		const editor = wp.data.select('core/editor');
		// Unsaved posts have to exist (and keep their title) before the builder can load them.
		if (editor && (editor.isEditedPostDirty() || editor.isEditedPostNew())) {
			wp.data.dispatch('core/editor').savePost().then(() => (window.location.href = cfg.url));
			return;
		}
		window.location.href = cfg.url;
	};

	const inject = () => {
		if (document.querySelector('.brik-editor-button')) return;
		const bar = document.querySelector('.editor-header__toolbar, .edit-post-header-toolbar');
		if (!bar) return;
		const button = document.createElement('a');
		button.href = cfg.url;
		button.className = 'components-button is-primary brik-editor-button';
		button.textContent = cfg.label;
		button.addEventListener('click', open);
		bar.append(button);
	};

	// The header re-renders (e.g. when switching modes), so keep the button in place.
	wp.domReady(() => {
		inject();
		new MutationObserver(inject).observe(document.body, { childList: true, subtree: true });
	});
})(window.wp);
