/**
 * Operation mode logic: select operation, highlight eligible regions, select units, execute.
 * Depends on: notifications.js, WorldApp.worldMap
 */
(function () {
    let operationMode = false;
    let operationTargetRegion = null;
    let selectedOperation = null;
    let operationEligibleRegionIds = new Set();
    let currentOperationSource = null;

    const selectOperationModal = document.getElementById('selectOperationModal');
    const operationsContainer = document.getElementById('operationsContainer');
    const operationModeBanner = document.getElementById('operationModeBanner');

    const operationUnitsModal = document.getElementById('operationUnitsModal');
    const confirmOperationBtn = document.getElementById('confirmOperationBtn');

    const operationResultsModal = document.getElementById('operationResultsModal');
    const operationResultsContainer = document.getElementById('operationResultsContainer');

    // Close handlers
    document.getElementById('closeSelectOperationModal').onclick = function () { selectOperationModal.style.display = 'none'; };
    document.getElementById('cancelSelectOperationBtn').onclick = function () { selectOperationModal.style.display = 'none'; };

    document.getElementById('closeOperationUnitsModal').onclick = function () { operationUnitsModal.style.display = 'none'; };
    document.getElementById('cancelOperationUnitsBtn').onclick = function () { operationUnitsModal.style.display = 'none'; };

    document.getElementById('closeOperationResultsModal').onclick = function () { operationResultsModal.style.display = 'none'; };
    document.getElementById('closeOperationResultsBtn').onclick = function () { operationResultsModal.style.display = 'none'; };

    document.getElementById('cancelOperationModeBtn').onclick = cancelOperationMode;

    confirmOperationBtn.onclick = executeOperation;

    function getDefaultTileClick() {
        return WorldApp.defaultTileClick;
    }

    // Step 1: Show available operations for target region
    async function startOperation(enemyRegion) {
        document.getElementById('enemyRegionModal').style.display = 'none';
        operationTargetRegion = enemyRegion;

        document.getElementById('operationTargetInfo').textContent =
            enemyRegion.x + ',' + enemyRegion.y + ' (' + enemyRegion.ownerName + ')';

        operationsContainer.innerHTML = '<div class="build-loading">Loading operations...</div>';
        selectOperationModal.style.display = 'block';

        try {
            const response = await fetch('/game/api/operation/list/' + enemyRegion.id);
            const result = await response.json();

            if (!result.success) {
                operationsContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">' + escapeHtml(result.message) + '</div>';
                return;
            }

            if (result.operations.length === 0) {
                operationsContainer.innerHTML = '<div class="build-loading">No operations available. Research new technologies to unlock operations.</div>';
                return;
            }

            renderOperationsList(result.operations);
        } catch (error) {
            console.error('Error loading operations:', error);
            operationsContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load operations</div>';
        }
    }

    function renderOperationsList(operations) {
        let html = '';
        operations.forEach(function (op) {
            html += '<div class="operation-card" data-operation-slug="' + op.slug + '">';
            html += '<div class="operation-card-header">';
            html += '<div class="operation-card-info">';
            html += '<div class="operation-card-name">' + escapeHtml(op.name) + '</div>';
            html += '<div class="operation-card-desc">' + escapeHtml(op.description) + '</div>';
            html += '</div>';
            html += '</div>';
            html += '<div class="operation-card-stats">';
            html += '<span>Cost: $' + op.cost.toLocaleString('en-US') + '/unit</span>';
            html += '<span>Range: ' + op.maxDistance + '</span>';
            html += '<span>Unit: ' + escapeHtml(op.unitName) + '</span>';
            html += '</div>';
            html += '</div>';
        });

        operationsContainer.innerHTML = html;

        operationsContainer.querySelectorAll('.operation-card').forEach(function (card) {
            card.onclick = function () {
                const opSlug = this.getAttribute('data-operation-slug');
                const op = operations.find(function (o) { return o.slug === opSlug; });
                if (op) selectOperationAndHighlight(op);
            };
        });
    }

    // Step 2: Select operation, fetch eligible regions, highlight them
    async function selectOperationAndHighlight(operation) {
        selectOperationModal.style.display = 'none';
        selectedOperation = operation;
        showNotification('Loading eligible regions for ' + operation.name + '...', 'info');

        try {
            const response = await fetch('/game/api/operation/eligible-regions/' + operationTargetRegion.id + '/' + operation.slug);
            const result = await response.json();

            if (!result.success) {
                showNotification(result.message || 'Failed to load eligible regions', 'error');
                return;
            }

            if (result.eligibleRegions.length === 0) {
                showNotification('No regions in range with the required units (' + operation.unitName + '). Move units closer or build more.', 'error');
                return;
            }

            enterOperationMode(result.eligibleRegions);
        } catch (error) {
            console.error('Error loading eligible regions:', error);
            showNotification('An error occurred. Please try again.', 'error');
        }
    }

    function enterOperationMode(eligibleRegions) {
        operationMode = true;
        operationEligibleRegionIds = new Set(eligibleRegions.map(function (r) { return r.regionId; }));

        const worldMap = WorldApp.worldMap;
        worldMap.sectors.forEach(function (region) {
            region._attackEligible = operationEligibleRegionIds.has(region.id);
            region._attackTarget = (region.id === operationTargetRegion.id);
        });

        worldMap.config.onTileClick = function (region) {
            if (region._attackEligible) {
                showOperationUnitsModal(region);
            } else if (region._attackTarget) {
                cancelOperationMode();
            } else {
                showNotification('This region cannot launch the operation. Select a highlighted region.', 'error');
            }
        };

        worldMap.render();
        operationModeBanner.style.display = 'flex';
        document.getElementById('operationModeText').textContent =
            selectedOperation.name + ': Select one of ' + eligibleRegions.length + ' highlighted region(s) to launch from. Click target or press Escape to cancel.';
    }

    function cancelOperationMode() {
        operationMode = false;
        operationTargetRegion = null;
        selectedOperation = null;
        operationEligibleRegionIds.clear();
        currentOperationSource = null;

        const worldMap = WorldApp.worldMap;
        worldMap.sectors.forEach(function (region) {
            delete region._attackEligible;
            delete region._attackTarget;
        });

        worldMap.config.onTileClick = getDefaultTileClick();
        worldMap.render();
        operationModeBanner.style.display = 'none';
        showNotification('Operation cancelled.', 'info');
    }

    // Step 3: Show unit selection for chosen source region
    async function showOperationUnitsModal(sourceRegion) {
        currentOperationSource = sourceRegion;

        document.getElementById('operationUnitsName').textContent = selectedOperation.name;
        document.getElementById('operationUnitsInfo').innerHTML =
            '<strong>Launching from:</strong> ' + sourceRegion.x + ', ' + sourceRegion.y +
            ' &rarr; <strong>Target:</strong> ' + operationTargetRegion.x + ', ' + operationTargetRegion.y +
            ' (' + operationTargetRegion.ownerName + ')';

        const container = document.getElementById('operationUnitsContainer');
        container.innerHTML = '<div class="build-loading">Loading...</div>';
        confirmOperationBtn.disabled = false;
        confirmOperationBtn.textContent = 'Launch Operation';
        operationUnitsModal.style.display = 'block';

        try {
            const response = await fetch(
                '/game/api/operation/units/' + operationTargetRegion.id + '/' + selectedOperation.slug + '/' + sourceRegion.id
            );
            const result = await response.json();

            if (!result.success) {
                container.innerHTML = '<div class="build-loading" style="color: #f44336;">' + escapeHtml(result.message) + '</div>';
                return;
            }

            renderOperationUnits(result);
        } catch (error) {
            console.error('Error loading operation units:', error);
            container.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load unit data</div>';
        }
    }

    function renderOperationUnits(data) {
        const container = document.getElementById('operationUnitsContainer');
        const maxAffordable = data.costPerUnit > 0 ? Math.floor(data.playerCash / data.costPerUnit) : data.available;
        const maxSend = Math.min(data.available, maxAffordable);

        let html = '<div class="operation-unit-select">';
        html += '<p><strong>' + escapeHtml(data.unitName) + '</strong></p>';
        html += '<p>Available: <strong>' + data.available + '</strong></p>';
        html += '<p>Cost: $' + data.costPerUnit.toLocaleString('en-US') + ' per unit</p>';
        html += '<p>Your cash: $' + data.playerCash.toLocaleString('en-US') + '</p>';
        if (maxAffordable < data.available) {
            html += '<p style="color: #ffa500; font-size: 12px;">You can afford up to ' + maxAffordable + ' units</p>';
        }
        html += '<div class="message-form-group" style="margin-top: 10px;">';
        html += '<label for="operationAmount">Amount (max ' + maxSend + ')</label>';
        html += '<input type="number" id="operationAmount" min="1" max="' + maxSend + '" value="1">';
        html += '</div>';
        html += '<p id="operationTotalCost" style="font-size: 13px;">Total cost: $' + data.costPerUnit.toLocaleString('en-US') + '</p>';
        html += '</div>';

        container.innerHTML = html;

        const amountInput = document.getElementById('operationAmount');
        const totalCostEl = document.getElementById('operationTotalCost');
        amountInput.addEventListener('input', function () {
            let val = parseInt(amountInput.value) || 0;
            if (val > maxSend) { val = maxSend; amountInput.value = val; }
            if (val < 0) { val = 0; amountInput.value = val; }
            totalCostEl.textContent = 'Total cost: $' + (val * data.costPerUnit).toLocaleString('en-US');
        });
    }

    // Step 4: Execute the operation
    async function executeOperation() {
        const amountInput = document.getElementById('operationAmount');
        const amount = parseInt(amountInput.value) || 0;

        if (amount < 1) {
            showNotification('Enter at least 1 unit', 'error');
            return;
        }

        confirmOperationBtn.disabled = true;
        confirmOperationBtn.textContent = 'Executing...';

        try {
            const response = await fetch(
                '/game/api/operation/execute/' + operationTargetRegion.id + '/' + selectedOperation.slug + '/' + currentOperationSource.id,
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ amount: amount })
                }
            );
            const result = await response.json();

            operationUnitsModal.style.display = 'none';

            if (result.success) {
                // Update cash display
                if (result.newCash !== undefined) {
                    const cashElements = document.querySelectorAll('.resource-amount');
                    if (cashElements.length > 0) cashElements[0].textContent = result.newCash.toLocaleString('en-US');
                }

                // Add bombardment cooldown arc if present
                if (result.bombardmentCooldown && WorldApp.worldMap) {
                    WorldApp.worldMap.bombardmentManager.addBombardment(result.bombardmentCooldown);
                }

                cancelOperationMode();
                showOperationResults(result.results);
            } else {
                showNotification(result.message || 'Operation failed', 'error');
                confirmOperationBtn.disabled = false;
                confirmOperationBtn.textContent = 'Launch Operation';
            }
        } catch (error) {
            console.error('Error executing operation:', error);
            showNotification('An error occurred. Please try again.', 'error');
            confirmOperationBtn.disabled = false;
            confirmOperationBtn.textContent = 'Launch Operation';
        }
    }

    function showOperationResults(results) {
        let html = '<div class="operation-results-log">';
        results.forEach(function (line) {
            html += '<p>' + escapeHtml(line) + '</p>';
        });
        html += '</div>';
        operationResultsContainer.innerHTML = html;
        operationResultsModal.style.display = 'block';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Escape key handler
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && operationMode) {
            cancelOperationMode();
        }
    });

    // Expose globally
    window.WorldOperations = {
        startOperation: startOperation,
        selectOperationModal: selectOperationModal,
        operationUnitsModal: operationUnitsModal,
        operationResultsModal: operationResultsModal
    };
})();
