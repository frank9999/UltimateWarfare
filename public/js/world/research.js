(function () {
    const researchModal = document.getElementById('researchModal');
    const researchTreeContainer = document.getElementById('researchTreeContainer');

    let timerInterval = null;

    const TREE_LAYOUT = {
        'research-level':                { col: 0, row: 0 },
        'special-operations':            { col: 2, row: 1 },
        'spy-technology':                { col: 4, row: 1 },
        'nuclear-technology':            { col: 6, row: 1 },
        'factory-blueprint':             { col: 0, row: 3 },
        'advanced-optics':               { col: 1, row: 3 },
        'submarine-technology':          { col: 2, row: 3 },
        'ballistic-missile-technology':  { col: 3, row: 3 },
        'radar-technology':              { col: 4, row: 3 },
        'naval-bombardment':             { col: 5, row: 3 }
    };

    const NODE_WIDTH = 160;
    const NODE_HEIGHT = 200;
    const COL_GAP = 190;
    const ROW_GAP = 230;
    const PADDING = 20;

    researchModal.addEventListener('hidden.bs.modal', function () {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    });

    function showResearchModal() {
        bootstrap.Modal.getOrCreateInstance(researchModal).show();
        loadResearchTree();
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

        let maxCol = 0;
        let maxRow = 0;
        for (const key in TREE_LAYOUT) {
            if (TREE_LAYOUT[key].col > maxCol) maxCol = TREE_LAYOUT[key].col;
            if (TREE_LAYOUT[key].row > maxRow) maxRow = TREE_LAYOUT[key].row;
        }

        const containerWidth = (maxCol + 1) * COL_GAP + PADDING * 2;
        const containerHeight = (maxRow + 1) * ROW_GAP + PADDING * 2;

        const researchMap = {};
        for (let i = 0; i < researchList.length; i++) {
            researchMap[researchList[i].slug] = researchList[i];
        }

        let html = '<div class="research-tree-inner" style="position: relative; width: ' + containerWidth + 'px; height: ' + containerHeight + 'px;">';

        // SVG overlay for connection lines
        html += '<svg class="research-tree-svg" width="' + containerWidth + '" height="' + containerHeight + '">';

        for (let i = 0; i < researchList.length; i++) {
            const item = researchList[i];
            const layout = TREE_LAYOUT[item.slug];
            if (!layout) continue;

            const prereqs = item.prerequisites || [];
            for (let j = 0; j < prereqs.length; j++) {
                const prereq = prereqs[j];
                const prereqLayout = TREE_LAYOUT[prereq.slug];
                if (!prereqLayout) continue;

                const prereqItem = researchMap[prereq.slug];
                const prereqMet = prereqItem && prereqItem.currentLevel >= prereq.minLevel;
                const lineColor = prereqMet ? '#4CAF50' : '#666';

                let x1, y1, x2, y2;
                if (prereqLayout.row === layout.row) {
                    x1 = PADDING + prereqLayout.col * COL_GAP + NODE_WIDTH;
                    y1 = PADDING + prereqLayout.row * ROW_GAP + NODE_HEIGHT / 2;
                    x2 = PADDING + layout.col * COL_GAP;
                    y2 = PADDING + layout.row * ROW_GAP + NODE_HEIGHT / 2;
                } else {
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
        for (let i = 0; i < researchList.length; i++) {
            const item = researchList[i];
            const layout = TREE_LAYOUT[item.slug];
            if (!layout) continue;

            const x = PADDING + layout.col * COL_GAP;
            const y = PADDING + layout.row * ROW_GAP;
            const showsLevels = item.maxLevel > 1;
            const levelLabel = showsLevels ? ' (Lvl ' + item.currentLevel + '/' + item.maxLevel + ')' : '';

            html += '<div class="research-node research-' + item.status + '" style="left: ' + x + 'px; top: ' + y + 'px;">';
            html += '<img class="research-node-image" src="' + WorldApp.imageBasePath + '/research/' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.name) + '">';
            html += '<div class="research-node-name">' + escapeHtml(item.name) + escapeHtml(levelLabel) + '</div>';
            html += '<div class="research-node-desc">' + escapeHtml(item.description) + '</div>';

            if (item.nextCost !== null && item.nextDuration !== null) {
                html += '<div class="research-node-info">';
                html += '<span>$' + formatNumber(item.nextCost) + '</span>';
                html += '<span>' + formatDuration(item.nextDuration) + '</span>';
                html += '</div>';
            }

            if (item.status === 'available') {
                const buttonLabel = showsLevels
                    ? 'Upgrade to Lvl ' + (item.currentLevel + 1)
                    : 'Research';
                html += '<div class="research-node-action"><button class="research-start-btn" data-slug="' + item.slug + '">' + escapeHtml(buttonLabel) + '</button></div>';
            } else if (item.status === 'researching') {
                const targetLabel = showsLevels && item.targetLevel !== null
                    ? 'Lvl ' + item.targetLevel + ' '
                    : '';
                html += '<div class="research-node-action">';
                html += '<span class="research-timer" data-remaining="' + item.remainingSeconds + '">' + escapeHtml(targetLabel) + formatTime(item.remainingSeconds) + '</span>';
                html += '<button class="research-cancel-btn" data-slug="' + item.slug + '">Cancel</button>';
                html += '</div>';
            } else if (item.status === 'maxed') {
                const maxedLabel = showsLevels ? 'Maxed (Lvl ' + item.maxLevel + ')' : 'Completed';
                html += '<div class="research-node-status">' + escapeHtml(maxedLabel) + '</div>';
            }

            html += '</div>';
        }

        html += '</div>';
        researchTreeContainer.innerHTML = html;

        // Bind click handlers
        const startBtns = researchTreeContainer.querySelectorAll('.research-start-btn');
        for (let b = 0; b < startBtns.length; b++) {
            startBtns[b].addEventListener('click', function (e) {
                e.stopPropagation();
                startResearch(this.getAttribute('data-slug'));
            });
        }

        const cancelBtns = researchTreeContainer.querySelectorAll('.research-cancel-btn');
        for (let b = 0; b < cancelBtns.length; b++) {
            cancelBtns[b].addEventListener('click', function (e) {
                e.stopPropagation();
                cancelResearch(this.getAttribute('data-slug'));
            });
        }

        // Start timer for researching nodes
        const timerElements = researchTreeContainer.querySelectorAll('.research-timer');
        if (timerElements.length > 0) {
            timerInterval = setInterval(function () {
                for (let t = 0; t < timerElements.length; t++) {
                    const el = timerElements[t];
                    let remaining = parseInt(el.getAttribute('data-remaining'), 10);
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(timerInterval);
                        timerInterval = null;
                        loadResearchTree();
                        return;
                    }
                    el.setAttribute('data-remaining', remaining);
                    const prefix = el.textContent.match(/^Lvl \d+ /);
                    el.textContent = (prefix ? prefix[0] : '') + formatTime(remaining);
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
                    const cashEl = document.getElementById('resourceCash');
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
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function formatDuration(seconds) {
        if (seconds <= 0) return '0s';
        const d = Math.floor(seconds / 86400);
        const h = Math.floor((seconds % 86400) / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        if (d > 0) return d + 'd ' + h + 'h';
        if (h > 0) return h + 'h ' + m + 'm';
        return m + 'm';
    }

    function formatTime(seconds) {
        if (seconds <= 0) return 'Done';
        const d = Math.floor(seconds / 86400);
        const h = Math.floor((seconds % 86400) / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (d > 0) return d + 'd ' + h + 'h';
        if (h > 0) return h + 'h ' + m + 'm';
        if (m > 0) return m + 'm ' + s + 's';
        return s + 's';
    }

    window.WorldResearch = {
        show: showResearchModal
    };
})();
