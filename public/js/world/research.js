(function () {
    const researchModal = document.getElementById('researchModal');
    const closeResearchModal = document.getElementById('closeResearchModal');
    const researchTreeContainer = document.getElementById('researchTreeContainer');

    let timerInterval = null;

    const TREE_LAYOUT = {
        'research-level-1':        { col: 0, row: 0 },
        'research-level-2':        { col: 1, row: 0 },
        'research-level-3':        { col: 2, row: 0 },
        'research-level-4':        { col: 3, row: 0 },
        'research-level-5':        { col: 4, row: 0 },
        'research-level-6':        { col: 5, row: 0 },
        'research-level-7':        { col: 6, row: 0 },
        'research-level-8':        { col: 7, row: 0 },
        'research-level-9':        { col: 8, row: 0 },
        'research-level-10':       { col: 9, row: 0 },
        'special-operations':      { col: 0, row: 1 },
        'special-operations-2':    { col: 1, row: 1 },
        'special-operations-3':    { col: 2, row: 1 },
        'special-operations-4':    { col: 3, row: 1 },
        'spy-technology':          { col: 1, row: 2 },
        'advanced-spy-technology': { col: 2, row: 2 },
        'nuclear-technology':      { col: 6, row: 1 }
    };

    const NODE_WIDTH = 160;
    const NODE_HEIGHT = 170;
    const COL_GAP = 190;
    const ROW_GAP = 200;
    const PADDING = 20;

    closeResearchModal.onclick = function () { hideModal(); };

    function showResearchModal() {
        researchModal.style.display = 'block';
        loadResearchTree();
    }

    function hideModal() {
        researchModal.style.display = 'none';
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    async function loadResearchTree() {
        researchTreeContainer.innerHTML = '<div class="build-loading">Loading research tree...</div>';

        try {
            const response = await fetch('/game/api/research/tree');
            const data = await response.json();

            if (!data.success) {
                researchTreeContainer.innerHTML = '<div style="color: #c44; padding: 20px;">Failed to load research tree.</div>';
                return;
            }

            renderTree(data.research, data.playerCash);
        } catch (e) {
            researchTreeContainer.innerHTML = '<div style="color: #c44; padding: 20px;">Error loading research tree.</div>';
        }
    }

    function renderTree(researchList, playerCash) {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }

        var maxCol = 0;
        var maxRow = 0;
        for (var key in TREE_LAYOUT) {
            if (TREE_LAYOUT[key].col > maxCol) maxCol = TREE_LAYOUT[key].col;
            if (TREE_LAYOUT[key].row > maxRow) maxRow = TREE_LAYOUT[key].row;
        }

        var containerWidth = (maxCol + 1) * COL_GAP + PADDING * 2;
        var containerHeight = (maxRow + 1) * ROW_GAP + PADDING * 2;

        var researchMap = {};
        for (var i = 0; i < researchList.length; i++) {
            researchMap[researchList[i].slug] = researchList[i];
        }

        var html = '<div class="research-tree-inner" style="position: relative; width: ' + containerWidth + 'px; height: ' + containerHeight + 'px;">';

        // SVG overlay for connection lines
        html += '<svg class="research-tree-svg" width="' + containerWidth + '" height="' + containerHeight + '">';

        for (var i = 0; i < researchList.length; i++) {
            var item = researchList[i];
            var layout = TREE_LAYOUT[item.slug];
            if (!layout) continue;

            for (var j = 0; j < item.prerequisites.length; j++) {
                var prereqSlug = item.prerequisites[j];
                var prereqLayout = TREE_LAYOUT[prereqSlug];
                if (!prereqLayout) continue;

                var prereqItem = researchMap[prereqSlug];
                var bothCompleted = prereqItem && prereqItem.status === 'completed' && item.status === 'completed';
                var lineColor = bothCompleted ? '#4CAF50' : '#666';

                var x1, y1, x2, y2;
                if (prereqLayout.row === layout.row) {
                    // Same row: right edge to left edge
                    x1 = PADDING + prereqLayout.col * COL_GAP + NODE_WIDTH;
                    y1 = PADDING + prereqLayout.row * ROW_GAP + NODE_HEIGHT / 2;
                    x2 = PADDING + layout.col * COL_GAP;
                    y2 = PADDING + layout.row * ROW_GAP + NODE_HEIGHT / 2;
                } else {
                    // Cross row: bottom center to top center
                    x1 = PADDING + prereqLayout.col * COL_GAP + NODE_WIDTH / 2;
                    y1 = PADDING + prereqLayout.row * ROW_GAP + NODE_HEIGHT;
                    x2 = PADDING + layout.col * COL_GAP + NODE_WIDTH / 2;
                    y2 = PADDING + layout.row * ROW_GAP;
                }

                html += '<line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '" stroke="' + lineColor + '" stroke-width="2" stroke-dasharray="5,5" />';
            }
        }

        html += '</svg>';

        // Render nodes
        for (var i = 0; i < researchList.length; i++) {
            var item = researchList[i];
            var layout = TREE_LAYOUT[item.slug];
            if (!layout) continue;

            var x = PADDING + layout.col * COL_GAP;
            var y = PADDING + layout.row * ROW_GAP;

            html += '<div class="research-node research-' + item.status + '" style="left: ' + x + 'px; top: ' + y + 'px;">';
            html += '<img class="research-node-image" src="' + WorldApp.imageBasePath + '/research/' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.name) + '">';
            html += '<div class="research-node-name">' + escapeHtml(item.name) + '</div>';
            html += '<div class="research-node-desc">' + escapeHtml(item.description) + '</div>';
            html += '<div class="research-node-info">';
            html += '<span>$' + formatNumber(item.cost) + '</span>';
            html += '<span>' + formatDuration(item.duration) + '</span>';
            html += '</div>';

            if (item.status === 'available') {
                html += '<div class="research-node-action"><button class="research-start-btn" data-slug="' + item.slug + '">Research</button></div>';
            } else if (item.status === 'researching') {
                html += '<div class="research-node-action">';
                html += '<span class="research-timer" data-remaining="' + item.remainingSeconds + '">' + formatTime(item.remainingSeconds) + '</span>';
                html += '<button class="research-cancel-btn" data-slug="' + item.slug + '">Cancel</button>';
                html += '</div>';
            } else if (item.status === 'completed') {
                html += '<div class="research-node-status">Completed</div>';
            }

            html += '</div>';
        }

        html += '</div>';
        researchTreeContainer.innerHTML = html;

        // Bind click handlers
        var startBtns = researchTreeContainer.querySelectorAll('.research-start-btn');
        for (var b = 0; b < startBtns.length; b++) {
            startBtns[b].addEventListener('click', function (e) {
                e.stopPropagation();
                startResearch(this.getAttribute('data-slug'));
            });
        }

        var cancelBtns = researchTreeContainer.querySelectorAll('.research-cancel-btn');
        for (var b = 0; b < cancelBtns.length; b++) {
            cancelBtns[b].addEventListener('click', function (e) {
                e.stopPropagation();
                cancelResearch(this.getAttribute('data-slug'));
            });
        }

        // Start timer for researching nodes
        var timerElements = researchTreeContainer.querySelectorAll('.research-timer');
        if (timerElements.length > 0) {
            timerInterval = setInterval(function () {
                for (var t = 0; t < timerElements.length; t++) {
                    var el = timerElements[t];
                    var remaining = parseInt(el.getAttribute('data-remaining'), 10);
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(timerInterval);
                        timerInterval = null;
                        loadResearchTree();
                        return;
                    }
                    el.setAttribute('data-remaining', remaining);
                    el.textContent = formatTime(remaining);
                }
            }, 1000);
        }
    }

    async function startResearch(slug) {
        try {
            const response = await fetch('/game/api/research/perform/' + slug, { method: 'POST' });
            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                if (result.newCash !== undefined) {
                    var cashEl = document.getElementById('resourceCash');
                    if (cashEl) {
                        cashEl.textContent = formatNumber(result.newCash);
                    }
                }
                loadResearchTree();
            } else {
                showNotification(result.message, 'error');
            }
        } catch (e) {
            showNotification('Failed to start research.', 'error');
        }
    }

    async function cancelResearch(slug) {
        try {
            const response = await fetch('/game/api/research/cancel/' + slug, { method: 'POST' });
            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                loadResearchTree();
            } else {
                showNotification(result.message, 'error');
            }
        } catch (e) {
            showNotification('Failed to cancel research.', 'error');
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function formatDuration(seconds) {
        if (seconds <= 0) return '0s';
        var d = Math.floor(seconds / 86400);
        var h = Math.floor((seconds % 86400) / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        if (d > 0) return d + 'd ' + h + 'h';
        if (h > 0) return h + 'h ' + m + 'm';
        return m + 'm';
    }

    function formatTime(seconds) {
        if (seconds <= 0) return 'Done';
        var d = Math.floor(seconds / 86400);
        var h = Math.floor((seconds % 86400) / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        if (d > 0) return d + 'd ' + h + 'h';
        if (h > 0) return h + 'h ' + m + 'm';
        if (m > 0) return m + 'm ' + s + 's';
        return s + 's';
    }

    window.WorldResearch = {
        modal: researchModal,
        show: showResearchModal
    };
})();
