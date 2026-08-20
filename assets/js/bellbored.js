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
        // On mobile the bell is a plain link to the notifications page
        // (the numbered badge stays). Dropdowns are disabled there.
        if (window.matchMedia && window.matchMedia('(max-width: 991.98px)').matches) {
            return;
        }

        var userNav = document.querySelector('ul.topbar-user') || document.querySelector('.topbar-user');
        if (!userNav) {
            return;
        }

        var icon = userNav.querySelector('a[href*="notifications"][title="Notifications"]');

        if (!icon) {
            // No core bell present: create our own and insert it just before
            // the user dropdown (last <li>), so it sits after the messages icon.
            var root = el(
                '<div class="bellbored-bell" id="bellbored-root">' +
                '  <a href="#" class="bellbored-bell__icon" title="Notifications">' +
                '    <i class="fas fa-bell"></i>' +
                '    <span class="bellbored-bell__count" hidden>0</span>' +
                '  </a>' +
                '  <div class="bellbored-panel">' +
                '    <div class="bellbored-empty">Loading…</div>' +
                '  </div>' +
                '</div>'
            );
            var lastLi = userNav.querySelector('li:last-child');
            if (lastLi) {
                userNav.insertBefore(root, lastLi);
            } else {
                userNav.appendChild(root);
            }
            icon = root.querySelector('.bellbored-bell__icon');
        }

        var root = icon;
        var panel = document.createElement('div');
        panel.className = 'bellbored-panel';
        panel.innerHTML = '<div class="bellbored-empty">Loading…</div>';
        (icon.closest('li') || icon.parentNode).appendChild(panel);

        var countEl = icon.querySelector('.nav-badge') || icon.querySelector('.bellbored-bell__count');
        if (!countEl) {
            countEl = document.createElement('span');
            countEl.className = 'nav-badge';
            countEl.hidden = true;
            icon.appendChild(countEl);
        }

        function load() {
            fetch(B.apiUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var count = data.count || 0;
                    if (count > 0) {
                        countEl.textContent = count > 99 ? '99+' : count;
                        countEl.removeAttribute('hidden');
                    } else {
                        countEl.setAttribute('hidden', '');
                    }

                    if (!data.items || !data.items.length) {
                        panel.innerHTML = '<div class="bellbored-empty">No notifications</div>';
                        return;
                    }

                    panel.innerHTML = '';
                    data.items.forEach(function (item) {
                        var inner = item.link
                            ? '<a href="' + item.link + '">' + escapeHtml(item.message) + '</a>'
                            : '<span>' + escapeHtml(item.message) + '</span>';
                        panel.appendChild(el('<div class="bellbored-item" data-id="' + item.id + '">' + inner + '</div>'));
                    });
                })
                .catch(function () {});
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function closeOtherDropdowns() {
            var otherDropdown = document.querySelector('.textmebored-dropdown');
            if (otherDropdown && otherDropdown.style.display !== 'none') {
                otherDropdown.style.display = 'none';
            }
        }

        icon.addEventListener('click', function (e) {
            e.preventDefault();
            var open = panel.getAttribute('data-open') === '1';
            panel.setAttribute('data-open', open ? '0' : '1');
            if (!open) {
                closeOtherDropdowns();
                load();
            }
        });

        panel.addEventListener('click', function (e) {
            var item = e.target.closest('.bellbored-item');
            if (!item) {
                return;
            }
            fetch(B.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id: parseInt(item.getAttribute('data-id'), 10), csrf_token: B.csrfToken })
            }).then(function () { load(); }).catch(function () {});
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                panel.setAttribute('data-open', '0');
            }
        });

        load();
    }

    B.init = mount;
})();
