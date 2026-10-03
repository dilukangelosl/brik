/* Brik admin screens: theme builder conditions, status toggles, Connect AI. */
(function () {
	'use strict';

	const cfg = window.brikAdmin || {};
	const t = cfg.i18n || {};
	const apiFetch = window.wp && window.wp.apiFetch;

	const el = (tag, attrs = {}, children = []) => {
		const node = document.createElement(tag);
		Object.entries(attrs).forEach(([k, v]) => {
			if (k === 'text') node.textContent = v;
			else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
			else if (v !== false && v != null) node.setAttribute(k, v === true ? '' : v);
		});
		[].concat(children).forEach((c) => c && node.append(c));
		return node;
	};

	const select = (options, value, attrs = {}) => {
		const s = el('select', attrs);
		Object.entries(options).forEach(([v, label]) => {
			const o = el('option', { value: v, text: label });
			if (v === value) o.selected = true;
			s.append(o);
		});
		return s;
	};

	const save = (id, data) => apiFetch({ path: '/brik/v1/posts/' + id, method: 'POST', data });

	const fail = (err) => window.alert((t.error || 'Error') + ' ' + ((err && err.message) || err));

	/* Delete confirmations ---------------------------------------------- */

	document.addEventListener('click', (e) => {
		const link = e.target.closest('.brik-delete');
		if (link && !window.confirm(t.deleteAsk)) e.preventDefault();
	});

	/* Theme builder ------------------------------------------------------- */

	const NEEDS_TYPE = ['singular', 'archive'];
	const NEEDS_TAX = ['term', 'in_term'];
	const NEEDS_IDS = ['post', 'term', 'in_term', 'author'];

	function picker(rule, onChange) {
		const wrap = el('div', { class: 'brik-picker' });
		const chips = el('div', { class: 'brik-chips' });
		const input = el('input', { type: 'search', placeholder: t.search, 'aria-label': t.search });
		const results = el('ul', { class: 'brik-picker-results', hidden: true });
		let timer;

		const renderChips = () => {
			chips.replaceChildren(
				...rule.items.map((item, i) =>
					el('span', { class: 'brik-chip' }, [
						item.title,
						el('button', {
							type: 'button',
							'aria-label': t.remove + ' ' + item.title,
							text: '×',
							onclick: () => {
								rule.items.splice(i, 1);
								renderChips();
								onChange();
							},
						}),
					])
				)
			);
		};

		const search = async () => {
			const q = input.value.trim();
			if (!q) {
				results.hidden = true;
				return;
			}
			const term = NEEDS_TAX.includes(rule.rule);
			if (term && !rule.taxonomy) return;
			let path = '/brik/v1/search?q=' + encodeURIComponent(q);
			if (term) path += '&what=term&taxonomy=' + encodeURIComponent(rule.taxonomy);
			else if (rule.post_type) path += '&post_type=' + encodeURIComponent(rule.post_type);
			let found = [];
			if (rule.rule === 'author') {
				found = (await apiFetch({ path: '/wp/v2/users?per_page=20&search=' + encodeURIComponent(q) })).map((u) => ({ id: u.id, title: u.name }));
			} else {
				found = await apiFetch({ path });
			}
			results.replaceChildren(
				...found.map((item) =>
					el('li', {}, el('button', {
						type: 'button',
						text: item.title + (item.type ? ' · ' + item.type : ''),
						onclick: () => {
							if (!rule.items.some((x) => x.id === item.id)) rule.items.push({ id: item.id, title: item.title });
							input.value = '';
							results.hidden = true;
							renderChips();
							onChange();
						},
					}))
				)
			);
			results.hidden = !found.length;
		};

		input.addEventListener('input', () => {
			clearTimeout(timer);
			timer = setTimeout(() => search().catch(() => {}), 250);
		});
		input.addEventListener('keydown', (e) => e.key === 'Escape' && (results.hidden = true));
		renderChips();
		wrap.append(chips, input, results);
		return wrap;
	}

	function ruleRow(rule, list, redraw) {
		const row = el('div', { class: 'brik-rule' });
		const line = el('div', { class: 'brik-rule-line' });
		const type = select({ include: t.include, exclude: t.exclude }, rule.type, { 'aria-label': t.include + ' / ' + t.exclude });
		const kind = select(cfg.rules, rule.rule, { 'aria-label': 'Rule' });
		type.addEventListener('change', () => (rule.type = type.value));
		kind.addEventListener('change', () => {
			rule.rule = kind.value;
			rule.items = [];
			redraw();
		});
		line.append(type, kind);

		if (NEEDS_TYPE.includes(rule.rule) || rule.rule === 'post') {
			const types = Object.assign({ '': t.anyType }, cfg.postTypes);
			const pt = select(types, rule.post_type || '', { 'aria-label': t.anyType });
			pt.addEventListener('change', () => (rule.post_type = pt.value));
			line.append(pt);
		}
		if (NEEDS_TAX.includes(rule.rule)) {
			const tax = select(Object.assign({ '': t.chooseTax }, cfg.taxonomies), rule.taxonomy || '', { 'aria-label': t.chooseTax });
			tax.addEventListener('change', () => {
				rule.taxonomy = tax.value;
				rule.items = [];
				redraw();
			});
			line.append(tax);
		}
		line.append(el('button', {
			type: 'button',
			class: 'button-link brik-rule-remove',
			text: t.remove,
			onclick: () => {
				list.splice(list.indexOf(rule), 1);
				redraw();
			},
		}));
		row.append(line);
		if (NEEDS_IDS.includes(rule.rule) && (!NEEDS_TAX.includes(rule.rule) || rule.taxonomy)) {
			row.append(picker(rule, () => {}));
		}
		return row;
	}

	function openEditor(card) {
		const box = card.querySelector('.brik-conditions-editor');
		if (!box.hidden) {
			box.hidden = true;
			return;
		}
		let rules = [];
		try {
			rules = JSON.parse(card.dataset.conditions || '[]');
		} catch (e) {}
		rules.forEach((r) => (r.items = r.items || []));

		const draw = () => {
			const rows = rules.map((r) => ruleRow(r, rules, draw));
			const status = el('span', { class: 'brik-muted' });
			const saveBtn = el('button', {
				type: 'button',
				class: 'button button-primary button-small',
				text: t.save,
				onclick: async () => {
					saveBtn.disabled = true;
					status.textContent = t.saving;
					const conditions = rules.map((r) => ({
						type: r.type,
						rule: r.rule,
						post_type: r.post_type || '',
						taxonomy: r.taxonomy || '',
						ids: r.items.map((i) => i.id),
					}));
					try {
						await save(card.dataset.id, { conditions });
						window.location.reload();
					} catch (err) {
						saveBtn.disabled = false;
						status.textContent = '';
						fail(err);
					}
				},
			});
			box.replaceChildren(
				...(rows.length ? rows : [el('p', { class: 'brik-muted', text: t.noRules })]),
				el('div', { class: 'brik-editor-actions' }, [
					el('button', {
						type: 'button',
						class: 'button button-small',
						text: t.addRule,
						onclick: () => {
							rules.push({ type: 'include', rule: 'entire_site', post_type: '', taxonomy: '', items: [] });
							draw();
						},
					}),
					saveBtn,
					el('button', { type: 'button', class: 'button-link', text: t.cancel, onclick: () => (box.hidden = true) }),
					status,
				])
			);
		};
		draw();
		box.hidden = false;
	}

	document.querySelectorAll('.brik-template').forEach((card) => {
		const id = card.dataset.id;
		card.querySelector('.brik-edit-conditions').addEventListener('click', () => openEditor(card));

		card.querySelector('.brik-rename').addEventListener('click', async (e) => {
			const current = e.currentTarget.dataset.title;
			const title = window.prompt(t.renamePrompt, current);
			if (title === null || title.trim() === '' || title === current) return;
			try {
				const res = await save(id, { title: title.trim() });
				card.querySelector('.brik-template-title').textContent = res.title;
				e.currentTarget.dataset.title = res.title;
			} catch (err) {
				fail(err);
			}
		});

		const toggle = card.querySelector('.brik-status-toggle');
		const label = card.querySelector('.brik-switch-label');
		toggle.addEventListener('change', async () => {
			toggle.disabled = true;
			try {
				const res = await save(id, { status: toggle.checked ? 'publish' : 'draft' });
				toggle.checked = res.status === 'publish';
			} catch (err) {
				toggle.checked = !toggle.checked;
				fail(err);
			}
			label.textContent = toggle.checked ? t.published : t.draft;
			toggle.disabled = false;
		});
	});

	/* Connect AI ---------------------------------------------------------- */

	const copyText = async (text, button) => {
		try {
			await navigator.clipboard.writeText(text);
		} catch (e) {
			const area = el('textarea', { style: 'position:fixed;opacity:0' });
			area.value = text;
			document.body.append(area);
			area.select();
			document.execCommand('copy');
			area.remove();
		}
		const old = button.textContent;
		button.textContent = t.copied;
		setTimeout(() => (button.textContent = old), 1500);
	};

	document.querySelectorAll('.brik-copy-button').forEach((button) => {
		button.addEventListener('click', () => {
			let source;
			if (button.dataset.copyActive) {
				const panel = document.querySelector('.brik-tab-panel:not([hidden]) .brik-code');
				source = panel && panel.textContent;
			} else {
				const target = document.querySelector(button.dataset.copy);
				source = target && target.textContent;
			}
			if (source) copyText(source.trim(), button);
		});
	});

	document.querySelectorAll('.brik-tab').forEach((tab) => {
		tab.addEventListener('click', () => {
			document.querySelectorAll('.brik-tab').forEach((x) => x.setAttribute('aria-selected', x === tab ? 'true' : 'false'));
			document.querySelectorAll('.brik-tab-panel').forEach((p) => (p.hidden = p.dataset.panel !== tab.dataset.tab));
		});
	});

	const snippets = (auth) => {
		const url = cfg.mcpUrl;
		const header = 'Authorization: Basic ' + auth;
		const shellQuote = (s) => "'" + s.replace(/'/g, "'\\''") + "'";
		return {
			'claude-code': 'claude mcp add --transport http brik ' + shellQuote(url) + ' --header ' + shellQuote(header),
			desktop: JSON.stringify(
				{ mcpServers: { brik: { command: 'npx', args: ['-y', 'mcp-remote', url, '--header', 'Authorization:${BRIK_AUTH}'].concat(url.indexOf('http://') === 0 ? ['--allow-http'] : []), env: { BRIK_AUTH: 'Basic ' + auth } } } },
				null,
				2
			),
			cursor: JSON.stringify({ mcpServers: { brik: { url, headers: { Authorization: 'Basic ' + auth } } } }, null, 2),
			curl:
				'curl -s ' + shellQuote(url) + ' \\\n  -H ' + shellQuote(header) + " \\\n  -H 'Content-Type: application/json' \\\n" +
				"  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'",
		};
	};

	const create = document.getElementById('brik-create-password');
	if (create) {
		create.addEventListener('click', async () => {
			const error = document.getElementById('brik-password-error');
			const name = document.getElementById('brik-app-name').value.trim() || 'Brik MCP';
			error.hidden = true;
			create.disabled = true;
			const label = create.textContent;
			create.textContent = t.creating;
			try {
				const res = await apiFetch({ path: '/wp/v2/users/me/application-passwords', method: 'POST', data: { name } });
				const auth = window.btoa(unescape(encodeURIComponent(cfg.userLogin + ':' + res.password)));
				const out = snippets(auth);
				Object.keys(out).forEach((key) => (document.getElementById('brik-snippet-' + key).textContent = out[key]));
				const box = document.getElementById('brik-snippets');
				box.hidden = false;
				box.scrollIntoView({ behavior: 'smooth', block: 'start' });
			} catch (err) {
				error.textContent = (t.error || '') + ' ' + ((err && err.message) || '');
				error.hidden = false;
			}
			create.disabled = false;
			create.textContent = label;
		});
	}
})();
