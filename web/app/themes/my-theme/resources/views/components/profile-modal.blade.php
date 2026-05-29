<div id="profileModal" class="modal account-modal">
    <div class="account-modal-content">
        <button type="button" class="close-modal" id="closeProfile" aria-label="Đóng">&times;</button>

        <h3 class="account-modal-title">Thông tin cá nhân</h3>

        <div class="profile-avatar-block">
            <div class="profile-avatar-frame">
                <img
                    id="profAvatarImg"
                    class="profile-avatar-image"
                    src="https://www.w3schools.com/howto/img_avatar.png"
                    alt="Avatar"
                >

                <label for="avatarUploadInput" class="profile-avatar-upload" title="Đổi ảnh đại diện">
                    📷
                </label>
            </div>

            <input type="file" id="avatarUploadInput" class="avatar-upload-input" accept="image/*">
        </div>

        <form id="profileForm">
            <div class="modal-field">
                <label for="profId">ID người dùng:</label>
                <input type="text" id="profId" class="modal-input" readonly>
            </div>

            <div class="modal-field">
                <label for="profName">Tên hiển thị:</label>
                <input type="text" id="profName" name="display_name" class="modal-input modal-input-editable">
            </div>

            <div class="modal-field">
                <label for="profEmail">Email:</label>
                <input type="email" id="profEmail" class="modal-input" readonly>
            </div>

            <div class="modal-field modal-field-spaced">
                <label for="profPhone">Số điện thoại:</label>
                <input type="text" id="profPhone" class="modal-input" readonly>
            </div>

            <button type="submit" class="modal-primary-button">Lưu thay đổi</button>
        </form>
    </div>
</div>

<div id="settingModal" class="modal account-modal">
    <div class="account-modal-content setting-modal-content">
        <button type="button" class="close-modal" id="closeSetting" aria-label="Đóng">&times;</button>

        <h3 class="account-modal-title setting-modal-title">Cài đặt tài khoản</h3>

        <form id="settingForm">
            <div class="setting-field">
                <label for="interfaceLanguage">Ngôn ngữ giao diện:</label>
                <select id="interfaceLanguage" class="modal-select">
                    <option value="vi">Tiếng Việt</option>
                    <option value="en">English</option>
                </select>
            </div>

            <div class="setting-field">
                <label>Thông báo qua Email:</label>
                <label class="setting-checkbox-label">
                    <input type="checkbox" checked> Nhận thông tin đơn hàng mới
                </label>
            </div>

            <button type="button" id="saveSettingBtn" class="modal-success-button">Lưu cài đặt</button>
        </form>
    </div>
</div>
