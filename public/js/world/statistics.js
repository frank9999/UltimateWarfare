/**
 * Statistics modal logic (overview, income, army, infrastructure).
 * Depends on: notifications.js
 */
(function () {
    var statisticsModal = document.getElementById('statisticsModal');
    var statisticsTabs = document.getElementById('statisticsTabs');
    var statisticsContainer = document.getElementById('statisticsContainer');
    var statisticsFooter = document.getElementById('statisticsFooter');

    var currentTab = 'overview';
    var cachedData = null;

    document.getElementById('closeStatisticsModal').onclick = function () { hide(); };

    function show() {
        currentTab = 'overview';
        cachedData = null;
        statisticsModal.style.display = 'block';
        loadStatistics();
    }

    function hide() {
        statisticsModal.style.display = 'none';
        cachedData = null;
    }

    function renderTabs() {
        statisticsTabs.innerHTML = '';
        var tabs = [
            { id: 'overview', name: 'Overview' },
            { id: 'income', name: 'Income' },
            { id: 'army', name: 'Army' },
            { id: 'infrastructure', name: 'Infrastructure' }
        ];

        tabs.forEach(function (tab) {
            var el = document.createElement('div');
            el.className = 'build-tab' + (currentTab === tab.id ? ' active' : '');
            el.textContent = tab.name;
            el.onclick = function () {
                currentTab = tab.id;
                renderTabs();
                renderCurrentTab();
            };
            statisticsTabs.appendChild(el);
        });
    }

    async function loadStatistics() {
        statisticsContainer.innerHTML = '<div class="build-loading">Loading statistics...</div>';

        try {
            var response = await fetch('/game/api/statistics');
            var result = await response.json();

            if (result.success) {
                cachedData = result.data;
                renderTabs();
                renderCurrentTab();
            } else {
                statisticsContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load statistics</div>';
            }
        } catch (error) {
            statisticsContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Error loading statistics</div>';
        }
    }

    function renderCurrentTab() {
        if (!cachedData) return;

        if (currentTab === 'overview') {
            renderOverviewTab();
        } else if (currentTab === 'income') {
            renderIncomeTab();
        } else if (currentTab === 'army') {
            renderArmyTab();
        } else if (currentTab === 'infrastructure') {
            renderInfrastructureTab();
        }
    }

    function formatNumber(n) {
        return Number(n).toLocaleString();
    }

    function renderOverviewTab() {
        var status = cachedData.status;
        var warnings = cachedData.warnings;

        var html = '';

        for (var i = 0; i < warnings.length; i++) {
            var w = warnings[i];
            html += '<div style="background: #3a1a1a; border: 1px solid #f44336; border-radius: 4px; padding: 10px; margin-bottom: 10px; color: #f44336;">';
            if (w.type === 'food') {
                if (w.seconds === 0) {
                    html += '<strong>You don\'t have enough food to feed your Population</strong><br>';
                    html += 'You will lose 30% of your population every hour!<br><br>';
                    html += '<strong>You don\'t have enough food to feed your army</strong><br>';
                    html += 'You will lose 10% of your army every hour!';
                } else {
                    html += 'You don\'t have enough food to feed your Population in <strong>' + formatNumber(w.seconds) + ' seconds from now!</strong><br>';
                    html += 'Once you hit 0 food, you will lose 30% of your population and 10% of your army every hour!';
                }
            } else if (w.type === 'cash') {
                if (w.seconds === 0) {
                    html += '<strong>You don\'t have enough cash to pay your army</strong><br>';
                    html += 'You will lose 10% of your army every hour!';
                } else {
                    html += 'You don\'t have enough cash to pay your army in <strong>' + formatNumber(w.seconds) + ' seconds from now!</strong><br>';
                    html += 'Once you hit 0 cash, you will lose 10% of your army every hour!';
                }
            }
            html += '</div>';
        }

        html += '<table style="width: 100%; border-collapse: collapse;">';
        html += '<tr><td style="padding: 8px; color: #aaa;">Cash</td><td style="padding: 8px;">$ ' + formatNumber(status.cash) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Wood</td><td style="padding: 8px;">' + formatNumber(status.wood) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Steel</td><td style="padding: 8px;">' + formatNumber(status.steel) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Food</td><td style="padding: 8px;">' + formatNumber(status.food) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Regions</td><td style="padding: 8px;">' + formatNumber(status.regions) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Net Worth</td><td style="padding: 8px;">' + formatNumber(status.netWorth) + '</td></tr>';
        html += '</table>';

        statisticsContainer.innerHTML = html;
        renderFooter();
    }

    function renderIncomeTab() {
        var inc = cachedData.income;
        var html = '';

        html += '<h3 style="margin-top: 0; color: #ccc;">Cash Income</h3>';
        html += '<table style="width: 100%; border-collapse: collapse;">';
        html += '<tr><td style="padding: 6px; color: #aaa;">Income</td><td style="padding: 6px;">' + formatNumber(inc.cashIncome) + '</td></tr>';
        html += '<tr><td style="padding: 6px; color: #aaa;">Upkeep</td><td style="padding: 6px;">-' + formatNumber(inc.cashUpkeep) + '</td></tr>';
        html += '<tr><td style="padding: 6px; color: #aaa;">Net Income</td><td style="padding: 6px; color: ' + (inc.cashNet < 0 ? '#f44336' : '#4caf50') + ';">' + (inc.cashNet >= 0 ? '+' : '') + formatNumber(inc.cashNet) + '</td></tr>';
        html += '</table>';

        html += '<h3 style="color: #ccc;">Food Production</h3>';
        html += '<table style="width: 100%; border-collapse: collapse;">';
        html += '<tr><td style="padding: 6px; color: #aaa;">Production</td><td style="padding: 6px;">' + formatNumber(inc.foodProduction) + '</td></tr>';
        html += '<tr><td style="padding: 6px; color: #aaa;">Consumption</td><td style="padding: 6px;">-' + formatNumber(inc.foodConsumption) + '</td></tr>';
        html += '<tr><td style="padding: 6px; color: #aaa;">Net</td><td style="padding: 6px; color: ' + (inc.foodNet < 0 ? '#f44336' : '#4caf50') + ';">' + (inc.foodNet >= 0 ? '+' : '') + formatNumber(inc.foodNet) + '</td></tr>';
        html += '</table>';

        html += '<h3 style="color: #ccc;">Resources</h3>';
        html += '<table style="width: 100%; border-collapse: collapse;">';
        html += '<tr><td style="padding: 6px; color: #aaa;">Steel</td><td style="padding: 6px;">' + formatNumber(inc.steelIncome) + '</td></tr>';
        html += '<tr><td style="padding: 6px; color: #aaa;">Wood</td><td style="padding: 6px;">' + formatNumber(inc.woodIncome) + '</td></tr>';
        html += '</table>';

        statisticsContainer.innerHTML = html;
        renderFooter();
    }

    function renderArmyTab() {
        var army = cachedData.army;
        var html = '';

        for (var i = 0; i < army.length; i++) {
            var group = army[i];
            html += '<h3 style="' + (i === 0 ? 'margin-top: 0; ' : '') + 'color: #ccc;">' + escapeHtml(group.category) + '</h3>';

            if (group.units.length === 0) {
                html += '<p style="color: #666;">You don\'t have any ' + escapeHtml(group.category) + '</p>';
            } else {
                html += '<table style="width: 100%; border-collapse: collapse;">';
                for (var j = 0; j < group.units.length; j++) {
                    var unit = group.units[j];
                    html += '<tr><td style="padding: 6px; color: #aaa;">' + escapeHtml(unit.name) + '</td><td style="padding: 6px;">' + formatNumber(unit.amount) + '</td></tr>';
                }
                html += '</table>';
            }
        }

        statisticsContainer.innerHTML = html;
        renderFooter();
    }

    function renderInfrastructureTab() {
        var infra = cachedData.infrastructure;
        var html = '';

        for (var i = 0; i < infra.length; i++) {
            var group = infra[i];
            html += '<h3 style="' + (i === 0 ? 'margin-top: 0; ' : '') + 'color: #ccc;">' + escapeHtml(group.category) + '</h3>';

            if (group.units.length === 0) {
                html += '<p style="color: #666;">You don\'t have any ' + escapeHtml(group.category) + '</p>';
            } else {
                html += '<table style="width: 100%; border-collapse: collapse;">';
                for (var j = 0; j < group.units.length; j++) {
                    var unit = group.units[j];
                    html += '<tr><td style="padding: 6px; color: #aaa;">' + escapeHtml(unit.name) + '</td><td style="padding: 6px;">' + formatNumber(unit.amount) + '</td></tr>';
                }
                html += '</table>';
            }
        }

        statisticsContainer.innerHTML = html;
        renderFooter();
    }

    function renderFooter() {
        statisticsFooter.innerHTML = '<button type="button" id="closeStatisticsBtn">Close</button>';
        document.getElementById('closeStatisticsBtn').onclick = function () { hide(); };
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    window.WorldStatistics = {
        modal: statisticsModal,
        show: show
    };
})();
