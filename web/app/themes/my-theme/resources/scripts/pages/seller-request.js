import { scheduleBottomNavPurge } from './seller-request/bottom-nav.js';
import { sellerPendingTemplate } from './seller-request/templates.js';
import { UserAPI } from '../api/user.js';
import { SellerRequestAPI } from '../api/seller-request.js';

console.log('SELLER REQUEST FILE LOADED - STABLE PRODUCTION READY V10.0');

scheduleBottomNavPurge();

document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('sellerForm');
    const submitBtn = document.getElementById('submitBtn');
    const alertBox = document.getElementById('response-alert');
    const pendingBox = document.getElementById('sellerPendingBox');

    if (!form) {
        return;
    }

    let isSubmitting = false;

    const SELLER_PENDING_KEY =
        'seller_request_pending';

    checkSellerPendingStatus();

    async function checkSellerPendingStatus(){

        try{

            if(!localStorage.getItem('access_token')) return;

            const profile = await UserAPI.getProfile();

            if(
                profile &&
                profile.seller_request &&
                profile.seller_request.status === 'pending'
            ){

                localStorage.setItem(
                    SELLER_PENDING_KEY,
                    'true'
                );

                disableSellerForm(
                    'Đang chờ duyệt'
                );
            }

        }catch(error){

            console.error(
                'CHECK PENDING ERROR:',
                error
            );
        }
    }

    form.addEventListener(
        'submit',
        async (e) => {

            e.preventDefault();

            if (isSubmitting) {

                console.warn(
                    'BLOCK DUPLICATE SUBMIT'
                );

                return;
            }

            if (
                localStorage.getItem(
                    SELLER_PENDING_KEY
                ) === 'true'
            ) {

                disableSellerForm(
                    'Tài khoản của bạn đã đăng ký seller, vui lòng đợi duyệt.'
                );

                return;
            }
            isSubmitting = true;

            if (submitBtn) {

                submitBtn.disabled = true;

                submitBtn.innerText = 'Đang tải hồ sơ và xử lý dữ liệu...';
            }

            resetAlert();

            try {

                if (!localStorage.getItem('access_token')) {
                    throw new Error('Phien lam viec da het han. Vui long dang nhap lai!');
                }

                const formData = new FormData(form);
                const response = await SellerRequestAPI.create(formData);
                const message = response?.message || 'Tai khoan cua ban da dang ky seller, vui long doi duyet.';

                localStorage.setItem(
                    SELLER_PENDING_KEY,
                    'true'
                );

                disableSellerForm(message);
                form.reset();

            } catch (err) {

                console.error(
                    'FETCH TRANSACTION ERROR:',
                    err
                );

                const detailMessage = String(err?.message || '');

                if (
                    detailMessage.includes('doi duyet')
                    || detailMessage.includes('dang ky seller')
                    || detailMessage.includes('đợi duyệt')
                    || detailMessage.includes('đã đăng ký seller')
                ) {
                    localStorage.setItem(SELLER_PENDING_KEY, 'true');
                    disableSellerForm(detailMessage);
                } else {
                    showError(
                        detailMessage || 'Da xay ra loi he thong.'
                    );
                }

            } finally {

                isSubmitting = false;

                if (
                    localStorage.getItem(
                        SELLER_PENDING_KEY
                    ) !== 'true'
                ) {

                    if (submitBtn) {

                        submitBtn.disabled = false;

                        submitBtn.innerText =
                            'Gửi hồ sơ đăng ký ngay';
                    }
                }
            }
        }
    );

    function disableSellerForm(message){

    const sellerContainer =
        document.querySelector('.seller-request-container');

    if(sellerContainer){

        sellerContainer.innerHTML = sellerPendingTemplate(message);
    }

    localStorage.setItem(
        SELLER_PENDING_KEY,
        'true'
    );
}

    function showError(message) {

        if (!alertBox) {
            return;
        }

        alertBox.style.display = 'block';

        alertBox.className = 'alert-danger';

        alertBox.innerText =  message;
    }

    function resetAlert() {

        if (!alertBox) {
            return;
        }

        alertBox.style.display = 'none';

        alertBox.className = '';

        alertBox.innerText = '';
    }
});

