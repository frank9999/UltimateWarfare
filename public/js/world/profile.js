/**
 * Profile modal logic (account info, password change).
 * Depends on: notifications.js
 */
(function () {
    var profileModal = document.getElementById('profileModal');
    var profileTabs = document.getElementById('profileTabs');
    var profileContainer = document.getElementById('profileContainer');
    var profileFooter = document.getElementById('profileFooter');

    var currentTab = 'info';
    var profileData = null;

    document.getElementById('profileBtn').addEventListener('click', function (e) {
        e.preventDefault();
        var dropdown = document.getElementById('profileDropdown');
        if (dropdown) dropdown.classList.remove('show');
        showProfileModal();
    });

    document.getElementById('closeProfileModal').onclick = function () { profileModal.style.display = 'none'; };

    function showProfileModal() {
        currentTab = 'info';
        profileModal.style.display = 'block';
        loadProfile();
    }

    function renderTabs() {
        profileTabs.innerHTML = '';
        var tabs = [
            { id: 'info', name: 'Account Info' },
            { id: 'password', name: 'Password' }
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
            profileTabs.appendChild(el);
        });
    }

    async function loadProfile() {
        profileContainer.innerHTML = '<div class="build-loading">Loading profile...</div>';

        try {
            var response = await fetch('/game/api/profile');
            var result = await response.json();

            if (result.success) {
                profileData = result.data;
                renderTabs();
                renderCurrentTab();
            } else {
                profileContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Failed to load profile</div>';
            }
        } catch (error) {
            profileContainer.innerHTML = '<div class="build-loading" style="color: #f44336;">Error loading profile</div>';
        }
    }

    function renderCurrentTab() {
        if (!profileData) return;

        if (currentTab === 'info') {
            renderInfoTab();
        } else if (currentTab === 'password') {
            renderPasswordTab();
        }
    }

    function renderInfoTab() {
        var status = profileData.active ? 'Active' : 'Banned';
        var statusColor = profileData.active ? '#4caf50' : '#f44336';

        var html = '<table style="width: 100%; border-collapse: collapse;">';
        html += '<tr><td style="padding: 8px; color: #aaa;">Username</td><td style="padding: 8px;">' + escapeHtml(profileData.username) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Email</td><td style="padding: 8px;">' + escapeHtml(profileData.email) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Signup Date</td><td style="padding: 8px;">' + escapeHtml(profileData.signup) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Account Type</td><td style="padding: 8px;">' + escapeHtml(profileData.accountType) + '</td></tr>';
        html += '<tr><td style="padding: 8px; color: #aaa;">Status</td><td style="padding: 8px; color: ' + statusColor + ';">' + status + '</td></tr>';
        html += '</table>';

        profileContainer.innerHTML = html;
        profileFooter.innerHTML = '<button type="button" id="closeProfileBtn">Close</button>';
        document.getElementById('closeProfileBtn').onclick = function () { profileModal.style.display = 'none'; };
    }

    function renderPasswordTab() {
        var html = '<div style="max-width: 400px; margin: 0 auto;">';
        html += '<div style="margin-bottom: 12px;"><label style="display: block; color: #aaa; margin-bottom: 4px;">Current Password</label>';
        html += '<input type="password" id="profileOldPassword" style="width: 100%; padding: 8px; background: #1a1a2e; border: 1px solid #444; color: #eee; border-radius: 4px;"></div>';
        html += '<div style="margin-bottom: 12px;"><label style="display: block; color: #aaa; margin-bottom: 4px;">New Password</label>';
        html += '<input type="password" id="profileNewPassword" style="width: 100%; padding: 8px; background: #1a1a2e; border: 1px solid #444; color: #eee; border-radius: 4px;"></div>';
        html += '<div style="margin-bottom: 12px;"><label style="display: block; color: #aaa; margin-bottom: 4px;">Confirm New Password</label>';
        html += '<input type="password" id="profileNewPasswordRepeat" style="width: 100%; padding: 8px; background: #1a1a2e; border: 1px solid #444; color: #eee; border-radius: 4px;"></div>';
        html += '<div id="profilePasswordMessage" style="margin-top: 8px;"></div>';
        html += '</div>';

        profileContainer.innerHTML = html;
        profileFooter.innerHTML = '<button type="button" id="closeProfileBtn">Close</button> <button type="button" id="savePasswordBtn">Change Password</button>';
        document.getElementById('closeProfileBtn').onclick = function () { profileModal.style.display = 'none'; };
        document.getElementById('savePasswordBtn').onclick = changePassword;
    }

    async function changePassword() {
        var oldPassword = document.getElementById('profileOldPassword').value;
        var newPassword = document.getElementById('profileNewPassword').value;
        var newPasswordRepeat = document.getElementById('profileNewPasswordRepeat').value;
        var messageDiv = document.getElementById('profilePasswordMessage');

        if (!oldPassword || !newPassword || !newPasswordRepeat) {
            messageDiv.innerHTML = '<span style="color: #f44336;">All fields are required.</span>';
            return;
        }

        if (newPassword !== newPasswordRepeat) {
            messageDiv.innerHTML = '<span style="color: #f44336;">New passwords do not match.</span>';
            return;
        }

        try {
            var response = await fetch('/game/api/profile/change-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    oldPassword: oldPassword,
                    newPassword: newPassword,
                    newPasswordRepeat: newPasswordRepeat
                })
            });
            var result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                document.getElementById('profileOldPassword').value = '';
                document.getElementById('profileNewPassword').value = '';
                document.getElementById('profileNewPasswordRepeat').value = '';
                messageDiv.innerHTML = '<span style="color: #4caf50;">' + escapeHtml(result.message) + '</span>';
            } else {
                messageDiv.innerHTML = '<span style="color: #f44336;">' + escapeHtml(result.message) + '</span>';
            }
        } catch (error) {
            messageDiv.innerHTML = '<span style="color: #f44336;">An error occurred.</span>';
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
})();
