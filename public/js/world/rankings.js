/**
 * Rankings modal logic.
 */
(function () {
    var rankingsModal = document.getElementById('rankingsModal');
    var rankingsContainer = document.getElementById('rankingsContainer');

    var allRankings = [];
    var sortColumn = 'regions';
    var sortAscending = false;

    document.getElementById('closeRankingsModal').onclick = function () { hide(); };
    document.getElementById('closeRankingsBtn').onclick = function () { hide(); };

    function show() {
        rankingsModal.style.display = 'block';
        loadRankings();
    }

    function hide() {
        rankingsModal.style.display = 'none';
    }

    async function loadRankings() {
        rankingsContainer.innerHTML = '<div class="build-loading">Loading rankings...</div>';

        try {
            var response = await fetch('/game/api/rankings');
            var result = await response.json();

            if (result.success) {
                allRankings = result.rankings;
                renderRankings();
            } else {
                rankingsContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load rankings</div>';
            }
        } catch (error) {
            console.error('Error loading rankings:', error);
            rankingsContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load rankings</div>';
        }
    }

    function sortRankings() {
        allRankings.sort(function (a, b) {
            var valA = a[sortColumn];
            var valB = b[sortColumn];

            if (valA < valB) return sortAscending ? -1 : 1;
            if (valA > valB) return sortAscending ? 1 : -1;
            return 0;
        });
    }

    function getSortArrow(column) {
        if (sortColumn !== column) return '';
        return sortAscending ? ' ↑' : ' ↓';
    }

    function onHeaderClick(column) {
        if (sortColumn === column) {
            sortAscending = !sortAscending;
        } else {
            sortColumn = column;
            sortAscending = false;
        }
        renderRankings();
    }

    function renderRankings() {
        if (allRankings.length === 0) {
            rankingsContainer.innerHTML = '<div class="build-loading">No players found</div>';
            return;
        }

        sortRankings();

        var html = '<table style="width: 100%; border-collapse: collapse;">';
        html += '<tr style="border-bottom: 2px solid #8B7355;">';
        html += '<th style="padding: 8px; text-align: center; width: 60px;">Rank</th>';
        html += '<th style="padding: 8px; text-align: left;">Player</th>';
        html += '<th style="padding: 8px; text-align: center; cursor: pointer;" data-sort="regions">Regions' + getSortArrow('regions') + '</th>';
        html += '<th style="padding: 8px; text-align: center; cursor: pointer;" data-sort="netWorth">Net Worth' + getSortArrow('netWorth') + '</th>';
        html += '</tr>';

        allRankings.forEach(function (player, index) {
            html += '<tr style="border-bottom: 1px solid #555;">';
            html += '<td style="padding: 8px; text-align: center;">' + (index + 1) + '</td>';
            html += '<td style="padding: 8px; text-align: left;">' + escapeHtml(player.name);
            if (player.federation) {
                html += ' <i style="color: #aaa;">(' + escapeHtml(player.federation) + ')</i>';
            }
            html += '</td>';
            html += '<td style="padding: 8px; text-align: center;">' + player.regions + '</td>';
            html += '<td style="padding: 8px; text-align: center;">' + player.netWorth + '</td>';
            html += '</tr>';
        });

        html += '</table>';
        rankingsContainer.innerHTML = html;

        rankingsContainer.querySelectorAll('th[data-sort]').forEach(function (th) {
            th.onclick = function () {
                onHeaderClick(th.getAttribute('data-sort'));
            };
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    window.WorldRankings = {
        modal: rankingsModal,
        show: show
    };
})();
