import { escapeHtml, formatCurrency } from '../../../utils/format.js';
import { statusLabel } from './text.js';

function fieldValue(value) {
  return value ? escapeHtml(value) : 'Chưa cập nhật';
}

function firstFilled(...values) {
  return values.find((value) => value !== undefined && value !== null && String(value).trim() !== '') || '';
}

function fillMissing(base, fallback) {
  return Object.keys(fallback).reduce((result, key) => {
    result[key] = firstFilled(result[key], fallback[key]);

    return result;
  }, { ...(base || {}) });
}

function profileCompanyData(profile) {
  const company = profile?.company || {};
  const sellerRequest = profile?.seller_request || {};

  return {
    id: Number(company.id || profile?.company_member?.company_id || sellerRequest.company_id || 0),
    company_name: firstFilled(company.company_name, sellerRequest.company_name),
    tax_code: firstFilled(company.tax_code, sellerRequest.tax_code),
    address: firstFilled(company.address, sellerRequest.address),
    phone: firstFilled(company.phone, profile?.user?.phone),
    email: firstFilled(company.email, sellerRequest.company_email, profile?.wp_user?.email),
    representative_name: firstFilled(
      company.representative_name,
      sellerRequest.representative_name,
      profile?.user?.fullname,
      profile?.wp_user?.display_name
    ),
    citizen_id_number: firstFilled(company.citizen_id_number, sellerRequest.citizen_id_number),
    bank_account: firstFilled(company.bank_account, profile?.company_member?.bank_account),
    bank_name: firstFilled(company.bank_name, profile?.company_member?.bank_name),
  };
}

function contractParties(data, profile) {
  const profileCompany = profileCompanyData(profile);
  let buyer = data.buyer_company || {};
  let seller = data.seller_company || {};

  if (profileCompany.id && Number(data.buyer_company_id) === profileCompany.id) {
    buyer = fillMissing(buyer, profileCompany);
  }

  if (profileCompany.id && Number(data.seller_company_id) === profileCompany.id) {
    seller = fillMissing(seller, profileCompany);
  }

  return { buyer, seller };
}

function contractDate(value) {
  if (!value) {
    return new Date().toLocaleDateString('vi-VN');
  }

  const date = new Date(String(value).replace(' ', 'T'));

  return Number.isNaN(date.getTime())
    ? escapeHtml(value)
    : date.toLocaleDateString('vi-VN');
}

function signatureBlock(signatures, party, label) {
  const signature = signatures?.[party] || {};
  const image = signature.signature_data
    ? `<img src="${escapeHtml(signature.signature_data)}" alt="Chữ ký ${escapeHtml(label)}">`
    : '<div class="contract-signature-empty">Chưa ký</div>';

  return `
    <div class="contract-signature-box">
      <strong>${escapeHtml(label)}</strong>
      <span>Ký, ghi rõ họ tên</span>
      <div class="contract-signature-image">${image}</div>
      <b>${escapeHtml(signature.signed_name || '')}</b>
      <small>${signature.signed_at ? `Ngày ký: ${escapeHtml(signature.signed_at)}` : ''}</small>
    </div>
  `;
}

function defaultContractTerms() {
  return [
    'Người mua thanh toán khoản tiền ký quỹ cho nền tảng trước khi giao hàng. Người bán chỉ nhận được tiền sau khi người mua xác nhận hoặc hệ thống tự động giải phóng tiền.',
    'Sản phẩm, số lượng và giá cả tuân theo báo giá đã được chấp nhận trong hợp đồng này.',
    'Người bán phải giao hàng theo báo giá đã thỏa thuận và cung cấp thông tin cập nhật giao hàng trung thực.',
    'Người mua có thể kiểm tra hàng hóa và phải xác nhận đã nhận hàng khi hàng hóa đúng như yêu cầu.',
    'Nếu người mua không xác nhận trong vòng 7 ngày sau khi nhận được thông tin giao hàng, hệ thống có thể giải phóng tiền ký quỹ cho người bán.',
    'Cả hai bên đều có thể mở yêu cầu hỗ trợ. Bộ phận hỗ trợ có thể xem xét bằng chứng trước khi hoàn tiền hoặc giải phóng tiền.',
    'Trước khi tiền ký quỹ được giải phóng, việc hủy đơn hàng có thể dừng đơn hàng và đánh dấu thanh toán thất bại/có thể hoàn tiền nếu có vấn đề chính đáng.',
  ];
}

export function contractA4Document(contractDetail) {
  const contract = contractDetail?.contract || {};
  const data = contractDetail?.contract_data || {};
  const buyer = data.buyer_company || {};
  const seller = data.seller_company || {};
  const signatures = data.signatures || {};
  const items = data.items || [];
  const legalBasis = data.legal_basis || [];
  const terms = Object.values(data.terms || {});
  const contractTerms = terms.length ? terms : defaultContractTerms();
  const signedDate = data.signed_at || contract.signed_at;

  return `
    <article class="contract-a4-sheet">
      <header class="contract-a4-header">
        <strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong>
        <h2>Độc lập - Tự do - Hạnh phúc</h2>
        <h3>HỢP ĐỒNG CUNG CẤP DỊCH VỤ THƯƠNG MẠI ĐIỆN TỬ TRÊN SÀN GIAO DỊCH THƯƠNG MẠI ĐIỆN TỬ</h2>
        <p>Số: ${escapeHtml(data.contract_no || contract.id || '')}</p>
      </header>

      <p>Hôm nay, ngày ${contractDate(signedDate || data.created_at || contract.created_at)}, tại ${fieldValue(data.contract_place)}, chúng tôi gồm:</p>

      ${legalBasis.length ? `
        <section class="contract-a4-section">
          <h3>Căn cứ ký kết</h3>
          <ul>
            ${legalBasis.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}
          </ul>
        </section>
      ` : ''}

      <section class="contract-a4-section">
        <h3>Bên A - Bên bán/cung cấp</h3>
        <p><b>Tên doanh nghiệp:</b> ${fieldValue(seller.company_name)}</p>
        <p><b>Mã số thuê:</b> ${fieldValue(seller.tax_code)}</p>
        <p><b>Địa chỉ:</b> ${fieldValue(seller.address)}</p>
        <p><b>Điện thoại:</b> ${fieldValue(seller.phone)} · <b>Email:</b> ${fieldValue(seller.email)}</p>
        <p><b>Người đại diện:</b> ${fieldValue(seller.representative_name)}</p>
        <p><b>CCCD/CMND:</b> ${fieldValue(seller.citizen_id_number)}</p>
      </section>

      <section class="contract-a4-section">
        <h3>Bên B - Bên mua</h3>
        <p><b>Tên tổ chức:</b> ${fieldValue(buyer.company_name)}</p>
        <p><b>Mã số thuê:</b> ${fieldValue(buyer.tax_code)}</p>
        <p><b>Địa chỉ:</b> ${fieldValue(buyer.address)}</p>
        <p><b>Điện thoại:</b> ${fieldValue(buyer.phone)} · <b>Email:</b> ${fieldValue(buyer.email)}</p>
        <p><b>Người đại diện:</b> ${fieldValue(buyer.representative_name)}</p>
        <p><b>CCCD/CMND:</b> ${fieldValue(buyer.citizen_id_number)}</p>
      </section>

      <section class="contract-a4-section">
        <h3>Điều 1. Nội dung giao dịch</h3>
        <table class="contract-items-table">
          <thead>
            <tr>
              <th>STT</th>
              <th>Sản phẩm</th>
              <th>Hãng xe</th>
              <th>Màu sắc</th>
              <th>Năm</th>
              <th>SL</th>
              <th>Đơn giá</th>
              <th>KM</th>
              <th>Thành tiền</th>
            </tr>
          </thead>
          <tbody>
            ${items.map((item, index) => `
            <tr>
              <td>${index + 1}</td>
              <td>${escapeHtml(item.product_name || `Sản phẩm #${item.product_id}`)}</td>
              <td>${escapeHtml(item.brand || item.product_brand || 'Chưa cập nhật')}</td>
              <td>${escapeHtml(item.color || item.product_color || 'Chưa cập nhật')}</td>
              <td>${escapeHtml(item.year || item.manufacture_year || item.product_year || 'Chưa cập nhật')}</td>
              <td>${escapeHtml(item.quantity || 0)}</td>
              <td>${formatCurrency(item.unit_price || 0)}</td>
              <td>${escapeHtml(item.discount_percent || 0)}%</td>
              <td>${formatCurrency(item.line_total || 0)}</td>
            </tr>
          `).join('')}
          </tbody>
        </table>
      </section>

      <section class="contract-a4-section contract-a4-totals">
        <h3>Điều 2. Giá trị hợp đồng và thanh toán</h3>
        <p><span>Tạm tính:</span><b>${formatCurrency(data.subtotal_amount || 0)}</b></p>
        <p><span>Khuyến mãi:</span><b>${formatCurrency(data.discount_total || 0)}</b></p>
        <p><span>Tổng giá trị thanh toán:</span><b>${formatCurrency(data.total_amount || 0)}</b></p>
      </section>

      <section class="contract-a4-section">
        <h3>ĐIỀU 3. ĐIỀU KHOẢN THỰC HIỆN</h3>

        <h4>A/ Trách nhiệm bên bán</h4>
        <p><b>1.</b> Người bán chỉ nhận được tiền hàng sau khi người mua xác nhận đã nhận được hàng hoặc được admin ký xác nhận đảm bảo tính đúng đắn của quy trình trước pháp luật.</p>
        <p><b>2.</b> Thông tin mẫu mã sản phẩm, số lượng và giá cả phải tuân theo thông tin mẫu mã, số lượng và báo giá đã được chấp nhận trong hợp đồng này.</p>
        <p><b>3.</b> Người bán phải giao hàng theo báo giá đã thỏa thuận, chỉ giao hàng khi người mua đã thanh toán xong escrow đã được hệ thống xác nhận trạng thái "đã thanh toán" trên đơn hàng và phải cung cấp thông tin cập nhật tiến trình giao hàng trung thực.</p>
        <p><b>4.</b> Người bán phải đảm bảo tính đúng đắn của minh chứng đối với việc đơn vị vận chuyển thông báo giao hàng thành công cho người mua. Nghiêm cấm mọi hành vi giả dạng minh chứng xác nhận giao hàng thành công của đơn vị vận chuyển.</p>
        <p><b>5.</b> Người bán phải đảm bảo uy tín của đơn vị vận chuyển của mình trong phiên giao dịch.</p>
        <p><b>6.</b> Đối với việc "tự ý" hủy đơn hàng đến từ phía bên bán, bên bán phải chịu hoàn toàn tổn thất của quá trình vận chuyển và giá trị hàng hóa thất thoát.</p>
        <p><b>7.</b> Sàn giao dịch đang áp dụng mức thuế và phí là 3% trên tổng giá trị đơn hàng. Trong đó 1,5% tiền thuế nộp vào ngân sách nhà nước (Thuế Giá trị gia tăng: 1% và Thuế Thu nhập cá nhân: 0,5%), 1,5% còn lại là phí vận hành trả cho sàn.</p>

        <h4>B/ Trách nhiệm bên mua</h4>
        <p><b>1.</b> Người mua cần thanh toán khoản tiền ký quỹ cho nền tảng sau khi ký điện tử xác nhận hợp đồng có hiệu lực pháp lý, tức trước khi hệ thống thành lập xong đơn hàng và trước khi người bán giao hàng.</p>
        <p><b>2.</b> Người mua có thể kiểm tra hàng hóa trong thời gian không quá 3 ngày kể từ khi nhận được hàng. Phải xác nhận đã nhận hàng khi hàng hóa đúng như thông tin đăng ký trong hợp đồng ngay trong thời hạn kiểm tra hàng này.</p>
        <p><b>3.</b> Nếu người mua không xác nhận "đã nhận hàng" sau 3 ngày khi đã nhận được hàng trong khi đơn vị vận chuyển đã báo lại cho người bán cùng hệ thống đã giao hàng thành công, hệ thống có thể giải ngân tiền ký quỹ cho người bán mà không cần bên mua xác nhận.</p>
        <p><b>4.</b> Địa chỉ giao hàng là địa chỉ công ty người mua đã xác nhận khi đăng ký mở tài khoản mua hàng trên hệ thống. Bên mua phải đảm bảo đúng địa chỉ này.</p>
        <p><b>5.</b> Đối với việc "tự ý" hủy đơn hàng đến từ phía bên mua, bên mua phải chi trả chi phí tổn thất của quá trình vận chuyển và giá trị hàng hóa thất thoát.</p>

        <h4>C/ Trách nhiệm chung khi tham gia hợp đồng</h4>
        <p><b>1.</b> Cả hai bên đều có thể mở yêu cầu hỗ trợ. Bộ phận hỗ trợ có thể xem xét bằng chứng, hành vi tuân theo hợp đồng và tính đúng đắn của quy trình trước khi hoàn tiền hoặc giải ngân tiền.</p>
        <p><b>2.</b> Đối với việc "hủy đơn hàng" đến từ cả hai phía, hệ thống sẽ đảm bảo hoàn đủ "khoản tiền ký quỹ" bên mua gửi vào escrow sau khi đã tính toán và xử lý các tổn thất phát sinh nếu có.</p>
        <p><b>3.</b> Cả hai bên và bên hệ thống trung gian phải đảm bảo thực thi đúng với điều khoản hợp đồng đặt ra.</p>
        <p><b>4.</b> Mọi hành vi vi phạm hợp đồng đã gây ra tổn thất cho đơn hàng, bên vi phạm phải chịu trách nhiệm trước pháp luật.</p>
        <p><b>5.</b> Thông tin người đại diện công ty và thông tin công ty phải đúng.</p>

        <h4>D/ Trách nhiệm của hệ thống sàn TMDT B2B Marketplace</h4>
        <p><b>1.</b> Hệ thống chỉ có thể đảm bảo trực tiếp quyền lợi của bên bán nếu "thất thoát hàng hóa" đến từ hành vi của bên mua. Trong trường hợp thất thoát hàng hóa đến từ bên bán hoặc đơn vị vận chuyển của bên bán, hệ thống chỉ đảm bảo hỗ trợ cung cấp đầy đủ thông tin giấy tờ hợp đồng và mọi chi tiết về thông tin đơn hàng.</p>
        <p><b>2.</b> Trước khi tiền ký quỹ được giải ngân cho bên bán và sau khi hợp đồng đã có hiệu lực pháp lý nhờ sự thể hiện đồng ý thông qua chữ ký điện tử của cả hai bên, thì việc tự ý hủy đơn hàng tuyệt đối bị nghiêm cấm. Thời điểm này việc hủy đơn hàng không thể thực hiện đơn phương mà bắt buộc phải có sự đồng ý của cả hai bên và được support trực tiếp đại diện bên thứ ba hệ thống tiếp nhận yêu cầu, tiến hành hòa giải để tính toán và điều chỉnh tổn thất gây ra cho đơn hàng/tiền gửi. Khi hòa giải thành công, ở bước cuối cùng hệ thống mới hỗ trợ dừng đơn hàng, đồng thời đánh dấu "đơn hàng đã bị hủy" và hoàn lại tiền ký quỹ theo kết quả xử lý.</p>
        <p><b>3.</b> Hệ thống phải đặt tính Minh Bạch - Bảo Mật - Uy Tín lên hàng đầu.</p>
        <p><b>4.</b> Không được tự ý sử dụng hoặc sử dụng thông tin của khách hàng với mục đích trái pháp luật.</p>
      </section>

      <section class="contract-a4-section">
        <h3>Điều 4. Hiệu lực và ký kết</h3>
        <p>Hợp đồng có hiệu lực kể từ ngày hai bên ký điện tử trên hệ thống. Mỗi bên giữ một bản điện tử có giá trị đối chiếu trên sàn giao dịch.</p>
      </section>

      <footer class="contract-a4-signatures">
        ${signatureBlock(signatures, 'buyer', 'ĐẠI DIỆN BÊN B')}
        ${signatureBlock(signatures, 'seller', 'ĐẠI DIỆN BÊN A')}
      </footer>
    </article>
  `;
}

export function contractPanel(contractDetail, currentParty, canSign) {
  const contract = contractDetail?.contract;
  const data = contractDetail?.contract_data || {};
  const signatures = data.signatures || {};

  if (!contract) {
    return '';
  }

  return `
    <section class="chat-panel">
      <h3>Hợp đồng</h3>
      <div class="chat-panel-row">
        <span class="chat-status-badge">${escapeHtml(statusLabel(contract.status))}</span>
        <strong>${formatCurrency(data.total_amount || 0)}</strong>
      </div>
      <div class="chat-signatures">
        <span>Buyer: ${signatures.buyer?.signed ? 'Đã ký' : 'Chưa ký'}</span>
        <span>Seller: ${signatures.seller?.signed ? 'Đã ký' : 'Chưa ký'}</span>
      </div>
      <div class="chat-contract-preview" role="button" tabindex="0" data-chat-action="view-contract" data-contract-id="${escapeHtml(contract.id)}">
        <div class="chat-contract-scale">
          ${contractA4Document(contractDetail)}
        </div>
      </div>
      <div class="chat-inline-actions">
        <button type="button" class="secondary" data-chat-action="view-contract" data-contract-id="${escapeHtml(contract.id)}">Xem hợp đồng</button>
        ${canSign ? `<button type="button" data-chat-action="sign-contract" data-contract-id="${escapeHtml(contract.id)}" data-party="${escapeHtml(currentParty)}">Ký điện tử</button>` : ''}
        ${contract.status !== 'cancelled' ? `<button type="button" class="secondary" data-chat-action="support-contract" data-contract-id="${escapeHtml(contract.id)}">Support</button>` : ''}
      </div>
    </section>
  `;
}
