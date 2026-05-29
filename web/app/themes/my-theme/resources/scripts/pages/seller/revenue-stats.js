export async function loadSellerRevenueStats({ StatisticsAPI, revenueNode, orderNode }) {
  try {
    const stats = await StatisticsAPI.revenue();
    const seller = stats?.seller;

    if (revenueNode) {
      revenueNode.textContent = `${Number(seller?.total_revenue || 0).toLocaleString('vi-VN')} VND`;
    }

    if (orderNode) {
      orderNode.textContent = Number(seller?.total_orders || 0).toLocaleString('vi-VN');
    }
  } catch (error) {
    console.warn('Không tải được thống kê seller:', error.message);
  }
}
