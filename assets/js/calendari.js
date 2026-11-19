(function () {
    'use strict';

    function scaleTable(wrapper) {
        var table = wrapper.querySelector('.calendari-table');
        if (!table) {
            return;
        }

        table.style.transform = '';
        wrapper.style.height = '';

        var availableWidth = wrapper.clientWidth;
        var tableWidth = table.scrollWidth;
        if (!availableWidth || tableWidth <= availableWidth) {
            return;
        }

        var ratio = availableWidth / tableWidth;
        table.style.transformOrigin = 'top left';
        table.style.transform = 'scale(' + ratio + ')';
        wrapper.style.height = Math.ceil(table.offsetHeight * ratio) + 2 + 'px';
    }

    function scaleTables() {
        document.querySelectorAll('.calendari-table-wrap').forEach(scaleTable);
    }

    function activateViewTab(tabs, selectedTab, moveFocus) {
        tabs.forEach(function (tab) {
            var isSelected = tab === selectedTab;
            tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            tab.setAttribute('tabindex', isSelected ? '0' : '-1');

            var panel = document.getElementById(tab.getAttribute('aria-controls'));
            if (panel) {
                panel.hidden = !isSelected;
            }
        });

        if (moveFocus) {
            selectedTab.focus();
        }

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(scaleTables);
        } else {
            scaleTables();
        }
    }

    function initTabbedViews() {
        document.querySelectorAll('[data-calendari-tabbed-view]').forEach(function (view) {
            var tabs = Array.prototype.slice.call(view.querySelectorAll('[role="tab"]'));
            if (!tabs.length) {
                return;
            }

            tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function () {
                    activateViewTab(tabs, tab, false);
                });

                tab.addEventListener('keydown', function (event) {
                    var nextIndex = index;
                    if ('ArrowRight' === event.key) {
                        nextIndex = (index + 1) % tabs.length;
                    } else if ('ArrowLeft' === event.key) {
                        nextIndex = (index - 1 + tabs.length) % tabs.length;
                    } else if ('Home' === event.key) {
                        nextIndex = 0;
                    } else if ('End' === event.key) {
                        nextIndex = tabs.length - 1;
                    } else {
                        return;
                    }

                    event.preventDefault();
                    activateViewTab(tabs, tabs[nextIndex], true);
                });
            });
        });
    }

    function init() {
        scaleTables();
        initTabbedViews();
        window.addEventListener('load', scaleTables);
        window.addEventListener('resize', scaleTables);
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
