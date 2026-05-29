<?php

class AIService
{
    private $knowledgeLoader;
    private $intentDetector;

    public function __construct()
    {
        $this->knowledgeLoader =
            new KnowledgeLoader();

        $this->intentDetector =
            new IntentDetector();
    }

    public function getIntent($question)
    {
        return $this->intentDetector
            ->detect($question);
    }


    public function getGuidedSteps($question): array
    {
        $intent = $this->getIntent($question);

        return match ($intent) {

            'seller' => [
                'Đăng ký seller',
                'Đăng sản phẩm',
                'Gửi báo giá',
            ],

            'rfq' => [
                'Buyer tạo RFQ',
                'Seller nhận RFQ',
                'Gửi quotation',
            ],

            'quotation' => [
                'Tạo báo giá',
                'Thương lượng',
                'Tiếp tục hợp đồng',
            ],

            'contract' => [
                'Tạo hợp đồng',
                'Ký hợp đồng',
                'Không thể tự hủy',
            ],

            'payment' => [
                'Thanh toán escrow',
                'Trạng thái thanh toán',
                'Release tiền',
            ],

            'dispute' => [
                'Mở support ticket',
                'Support xem giao dịch',
                'Xử lý tranh chấp',
            ],

            default => [
                'Escrow là gì?',
                'RFQ hoạt động sao?',
                'Support xử lý thế nào?',
            ],
        };
    }


    public function buildPrompt($question)
    {
        $intent =
            $this->intentDetector
                ->detect($question);

        if ($intent === 'out_of_scope') {

            return 'Xin lỗi, tôi hiện chỉ hỗ trợ các câu hỏi liên quan đến hệ thống B2B Escrow.';
        }

        $knowledge =
            $this->knowledgeLoader
                ->getRelevantKnowledge(
                    $question,
                    $intent
                );

        if (empty($knowledge)) {

            return 'Xin lỗi, tôi chưa tìm thấy dữ liệu phù hợp.';
        }

        $best =
            $knowledge[0];

        $chunks =
            $best['chunks'] ?? [];

        if (empty($chunks)) {

            return 'Xin lỗi, tôi chưa có dữ liệu phù hợp.';
        }


        $cleanedChunks = [];

        foreach ($chunks as $chunk) {

            $chunk =
                $this->removeSectionTags(
                    $chunk
                );

            $chunk =
                trim($chunk);

            if (!$chunk) {
                continue;
            }

            $cleanedChunks[] =
                $chunk;
        }



        $cleanedChunks =
            $this->removeDuplicateParagraphs(
                $cleanedChunks
            );

         if ($intent === 'greeting') {
        
            $response =
                trim(
                    $cleanedChunks[0] ?? ''
                );
        
        } else {
        
            $response =
                implode(
                    "\n\n",
                    $cleanedChunks
                );
        }


        $response =
            BusinessTranslator::humanize(
                trim($response)
            );


        $response =
            $this->cleanupResponse(
                $response
            );

        return $response;
    }



    private function removeSectionTags(
        $text
    ) {
        return preg_replace(
            '/^\[(.*?)\]\s*/m',
            '',
            $text
        );
    }



    private function removeDuplicateParagraphs(
        $chunks
    ) {
        $unique = [];

        foreach ($chunks as $chunk) {

            $normalized =
                mb_strtolower(
                    trim($chunk)
                );

            $exists = false;

            foreach (
                $unique as $saved
            ) {

                similar_text(
                    $normalized,
                    mb_strtolower($saved),
                    $percent
                );

                if ($percent > 88) {

                    $exists = true;

                    break;
                }
            }

            if (!$exists) {

                $unique[] = $chunk;
            }
        }

        return $unique;
    }

 

    private function cleanupResponse(
        $text
    ) {


        $text =
            preg_replace(
                "/\n{3,}/",
                "\n\n",
                $text
            );

  

        $text =
            preg_replace(
                '/[ \t]+/',
                ' ',
                $text
            );

        $lines =
            preg_split(
                '/\r\n|\r|\n/',
                $text
            );

        $unique = [];

        foreach ($lines as $line) {

            $line = trim($line);

            if (!$line) {
                continue;
            }

            if (
                in_array(
                    mb_strtolower($line),
                    array_map(
                        'mb_strtolower',
                        $unique
                    )
                )
            ) {
                continue;
            }

            $unique[] = $line;
        }

        return implode(
            "\n",
            $unique
        );
    }
}