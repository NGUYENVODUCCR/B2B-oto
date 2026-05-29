<?php

class KnowledgeLoader
{
    private $knowledgePath;

    private $stopWords = [

        'là',
        'và',
        'của',
        'cho',
        'tôi',
        'muốn',
        'về',
        'có',
        'không',
        'thì',
        'khi',
        'được',
        'đến',
        'trong',
        'với',
        'hay',
        'sao',
        'như',
        'thế',
        'nào',
        'một',
        'cách',
        'giúp',
        'hỏi',
        'biết',
    ];

    private $synonyms = [

        'seller' => [
            'người bán',
            'nhà cung cấp',
            'bên bán',
        ],

        'buyer' => [
            'người mua',
            'bên mua',
        ],

        'rfq' => [
            'yêu cầu báo giá',
            'hỏi giá',
        ],

        'quotation' => [
            'báo giá',
        ],

        'contract' => [
            'hợp đồng',
        ],

        'payment' => [
            'thanh toán',
            'trả tiền',
        ],

        'escrow' => [
            'giữ tiền',
            'trung gian',
        ],

        'dispute' => [
            'tranh chấp',
            'khiếu nại',
            'support',
            'admin',
        ],
    ];

    public function __construct()
    {
        $this->knowledgePath =
            B2B_PLUGIN_PATH
            . 'storage/knowledge/';
    }

    public function getRelevantKnowledge(
        $question,
        $intent = 'general'
    ) {

        $question =
            mb_strtolower(trim($question));

        $keywords =
            $this->extractKeywords(
                $question
            );

        $files =
            glob(
                $this->knowledgePath
                . '*.txt'
            );

        if (!$files) {
            return [];
        }

        $results = [];

        foreach ($files as $file) {

            $content =
                file_get_contents($file);

            $chunks =
                $this->splitChunks(
                    $content
                );

            $fileScore = 0;

            $matchedChunks = [];


            $filename =
                mb_strtolower(
                    basename(
                        $file,
                        '.txt'
                    )
                );

            if (
                str_contains(
                    $filename,
                    $intent
                )
            ) {

                $fileScore += 300;
            }


            foreach ($chunks as $chunk) {

                $score =
                    $this->scoreChunk(
                        $chunk,
                        $keywords,
                        $intent,
                        $filename
                    );

                if ($score <= 0) {
                    continue;
                }

                $matchedChunks[] = [

                    'text' => trim($chunk),
                    'score' => $score,
                ];

                $fileScore += $score;
            }

            if ($fileScore <= 0) {
                continue;
            }

            usort(
                $matchedChunks,
                fn($a, $b)
                    => $b['score']
                    <=> $a['score']
            );

            $results[] = [

                'file' =>
                    basename($file),

                'score' =>
                    $fileScore,

                'chunks' =>
                    array_slice(
                        array_column(
                            $matchedChunks,
                            'text'
                        ),
                        0,
                        5
                    ),
            ];
        }

        usort(
            $results,
            fn($a, $b)
                => $b['score']
                <=> $a['score']
        );

        return array_slice(
            $results,
            0,
            3
        );
    }

    private function splitChunks(
        $content
    ) {

        $chunks =
            preg_split(
                '/\n\s*\n/',
                trim($content)
            );

        return array_filter(
            array_map(
                'trim',
                $chunks
            )
        );
    }


    private function extractKeywords(
        $question
    ) {

        $question =
            preg_replace(
                '/[^\p{L}\p{N}\s]/u',
                '',
                $question
            );

        $words =
            explode(
                ' ',
                $question
            );

        $keywords = [];

        foreach ($words as $word) {

            $word =
                trim(
                    mb_strtolower($word)
                );

            if (
                mb_strlen($word) < 2
            ) {
                continue;
            }

            if (
                in_array(
                    $word,
                    $this->stopWords
                )
            ) {
                continue;
            }

            $keywords[] = $word;

            foreach (
                $this->synonyms
                as $main => $syns
            ) {

                if (
                    $word === $main
                    || in_array(
                        $word,
                        $syns
                    )
                ) {

                    $keywords[] =
                        $main;

                    $keywords =
                        array_merge(
                            $keywords,
                            $syns
                        );
                }
            }
        }

        return array_unique(
            $keywords
        );
    }


    private function scoreChunk(
        $chunk,
        $keywords,
        $intent,
        $filename
    ) {

        $chunkLower =
            mb_strtolower($chunk);

        $score = 0;


        if (
            str_contains(
                $filename,
                $intent
            )
        ) {

            $score += 150;
        }

        foreach ($keywords as $keyword) {

            if (
                str_contains(
                    $chunkLower,
                    $keyword
                )
            ) {

                $score += 40;

                $score +=
                    substr_count(
                        $chunkLower,
                        $keyword
                    ) * 8;
            }
        }



        if (
            str_contains(
                $chunkLower,
                $intent
            )
        ) {

            $score += 120;
        }

  

        if (
            str_contains(
                $chunk,
                '- '
            )
        ) {

            $score += 20;
        }



        $length =
            mb_strlen($chunk);

        if (
            $length > 80
            && $length < 1200
        ) {

            $score += 30;
        }

        return $score;
    }
}