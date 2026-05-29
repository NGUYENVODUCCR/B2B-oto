<?php

class AIController
{
    private $service;

    public function __construct()
    {
        $this->service = new AIService();
    }

    public function ask($request)
    {
        try {
            $data = RequestHelper::params($request);
            $question = AIValidator::message($data);

            return ResponseHelper::success([
                'question' => $question,
                'intent' => $this->service->getIntent($question),
                'guided_steps' => $this->service->getGuidedSteps($question),
                'answer' => $this->service->buildPrompt($question),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
