/**
 * Invite Registration — admin settings UI (build-free, plain JavaScript).
 *
 * This file is written by hand and runs directly in the browser: no npm, no
 * build step. It relies only on globals that Nextcloud provides on every admin
 * page: OC (generateUrl, requestToken, isUserAdmin), OCP.Toast, and the
 * initial-state <input> emitted by OCP\AppFramework\Services\IInitialState.
 */
(function () {
	'use strict';

	var APP_ID = 'invite_registration';

	// ---- small helpers -------------------------------------------------

	function t(text) {
		// Nextcloud exposes a global translation function `t(appId, text)` and
		// also OC.L10N.translate. Use whichever exists; fall back to source text.
		if (typeof window.t === 'function') {
			return window.t(APP_ID, text);
		}
		if (window.OC && OC.L10N && typeof OC.L10N.translate === 'function') {
			return OC.L10N.translate(APP_ID, text);
		}
		return text;
	}

	function url(path) {
		return OC.generateUrl('/apps/' + APP_ID + path);
	}

	function toastSuccess(msg) {
		if (window.OCP && OCP.Toast) {
			OCP.Toast.success(msg);
		} else if (window.OC && OC.Notification) {
			OC.Notification.showTemporary(msg);
		}
	}

	function toastError(msg) {
		if (window.OCP && OCP.Toast) {
			OCP.Toast.error(msg);
		} else if (window.OC && OC.Notification) {
			OC.Notification.showTemporary(msg);
		} else {
			window.alert(msg);
		}
	}

	/** Read a value provided via IInitialState. */
	function loadState(key, fallback) {
		var el = document.getElementById('initial-state-' + APP_ID + '-' + key);
		if (!el) {
			return fallback;
		}
		try {
			return JSON.parse(atob(el.value));
		} catch (e) {
			return fallback;
		}
	}

	/** Minimal fetch wrapper that sends the CSRF token and JSON. */
	function api(method, path, body) {
		var opts = {
			method: method,
			headers: {
				'Content-Type': 'application/json',
				'requesttoken': OC.requestToken,
				'OCS-APIRequest': 'true',
			},
			credentials: 'same-origin',
		};
		if (body !== undefined) {
			opts.body = JSON.stringify(body);
		}
		return fetch(url(path), opts).then(function (res) {
			if (!res.ok) {
				return res.json().catch(function () { return {}; }).then(function (data) {
					var err = new Error(data && data.message ? data.message : ('HTTP ' + res.status));
					throw err;
				});
			}
			if (res.status === 204) {
				return null;
			}
			return res.json();
		});
	}

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'text') {
					node.textContent = attrs[k];
				} else if (k === 'html') {
					node.innerHTML = attrs[k];
				} else if (k === 'class') {
					node.className = attrs[k];
				} else if (k.indexOf('on') === 0 && typeof attrs[k] === 'function') {
					node.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
				} else {
					node.setAttribute(k, attrs[k]);
				}
			});
		}
		(children || []).forEach(function (c) {
			if (c === null || c === undefined) {
				return;
			}
			node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		});
		return node;
	}

	function formatDate(unixSeconds) {
		if (!unixSeconds) {
			// expiresAt = 0 means the link never expires.
			return t('Never');
		}
		return new Date(unixSeconds * 1000).toLocaleString();
	}

	function shortToken(token) {
		if (!token) {
			return '';
		}
		return token.length > 12 ? token.slice(0, 8) + '…' : token;
	}

	function statusLabel(status) {
		switch (status) {
			case 'active': return t('Active');
			case 'expired': return t('Expired');
			case 'exhausted': return t('Used up');
			case 'revoked': return t('Revoked');
			default: return status;
		}
	}

	function copyToClipboard(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				toastSuccess(t('Link copied to clipboard.'));
			}, function () {
				toastError(t('Could not copy the link.'));
			});
		} else {
			// Fallback for older browsers.
			var ta = document.createElement('textarea');
			ta.value = text;
			document.body.appendChild(ta);
			ta.select();
			try {
				document.execCommand('copy');
				toastSuccess(t('Link copied to clipboard.'));
			} catch (e) {
				toastError(t('Could not copy the link.'));
			}
			document.body.removeChild(ta);
		}
	}

	// ---- state ---------------------------------------------------------

	var state = {
		invites: [],
		groups: loadState('groups', []),
	};

	var refs = {};

	/** Human-readable name for a group ID, falling back to the ID or a dash. */
	function groupLabel(groupId) {
		if (!groupId) {
			return '—';
		}
		for (var i = 0; i < state.groups.length; i++) {
			if (state.groups[i].id === groupId) {
				return state.groups[i].displayName;
			}
		}
		return groupId;
	}

	// ---- rendering -----------------------------------------------------

	function buildCreateSection() {
		var validityOptions = [
			{ hours: 1, label: t('1 hour') },
			{ hours: 24, label: t('24 hours') },
			{ hours: 48, label: t('48 hours') },
			{ hours: 24 * 7, label: t('7 days') },
			{ hours: 24 * 30, label: t('30 days') },
			{ hours: 0, label: t('Never (no expiry)') },
		];

		var validitySelect = el('select', { id: 'ir-validity', class: 'ir-input' },
			validityOptions.map(function (o) {
				var opt = el('option', { value: String(o.hours), text: o.label });
				if (o.hours === 24) {
					opt.setAttribute('selected', 'selected');
				}
				return opt;
			})
		);

		var usesInput = el('input', {
			id: 'ir-maxuses',
			type: 'number',
			min: '1',
			value: '1',
			class: 'ir-input',
		});

		// Per-link group selector. "No group" leaves the user ungrouped and
		// creates no team folder.
		var groupSelect = el('select', { id: 'ir-group', class: 'ir-input' }, [
			el('option', { value: '', text: t('No group') }),
		].concat(state.groups.map(function (g) {
			return el('option', { value: g.id, text: g.displayName });
		})));

		var createBtn = el('button', {
			class: 'button primary',
			type: 'button',
			text: t('Create invitation link'),
			onclick: function () {
				createInvite(
					parseInt(validitySelect.value, 10),
					parseInt(usesInput.value, 10),
					groupSelect.value,
					createBtn
				);
			},
		});

		var lastLinkBox = el('div', { class: 'ir-lastlink', style: 'display:none;' });
		refs.lastLinkBox = lastLinkBox;

		return el('div', { class: 'section ir-section' }, [
			el('h2', { text: t('Create invitation link') }),
			el('p', { class: 'settings-hint', text: t('Generate a personal, single- or multi-use link that lets someone register a normal Nextcloud account.') }),
			el('div', { class: 'ir-create-row' }, [
				el('div', { class: 'ir-field' }, [
					el('label', { for: 'ir-validity', text: t('Validity') }),
					validitySelect,
				]),
				el('div', { class: 'ir-field' }, [
					el('label', { for: 'ir-maxuses', text: t('Number of uses') }),
					usesInput,
				]),
				el('div', { class: 'ir-field' }, [
					el('label', { for: 'ir-group', text: t('Team') }),
					groupSelect,
				]),
				el('div', { class: 'ir-field ir-field-btn' }, [createBtn]),
			]),
			el('p', { class: 'settings-hint ir-group-hint', text: t('The user joins this team. If the Group Folders app is installed, a shared team folder is created for the team automatically.') }),
			lastLinkBox,
		]);
	}

	function showLastLink(link) {
		var box = refs.lastLinkBox;
		box.innerHTML = '';
		var input = el('input', { type: 'text', readonly: 'readonly', value: link, class: 'ir-lastlink-input' });
		var copyBtn = el('button', {
			class: 'button', type: 'button', text: t('Copy'),
			onclick: function () { copyToClipboard(link); },
		});
		box.appendChild(el('p', { text: t('Your invitation link:') }));
		box.appendChild(el('div', { class: 'ir-lastlink-row' }, [input, copyBtn]));
		box.style.display = '';
		input.focus();
		input.select();
	}

	function buildListSection() {
		var container = el('div', { class: 'section ir-section' }, [
			el('h2', { text: t('Invitation links') }),
		]);
		refs.listContainer = el('div', { class: 'ir-list' });
		container.appendChild(refs.listContainer);
		return container;
	}

	function renderList() {
		var container = refs.listContainer;
		container.innerHTML = '';

		if (!state.invites.length) {
			container.appendChild(el('p', { class: 'ir-empty', text: t('No invitation links yet.') }));
			return;
		}

		var thead = el('thead', {}, [
			el('tr', {}, [
				el('th', { text: t('Link') }),
				el('th', { text: t('Team') }),
				el('th', { text: t('Valid until') }),
				el('th', { text: t('Usage') }),
				el('th', { text: t('Status') }),
				el('th', { class: 'ir-actions-col', text: t('Actions') }),
			]),
		]);

		var tbody = el('tbody', {}, state.invites.map(function (invite) {
			var copyBtn = el('button', {
				class: 'button ir-icon-btn', type: 'button',
				title: t('Copy link'), 'aria-label': t('Copy link'),
				text: '⧉',
				onclick: function () { copyToClipboard(invite.link); },
			});

			var actions = [];
			if (invite.status === 'active') {
				actions.push(el('button', {
					class: 'button', type: 'button', text: t('Revoke'),
					onclick: function () { revokeInvite(invite); },
				}));
			}
			actions.push(el('button', {
				class: 'button', type: 'button', text: t('Delete'),
				onclick: function () { deleteInvite(invite); },
			}));

			return el('tr', {}, [
				el('td', { class: 'ir-link-cell' }, [
					el('code', { title: invite.link, text: shortToken(invite.token) }),
					copyBtn,
				]),
				el('td', { text: groupLabel(invite.groupId) }),
				el('td', { text: formatDate(invite.expiresAt) }),
				el('td', { text: invite.usedCount + ' / ' + invite.maxUses }),
				el('td', {}, [
					el('span', { class: 'ir-status ir-status--' + invite.status, text: statusLabel(invite.status) }),
				]),
				el('td', { class: 'ir-actions-col' }, actions),
			]);
		}));

		var table = el('table', { class: 'ir-table' }, [thead, tbody]);
		container.appendChild(table);
	}

	// ---- actions -------------------------------------------------------

	function loadInvites() {
		api('GET', '/admin/invites').then(function (data) {
			state.invites = (data && data.invites) || [];
			renderList();
		}).catch(function () {
			toastError(t('Could not load invitation links.'));
		});
	}

	function createInvite(validityHours, maxUses, groupId, btn) {
		if (!Number.isInteger(maxUses) || maxUses < 1) {
			toastError(t('Number of uses must be at least 1.'));
			return;
		}
		btn.setAttribute('disabled', 'disabled');
		api('POST', '/admin/invites', { validityHours: validityHours, maxUses: maxUses, groupId: groupId || '' })
			.then(function (data) {
				state.invites.unshift(data.invite);
				renderList();
				showLastLink(data.invite.link);
				toastSuccess(t('Invitation link created.'));
			})
			.catch(function (e) {
				toastError(e.message || t('Could not create invitation link.'));
			})
			.then(function () {
				btn.removeAttribute('disabled');
			});
	}

	function revokeInvite(invite) {
		api('POST', '/admin/invites/' + invite.id + '/revoke')
			.then(function (data) {
				var idx = state.invites.findIndex(function (i) { return i.id === invite.id; });
				if (idx !== -1) {
					state.invites[idx] = data.invite;
				}
				renderList();
				toastSuccess(t('Invitation link revoked.'));
			})
			.catch(function () {
				toastError(t('Could not revoke invitation link.'));
			});
	}

	function deleteInvite(invite) {
		api('DELETE', '/admin/invites/' + invite.id)
			.then(function () {
				state.invites = state.invites.filter(function (i) { return i.id !== invite.id; });
				renderList();
				toastSuccess(t('Invitation link deleted.'));
			})
			.catch(function () {
				toastError(t('Could not delete invitation link.'));
			});
	}

	// ---- bootstrap -----------------------------------------------------

	function init() {
		var mount = document.getElementById('invite_registration-admin-settings');
		if (!mount) {
			return;
		}
		mount.innerHTML = '';
		mount.appendChild(buildCreateSection());
		mount.appendChild(buildListSection());
		loadInvites();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
