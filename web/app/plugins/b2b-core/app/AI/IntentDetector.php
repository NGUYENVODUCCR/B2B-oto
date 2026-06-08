<?php

class IntentDetector
{
    private function containsAny(
        $question,
        $keywords
    ) {
        foreach ($keywords as $keyword) {

            if (
                str_contains(
                    $question,
                    $keyword
                )
            ) {
                return true;
            }
        }

        return false;
    }

    public function detect($question)
    {
        $question =
            mb_strtolower($question);

        $sellerKeywords = [
            'seller',
            'người bán',
            'đăng ký bán',
            'bán hàng',
            'nhà cung cấp',
        ];

        $rfqKeywords = [
            'rfq',
            'yêu cầu báo giá',
            'cần báo giá',
            'hỏi giá',
        ];

        $quotationKeywords = [
            'quotation',
            'báo giá',
            'giá bán',
        ];

        $escrowKeywords = [
            'escrow',
            'giữ tiền',
            'trung gian thanh toán',
        ];

        $contractKeywords = [
            'hợp đồng',
            'ký hợp đồng',
        ];

        $paymentKeywords = [
            'payment',
            'thanh toán',
            'trả tiền',
        ];

        $disputeKeywords = [
            'tranh chấp',
            'khiếu nại',
            'hoàn tiền',
            'dispute',
        ];

        $cancellationKeywords = [
            'hủy',
            'cancel',
        ];

        $domainKeywords = [

            'seller',
            'người bán',
            'đăng ký bán',
            'bán hàng',
            'nhà cung cấp',
            'buyer',
            'rfq',
            'quotation',
            'báo giá',
            'escrow',
            'hợp đồng',
            'contract',
            'payment',
            'thanh toán',
            'order',
            'đơn hàng',
            'tranh chấp',
            'support',
            'refund',
            'hoàn tiền',
            'giao dịch',
            'admin',
            'hello',
            'hi',
            'hey',
            'alo',
            'chào',
            'xin chào',
            'bạn là ai',
            'ai là ai',
            'giới thiệu',
        ];

        $isDomainQuestion = false;

        foreach ($domainKeywords as $keyword) {

            if (
                str_contains(
                    $question,
                    $keyword
                )
            ) {
                $isDomainQuestion = true;
                break;
            }
        }

        if (!$isDomainQuestion) {
            return 'out_of_scope';
        }

        $greetingKeywords = [
            'hello',
            'hi',
            'hey',
            'alo',
            'xin chào',
            'chào',
            'bạn là ai',
            'ai là ai',
            'giới thiệu',
        ];
        if (
            $this->containsAny(
                $question,
                $greetingKeywords
            )
        ) {
            return 'greeting';
        }

        if (
            $this->containsAny(
                $question,
                $disputeKeywords
            )
        ) {
            return 'dispute';
        }

        if (
            $this->containsAny(
                $question,
                $paymentKeywords
            )
        ) {
            return 'payment';
        }

        if (
            $this->containsAny(
                $question,
                $contractKeywords
            )
        ) {
            return 'contract';
        }

        if (
            $this->containsAny(
                $question,
                $quotationKeywords
            )
        ) {
            return 'quotation';
        }

        if (
            $this->containsAny(
                $question,
                $rfqKeywords
            )
        ) {
            return 'rfq';
        }

        if (
            $this->containsAny(
                $question,
                $cancellationKeywords
            )
        ) {
            return 'cancellation';
        }

        if (
            $this->containsAny(
                $question,
                $escrowKeywords
            )
        ) {
            return 'escrow';
        }
        
        if (
            $this->containsAny(
                $question,
                $sellerKeywords
            )
        ) {
            return 'seller';
        }        

        return 'general';
    }
}