document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('[data-sidebar-toggle]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                form.reportValidity();
                event.preventDefault();
                return;
            }

            var confirmText = form.getAttribute('data-confirm');
            if (confirmText && !window.confirm(confirmText)) {
                event.preventDefault();
                return;
            }

            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.dataset.originalText = button.textContent;
                button.textContent = button.getAttribute('data-loading-text') || 'Working...';
                button.disabled = true;
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.querySelector(button.getAttribute('data-password-toggle'));
            if (!input) {
                return;
            }
            input.type = input.type === 'password' ? 'text' : 'password';
            button.textContent = input.type === 'password' ? 'Show' : 'Hide';
        });
    });

    document.querySelectorAll('[data-copy-text]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-copy-text') || '';
            if (navigator.clipboard && value) {
                navigator.clipboard.writeText(value).then(function () {
                    button.textContent = 'Copied';
                    window.setTimeout(function () {
                        button.textContent = 'Copy Number';
                    }, 1400);
                });
            }
        });
    });

    document.querySelectorAll('[data-table]').forEach(function (table) {
        enhanceTable(table);
    });
});

function enhanceTable(table) {
    var key = table.getAttribute('data-table');
    var wrapper = table.closest('.table-shell') || table.parentElement;
    var tbody = table.querySelector('tbody');
    var originalRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    var search = document.querySelector('[data-table-search="' + key + '"]');
    var filters = Array.prototype.slice.call(document.querySelectorAll('[data-table-filter="' + key + '"]'));
    var perPage = parseInt(table.getAttribute('data-page-size') || '10', 10);
    var currentPage = 1;
    var sortIndex = null;
    var sortDirection = 1;

    if (!tbody || originalRows.length === 0 || (originalRows.length === 1 && originalRows[0].querySelector('.empty-state'))) {
        return;
    }

    var footer = document.createElement('div');
    footer.className = 'table-footer';
    footer.innerHTML = '<span data-count></span><div class="pagination" data-pagination></div>';
    wrapper.appendChild(footer);

    table.querySelectorAll('th[data-sort]').forEach(function (heading, index) {
        heading.addEventListener('click', function () {
            if (sortIndex === index) {
                sortDirection *= -1;
            } else {
                sortIndex = index;
                sortDirection = 1;
            }
            currentPage = 1;
            render();
        });
    });

    if (search) {
        search.addEventListener('input', function () {
            currentPage = 1;
            render();
        });
    }

    filters.forEach(function (filter) {
        filter.addEventListener('change', function () {
            currentPage = 1;
            render();
        });
    });

    render();

    function render() {
        var query = search ? search.value.trim().toLowerCase() : '';
        var rows = originalRows.filter(function (row) {
            var textMatch = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
            var filterMatch = filters.every(function (filter) {
                var value = filter.value;
                if (!value) {
                    return true;
                }
                var field = filter.getAttribute('data-field');
                var cell = row.querySelector('[data-field="' + field + '"]');
                return cell && cell.getAttribute('data-value') === value;
            });
            return textMatch && filterMatch;
        });

        if (sortIndex !== null) {
            rows.sort(function (a, b) {
                var aText = (a.children[sortIndex] ? a.children[sortIndex].textContent : '').trim();
                var bText = (b.children[sortIndex] ? b.children[sortIndex].textContent : '').trim();
                var aNumber = parseFloat(aText.replace(/[^0-9.-]/g, ''));
                var bNumber = parseFloat(bText.replace(/[^0-9.-]/g, ''));
                if (!Number.isNaN(aNumber) && !Number.isNaN(bNumber)) {
                    return (aNumber - bNumber) * sortDirection;
                }
                return aText.localeCompare(bText) * sortDirection;
            });
        }

        var pageCount = Math.max(1, Math.ceil(rows.length / perPage));
        currentPage = Math.min(currentPage, pageCount);
        var start = (currentPage - 1) * perPage;
        var visible = rows.slice(start, start + perPage);

        tbody.innerHTML = '';
        visible.forEach(function (row) {
            tbody.appendChild(row);
        });

        if (visible.length === 0) {
            var empty = document.createElement('tr');
            empty.innerHTML = '<td class="empty-state" colspan="' + table.querySelectorAll('thead th').length + '"><strong>No matching records</strong><span>Adjust the search or filters to see more results.</span></td>';
            tbody.appendChild(empty);
        }

        footer.querySelector('[data-count]').textContent = rows.length + ' record' + (rows.length === 1 ? '' : 's');
        renderPagination(footer.querySelector('[data-pagination]'), pageCount);
    }

    function renderPagination(target, pageCount) {
        target.innerHTML = '';
        for (var page = 1; page <= pageCount; page++) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = page;
            button.className = page === currentPage ? 'active' : '';
            button.addEventListener('click', function () {
                currentPage = parseInt(this.textContent, 10);
                render();
            });
            target.appendChild(button);
        }
    }
}
