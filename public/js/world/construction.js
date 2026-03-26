/**
 * Construction overview modal logic.
 */
(function () {
    const constructionModal = document.getElementById('constructionModal');
    const constructionTabs = document.getElementById('constructionTabs');
    const constructionContainer = document.getElementById('constructionContainer');

    let currentCategory = 'all';
    let allConstructions = [];
    let timerInterval = null;

    document.getElementById('closeConstructionModal').onclick = function () { hide(); };
    document.getElementById('closeConstructionBtn').onclick = function () { hide(); };

    function show() {
        constructionModal.style.display = 'block';
        currentCategory = 'all';
        loadConstruction();
    }

    function hide() {
        constructionModal.style.display = 'none';
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    async function loadConstruction() {
        constructionContainer.innerHTML = '<div class="build-loading">Loading construction data...</div>';

        try {
            var response = await fetch('/game/api/construction/overview');
            var result = await response.json();

            if (result.success) {
                allConstructions = result.constructions;
                renderTabs();
                renderConstructions();
                startTimers();
            } else {
                constructionContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load construction data</div>';
            }
        } catch (error) {
            console.error('Error loading constructions:', error);
            constructionContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load construction data</div>';
        }
    }

    function renderTabs() {
        constructionTabs.innerHTML = '';

        var categories = [{ id: 'all', name: 'All' }];
        var seen = {};
        allConstructions.forEach(function (c) {
            if (!seen[c.categoryId]) {
                seen[c.categoryId] = true;
                categories.push({ id: c.categoryId, name: c.categoryName });
            }
        });

        categories.forEach(function (cat) {
            var tab = document.createElement('div');
            tab.className = 'build-tab' + (String(currentCategory) === String(cat.id) ? ' active' : '');
            tab.textContent = cat.name;
            tab.onclick = function () {
                currentCategory = cat.id;
                renderTabs();
                renderConstructions();
            };
            constructionTabs.appendChild(tab);
        });
    }

    function renderConstructions() {
        var filtered = currentCategory === 'all'
            ? allConstructions
            : allConstructions.filter(function (c) { return c.categoryId === currentCategory; });

        if (filtered.length === 0) {
            constructionContainer.innerHTML = '<div class="build-loading">No units under construction</div>';
            return;
        }

        var html = '<table style="width: 100%; border-collapse: collapse;">';
        html += '<tr style="border-bottom: 2px solid #8B7355;">';
        html += '<th style="padding: 8px; text-align: left;">Unit</th>';
        html += '<th style="padding: 8px; text-align: center;">Count</th>';
        html += '<th style="padding: 8px; text-align: center;">Region</th>';
        html += '<th style="padding: 8px; text-align: center;">Time Left</th>';
        html += '<th style="padding: 8px; text-align: center;">Action</th>';
        html += '</tr>';

        filtered.forEach(function (c) {
            html += '<tr style="border-bottom: 1px solid #555;">';
            html += '<td style="padding: 8px;">' + escapeHtml(c.unitName) + '</td>';
            html += '<td style="padding: 8px; text-align: center;">' + c.number + '</td>';
            html += '<td style="padding: 8px; text-align: center;">' + c.regionX + ', ' + c.regionY + '</td>';
            html += '<td style="padding: 8px; text-align: center;"><span class="construction-timer" data-timeleft="' + c.timeLeft + '">' + formatTime(c.timeLeft) + '</span></td>';
            html += '<td style="padding: 8px; text-align: center;"><button class="construction-cancel-btn" data-id="' + c.id + '">Cancel</button></td>';
            html += '</tr>';
        });

        html += '</table>';
        constructionContainer.innerHTML = html;

        constructionContainer.querySelectorAll('.construction-cancel-btn').forEach(function (btn) {
            btn.onclick = function () {
                cancelConstruction(parseInt(btn.getAttribute('data-id'), 10));
            };
        });
    }

    function startTimers() {
        if (timerInterval) {
            clearInterval(timerInterval);
        }

        timerInterval = setInterval(function () {
            var timers = constructionContainer.querySelectorAll('.construction-timer');
            timers.forEach(function (el) {
                var timeLeft = parseInt(el.getAttribute('data-timeleft'), 10) - 1;
                if (timeLeft < 0) { timeLeft = 0; }
                el.setAttribute('data-timeleft', String(timeLeft));
                el.textContent = formatTime(timeLeft);
            });
        }, 1000);
    }

    function formatTime(seconds) {
        if (seconds <= 0) { return 'Done'; }
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        var parts = [];
        if (h > 0) { parts.push(h + 'h'); }
        if (m > 0) { parts.push(m + 'm'); }
        parts.push(s + 's');
        return parts.join(' ');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    async function cancelConstruction(constructionId) {
        try {
            var response = await fetch('/game/api/construction/cancel/' + constructionId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            var result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                loadConstruction();
            } else {
                showNotification(result.message || 'Failed to cancel construction', 'error');
            }
        } catch (error) {
            console.error('Error cancelling construction:', error);
            showNotification('An error occurred while cancelling construction', 'error');
        }
    }

    window.WorldConstruction = {
        modal: constructionModal,
        show: show
    };
})();
