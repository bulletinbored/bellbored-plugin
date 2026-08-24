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
            return;
        }

        icon.classList.add('dropdown-toggle');
        icon.setAttribute('data-bs-toggle', 'dropdown');
        icon.setAttribute('role', 'button');
        icon.setAttribute('aria-expanded', 'false');

        var panel = document.createElement('ul');
        panel.className = 'dropdown-menu dropdown-menu-end';
        panel.style.width = '320px';
        panel.style.maxHeight = '400px';
        panel.style.overflowY = 'auto';
        panel.innerHTML = '<li class="dropdown-header d-flex justify-content-between align-items-center"><span><i class="fas fa-bell me-1"></i>Notifications</span><a class="small" href="' + B.baseUrl + '/notifications">View all</a></li><li><hr class="dropdown-divider"></li><li class="bellbored-empty-msg dropdown-item-text text-center text-muted py-3">Loading…</li>';

        (icon.closest('li') || icon.parentNode).appendChild(panel);

        var countEl = icon.querySelector('.nav-badge');
        if (!countEl) {
            countEl = document.createElement('span');
            countEl.className = 'nav-badge';
            icon.appendChild(countEl);
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function load() {
            fetch(B.apiUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var count = data.count || 0;
                    if (count > 0) {
                        countEl.textContent = count > 99 ? '99+' : count;
                    } else {
                        countEl.textContent = '';
                    }

                    if (!data.items || !data.items.length) {
                        panel.innerHTML = '<li class="dropdown-header d-flex justify-content-between align-items-center"><span><i class="fas fa-bell me-1"></i>Notifications</span><a class="small" href="' + B.baseUrl + '/notifications">View all</a></li><li><hr class="dropdown-divider"></li><li class="bellbored-empty-msg dropdown-item-text text-center text-muted py-3">No notifications</li>';
                        return;
                    }

                    var html = '<li class="dropdown-header d-flex justify-content-between align-items-center"><span><i class="fas fa-bell me-1"></i>Notifications</span><a class="small" href="' + B.baseUrl + '/notifications">View all</a></li><li><hr class="dropdown-divider"></li>';
                    data.items.forEach(function (item) {
                        var label = item.title ? item.title : escapeHtml(item.message);
                        var href = item.link || (B.baseUrl + '/notifications');
                        html += '<li><a class="dropdown-item' + (item.is_read ? '' : ' fw-semibold') + '" href="' + href + '"><div class="small text-truncate">' + escapeHtml(label) + '</div></a></li>';
                    });
                    html += '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-center" href="' + B.baseUrl + '/notifications"><i class="fas fa-bell me-2"></i>Open notifications</a></li>';
                    panel.innerHTML = html;
                })
                .catch(function () {});
        }

        icon.addEventListener('shown.bs.dropdown', function () {
            if (!panel.querySelector('.dropdown-item[data-id], .bellbored-empty-msg')) {
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
