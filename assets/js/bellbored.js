(function () {
    var B = window.bellbored;
    if (!B) {
        return;
    }

    function el(html) {
        var t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstChild;
    }

    function mount() {
        if (!B.loggedIn) {
            return;
        }
        // Dropdowns are now enabled on every breakpoint (incl. mobile), matching
        // the in-app behavior the user expects.

        var userNav = document.querySelector('ul.topbar-user') || document.querySelector('.topbar-user');
        if (!userNav) {
            return;
        }

        var icon = userNav.querySelector('a[href*="notifications"][title="Notifications"]');
        if (!icon) {
            return;
        }

        icon.classList.add('dropdown-toggle');
        icon.setAttribute('data-bs-toggle', 'dropdown');
        icon.setAttribute('role', 'button');
        icon.setAttribute('aria-expanded', 'false');

        var panel = document.createElement('ul');
        panel.className = 'dropdown-menu dropdown-menu-end bellbored-panel';
        panel.setAttribute('data-open', '0');
        panel.style.width = '340px';
        panel.style.maxHeight = '420px';
        panel.style.overflowY = 'auto';
        panel.innerHTML = '<li class="dropdown-header d-flex justify-content-between align-items-center"><span><i class="fas fa-bell me-1"></i>Notifications</span><a class="small bellbored-mark-all" href="#" role="button">Mark all read</a></li><li><hr class="dropdown-divider"></li><li class="bellbored-empty-msg dropdown-item-text text-center text-muted py-3">Loading…</li>';

        (icon.closest('li') || icon.parentNode).appendChild(panel);

        var countEl = icon.querySelector('.nav-badge');
        if (!countEl) {
            countEl = document.createElement('span');
            countEl.className = 'nav-badge';
            icon.appendChild(countEl);
        }

        var labels = (window.bellbored && window.bellbored.labels) || { markAllRead: 'Mark all read', markRead: 'Mark as read' };

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        // Mark a single notification (or all, when id is 0) as read via the API.
        function markRead(id) {
            var payload = JSON.stringify({ id: id, csrf_token: B.csrfToken || '' });
            fetch(B.apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: payload
            })
            .then(function (r) { return r.json(); })
            .then(function () { load(true); })
            .catch(function () {});
        }

        function load(skipEmptyCheck) {
            fetch(B.apiUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var count = data.count || 0;
                    if (count > 0) {
                        countEl.textContent = count > 99 ? '99+' : count;
                        countEl.style.display = '';
                    } else {
                        countEl.textContent = '';
                        countEl.style.display = 'none';
                    }

                    if (!data.items || !data.items.length) {
                        panel.innerHTML = '<li class="dropdown-header d-flex justify-content-between align-items-center"><span><i class="fas fa-bell me-1"></i>Notifications</span></li><li><hr class="dropdown-divider"></li><li class="bellbored-empty-msg dropdown-item-text text-center text-muted py-3">No notifications</li>';
                        return;
                    }

                    var html = '<li class="dropdown-header d-flex justify-content-between align-items-center"><span><i class="fas fa-bell me-1"></i>Notifications</span><a class="small bellbored-mark-all" href="#" role="button">' + escapeHtml(labels.markAllRead) + '</a></li><li><hr class="dropdown-divider"></li>';
                    data.items.forEach(function (item) {
                        var label = item.title ? item.title : escapeHtml(item.message);
                        var href = item.link || (B.baseUrl + '/notifications');
                        html += '<li class="bellbored-item' + (item.is_read ? '' : ' bellbored-unread') + '">';
                        html += '<a class="dropdown-item' + (item.is_read ? '' : ' fw-semibold') + '" href="' + href + '"><div class="small text-truncate">' + escapeHtml(label) + '</div></a>';
                        if (!item.is_read) {
                            html += '<button type="button" class="btn btn-sm bellbored-mark-one" data-id="' + (item.id || 0) + '" title="' + escapeHtml(labels.markRead) + '"><i class="fas fa-check"></i></button>';
                        }
                        html += '</li>';
                    });
                    html += '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-center" href="' + B.baseUrl + '/notifications"><i class="fas fa-bell me-2"></i>Open notifications</a></li>';
                    panel.innerHTML = html;
                })
                .catch(function () {});
        }

        panel.addEventListener('click', function (e) {
            var markAll = e.target.closest('.bellbored-mark-all');
            if (markAll) {
                e.preventDefault();
                markRead(0);
                return;
            }
            var markOne = e.target.closest('.bellbored-mark-one');
            if (markOne) {
                e.preventDefault();
                e.stopPropagation();
                markRead(parseInt(markOne.getAttribute('data-id'), 10));
            }
        });

        icon.addEventListener('shown.bs.dropdown', function () {
            if (!panel.querySelector('.dropdown-item[data-id], .bellbored-empty-msg, .bellbored-item')) {
                load();
            }
        });

        load();
    }

    B.init = mount;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
})();
