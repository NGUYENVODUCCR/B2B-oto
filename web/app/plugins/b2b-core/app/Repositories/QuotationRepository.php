<?php

class QuotationRepository
{
    protected $table;

    public function __construct()
    {
        global $wpdb;

        $this->table =
            $wpdb->prefix . 'b2b_quotations';
    }

    public function create($data)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            $data
        );

        if (!$result) {
            return false;
        }

        return $wpdb->insert_id;
    }

    public function update($id, $data)
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $data,
            [
                'id' => (int) $id
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function findById($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE id = %d
                ",
                $id
            )
        );
    }

    public function findByRFQAndSeller($rfqId, $sellerCompanyId) 
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE rfq_id = %d
                AND seller_company_id = %d
                ",
                $rfqId,
                $sellerCompanyId
            )
        );
    }

    public function updateStatus($id, $status)
    {
        return $this->update($id, ['status' => $status]);
    }

    public function rejectOtherQuotations($rfqId, $acceptedQuotationId)
    {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "
                UPDATE {$this->table}
                SET status = 'rejected',
                    is_selected = 0,
                    updated_at = %s
                WHERE rfq_id = %d
                AND id != %d
                ",
                current_time('mysql'),
                $rfqId,
                $acceptedQuotationId
            )
        );
    }

    public function rejectOthers($rfqId, $acceptedQuotationId)
    {
        return $this->rejectOtherQuotations($rfqId, $acceptedQuotationId);
    }

    public function hasOpenByRFQ($rfqId)
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT id
                FROM {$this->table}
                WHERE rfq_id = %d
                AND status = 'pending'
                LIMIT 1
                ",
                $rfqId
            )
        );
    }

    public function getByRFQ($rfqId)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE rfq_id = %d
                ORDER BY id DESC
                ",
                $rfqId
            )
        );
    }

    public function findAcceptedByRFQ($rfqId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE rfq_id = %d
                AND status = 'accepted'
                ORDER BY id DESC
                LIMIT 1
                ",
                $rfqId
            )
        );
    }
}
