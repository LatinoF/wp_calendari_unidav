(function () {
    'use strict';

    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-copy-target'));
            if (!target) {
                return;
            }

            target.select();
            navigator.clipboard.writeText(target.value).then(function () {
                var original = button.textContent;
                button.textContent = 'Copiato';
                window.setTimeout(function () {
                    button.textContent = original;
                }, 1400);
            });
        });
    });

    function cancelNoticeDismissal(notice) {
        if (notice && notice.calendariDismissTimer) {
            window.clearTimeout(notice.calendariDismissTimer);
            notice.calendariDismissTimer = null;
        }
    }

    function dismissNotice(notice) {
        cancelNoticeDismissal(notice);
        if (!notice || !notice.parentNode) {
            return;
        }

        notice.classList.add('is-dismissing');
        window.setTimeout(function () {
            if (notice.parentNode) {
                notice.parentNode.removeChild(notice);
            }
        }, 240);
    }

    function scheduleNoticeDismissal(notice, delay) {
        cancelNoticeDismissal(notice);
        notice.classList.remove('is-dismissing');
        notice.calendariDismissTimer = window.setTimeout(function () {
            dismissNotice(notice);
        }, delay);
    }

    document.querySelectorAll('[data-calendari-auto-dismiss]').forEach(function (notice) {
        var delay = parseInt(notice.getAttribute('data-calendari-auto-dismiss'), 10);
        scheduleNoticeDismissal(notice, Number.isFinite(delay) && delay > 0 ? delay : 5000);
    });

    var filterRequestId = 0;
    var infiniteScrollObserver = 'IntersectionObserver' in window
        ? new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                var results = entry.target.closest('[data-filter-results]');
                var form = entry.target.calendariFilterForm;
                if (results && form) {
                    loadNextFilterPage(form, results);
                }
            });
        }, { rootMargin: '320px 0px' })
        : null;

    function updateClearFilterButton(form) {
        var button = form.querySelector('[data-clear-filters]');
        if (!button) {
            return;
        }

        var hasActiveFilters = Array.prototype.slice.call(form.querySelectorAll('[data-dropdown-value]')).some(function (input) {
            return input.value !== (input.getAttribute('data-default-value') || '');
        });
        button.disabled = !hasActiveFilters;
    }

    function updateFilterAddress(form) {
        if (!window.history || !window.URL) {
            return;
        }

        var url = new URL(window.location.href);
        var tab = form.querySelector('[name="tab"]');
        if (tab) {
            url.searchParams.set('tab', tab.value);
        }
        form.querySelectorAll('[data-dropdown-value]').forEach(function (input) {
            var defaultValue = input.getAttribute('data-default-value') || '';
            if (input.value === defaultValue || '' === input.value) {
                url.searchParams.delete(input.name);
            } else {
                url.searchParams.set(input.name, input.value);
            }
        });
        url.searchParams.delete('paged');
        window.history.replaceState(null, '', url.href);
    }

    function observeFilterSentinel(form, results) {
        var sentinel = results.querySelector('[data-infinite-scroll-sentinel]');
        if (!sentinel) {
            return;
        }
        sentinel.calendariFilterForm = form;
        if (!infiniteScrollObserver) {
            return;
        }
        infiniteScrollObserver.unobserve(sentinel);
        if ('1' === results.getAttribute('data-has-more') && '1' !== results.getAttribute('data-restoring')) {
            infiniteScrollObserver.observe(sentinel);
        }
    }

    function loadNextFilterPage(form, results) {
        if ('1' !== results.getAttribute('data-has-more')
            || '1' === results.getAttribute('data-loading')
            || '1' === results.getAttribute('data-restoring')
        ) {
            return;
        }
        var nextPage = Math.max(2, parseInt(results.getAttribute('data-next-page'), 10) || 2);
        submitAjaxFilters(form, nextPage, true);
    }

    function finishFilterRestore(form, results) {
        var scrollPosition = Math.max(0, parseInt(results.getAttribute('data-restore-scroll'), 10) || 0);
        results.removeAttribute('data-restoring');
        results.removeAttribute('data-restore-pages');
        results.removeAttribute('data-restore-scroll');
        updateFilterAddress(form);
        window.requestAnimationFrame(function () {
            window.scrollTo(0, scrollPosition);
            observeFilterSentinel(form, results);
        });
    }

    function beginFilterRestore(form, results) {
        var targetPages = Math.max(1, parseInt(results.getAttribute('data-restore-pages'), 10) || 1);
        var scrollPosition = Math.max(0, parseInt(results.getAttribute('data-restore-scroll'), 10) || 0);
        if (targetPages <= 1 && scrollPosition <= 0) {
            observeFilterSentinel(form, results);
            return;
        }

        results.setAttribute('data-restoring', '1');
        if (targetPages <= 1 || '1' !== results.getAttribute('data-has-more')) {
            finishFilterRestore(form, results);
            return;
        }

        submitAjaxFilters(form, 2, true);
    }

    function checkFallbackInfiniteScroll() {
        if (infiniteScrollObserver) {
            return;
        }
        document.querySelectorAll('form[data-ajax-filters]').forEach(function (form) {
            var card = form.closest('.calendari-card');
            var results = card ? card.querySelector('[data-filter-results]') : null;
            var sentinel = results ? results.querySelector('[data-infinite-scroll-sentinel]') : null;
            if (!sentinel || !sentinel.getClientRects().length) {
                return;
            }
            if (sentinel.getBoundingClientRect().top < window.innerHeight + 320) {
                loadNextFilterPage(form, results);
            }
        });
    }

    function appendFilterPage(items, html) {
        var pageContainer = document.createElement('div');
        pageContainer.innerHTML = html || '';
        var currentBody = items.querySelector('.calendari-admin-table tbody');
        var pageRows = pageContainer.querySelectorAll('.calendari-admin-table tbody tr');

        if (currentBody && pageRows.length) {
            Array.prototype.forEach.call(pageRows, function (row) {
                currentBody.appendChild(row);
            });
            return;
        }

        items.insertAdjacentHTML('beforeend', html || '');
    }

    function insertRecordRow(items, row) {
        var tbody = items.querySelector('.calendari-admin-table tbody');
        if (!tbody) {
            items.innerHTML = '';
            var tableContainer = document.createElement('div');
            tableContainer.innerHTML = row.parentNode && row.parentNode.parentNode && row.parentNode.parentNode.parentNode
                ? row.parentNode.parentNode.parentNode.outerHTML
                : '';
            var table = tableContainer.querySelector('.calendari-admin-table');
            if (table) {
                var tableBody = table.querySelector('tbody');
                if (tableBody) {
                    tableBody.innerHTML = '';
                    tableBody.appendChild(row);
                    items.appendChild(tableContainer.firstElementChild);
                    return;
                }
            }
            return;
        }

        var rowDate = row.getAttribute('data-sort-date') || '';
        var rowId = parseInt(row.getAttribute('data-record-id'), 10) || 0;
        var beforeRow = Array.prototype.find.call(tbody.querySelectorAll('tr[data-record-id]'), function (candidate) {
            var candidateDate = candidate.getAttribute('data-sort-date') || '';
            var candidateId = parseInt(candidate.getAttribute('data-record-id'), 10) || 0;
            return rowDate < candidateDate || (rowDate === candidateDate && rowId < candidateId);
        });
        tbody.insertBefore(row, beforeRow || null);
    }

    function updateSavedRecord(form, data) {
        var card = form.closest('.calendari-card');
        var items = card ? card.querySelector('[data-filter-items]') : null;
        if (!items) {
            return;
        }

        var currentRow = items.querySelector('tr[data-record-id="' + String(data.id) + '"]');
        if (currentRow && !data.matches_filters) {
            currentRow.remove();
        } else if (data.matches_filters && data.html) {
            var updatedContainer = document.createElement('div');
            updatedContainer.innerHTML = data.html;
            var updatedRow = updatedContainer.querySelector('.calendari-admin-table tbody tr[data-record-id]');
            if (updatedRow) {
                if (currentRow) {
                    currentRow.replaceWith(updatedRow);
                } else if (data.is_new) {
                    insertRecordRow(items, updatedRow);
                }
            }
        }

        var status = card.querySelector('[data-filter-status]');
        if (status) {
            status.textContent = data.message || '';
        }
    }

    function submitAdminModal(form, closeModal, action) {
        var settings = window.CalendariAdmin || {};
        var errorStatus = form.querySelector('[data-modal-save-status]');
        var saveButton = form.querySelector('button[type="submit"]');
        var scrollPosition = Math.max(0, Math.round(window.scrollY || window.pageYOffset || 0));
        var payload = new FormData(form);
        payload.delete('calendari_save');
        payload.delete('calendari_degree_save');
        payload.set('action', action);
        if (errorStatus) {
            errorStatus.hidden = true;
            errorStatus.textContent = '';
        }
        if (saveButton) {
            saveButton.disabled = true;
        }
        form.setAttribute('aria-busy', 'true');

        fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data && data.data && data.data.message ? data.data.message : (settings.modalSaveError || 'Unable to save the record.'));
                }
                return data.data;
            });
        }).then(function (data) {
            updateSavedRecord(form, data);
            if (typeof closeModal === 'function') {
                closeModal();
            }
            window.requestAnimationFrame(function () {
                window.scrollTo(0, scrollPosition);
            });
        }).catch(function (error) {
            if (errorStatus) {
                errorStatus.textContent = error.message || settings.modalSaveError || 'Unable to save the record.';
                errorStatus.hidden = false;
            }
        }).finally(function () {
            if (saveButton) {
                saveButton.disabled = false;
            }
            form.removeAttribute('aria-busy');
        });
    }

    function submitAjaxFilters(form, page, append) {
        var settings = window.CalendariAdmin || {};
        var card = form.closest('.calendari-card');
        var results = card ? card.querySelector('[data-filter-results]') : null;
        if (!results || !settings.ajaxUrl || !settings.filterNonce) {
            return;
        }
        append = true === append;
        if (append && '1' === results.getAttribute('data-loading')) {
            return;
        }

        var requestId = ++filterRequestId;
        form.setAttribute('data-filter-request-id', String(requestId));
        var payload = new URLSearchParams();
        payload.set('action', 'calendari_filter_database');
        payload.set('nonce', settings.filterNonce);
        payload.set('tab', (form.querySelector('[name="tab"]') || {}).value || '');
        payload.set('paged', String(page || 1));
        form.querySelectorAll('[data-dropdown-value]').forEach(function (input) {
            payload.set(input.name, input.value);
        });

        results.setAttribute('aria-busy', 'true');
        results.setAttribute('data-loading', '1');
        var status = form.querySelector('[data-filter-status]');
        if (status) {
            status.textContent = '';
        }
        var loadingStatus = results.querySelector('[data-infinite-scroll-status]');
        if (loadingStatus) {
            loadingStatus.textContent = append ? 'Loading more records…' : '';
        }
        updateClearFilterButton(form);

        fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString(),
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data && data.data && data.data.message ? data.data.message : 'La richiesta dei filtri non è riuscita.');
                }
                return data.data;
            });
        }).then(function (data) {
            if (form.getAttribute('data-filter-request-id') !== String(requestId)) {
                return;
            }
            var items = results.querySelector('[data-filter-items]') || results;
            if (append) {
                appendFilterPage(items, data.html || '');
            } else {
                items.innerHTML = data.html || '';
            }
            results.setAttribute('data-next-page', String(data.next_page || ((page || 1) + 1)));
            results.setAttribute('data-has-more', data.has_more ? '1' : '0');
            var count = card.querySelector('[data-filter-count]');
            if (count && data.count_label) {
                count.textContent = data.count_label;
            }
            if (loadingStatus) {
                loadingStatus.textContent = '';
            }
            observeFilterSentinel(form, results);
            if ('1' !== results.getAttribute('data-restoring')) {
                updateFilterAddress(form);
            }
        }).catch(function (error) {
            if (form.getAttribute('data-filter-request-id') === String(requestId) && status) {
                status.textContent = settings.filterError || error.message;
            }
            if (form.getAttribute('data-filter-request-id') === String(requestId) && append && loadingStatus) {
                loadingStatus.textContent = settings.filterError || error.message;
                results.setAttribute('data-has-more', '0');
                observeFilterSentinel(form, results);
            }
        }).finally(function () {
            if (form.getAttribute('data-filter-request-id') !== String(requestId)) {
                return;
            }
            results.removeAttribute('aria-busy');
            results.removeAttribute('data-loading');
            updateClearFilterButton(form);
            if ('1' === results.getAttribute('data-restoring')) {
                var targetPages = Math.max(1, parseInt(results.getAttribute('data-restore-pages'), 10) || 1);
                var loadedPages = Math.max(1, (parseInt(results.getAttribute('data-next-page'), 10) || 2) - 1);
                if ('1' === results.getAttribute('data-has-more') && loadedPages < targetPages) {
                    submitAjaxFilters(form, loadedPages + 1, true);
                    return;
                }
                finishFilterRestore(form, results);
                return;
            }
            if (!infiniteScrollObserver) {
                window.requestAnimationFrame(checkFallbackInfiniteScroll);
            }
        });
    }

    document.querySelectorAll('[data-searchable-dropdown]').forEach(function (dropdown) {
        var trigger = dropdown.querySelector('.calendari-filter-trigger');
        var panel = dropdown.querySelector('.calendari-filter-panel');
        var search = dropdown.querySelector('[data-dropdown-search]');
        var hiddenValue = dropdown.querySelector('[data-dropdown-value]');
        var currentLabel = dropdown.querySelector('[data-dropdown-current]');
        var emptyMessage = dropdown.querySelector('.calendari-filter-empty');
        var options = Array.prototype.slice.call(dropdown.querySelectorAll('.calendari-filter-option'));

        if (!trigger || !panel || !search || !hiddenValue || !currentLabel) {
            return;
        }

        function visibleOptions() {
            return options.filter(function (option) {
                return !option.hidden;
            });
        }

        function filterOptions() {
            var query = search.value.trim().toLowerCase();
            var matches = 0;

            options.forEach(function (option) {
                var isMatch = !query || option.textContent.trim().toLowerCase().indexOf(query) !== -1;
                option.hidden = !isMatch;
                if (isMatch) {
                    matches++;
                }
            });

            if (emptyMessage) {
                emptyMessage.hidden = matches > 0;
            }
        }

        function closeDropdown(returnFocus) {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (returnFocus) {
                trigger.focus();
            }
        }

        function openDropdown() {
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            filterOptions();
            search.focus();
            search.select();
        }

        function chooseOption(option) {
            hiddenValue.value = option.getAttribute('data-value');
            currentLabel.textContent = option.textContent.trim();
            options.forEach(function (item) {
                item.setAttribute('aria-selected', item === option ? 'true' : 'false');
            });
            search.value = '';
            filterOptions();
            closeDropdown(true);

            var form = dropdown.closest('form');
            if (form) {
                if (form.hasAttribute('data-ajax-filters')) {
                    submitAjaxFilters(form, 1);
                } else if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        }

        trigger.addEventListener('click', function () {
            if (panel.hidden) {
                openDropdown();
            } else {
                closeDropdown(false);
            }
        });

        trigger.addEventListener('keydown', function (event) {
            if ('ArrowDown' === event.key) {
                event.preventDefault();
                openDropdown();
            } else if ('Escape' === event.key && !panel.hidden) {
                event.preventDefault();
                closeDropdown(true);
            }
        });

        search.addEventListener('input', filterOptions);
        search.addEventListener('keydown', function (event) {
            var visible = visibleOptions();
            if ('ArrowDown' === event.key && visible.length) {
                event.preventDefault();
                visible[0].focus();
            } else if ('Enter' === event.key && visible.length) {
                event.preventDefault();
                chooseOption(visible[0]);
            } else if ('Escape' === event.key) {
                event.preventDefault();
                closeDropdown(true);
            }
        });

        options.forEach(function (option) {
            option.addEventListener('click', function () {
                chooseOption(option);
            });
            option.addEventListener('keydown', function (event) {
                var visible = visibleOptions();
                var index = visible.indexOf(option);
                if ('ArrowDown' === event.key && index < visible.length - 1) {
                    event.preventDefault();
                    visible[index + 1].focus();
                } else if ('ArrowUp' === event.key) {
                    event.preventDefault();
                    if (index > 0) {
                        visible[index - 1].focus();
                    } else {
                        search.focus();
                    }
                } else if ('Escape' === event.key) {
                    event.preventDefault();
                    closeDropdown(true);
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (!dropdown.contains(event.target) && !panel.hidden) {
                closeDropdown(false);
            }
        });
        filterOptions();
    });

    document.querySelectorAll('form[data-ajax-filters]').forEach(function (form) {
        updateClearFilterButton(form);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAjaxFilters(form, 1);
        });

        var clearButton = form.querySelector('[data-clear-filters]');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                form.querySelectorAll('[data-dropdown-value]').forEach(function (hiddenValue) {
                    var dropdown = hiddenValue.closest('[data-searchable-dropdown]');
                    var defaultValue = hiddenValue.getAttribute('data-default-value') || '';
                    hiddenValue.value = defaultValue;
                    if (!dropdown) {
                        return;
                    }

                    var options = Array.prototype.slice.call(dropdown.querySelectorAll('.calendari-filter-option'));
                    var defaultOption = options.find(function (option) {
                        return option.getAttribute('data-value') === defaultValue;
                    });
                    var currentLabel = dropdown.querySelector('[data-dropdown-current]');
                    var search = dropdown.querySelector('[data-dropdown-search]');
                    var emptyMessage = dropdown.querySelector('.calendari-filter-empty');

                    if (defaultOption && currentLabel) {
                        currentLabel.textContent = defaultOption.textContent.trim();
                    }
                    options.forEach(function (option) {
                        option.hidden = false;
                        option.setAttribute('aria-selected', option === defaultOption ? 'true' : 'false');
                    });
                    if (search) {
                        search.value = '';
                    }
                    if (emptyMessage) {
                        emptyMessage.hidden = true;
                    }
                });
                submitAjaxFilters(form, 1);
            });
        }

        var card = form.closest('.calendari-card');
        var results = card ? card.querySelector('[data-filter-results]') : null;
        if (results) {
            beginFilterRestore(form, results);
        }
    });

    document.querySelectorAll('form[data-preserve-filter-position]').forEach(function (modalForm) {
        modalForm.addEventListener('submit', function () {
            var card = modalForm.closest('.calendari-card');
            var filterForm = card ? card.querySelector('form[data-ajax-filters]') : null;
            var results = card ? card.querySelector('[data-filter-results]') : null;
            if (filterForm) {
                modalForm.querySelectorAll('[data-return-filter]').forEach(function (returnField) {
                    var filterName = returnField.getAttribute('data-return-filter');
                    var currentFilter = filterForm.querySelector('[name="' + filterName + '"]');
                    if (currentFilter) {
                        returnField.value = currentFilter.value;
                    }
                });
            }

            var returnPages = modalForm.querySelector('[data-return-pages]');
            if (returnPages && results) {
                returnPages.value = String(Math.max(1, (parseInt(results.getAttribute('data-next-page'), 10) || 2) - 1));
            }
            var returnScroll = modalForm.querySelector('[data-return-scroll]');
            if (returnScroll) {
                returnScroll.value = String(Math.max(0, Math.round(window.scrollY || window.pageYOffset || 0)));
            }
        });
    });

    if (!infiniteScrollObserver) {
        var scrollCheckPending = false;
        function scheduleFallbackScrollCheck() {
            if (scrollCheckPending) {
                return;
            }
            scrollCheckPending = true;
            window.requestAnimationFrame(function () {
                scrollCheckPending = false;
                checkFallbackInfiniteScroll();
            });
        }
        window.addEventListener('scroll', scheduleFallbackScrollCheck, { passive: true });
        window.addEventListener('resize', scheduleFallbackScrollCheck);
        scheduleFallbackScrollCheck();
    }

    var shortcodeBuilder = document.getElementById('calendari-shortcode-builder-form');
    if (shortcodeBuilder) {
        var calendarType = shortcodeBuilder.querySelector('[name="builder_tipologia"]');
        var academicYear = shortcodeBuilder.querySelector('[name="builder_aa_id"]');
        var courseType = shortcodeBuilder.querySelector('[name="builder_tipo_corso"]');
        var calendarView = shortcodeBuilder.querySelector('[name="builder_view"]');
        var examTypeFilter = shortcodeBuilder.querySelector('[name="builder_filtro_tipo_prova"]');
        var previewMode = shortcodeBuilder.querySelector('[name="preview"]');
        var previewButton = shortcodeBuilder.querySelector('button[type="submit"]');
        var builderStatus = shortcodeBuilder.querySelector('[data-builder-status]');
        var examFields = ['builder_mostra_tipo_prova', 'builder_filtro_tipo_prova'].map(function (name) {
            return shortcodeBuilder.querySelector('[name="' + name + '"]');
        }).filter(Boolean);
        var builderOptionsRequestId = 0;
        var builderOptionsLoading = false;
        var builderValidationAttempted = '1' === shortcodeBuilder.getAttribute('data-validation-attempted');

        function updateBuilderStatus(message) {
            if (!builderStatus) {
                return;
            }
            builderStatus.textContent = message || '';
        }

        function removeBuilderToast() {
            var toast = shortcodeBuilder.closest('.calendari-admin');
            toast = toast ? toast.querySelector('[data-builder-toast]') : null;
            if (toast) {
                cancelNoticeDismissal(toast);
                toast.remove();
            }
        }

        function showBuilderToast(message, role) {
            if (!message) {
                removeBuilderToast();
                return;
            }

            var admin = shortcodeBuilder.closest('.calendari-admin') || document.body;
            var toast = admin.querySelector('[data-builder-toast]');
            if (!toast) {
                toast = document.createElement('div');
                toast.className = 'calendari-builder-validation calendari-status-notice';
                toast.setAttribute('data-builder-toast', '');
                toast.appendChild(document.createElement('p'));
                admin.appendChild(toast);
            }

            toast.setAttribute('role', role || 'status');
            toast.setAttribute('aria-live', 'alert' === role ? 'assertive' : 'polite');
            toast.classList.toggle('is-error', 'alert' === role);
            toast.classList.remove('is-dismissing');
            if ('alert' === role) {
                cancelNoticeDismissal(toast);
                toast.id = 'calendari-builder-validation';
            } else {
                toast.removeAttribute('id');
                scheduleNoticeDismissal(toast, 5000);
            }
            toast.querySelector('p').textContent = message;
        }

        function clearBuilderOutput() {
            removeBuilderToast();
            updateBuilderStatus('');
            var serverValidation = document.getElementById('calendari-builder-validation');
            if (serverValidation) {
                serverValidation.remove();
            }
            document.querySelectorAll('.calendari-builder-validation, .calendari-generated-shortcode, .calendari-preview-card').forEach(function (element) {
                element.hidden = true;
            });
        }

        function getMissingBuilderFields() {
            var selectedType = calendarType ? calendarType.value : '';
            var requiredFields = 'esami' === selectedType
                ? [academicYear, courseType]
                : ('laurea' === selectedType ? [academicYear] : [calendarType]);

            return requiredFields.filter(function (field) {
                return field && '' === field.value;
            });
        }

        function applyBuilderFieldErrors(missingFields) {
            [calendarType, academicYear, courseType].filter(Boolean).forEach(function (field) {
                var isMissing = missingFields.indexOf(field) !== -1;
                var label = field.closest('label');
                if (label) {
                    label.classList.toggle('is-error', isMissing);
                }
                if (isMissing) {
                    field.setAttribute('aria-invalid', 'true');
                    field.setAttribute('aria-describedby', 'calendari-builder-validation');
                } else {
                    field.removeAttribute('aria-invalid');
                    field.removeAttribute('aria-describedby');
                }
            });
        }

        function getBuilderValidationMessage(missingFields) {
            if (calendarType && missingFields.indexOf(calendarType) !== -1) {
                return 'Seleziona una tipologia di calendario per generare l’anteprima.';
            }
            if ('laurea' === calendarType.value) {
                return 'Seleziona un anno accademico per generare l’anteprima delle sessioni di laurea.';
            }
            if (missingFields.indexOf(academicYear) !== -1 && missingFields.indexOf(courseType) !== -1) {
                return 'Seleziona un anno accademico e un tipo di corso per generare l’anteprima degli esami.';
            }
            if (missingFields.indexOf(academicYear) !== -1) {
                return 'Seleziona un anno accademico per generare l’anteprima degli esami.';
            }
            return 'Seleziona un tipo di corso per generare l’anteprima degli esami.';
        }

        function refreshBuilderValidation() {
            if (!builderValidationAttempted) {
                applyBuilderFieldErrors([]);
                return [];
            }

            var missingFields = getMissingBuilderFields();
            applyBuilderFieldErrors(missingFields);
            if (missingFields.length) {
                var message = getBuilderValidationMessage(missingFields);
                updateBuilderStatus(message);
                showBuilderToast(message, 'alert');
            } else {
                builderValidationAttempted = false;
                shortcodeBuilder.removeAttribute('data-validation-attempted');
                updateBuilderStatus('');
                removeBuilderToast();
            }

            return missingFields;
        }

        function syncBuilderFields() {
            var selectedType = calendarType ? calendarType.value : '';
            var hasType = 'esami' === selectedType || 'laurea' === selectedType;
            var isExamCalendar = 'esami' === selectedType;
            var isCdsCourse = isExamCalendar && courseType && 'cds' === courseType.value.toLowerCase();

            if (academicYear) {
                academicYear.disabled = !hasType;
                var academicYearLabel = academicYear.closest('label');
                if (academicYearLabel) {
                    academicYearLabel.classList.toggle('is-disabled', academicYear.disabled);
                }
            }
            if (courseType) {
                courseType.disabled = !isExamCalendar;
                var courseTypeLabel = courseType.closest('label');
                if (courseTypeLabel) {
                    courseTypeLabel.classList.toggle('is-disabled', courseType.disabled);
                }
            }
            examFields.forEach(function (field) {
                field.disabled = !isCdsCourse;
                var label = field.closest('label');
                if (label) {
                    label.classList.toggle('is-disabled', field.disabled);
                }
            });
            if (calendarView) {
                var tabbedViewOption = calendarView.querySelector('option[value="tabs"]');
                if (tabbedViewOption) {
                    tabbedViewOption.disabled = !isCdsCourse || !examTypeFilter || 'tutti' !== examTypeFilter.value;
                }
                if (tabbedViewOption && tabbedViewOption.disabled && 'tabs' === calendarView.value) {
                    calendarView.value = 'table';
                }
                calendarView.disabled = !hasType;
                var viewLabel = calendarView.closest('label');
                if (viewLabel) {
                    viewLabel.classList.toggle('is-disabled', calendarView.disabled);
                }
            }
            if (previewButton) {
                previewButton.disabled = !hasType || builderOptionsLoading;
            }
        }

        function replaceSelectOptions(select, values, selectedValue, normalizeValues) {
            if (!select) {
                return;
            }

            var placeholder = select.options.length ? select.options[0].textContent : '';
            select.textContent = '';
            var placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            select.appendChild(placeholderOption);

            (Array.isArray(values) ? values : []).forEach(function (rawValue) {
                var isOptionObject = rawValue && 'object' === typeof rawValue;
                var value = isOptionObject ? String(rawValue.value || '') : String(rawValue);
                var label = isOptionObject ? String(rawValue.label || value) : value;
                var option = document.createElement('option');
                option.value = normalizeValues ? value.toLowerCase() : value;
                option.textContent = label;
                select.appendChild(option);
            });

            select.value = selectedValue || '';
            if (select.value !== (selectedValue || '')) {
                select.value = '';
            }
        }

        function loadBuilderOptions(selectedType, selectedAcademicYear, preserveAcademicYear) {
            var settings = window.CalendariAdmin || {};
            var requestId = ++builderOptionsRequestId;

            if (!selectedType) {
                builderOptionsLoading = false;
                replaceSelectOptions(academicYear, [], '', false);
                replaceSelectOptions(courseType, [], '', true);
                updateBuilderStatus('');
                removeBuilderToast();
                shortcodeBuilder.removeAttribute('aria-busy');
                syncBuilderFields();
                return;
            }

            if (!settings.ajaxUrl || !settings.builderNonce) {
                builderOptionsLoading = false;
                updateBuilderStatus('');
                showBuilderToast(settings.builderOptionsError || 'Impossibile caricare le opzioni per la tipologia selezionata.', 'alert');
                syncBuilderFields();
                return;
            }

            var payload = new URLSearchParams();
            payload.set('action', 'calendari_shortcode_builder_options');
            payload.set('nonce', settings.builderNonce);
            payload.set('builder_tipologia', selectedType);
            payload.set('builder_aa_id', selectedAcademicYear || '');
            builderOptionsLoading = true;
            shortcodeBuilder.setAttribute('aria-busy', 'true');
            updateBuilderStatus('Caricamento delle opzioni…');
            syncBuilderFields();

            fetch(settings.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: payload.toString(),
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error(data && data.data && data.data.message ? data.data.message : 'La richiesta delle opzioni non è riuscita.');
                    }
                    return data.data;
                });
            }).then(function (data) {
                if (requestId !== builderOptionsRequestId) {
                    return;
                }

                var years = Array.isArray(data.academic_years) ? data.academic_years : [];
                var courses = Array.isArray(data.course_types) ? data.course_types : [];
                replaceSelectOptions(academicYear, years, preserveAcademicYear ? selectedAcademicYear : '', false);
                replaceSelectOptions(courseType, courses, '', true);

                if (builderValidationAttempted) {
                    refreshBuilderValidation();
                } else if (!years.length) {
                    updateBuilderStatus('');
                    showBuilderToast('Non sono disponibili anni accademici per questa tipologia di calendario.', 'status');
                } else if ('esami' === selectedType && !courses.length) {
                    updateBuilderStatus('');
                    showBuilderToast('Non sono disponibili tipi di corso per l’anno accademico selezionato.', 'status');
                } else {
                    updateBuilderStatus('');
                    showBuilderToast('Opzioni aggiornate.', 'status');
                }
            }).catch(function () {
                if (requestId === builderOptionsRequestId) {
                    updateBuilderStatus('');
                    showBuilderToast(settings.builderOptionsError || 'Impossibile caricare le opzioni per la tipologia selezionata.', 'alert');
                }
            }).finally(function () {
                if (requestId !== builderOptionsRequestId) {
                    return;
                }
                builderOptionsLoading = false;
                shortcodeBuilder.removeAttribute('aria-busy');
                syncBuilderFields();
            });
        }

        shortcodeBuilder.addEventListener('change', function () {
            if (previewMode) {
                previewMode.value = '0';
            }
            clearBuilderOutput();
            syncBuilderFields();
            refreshBuilderValidation();
        });

        shortcodeBuilder.addEventListener('submit', function (event) {
            var missingFields = getMissingBuilderFields();
            if (missingFields.length) {
                event.preventDefault();
                builderValidationAttempted = true;
                shortcodeBuilder.setAttribute('data-validation-attempted', '1');
                clearBuilderOutput();
                missingFields = refreshBuilderValidation();
                if (previewMode) {
                    previewMode.value = '0';
                }
                if (missingFields.length) {
                    missingFields[0].focus();
                }
                return;
            }

            builderValidationAttempted = false;
            shortcodeBuilder.removeAttribute('data-validation-attempted');
            applyBuilderFieldErrors([]);
            removeBuilderToast();
            updateBuilderStatus('');
            if (previewMode) {
                previewMode.value = '1';
            }
        });

        if (calendarType) {
            calendarType.addEventListener('change', function () {
                if (academicYear) {
                    academicYear.value = '';
                }
                if (courseType) {
                    courseType.value = '';
                }
                replaceSelectOptions(academicYear, [], '', false);
                replaceSelectOptions(courseType, [], '', true);
                syncBuilderFields();
                refreshBuilderValidation();
                loadBuilderOptions(calendarType.value, '', false);
            });
        }

        if (academicYear) {
            academicYear.addEventListener('change', function () {
                if (calendarType && 'esami' === calendarType.value) {
                    if (courseType) {
                        courseType.value = '';
                        replaceSelectOptions(courseType, [], '', true);
                    }
                    refreshBuilderValidation();
                    loadBuilderOptions(calendarType.value, academicYear.value, true);
                }
            });
        }

        if (courseType) {
            courseType.addEventListener('change', function () {
                if ('cds' !== courseType.value.toLowerCase()) {
                    var showType = shortcodeBuilder.querySelector('[name="builder_mostra_tipo_prova"]');
                    var filterType = shortcodeBuilder.querySelector('[name="builder_filtro_tipo_prova"]');
                    if (showType) {
                        showType.value = 'nascondi';
                    }
                    if (filterType) {
                        filterType.value = 'tutti';
                    }
                    if (calendarView && 'tabs' === calendarView.value) {
                        calendarView.value = 'table';
                    }
                }
                syncBuilderFields();
            });
        }

        syncBuilderFields();
    }

    var groupForm = document.getElementById('calendari-group-form');
    if (groupForm) {
        var groupFormId = groupForm.querySelector('[name="id"]');
        var groupFormTitle = document.getElementById('calendari-group-form-title');
        var groupFields = {
            aaId: groupForm.querySelector('[name="aa_id"]'),
            course: groupForm.querySelector('[name="tipo_corso"]'),
            group: groupForm.querySelector('[name="gruppo"]'),
            effectiveFrom: groupForm.querySelector('[name="effective_from"]'),
            codes: groupForm.querySelector('[name="cds_ids"]'),
            note: groupForm.querySelector('[name="nota_pubblica"]'),
            order: groupForm.querySelector('[name="ordine"]'),
        };
        var groupIdentityFields = Array.prototype.slice.call(groupForm.querySelectorAll('[data-group-identity]'));

        document.querySelectorAll('[data-group-create]').forEach(function (button) {
            button.addEventListener('click', function () {
                groupForm.reset();
                groupFormId.value = '0';
                groupIdentityFields.forEach(function (field) { field.readOnly = false; });
                groupFormTitle.textContent = 'Nuovo gruppo';
                groupFields.aaId.focus();
            });
        });

        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-course-group-edit]') : null;
            if (!button) {
                return;
            }
            groupForm.reset();
            groupFormId.value = button.getAttribute('data-id') || '0';
            var isUngrouped = '1' === button.getAttribute('data-group-ungrouped');
            var hasSavedGroup = '0' !== groupFormId.value;
            groupIdentityFields.forEach(function (field) { field.readOnly = hasSavedGroup || isUngrouped; });
            groupFormTitle.textContent = isUngrouped ? 'Corsi senza gruppo' : 'Modifica gruppo';
            groupFields.aaId.value = button.getAttribute('data-aa-id') || '';
            groupFields.course.value = button.getAttribute('data-course') || '';
            groupFields.group.value = button.getAttribute('data-group') || '';
            groupFields.codes.value = button.getAttribute('data-cds-ids') || '';
            groupFields.note.value = button.getAttribute('data-note') || '';
            groupFields.order.value = button.getAttribute('data-order') || '0';
            groupFields.aaId.focus();
        });
    }

    var degreeSessionModal = document.getElementById('calendari-degree-session-modal');
    if (degreeSessionModal) {
        var degreeSessionForm = degreeSessionModal.querySelector('form');
        var degreeSessionId = degreeSessionModal.querySelector('[name="id"]');
        var degreeSessionTitle = document.getElementById('calendari-degree-modal-title');
        var degreeSessionAaId = degreeSessionModal.querySelector('[name="aa_id"]');
        var degreeSessionDate = degreeSessionModal.querySelector('[name="data"]');
        var degreeSessionVenue = degreeSessionModal.querySelector('[name="sede"]');
        var closeDegreeSessionModal = degreeSessionModal.close
            ? function () { degreeSessionModal.close(); }
            : function () { degreeSessionModal.removeAttribute('open'); };

        function openDegreeSessionModal(record) {
            degreeSessionForm.reset();
            degreeSessionId.value = record ? record.id : '0';
            degreeSessionTitle.textContent = record ? 'Modifica Sessione' : 'Nuova Sessione';
            if (record) {
                degreeSessionAaId.value = record.aaId;
                degreeSessionDate.value = record.date;
                degreeSessionVenue.value = record.venue;
            }
            if (degreeSessionModal.showModal) {
                degreeSessionModal.showModal();
            } else {
                degreeSessionModal.setAttribute('open', '');
            }
            degreeSessionAaId.focus();
        }

        document.querySelectorAll('[data-degree-session-create]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDegreeSessionModal(null);
            });
        });

        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-degree-session-edit]') : null;
            if (!button) {
                return;
            }
            openDegreeSessionModal({
                id: button.getAttribute('data-id'),
                aaId: button.getAttribute('data-aa-id'),
                date: button.getAttribute('data-date'),
                venue: button.getAttribute('data-venue'),
            });
        });

        degreeSessionForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAdminModal(degreeSessionForm, closeDegreeSessionModal, 'calendari_save_degree_session');
        });

        degreeSessionModal.querySelectorAll('[data-degree-modal-cancel]').forEach(function (button) {
            button.addEventListener('click', closeDegreeSessionModal);
        });

        degreeSessionModal.addEventListener('click', function (event) {
            if (event.target === degreeSessionModal) {
                closeDegreeSessionModal();
            }
        });
    }

    var examSessionModal = document.getElementById('calendari-exam-modal');
    if (examSessionModal) {
        var examSessionForm = examSessionModal.querySelector('form');
        var examSessionId = examSessionModal.querySelector('[name="id"]');
        var examSessionTitle = document.getElementById('calendari-exam-modal-title');
        var examSessionBookingVisibility = examSessionModal.querySelector('[name="booking_dates_tbd"]');
        var examSessionFields = {
            aaId: examSessionModal.querySelector('[name="aa_id"]'),
            course: examSessionModal.querySelector('[name="tipo_corso"]'),
            courseIds: examSessionModal.querySelector('[name="cds_ids"]'),
            group: examSessionModal.querySelector('[name="gruppo"]'),
            bookingOpen: examSessionModal.querySelector('[name="data_apertura_prenotazioni"]'),
            bookingClose: examSessionModal.querySelector('[name="data_chiusura_prenotazioni"]'),
            examDate: examSessionModal.querySelector('[name="data_esami"]'),
            pwDeadline: examSessionModal.querySelector('[name="data_termine_pw"]'),
            examType: examSessionModal.querySelector('[name="tipo_prova"]'),
            venue: examSessionModal.querySelector('[name="sede"]'),
        };
        var examTypeLabel = examSessionFields.examType.closest('[data-exam-type-field]');
        var closeExamSessionModal = examSessionModal.close
            ? function () { examSessionModal.close(); }
            : function () { examSessionModal.removeAttribute('open'); };

        function openExamSessionModal(record) {
            examSessionForm.reset();
            examSessionId.value = record ? record.id : '0';
            examSessionTitle.textContent = record ? 'Modifica Appello' : 'Nuovo Appello';
            if (record) {
                examSessionFields.aaId.value = record.aaId;
                var courseCode = record.course.trim().toUpperCase();
                var legacyCourseTypes = {
                    'CORSI ONLINE': 'CO',
                    'CORSI DI STUDIO': 'CDS',
                    'MASTER': 'M',
                    'CORSI DI PERFEZIONAMENTO': 'CP',
                    'CORSI DI ALTA FORMAZIONE': 'AF'
                };
                examSessionFields.course.value = legacyCourseTypes[courseCode] || courseCode;
                examSessionFields.courseIds.value = record.courseIds;
                examSessionFields.group.value = record.group;
                examSessionFields.bookingOpen.value = record.bookingOpen;
                examSessionFields.bookingClose.value = record.bookingClose;
                examSessionFields.examDate.value = record.examDate;
                examSessionFields.pwDeadline.value = record.pwDeadline;
                examSessionFields.examType.value = record.examType;
                examSessionFields.venue.value = record.venue;
            }
            if (examSessionBookingVisibility) {
                examSessionBookingVisibility.value = record && '1' === record.bookingDatesTbd ? '1' : '0';
            }
            if (examTypeLabel) {
                examTypeLabel.hidden = 'cds' !== examSessionFields.course.value.trim().toLowerCase();
            }
            if (examSessionModal.showModal) {
                examSessionModal.showModal();
            } else {
                examSessionModal.setAttribute('open', '');
            }
            examSessionFields.aaId.focus();
        }

        document.querySelectorAll('[data-exam-session-create]').forEach(function (button) {
            button.addEventListener('click', function () {
                openExamSessionModal(null);
            });
        });

        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-exam-session-edit]') : null;
            if (!button) {
                return;
            }
            openExamSessionModal({
                id: button.getAttribute('data-id'),
                aaId: button.getAttribute('data-aa-id'),
                course: button.getAttribute('data-course'),
                courseIds: button.getAttribute('data-course-ids') || '',
                group: button.getAttribute('data-group') || '',
                bookingOpen: button.getAttribute('data-booking-open'),
                bookingClose: button.getAttribute('data-booking-close'),
                examDate: button.getAttribute('data-exam-date'),
                pwDeadline: button.getAttribute('data-pw-deadline') || '',
                examType: button.getAttribute('data-exam-type'),
                bookingDatesTbd: button.getAttribute('data-booking-dates-tbd'),
                venue: button.getAttribute('data-venue'),
            });
        });

        examSessionForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAdminModal(examSessionForm, closeExamSessionModal, 'calendari_save_exam_record');
        });

        examSessionFields.course.addEventListener('change', function () {
            var isCds = 'cds' === examSessionFields.course.value.trim().toLowerCase();
            if (examTypeLabel) {
                examTypeLabel.hidden = !isCds;
            }
            if (!isCds) {
                examSessionFields.examType.value = '';
            }
        });

        examSessionModal.querySelectorAll('[data-exam-modal-cancel]').forEach(function (button) {
            button.addEventListener('click', closeExamSessionModal);
        });

        examSessionModal.addEventListener('click', function (event) {
            if (event.target === examSessionModal) {
                closeExamSessionModal();
            }
        });
    }
}());
