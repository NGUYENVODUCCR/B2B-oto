function purgeBottomNavStrict() {
    if (!document.querySelector('.seller-request-container')) {
        return;
    }

    const bottomSelectors = [
        '.bottom-nav',
        '.bottom-bar',
        'footer',
        '[class*="bottom-nav"]',
        '[class*="bottom-bar"]',
        '[class*="fixed-bottom"]',
    ];

    bottomSelectors.forEach((selector) => {
        document.querySelectorAll(selector).forEach((el) => {
            if (!el.contains(document.querySelector('.seller-request-container')) && el !== document.body) {
                console.log('👉 ĐÃ XÓA THANH ĐÁY THEO SELECTOR:', el);
                el.remove();
            }
        });
    });

    const dynamicElements = document.querySelectorAll('nav, div, footer');
    const targetKeywords = ['Trang chủ', 'Đơn hàng', 'Tin nhắn'];

    dynamicElements.forEach((el) => {
        let matchCount = 0;

        targetKeywords.forEach((key) => {
            if (el.textContent && el.textContent.includes(key)) {
                matchCount++;
            }
        });

        if (matchCount < 2) {
            return;
        }

        const mainFormContainer = document.querySelector('.seller-request-container');

        if (mainFormContainer && (mainFormContainer.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING)) {
            console.log('👉 ĐÃ XÓA THANH ĐÁY DỰA TRÊN VỊ TRÍ VÀ TỪ KHÓA:', el);
            el.remove();
        }
    });
}

export function scheduleBottomNavPurge() {
    document.addEventListener('DOMContentLoaded', purgeBottomNavStrict);
    window.addEventListener('load', purgeBottomNavStrict);
    setTimeout(purgeBottomNavStrict, 100);
    setTimeout(purgeBottomNavStrict, 400);
    setTimeout(purgeBottomNavStrict, 1200);
}
